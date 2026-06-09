<?php

namespace App\Services\Ai;

use App\Jobs\ProcessAiRequestJob;
use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationScoring;
use App\Models\CandidateDocument;
use App\Models\CvParsingResult;
use App\Models\JobPersona;
use App\Models\RejectionDraft;
use App\Models\SjtResponse;
use App\Models\SentimentResult;
use App\Models\StrategyLabAiSummary;
use App\Models\StrategyLabBrief;
use App\Models\UnifiedInterviewReport;
use App\Models\VideoResponse;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\EmployerBrand\EmployerBrandAlertService;
use App\Support\Ai\StrictJsonValidator;
use App\Support\Guardrails\AiOutputMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiRequestService
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
        private readonly StrictJsonValidator $jsonValidator,
        private readonly EmployerBrandAlertService $employerBrandAlerts,
        private readonly CandidateAnalysisService $candidateAnalysisService
    ) {
    }

    /**
     * @param array<string, mixed> $requestPayload
     */
    public function queueRequest(
        string $companyId,
        string $requestType,
        array $requestPayload,
        ?string $modelName = null,
        ?string $promptVersion = null
    ): AiRequest {
        $request = AiRequest::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'request_type' => $requestType,
            'input_hash' => hash('sha256', json_encode($requestPayload, JSON_UNESCAPED_UNICODE)),
            'status' => AiRequest::STATUS_QUEUED,
            'model_name' => $modelName ?: (string) config('services.gemini.model', 'gemini-1.5-flash'),
            'prompt_version' => $promptVersion ?? 'v1',
            'request_payload' => $requestPayload,
            'created_at' => now(),
        ]);

        ProcessAiRequestJob::dispatch($request->id)->afterResponse();

        return $request;
    }

    public function retry(AiRequest $request): void
    {
        $request->forceFill([
            'status' => AiRequest::STATUS_QUEUED,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        ProcessAiRequestJob::dispatch($request->id);
    }

    public function process(AiRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            $request->refresh();
            $request->forceFill([
                'status' => AiRequest::STATUS_RUNNING,
                'started_at' => now(),
                'finished_at' => null,
                'error_message' => null,
            ])->save();
        });

        $maxAttempts = max(1, (int) config('services.gemini.max_attempts', 3));
        $attemptLogs = [];
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $this->runSingleAttempt($request, $attempt);
                $attemptLogs[] = $result['attempt'];

                $request->forceFill([
                    'status' => AiRequest::STATUS_SUCCEEDED,
                    'response_payload' => [
                        'mode' => $result['mode'],
                        'output' => $result['output'],
                        'attempts' => $attemptLogs,
                    ],
                    'finished_at' => now(),
                    'error_message' => null,
                ])->save();

                $this->handlePostSuccessSideEffects($request, $result['output']);

                return;
            } catch (Throwable $exception) {
                $lastError = $exception;
                $attemptLogs[] = [
                    'attempt' => $attempt,
                    'at' => now()->toISOString(),
                    'status' => 'failed',
                    'error' => Str::limit($exception->getMessage(), 500),
                ];
            }
        }

        $request->forceFill([
            'status' => AiRequest::STATUS_FAILED,
            'response_payload' => [
                'attempts' => $attemptLogs,
            ],
            'error_message' => $lastError ? Str::limit($lastError->getMessage(), 2000) : 'Unknown AI processing failure.',
            'finished_at' => now(),
        ])->save();
    }

    /**
     * @return array{mode: string, output: mixed, attempt: array<string, mixed>}
     */
    private function runSingleAttempt(AiRequest $request, int $attemptNumber): array
    {
        $payload = $request->request_payload ?? [];
        $outputMode = Arr::get($payload, 'output_mode');
        $outputMode ??= AiOutputMode::for((string) $request->request_type) ?? 'text';

        if ($request->request_type === 'sentiment_analysis') {
            $outputMode = 'json_schema';
            $payload['json_schema'] = $payload['json_schema'] ?? [
                'required' => ['score', 'themes', 'risk_level'],
                'properties' => [
                    'score' => ['type' => 'number'],
                    'themes' => ['type' => 'array'],
                    'risk_level' => ['type' => 'string'],
                ],
            ];
        }

        if ($request->request_type === 'vrin_inference') {
            $outputMode = 'json_schema';
            $payload['json_schema'] = $payload['json_schema'] ?? [
                'required' => ['vrin', 'global_match_score'],
                'properties' => [
                    'vrin' => ['type' => 'object'],
                    'global_match_score' => ['type' => 'number'],
                ],
            ];
        }

        $prompt = (string) Arr::get($payload, 'prompt', '');
        if ($prompt === '') {
            throw new RuntimeException('Missing AI prompt.');
        }

        $parts = $this->buildPromptParts($request, $payload, $prompt);

        $usedLocalStub = false;
        try {
            $rawOutput = $this->geminiClient->generateParts($parts, (string) $request->model_name);
        } catch (Throwable $exception) {
            if (! $this->shouldUseLocalStub($exception)) {
                throw $exception;
            }

            $usedLocalStub = true;
            $rawOutput = $this->buildLocalStubOutput(
                requestType: (string) $request->request_type,
                outputMode: (string) $outputMode,
                payload: $payload
            );
        }

        // Dev-only: intentionally inject invalid JSON to exercise the retry + failure flow.
        if (Arr::get($payload, 'dev_force_invalid_json', false)) {
            $rawOutput = '{"this_is": "intentionally broken json';
        }

        if ($outputMode === 'json_schema') {
            $schema = Arr::get($payload, 'json_schema', []);
            if (! is_array($schema)) {
                $schema = [];
            }

            $decoded = $this->decodeJsonPayload($rawOutput);
            if ($request->request_type === 'cv_parsing') {
                $decoded = $this->repairCvParsingOutput(
                    decoded: $decoded,
                    payload: $payload,
                    schema: $schema
                );
            }

            if (! is_array($decoded)) {
                throw new RuntimeException('Strict JSON mode requires valid JSON output.');
            }

            $validation = $this->jsonValidator->validate($decoded, $schema);
            if (! $validation['valid'] && $request->request_type === 'cv_parsing') {
                $decoded = $this->repairCvParsingOutput(
                    decoded: $decoded,
                    payload: $payload,
                    schema: $schema
                );
                $validation = $this->jsonValidator->validate($decoded, $schema);
            }

            if (! $validation['valid']) {
                throw new RuntimeException((string) ($validation['error'] ?? 'Strict JSON validation failed.'));
            }

            return [
                'mode' => 'json',
                'output' => $decoded,
                'attempt' => [
                    'attempt' => $attemptNumber,
                    'at' => now()->toISOString(),
                    'status' => 'succeeded',
                    'mode' => 'json',
                    'source' => $usedLocalStub ? 'local_stub' : 'provider',
                ],
            ];
        }

        return [
            'mode' => 'text',
            'output' => $rawOutput,
            'attempt' => [
                'attempt' => $attemptNumber,
                'at' => now()->toISOString(),
                'status' => 'succeeded',
                'mode' => 'text',
                'source' => $usedLocalStub ? 'local_stub' : 'provider',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function buildPromptParts(AiRequest $request, array $payload, string $prompt): array
    {
        if ((string) $request->request_type !== 'cv_parsing') {
            return [
                ['text' => $prompt],
            ];
        }

        return [
            $this->buildCvParsingResumePart($payload),
            ['text' => $prompt],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildCvParsingResumePart(array $payload): array
    {
        $resumeDocumentId = trim((string) Arr::get($payload, 'resume_document_id', ''));
        if ($resumeDocumentId === '') {
            throw new RuntimeException('Missing resume document for CV parsing.');
        }

        $resumeDocument = CandidateDocument::withoutGlobalScopes()->find($resumeDocumentId);
        if (! $resumeDocument instanceof CandidateDocument) {
            throw new RuntimeException('Resume document for CV parsing was not found.');
        }

        if ((string) $resumeDocument->document_type !== CandidateDocument::TYPE_RESUME) {
            throw new RuntimeException('CV parsing requires a resume document.');
        }

        if (! $this->isPdfResumeDocument($resumeDocument)) {
            throw new RuntimeException('CV parsing only supports PDF resume documents.');
        }

        $path = trim((string) $resumeDocument->file_url);
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('Resume PDF for CV parsing is missing from local storage.');
        }

        $raw = (string) Storage::disk('local')->get($path);
        if ($raw === '') {
            throw new RuntimeException('Resume PDF for CV parsing is empty.');
        }

        return [
            'inline_data' => [
                'mime_type' => 'application/pdf',
                'data' => base64_encode($raw),
            ],
        ];
    }

    private function isPdfResumeDocument(CandidateDocument $document): bool
    {
        $extension = Str::lower((string) pathinfo((string) $document->original_filename, PATHINFO_EXTENSION));
        $mimeType = Str::lower(trim((string) $document->mime_type));

        return $extension === 'pdf' || str_contains($mimeType, 'application/pdf');
    }

    /**
     * Accept pure JSON plus common LLM wrappers like markdown fences.
     *
     * @return array<mixed>|null
     */
    private function decodeJsonPayload(string $rawOutput): ?array
    {
        $trimmed = trim($rawOutput);

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $matches) === 1) {
            $decodedFromFence = json_decode(trim((string) ($matches[1] ?? '')), true);
            if (is_array($decodedFromFence)) {
                return $decodedFromFence;
            }
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $candidate = substr($trimmed, $firstBrace, ($lastBrace - $firstBrace) + 1);
            $decodedFromObjectSlice = json_decode($candidate, true);
            if (is_array($decodedFromObjectSlice)) {
                return $decodedFromObjectSlice;
            }
        }

        $firstBracket = strpos($trimmed, '[');
        $lastBracket = strrpos($trimmed, ']');
        if ($firstBracket !== false && $lastBracket !== false && $lastBracket > $firstBracket) {
            $candidate = substr($trimmed, $firstBracket, ($lastBracket - $firstBracket) + 1);
            $decodedFromArraySlice = json_decode($candidate, true);
            if (is_array($decodedFromArraySlice)) {
                return $decodedFromArraySlice;
            }
        }

        return null;
    }

    /**
     * CV parsing must tolerate partial/irregular model payloads and still persist structured output.
     *
     * @param array<mixed>|null $decoded
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $schema
     * @return array<mixed>
     */
    private function repairCvParsingOutput(?array $decoded, array $payload, array $schema): array
    {
        $repairNotes = [];
        $candidateSnapshot = Arr::get($payload, 'candidate_snapshot', []);
        if (! is_array($candidateSnapshot)) {
            $candidateSnapshot = [];
        }

        $normalized = is_array($decoded) ? $decoded : [];
        if ($decoded === null) {
            $repairNotes[] = 'json_decode_failed';
        }

        if (array_is_list($normalized)) {
            $unwrapped = collect($normalized)->first(
                static fn (mixed $item): bool => is_array($item) && (! array_is_list($item) || $item === [])
            );
            if (is_array($unwrapped)) {
                $normalized = $unwrapped;
                $repairNotes[] = 'root_list_unwrapped';
            } else {
                $normalized = [];
                $repairNotes[] = 'root_list_replaced';
            }
        }

        $normalized = $this->coerceOutputByJsonSchema($normalized, $schema);

        $summary = trim((string) Arr::get($normalized, 'summary', ''));
        if ($summary === '') {
            $summary = 'CV parsed with partial data.';
            $repairNotes[] = 'summary_defaulted';
        }
        $normalized['summary'] = $summary;

        $profile = Arr::get($normalized, 'profile', []);
        if (! is_array($profile) || array_is_list($profile)) {
            $profile = [];
            $repairNotes[] = 'profile_defaulted';
        }
        $profile['full_name'] = $this->trimmedOrNull(data_get($profile, 'full_name'))
            ?? $this->trimmedOrNull(Arr::get($candidateSnapshot, 'full_name'));
        $profile['email'] = $this->trimmedOrNull(data_get($profile, 'email'))
            ?? $this->trimmedOrNull(Arr::get($candidateSnapshot, 'email'));
        $profile['phone'] = $this->trimmedOrNull(data_get($profile, 'phone'))
            ?? $this->trimmedOrNull(Arr::get($candidateSnapshot, 'phone'));
        $profile['location'] = $this->trimmedOrNull(data_get($profile, 'location'))
            ?? $this->trimmedOrNull(Arr::get($candidateSnapshot, 'location'));
        $normalized['profile'] = $profile;

        foreach ([
            'languages',
            'hard_skills',
            'soft_skills',
            'tools_frameworks',
            'experience',
            'employment_chronology',
            'certifications',
            'projects',
            'education',
            'honors',
            'role_keywords',
        ] as $listField) {
            $value = Arr::get($normalized, $listField, []);
            $normalized[$listField] = $this->normalizeListValue($value);
        }

        $years = $this->coerceNumericValue(data_get($normalized, 'total_years_experience', null));
        $normalized['total_years_experience'] = $years ?? 0.0;
        if ($years === null) {
            $repairNotes[] = 'total_years_experience_defaulted';
        }

        $parsedMetadata = Arr::get($normalized, 'parsed_metadata', []);
        if (! is_array($parsedMetadata) || array_is_list($parsedMetadata)) {
            $parsedMetadata = [];
            $repairNotes[] = 'parsed_metadata_defaulted';
        }
        $parsedMetadata['gender_inference'] = $this->trimmedOrNull(Arr::get($parsedMetadata, 'gender_inference')) ?? 'unknown';
        $parsedMetadata['school_background_tier'] = $this->trimmedOrNull(Arr::get($parsedMetadata, 'school_background_tier')) ?? 'unknown';
        $parsedMetadata['schema_repair_applied'] = true;
        $parsedMetadata['schema_repair_notes'] = collect($repairNotes)
            ->map(static fn (mixed $note): string => trim((string) $note))
            ->filter(static fn (string $note): bool => $note !== '')
            ->unique()
            ->values()
            ->all();
        $normalized['parsed_metadata'] = $parsedMetadata;

        $flags = Arr::get($normalized, 'flags', []);
        if (! is_array($flags) || array_is_list($flags)) {
            $flags = [];
            $repairNotes[] = 'flags_defaulted';
        }

        $missingSections = $this->normalizeListValue(Arr::get($flags, 'missing_sections', []));
        if ($missingSections === []) {
            foreach ([
                'languages',
                'hard_skills',
                'experience',
                'education',
                'role_keywords',
            ] as $field) {
                if ($normalized[$field] === []) {
                    $missingSections[] = $field;
                }
            }
        }

        $flags['missing_sections'] = collect($missingSections)
            ->map(static fn (mixed $section): string => trim((string) $section))
            ->filter(static fn (string $section): bool => $section !== '')
            ->unique()
            ->values()
            ->all();
        $flags['has_complete_profile'] = $flags['missing_sections'] === [];
        $normalized['flags'] = $flags;

        return $this->coerceOutputByJsonSchema($normalized, $schema);
    }

    /**
     * @param array<mixed> $decoded
     * @param array<string, mixed> $schema
     * @return array<mixed>
     */
    private function coerceOutputByJsonSchema(array $decoded, array $schema): array
    {
        $properties = Arr::get($schema, 'properties', []);
        if (! is_array($properties)) {
            $properties = [];
        }

        $required = Arr::wrap(Arr::get($schema, 'required', []));
        foreach ($required as $requiredKey) {
            $key = (string) $requiredKey;
            if ($key === '' || array_key_exists($key, $decoded)) {
                continue;
            }

            $type = trim((string) Arr::get($properties, $key.'.type', ''));
            $decoded[$key] = $this->defaultValueForJsonType($type);
        }

        foreach ($properties as $key => $definition) {
            $key = (string) $key;
            if ($key === '' || ! array_key_exists($key, $decoded)) {
                continue;
            }

            $type = trim((string) data_get($definition, 'type', ''));
            if ($type === '') {
                continue;
            }

            $decoded[$key] = $this->coerceValueToJsonType($decoded[$key], $type);
        }

        return $decoded;
    }

    private function defaultValueForJsonType(string $type): mixed
    {
        return match ($type) {
            'string' => '',
            'number' => 0.0,
            'integer' => 0,
            'boolean' => false,
            'array' => [],
            'object' => [],
            default => null,
        };
    }

    private function coerceValueToJsonType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => is_scalar($value) ? trim((string) $value) : '',
            'number' => $this->coerceNumericValue($value) ?? 0.0,
            'integer' => (int) round($this->coerceNumericValue($value) ?? 0),
            'boolean' => $this->coerceBooleanValue($value),
            'array' => $this->normalizeListValue($value),
            'object' => $this->coerceObjectValue($value),
            default => $value,
        };
    }

    /**
     * @return array<mixed>
     */
    private function normalizeListValue(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = $this->decodeStructuredJsonFragment($trimmed);
            if (is_array($decoded)) {
                return array_values($decoded);
            }

            return [$trimmed];
        }

        if (is_scalar($value)) {
            return [trim((string) $value)];
        }

        return [];
    }

    /**
     * @return array<mixed>
     */
    private function coerceObjectValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) && $value !== [] ? [] : $value;
        }

        if (is_string($value)) {
            $decoded = $this->decodeStructuredJsonFragment($value);
            if (is_array($decoded)) {
                return array_is_list($decoded) && $decoded !== [] ? [] : $decoded;
            }
        }

        return [];
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeStructuredJsonFragment(string $value): ?array
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function coerceNumericValue(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches) !== 1) {
            return null;
        }

        return is_numeric($matches[0]) ? (float) $matches[0] : null;
    }

    private function coerceBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        $text = Str::lower(trim((string) $value));
        if ($text === '') {
            return false;
        }

        return in_array($text, ['true', 'yes', 'y', '1', 'on'], true);
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function shouldUseLocalStub(Throwable $exception): bool
    {
        if ((bool) config('services.gemini.local_stub_enabled', false) !== true) {
            return false;
        }

        // Never allow stub responses in production-like environments.
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'status code 429')
            || str_contains($message, 'quota')
            || str_contains($message, 'timed out')
            || str_contains($message, 'curl error')
            || str_contains($message, 'api key')
            || str_contains($message, 'model is unavailable')
            || str_contains($message, 'is not found for api version')
            || str_contains($message, 'unable to connect')
            || str_contains($message, 'connection refused');
    }

    /**
     * Build deterministic local output so development flows can proceed without provider availability.
     */
    private function buildLocalStubOutput(string $requestType, string $outputMode, array $payload): string
    {
        if ($outputMode === 'json_schema') {
            $schema = Arr::get($payload, 'json_schema', []);
            if (! is_array($schema)) {
                $schema = [];
            }

            $stub = match ($requestType) {
                'job_persona_generation' => [
                    'persona_summary' => 'Local stub persona generated because external AI is unavailable.',
                    'must_haves' => ['Relevant experience', 'Clear communication'],
                    'ideal_traits' => ['Ownership', 'Collaboration'],
                ],
                'candidate_analysis', 'candidate_analysis_json' => [
                    'score' => 74,
                    'summary' => 'Local stub analysis generated because external AI is unavailable.',
                ],
                'cv_parsing' => [
                    'summary' => 'Experienced backend engineer with strong product collaboration and execution discipline.',
                    'profile' => [
                        'full_name' => 'Local Stub Candidate',
                        'email' => 'stub@example.test',
                        'phone' => '+1-555-0101',
                        'location' => 'Remote',
                    ],
                    'languages' => ['English'],
                    'hard_skills' => ['Laravel', 'PHP', 'PostgreSQL'],
                    'soft_skills' => ['Communication', 'Ownership'],
                    'tools_frameworks' => ['Docker', 'GitHub Actions'],
                    'total_years_experience' => 5.5,
                    'experience' => [
                        [
                            'job_title' => 'Backend Engineer',
                            'company' => 'Stub Tech',
                            'start_date' => '2021-01',
                            'end_date' => null,
                            'is_current' => true,
                            'highlights' => ['Built API services', 'Improved deployment reliability'],
                        ],
                    ],
                    'employment_chronology' => [
                        [
                            'job_title' => 'Backend Engineer',
                            'company' => 'Stub Tech',
                            'period' => '2021-01 to Present',
                        ],
                    ],
                    'certifications' => [
                        ['name' => 'AWS Practitioner', 'issuer' => 'AWS', 'date' => '2022-06'],
                    ],
                    'projects' => [
                        ['name' => 'Growth API', 'description' => 'Launched high-throughput API gateway.'],
                    ],
                    'education' => [
                        [
                            'school_name' => 'Stub University',
                            'degree_name' => 'BSc',
                            'study_field' => 'Computer Science',
                            'start_date' => '2016-09',
                            'end_date' => '2020-06',
                            'level' => 'bachelor',
                            'school_category' => 'regular_university',
                            'honors' => ['Dean list'],
                        ],
                    ],
                    'honors' => ['Dean list'],
                    'role_keywords' => ['backend', 'api', 'scalability'],
                    'parsed_metadata' => [
                        'gender_inference' => 'unknown',
                        'school_background_tier' => 'regular_university',
                        'confidence' => 0.78,
                    ],
                    'flags' => [
                        'missing_sections' => [],
                        'has_complete_profile' => true,
                    ],
                ],
                'sentiment_analysis' => $this->buildSentimentStub(
                    (string) Arr::get($payload, 'feedback_text', (string) Arr::get($payload, 'prompt', ''))
                ),
                'sjt_scoring' => [
                    'score' => 78,
                    'signals' => [
                        'accountability' => 'high',
                        'solution_orientation' => 'medium',
                        'tone' => 'high',
                    ],
                    'feedback' => [
                        'strengths' => ['Prioritizes impact', 'Considers stakeholders'],
                        'concerns' => ['Could provide more measurable next steps'],
                        'summary' => 'Balanced judgement with room for stronger execution detail.',
                        'recommendation' => 'Proceed',
                    ],
                ],
                'strategy_lab_executive_summary' => [
                    'executive_summary_text' => 'Clear and practical solution with coherent sequencing and measurable outcomes.',
                    'strengths_json' => ['Strong structure', 'Actionable milestones', 'Balanced risk thinking'],
                    'weaknesses_json' => ['Could deepen budget assumptions', 'Needs clearer dependency mapping'],
                    'creativity_score' => 81,
                    'overall_recommendation' => 'Proceed to final recruiter decision.',
                ],
                'video_response_metrics' => [
                    'transcript_text' => 'Local transcript summary generated because external AI is unavailable.',
                    'pauses_count' => 2,
                    'speech_rate_estimate' => 137.5,
                    'filler_ratio_estimate' => 0.042,
                ],
                'async_video_unified_report' => [
                    'xai_summary' => 'Candidate demonstrates structured communication and consistent problem framing with moderate risk signals.',
                    'ocean' => [
                        'openness' => 71,
                        'conscientiousness' => 78,
                        'extraversion' => 64,
                        'agreeableness' => 69,
                        'neuroticism' => 33,
                    ],
                    'match_percentage' => 76.5,
                    'salary' => [
                        'expected_min' => 90000,
                        'expected_max' => 110000,
                        'currency' => 'USD',
                        'fit_score' => 81.2,
                    ],
                    'generic_motivation' => false,
                    'vrin' => [
                        'acquired_skills' => ['Stakeholder communication', 'Structured analysis'],
                        'missing_skills' => ['Depth in distributed systems'],
                    ],
                    'global_match_score' => 76.5,
                ],
                'vrin_inference' => [
                    'vrin' => [
                        'acquired_skills' => [],
                        'missing_skills' => [],
                    ],
                    'global_match_score' => 0,
                ],
                default => $this->buildSchemaDrivenStub($schema),
            };

            return (string) json_encode($stub, JSON_UNESCAPED_UNICODE);
        }

        return match ($requestType) {
            'email_draft' => "Subject: Interview Follow-up\n\nHello,\n\nThis is a local stub email draft generated because external AI is unavailable.\n\nRegards,\nRecruiting Team",
            'executive_summary' => 'Local stub executive summary generated because external AI is unavailable.',
            'strategy_lab_brief_generation' => implode("\n", [
                'Objective: Build a concise growth strategy proposal for the target market segment.',
                'Constraints: Use only available internal channels and a fixed 90-day timeline.',
                'Deliverables: 1) strategic approach, 2) execution plan, 3) KPI framework.',
                'Evaluation: clarity, prioritization, feasibility, and originality.',
            ]),
            default => 'Local stub AI response generated because external AI is unavailable.',
        };
    }

    /**
     * @return array{score: float, themes: array<int, string>, risk_level: string}
     */
    private function buildSentimentStub(string $text): array
    {
        $normalized = Str::lower($text);
        $score = 0.0;
        $themes = [];

        $positive = [
            'professional' => 'professionalism',
            'smooth' => 'process',
            'clear' => 'communication',
            'respectful' => 'candidate care',
            'timely' => 'response speed',
            'supportive' => 'candidate support',
            'helpful' => 'candidate support',
            'transparent' => 'communication',
            'great' => 'overall experience',
            'excellent' => 'overall experience',
        ];

        $negative = [
            'slow' => 'response speed',
            'confusing' => 'communication',
            'disorganized' => 'process',
            'rude' => 'candidate care',
            'unprofessional' => 'professionalism',
            'ghosted' => 'follow-up',
            'ignored' => 'follow-up',
            'frustrating' => 'process',
            'bad' => 'overall experience',
            'awful' => 'overall experience',
        ];

        $critical = [
            'harassment' => 'candidate safety',
            'discrimination' => 'candidate safety',
            'hostile' => 'candidate safety',
            'abusive' => 'candidate safety',
            'humiliating' => 'candidate safety',
            'threat' => 'candidate safety',
            'unethical' => 'trust',
        ];

        foreach ($positive as $keyword => $theme) {
            if (str_contains($normalized, $keyword)) {
                $score += 0.20;
                $themes[] = $theme;
            }
        }

        foreach ($negative as $keyword => $theme) {
            if (str_contains($normalized, $keyword)) {
                $score -= 0.28;
                $themes[] = $theme;
            }
        }

        foreach ($critical as $keyword => $theme) {
            if (str_contains($normalized, $keyword)) {
                $score -= 0.40;
                $themes[] = $theme;
            }
        }

        $score = (float) round(max(-1, min(1, $score)), 2);

        $riskLevel = match (true) {
            $score <= -0.75 => SentimentResult::RISK_CRITICAL,
            $score <= -0.45 => SentimentResult::RISK_HIGH,
            $score <= -0.20 => SentimentResult::RISK_MEDIUM,
            default => SentimentResult::RISK_LOW,
        };

        $themes = collect($themes)
            ->map(static fn (string $theme): string => trim($theme))
            ->filter(static fn (string $theme): bool => $theme !== '')
            ->unique()
            ->take(5)
            ->values()
            ->all();

        if ($themes === []) {
            $themes = $score < 0 ? ['candidate experience'] : ['general feedback'];
        }

        return [
            'score' => $score,
            'themes' => $themes,
            'risk_level' => $riskLevel,
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function buildSchemaDrivenStub(array $schema): array
    {
        $required = Arr::wrap(Arr::get($schema, 'required', []));
        $properties = Arr::get($schema, 'properties', []);
        if (! is_array($properties)) {
            $properties = [];
        }

        $output = [];
        foreach ($required as $field) {
            $field = (string) $field;
            if ($field === '') {
                continue;
            }

            $type = Arr::get($properties, $field.'.type', 'string');
            $output[$field] = match ($type) {
                'number', 'integer' => 0,
                'boolean' => false,
                'array' => [],
                'object' => [],
                default => 'Local stub value',
            };
        }

        return $output;
    }

    private function handlePostSuccessSideEffects(AiRequest $request, mixed $output): void
    {
        if ($request->request_type === 'job_persona_generation') {
            $this->persistJobPersona($request, $output);

            return;
        }

        if ($request->request_type === 'cv_parsing') {
            $this->persistCvParsingResult($request, $output);

            return;
        }

        if ($request->request_type === 'candidate_analysis') {
            $this->persistCandidateAnalysis($request, $output);

            return;
        }

        if ($request->request_type === 'sentiment_analysis') {
            $this->persistSentimentAnalysis($request, $output);

            return;
        }

        if ($request->request_type === 'sjt_scoring') {
            $this->persistSjtScoring($request, $output);
            return;
        }

        if ($request->request_type === 'strategy_lab_brief_generation') {
            $this->persistStrategyLabBrief($request, $output);
            return;
        }

        if ($request->request_type === 'strategy_lab_executive_summary') {
            $this->persistStrategyLabSummary($request, $output);
            return;
        }

        if ($request->request_type === 'rejection_draft') {
            $this->persistRejectionDraft($request, $output);
            return;
        }

        if ($request->request_type === 'video_response_metrics') {
            $this->persistVideoResponseMetrics($request, $output);
            return;
        }

        if ($request->request_type === 'async_video_unified_report') {
            $this->persistAsyncVideoUnifiedReport($request, $output);
        }
    }

    private function persistJobPersona(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $jobId = data_get($request->request_payload, 'job_id');

        if (! is_string($jobId) || $jobId === '') {
            return;
        }

        JobPersona::query()->updateOrCreate(
            ['job_id' => $jobId],
            ['persona_json' => $output]
        );
    }

    private function persistCvParsingResult(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $companyId = (string) $request->company_id;
        $candidateId = trim((string) data_get($request->request_payload, 'candidate_id', ''));
        if ($candidateId === '') {
            return;
        }
        $applicationId = trim((string) data_get($request->request_payload, 'application_id', ''));
        $applicationId = $applicationId !== '' ? $applicationId : null;

        $profile = data_get($output, 'profile', []);
        if (! is_array($profile)) {
            $profile = [];
        }

        $summary = trim((string) data_get($output, 'summary', ''));
        $hardSkills = $this->normalizeCvStringList(
            data_get($output, 'hard_skills', data_get($output, 'extracted_skills', []))
        );
        $softSkills = $this->normalizeCvStringList(data_get($output, 'soft_skills', []));
        $languages = $this->normalizeCvStringList(data_get($output, 'languages', []));
        $toolsFrameworks = $this->normalizeCvStringList(data_get($output, 'tools_frameworks', []));
        $keywords = $this->normalizeCvStringList(data_get($output, 'role_keywords', []));
        $experienceEntries = $this->normalizeCvExperienceEntries(data_get($output, 'experience', []));
        $employmentChronology = $this->normalizeCvEmploymentChronology(
            chronology: data_get($output, 'employment_chronology', []),
            fallbackExperienceEntries: $experienceEntries
        );
        $educationEntries = $this->normalizeCvEducationEntries(data_get($output, 'education', []));
        $certifications = $this->normalizeCvCertificationEntries(data_get($output, 'certifications', []));
        $projects = $this->normalizeCvProjectEntries(data_get($output, 'projects', []));

        $jobTitles = collect($experienceEntries)
            ->map(static fn (array $entry): string => trim((string) data_get($entry, 'job_title', '')))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
        $companies = collect($experienceEntries)
            ->map(static fn (array $entry): string => trim((string) data_get($entry, 'company', '')))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        $honors = $this->normalizeCvStringList(data_get($output, 'honors', []));
        $educationHonors = collect($educationEntries)
            ->flatMap(static fn (array $entry): array => (array) data_get($entry, 'honors', []))
            ->values()
            ->all();
        $honors = collect(array_merge($honors, $educationHonors))
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        $schoolCategories = collect($educationEntries)
            ->map(static fn (array $entry): string => trim((string) data_get($entry, 'school_category', 'unknown')))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
        if ($schoolCategories === []) {
            $schoolCategories = ['unknown'];
        }

        $parsedMetadata = data_get($output, 'parsed_metadata', []);
        if (! is_array($parsedMetadata)) {
            $parsedMetadata = [];
        }

        $genderInference = $this->normalizeGenderInference(data_get($parsedMetadata, 'gender_inference'));
        $schoolBackgroundTier = $this->deriveSchoolBackgroundTier(
            preferredTier: data_get($parsedMetadata, 'school_background_tier'),
            schoolCategories: $schoolCategories
        );

        $totalYearsExperience = data_get($output, 'total_years_experience');
        $totalYearsExperience = is_numeric($totalYearsExperience)
            ? (string) round(max(0, min(80, (float) $totalYearsExperience)), 2)
            : null;

        $flags = data_get($output, 'flags', []);
        if (! is_array($flags)) {
            $flags = [];
        }

        $payloadWarnings = collect((array) data_get($request->request_payload, 'extraction_warnings', []))
            ->map(static fn (mixed $warning): string => trim((string) $warning))
            ->filter(static fn (string $warning): bool => $warning !== '')
            ->values()
            ->all();
        $missingSections = collect((array) data_get($flags, 'missing_sections', []))
            ->map(static fn (mixed $warning): string => trim((string) $warning))
            ->filter(static fn (string $warning): bool => $warning !== '')
            ->values()
            ->all();
        $parseErrors = collect(array_merge($payloadWarnings, $missingSections))
            ->unique()
            ->values()
            ->all();

        $parseStatus = $this->resolveCvParseStatus(
            summary: $summary,
            hardSkills: $hardSkills,
            experienceEntries: $experienceEntries,
            educationEntries: $educationEntries
        );

        $parsedMetadata['parse_trigger'] = trim((string) data_get($request->request_payload, 'parse_trigger', 'cv_upload'));
        $parsedMetadata['parse_signature'] = trim((string) data_get($request->request_payload, 'parse_signature', ''));
        $parsedMetadata['parser_version'] = trim((string) data_get($request->request_payload, 'parser_version', ''));
        $parsedMetadata['extraction_warnings'] = $payloadWarnings;
        $parsedMetadata['gender_inference'] = $genderInference;
        $parsedMetadata['school_background_tier'] = $schoolBackgroundTier;
        $parsedMetadata['parse_status'] = $parseStatus;

        $oceanDependencyStatus = $this->resolveCvOceanDependencyStatus($companyId, $applicationId);

        $sourceDocumentId = trim((string) data_get($request->request_payload, 'resume_document_id', ''));
        $sourceDocumentId = Str::isUuid($sourceDocumentId) ? $sourceDocumentId : null;
        $sourceDocumentSha = trim((string) data_get($request->request_payload, 'source_document_sha256', ''));
        $sourceDocumentSha = $sourceDocumentSha !== '' ? $sourceDocumentSha : null;
        $parserVersion = trim((string) data_get($request->request_payload, 'parser_version', ''));
        if ($parserVersion === '') {
            $parserVersion = 'cv_parsing_v1';
        }

        $parsedPayload = [
            'summary' => $summary,
            'profile' => [
                'full_name' => trim((string) data_get($profile, 'full_name', data_get($output, 'full_name', ''))),
                'email' => trim((string) data_get($profile, 'email', data_get($output, 'email', ''))),
                'phone' => trim((string) data_get($profile, 'phone', data_get($output, 'phone', ''))),
                'location' => trim((string) data_get($profile, 'location', data_get($output, 'location', ''))),
            ],
            'languages' => $languages,
            'hard_skills' => $hardSkills,
            'soft_skills' => $softSkills,
            'tools_frameworks' => $toolsFrameworks,
            'total_years_experience' => $totalYearsExperience,
            'job_titles' => $jobTitles,
            'companies' => $companies,
            'experience' => $experienceEntries,
            'employment_chronology' => $employmentChronology,
            'certifications' => $certifications,
            'projects' => $projects,
            'education' => $educationEntries,
            'honors' => $honors,
            'keywords' => $keywords,
            'metadata' => $parsedMetadata,
            'flags' => $flags,
        ];

        $attributes = [
            'source_document_id' => $sourceDocumentId,
            'source_document_sha256' => $sourceDocumentSha,
            'parser_version' => $parserVersion,
            'parse_status' => $parseStatus,
            'profile_summary' => $summary !== '' ? $summary : null,
            'parsed_full_name' => trim((string) data_get($parsedPayload, 'profile.full_name')) ?: null,
            'parsed_email' => trim((string) data_get($parsedPayload, 'profile.email')) ?: null,
            'parsed_phone' => trim((string) data_get($parsedPayload, 'profile.phone')) ?: null,
            'parsed_location' => trim((string) data_get($parsedPayload, 'profile.location')) ?: null,
            'total_years_experience' => $totalYearsExperience,
            'languages_json' => $languages,
            'extracted_skills' => $hardSkills,
            'hard_skills_json' => $hardSkills,
            'soft_skills_json' => $softSkills,
            'tools_frameworks_json' => $toolsFrameworks,
            'job_titles_json' => $jobTitles,
            'companies_json' => $companies,
            'experience_entries_json' => $experienceEntries,
            'employment_chronology_json' => $employmentChronology,
            'certifications_json' => $certifications,
            'projects_json' => $projects,
            'education_entries_json' => $educationEntries,
            'honors_json' => $honors,
            'school_categories_json' => $schoolCategories,
            'keywords_json' => $keywords,
            'gender_inference' => $genderInference,
            'school_background_tier' => $schoolBackgroundTier,
            'ocean_dependency_status' => $oceanDependencyStatus,
            'parsed_metadata_json' => $parsedMetadata,
            'parsed_payload_json' => $parsedPayload,
            'raw_output_json' => $output,
            'flags_json' => $flags,
            'parse_errors_json' => $parseErrors,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $existingQuery = CvParsingResult::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('candidate_id', $candidateId);
        if ($applicationId !== null) {
            $existingQuery->where('application_id', $applicationId);
        } else {
            $existingQuery->whereNull('application_id');
        }

        $existing = $existingQuery
            ->latest('created_at')
            ->first();

        if ($existing instanceof CvParsingResult) {
            $existing->forceFill($attributes)->save();
            $this->refreshCandidateAnalysis($companyId, $applicationId);
            return;
        }

        CvParsingResult::withoutGlobalScopes()->create(array_merge(
            [
                'company_id' => $companyId,
                'candidate_id' => $candidateId,
                'application_id' => $applicationId,
            ],
            $attributes
        ));

        $this->refreshCandidateAnalysis($companyId, $applicationId);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeCvStringList(mixed $values, int $limit = 80): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(static function (mixed $value): string {
                if (is_array($value)) {
                    $value = data_get($value, 'name', data_get($value, 'label', data_get($value, 'value', '')));
                }

                return trim((string) $value);
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->map(static fn (string $value): string => Str::limit($value, 140, ''))
            ->unique(static fn (string $value): string => Str::lower($value))
            ->take(max(1, $limit))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCvExperienceEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return collect($entries)
            ->map(function (mixed $entry): ?array {
                if (is_string($entry) && trim($entry) !== '') {
                    return [
                        'job_title' => trim($entry),
                        'company' => null,
                        'start_date' => null,
                        'end_date' => null,
                        'is_current' => false,
                        'highlights' => [],
                    ];
                }

                if (! is_array($entry)) {
                    return null;
                }

                $jobTitle = trim((string) data_get($entry, 'job_title', data_get($entry, 'title', '')));
                $company = trim((string) data_get($entry, 'company', data_get($entry, 'organization', '')));
                $startDate = $this->normalizeCvDate(data_get($entry, 'start_date', data_get($entry, 'from')));
                $endDate = $this->normalizeCvDate(data_get($entry, 'end_date', data_get($entry, 'to')));
                $isCurrent = (bool) data_get($entry, 'is_current', false);

                $rawEnd = Str::lower(trim((string) data_get($entry, 'end_date', data_get($entry, 'to', ''))));
                if ($rawEnd !== '' && preg_match('/\b(current|present|now)\b/', $rawEnd) === 1) {
                    $endDate = null;
                    $isCurrent = true;
                }

                $highlights = $this->normalizeCvStringList(
                    data_get($entry, 'highlights', data_get($entry, 'responsibilities', [])),
                    8
                );

                if ($jobTitle === '' && $company === '' && $startDate === null && $endDate === null && $highlights === []) {
                    return null;
                }

                return [
                    'job_title' => $jobTitle !== '' ? $jobTitle : null,
                    'company' => $company !== '' ? $company : null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_current' => $isCurrent,
                    'highlights' => $highlights,
                ];
            })
            ->filter()
            ->take(24)
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $fallbackExperienceEntries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCvEmploymentChronology(mixed $chronology, array $fallbackExperienceEntries): array
    {
        if (is_array($chronology) && $chronology !== []) {
            $normalized = collect($chronology)
                ->map(function (mixed $item): ?array {
                    if (! is_array($item)) {
                        return null;
                    }

                    $jobTitle = trim((string) data_get($item, 'job_title', data_get($item, 'title', '')));
                    $company = trim((string) data_get($item, 'company', ''));
                    $period = trim((string) data_get($item, 'period', ''));
                    $startDate = $this->normalizeCvDate(data_get($item, 'start_date', data_get($item, 'from')));
                    $endDate = $this->normalizeCvDate(data_get($item, 'end_date', data_get($item, 'to')));

                    if ($jobTitle === '' && $company === '' && $period === '' && $startDate === null && $endDate === null) {
                        return null;
                    }

                    return [
                        'job_title' => $jobTitle !== '' ? $jobTitle : null,
                        'company' => $company !== '' ? $company : null,
                        'period' => $period !== '' ? $period : null,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ];
                })
                ->filter()
                ->take(24)
                ->values()
                ->all();

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return collect($fallbackExperienceEntries)
            ->map(static function (array $entry): array {
                $start = (string) data_get($entry, 'start_date', '');
                $end = (string) data_get($entry, 'end_date', '');
                $period = trim($start !== '' || $end !== '' ? ($start !== '' ? $start : '?').' to '.($end !== '' ? $end : 'Present') : '');

                return [
                    'job_title' => data_get($entry, 'job_title'),
                    'company' => data_get($entry, 'company'),
                    'period' => $period !== '' ? $period : null,
                    'start_date' => data_get($entry, 'start_date'),
                    'end_date' => data_get($entry, 'end_date'),
                ];
            })
            ->take(24)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCvEducationEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return collect($entries)
            ->map(function (mixed $entry): ?array {
                if (is_string($entry) && trim($entry) !== '') {
                    $schoolCategory = $this->inferSchoolCategoryFromName(trim($entry));

                    return [
                        'school_name' => trim($entry),
                        'degree_name' => null,
                        'study_field' => null,
                        'start_date' => null,
                        'end_date' => null,
                        'level' => null,
                        'school_category' => $schoolCategory,
                        'honors' => [],
                    ];
                }

                if (! is_array($entry)) {
                    return null;
                }

                $schoolName = trim((string) data_get($entry, 'school_name', data_get($entry, 'school', data_get($entry, 'institution', ''))));
                $degreeName = trim((string) data_get($entry, 'degree_name', data_get($entry, 'degree', '')));
                $studyField = trim((string) data_get($entry, 'study_field', data_get($entry, 'major', data_get($entry, 'field', ''))));
                $level = trim((string) data_get($entry, 'level', data_get($entry, 'degree_level', '')));
                $startDate = $this->normalizeCvDate(data_get($entry, 'start_date', data_get($entry, 'from')));
                $endDate = $this->normalizeCvDate(data_get($entry, 'end_date', data_get($entry, 'to')));
                $honors = $this->normalizeCvStringList(data_get($entry, 'honors', []), 8);

                $schoolCategoryInput = data_get($entry, 'school_category', data_get($entry, 'school_type'));
                $schoolCategory = $this->normalizeSchoolCategory($schoolCategoryInput);
                if ($schoolCategory === 'unknown' && $schoolName !== '') {
                    $schoolCategory = $this->inferSchoolCategoryFromName($schoolName);
                }

                if (
                    $schoolName === ''
                    && $degreeName === ''
                    && $studyField === ''
                    && $startDate === null
                    && $endDate === null
                    && $level === ''
                ) {
                    return null;
                }

                return [
                    'school_name' => $schoolName !== '' ? $schoolName : null,
                    'degree_name' => $degreeName !== '' ? $degreeName : null,
                    'study_field' => $studyField !== '' ? $studyField : null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'level' => $level !== '' ? $level : null,
                    'school_category' => $schoolCategory,
                    'honors' => $honors,
                ];
            })
            ->filter()
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCvCertificationEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return collect($entries)
            ->map(function (mixed $entry): ?array {
                if (is_string($entry) && trim($entry) !== '') {
                    return [
                        'name' => trim($entry),
                        'issuer' => null,
                        'date' => null,
                    ];
                }

                if (! is_array($entry)) {
                    return null;
                }

                $name = trim((string) data_get($entry, 'name', data_get($entry, 'title', '')));
                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'issuer' => trim((string) data_get($entry, 'issuer', data_get($entry, 'organization', ''))) ?: null,
                    'date' => $this->normalizeCvDate(data_get($entry, 'date', data_get($entry, 'issued_at'))),
                ];
            })
            ->filter()
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCvProjectEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        return collect($entries)
            ->map(function (mixed $entry): ?array {
                if (is_string($entry) && trim($entry) !== '') {
                    return [
                        'name' => trim($entry),
                        'description' => null,
                        'tools' => [],
                    ];
                }

                if (! is_array($entry)) {
                    return null;
                }

                $name = trim((string) data_get($entry, 'name', data_get($entry, 'title', '')));
                $description = trim((string) data_get($entry, 'description', data_get($entry, 'summary', '')));
                $tools = $this->normalizeCvStringList(data_get($entry, 'tools', data_get($entry, 'technologies', [])), 12);

                if ($name === '' && $description === '' && $tools === []) {
                    return null;
                }

                return [
                    'name' => $name !== '' ? $name : null,
                    'description' => $description !== '' ? $description : null,
                    'tools' => $tools,
                ];
            })
            ->filter()
            ->take(20)
            ->values()
            ->all();
    }

    private function normalizeCvDate(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/\b(current|present|now)\b/i', $raw) === 1) {
            return null;
        }

        $normalized = str_replace('/', '-', $raw);
        if (preg_match('/^\d{4}$/', $normalized) === 1) {
            return $normalized;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $normalized, $matches) === 1) {
            return sprintf('%04d-%02d', (int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $normalized, $matches) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeSchoolCategory(mixed $value): string
    {
        $normalized = Str::lower(trim((string) $value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match (true) {
            in_array($normalized, ['top_school', 'topschool', 'elite_school'], true) => 'top_school',
            str_contains($normalized, 'grande') || str_contains($normalized, 'ecole') => 'grande_ecole',
            str_contains($normalized, 'faculty') => 'faculty',
            str_contains($normalized, 'university') || str_contains($normalized, 'college') => 'regular_university',
            $normalized === '' || $normalized === 'unknown' => 'unknown',
            default => 'unknown',
        };
    }

    private function inferSchoolCategoryFromName(string $schoolName): string
    {
        $normalized = Str::lower(trim($schoolName));
        if ($normalized === '') {
            return 'unknown';
        }

        $topSchoolKeywords = [
            'mit',
            'stanford',
            'harvard',
            'oxford',
            'cambridge',
            'imperial college',
            'eth zurich',
            'hec paris',
            'insead',
            'ecole polytechnique',
        ];
        foreach ($topSchoolKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'top_school';
            }
        }

        if (str_contains($normalized, 'grande ecole') || str_contains($normalized, 'ecole')) {
            return 'grande_ecole';
        }

        if (str_contains($normalized, 'faculty')) {
            return 'faculty';
        }

        if (str_contains($normalized, 'university') || str_contains($normalized, 'college')) {
            return 'regular_university';
        }

        return 'unknown';
    }

    private function normalizeGenderInference(mixed $value): string
    {
        $normalized = Str::lower(trim((string) $value));

        return match (true) {
            in_array($normalized, ['male', 'man', 'm'], true) => 'male',
            in_array($normalized, ['female', 'woman', 'f'], true) => 'female',
            in_array($normalized, ['non_binary', 'non-binary', 'nonbinary', 'nb'], true) => 'non_binary',
            default => 'unknown',
        };
    }

    /**
     * @param array<int, string> $schoolCategories
     */
    private function deriveSchoolBackgroundTier(mixed $preferredTier, array $schoolCategories): string
    {
        $preferred = Str::lower(trim((string) $preferredTier));
        $preferred = str_replace([' ', '-'], '_', $preferred);
        if (in_array($preferred, ['top_school', 'grande_ecole', 'regular_university', 'faculty', 'mixed', 'unknown'], true)) {
            return $preferred;
        }

        $categories = collect($schoolCategories)
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        if ($categories->isEmpty()) {
            return 'unknown';
        }

        if ($categories->count() > 1) {
            if ($categories->contains('top_school')) {
                return 'top_school';
            }

            if ($categories->contains('grande_ecole')) {
                return 'grande_ecole';
            }

            return 'mixed';
        }

        $single = (string) $categories->first();
        if (in_array($single, ['top_school', 'grande_ecole', 'regular_university', 'faculty'], true)) {
            return $single;
        }

        return 'unknown';
    }

    /**
     * @param array<int, mixed> $hardSkills
     * @param array<int, array<string, mixed>> $experienceEntries
     * @param array<int, array<string, mixed>> $educationEntries
     */
    private function resolveCvParseStatus(string $summary, array $hardSkills, array $experienceEntries, array $educationEntries): string
    {
        $signals = 0;
        if ($summary !== '') {
            $signals++;
        }
        if ($hardSkills !== []) {
            $signals++;
        }
        if ($experienceEntries !== []) {
            $signals++;
        }
        if ($educationEntries !== []) {
            $signals++;
        }

        return $signals >= 3 ? 'succeeded' : 'partial';
    }

    private function resolveCvOceanDependencyStatus(string $companyId, ?string $applicationId): string
    {
        if (! is_string($applicationId) || $applicationId === '') {
            return 'unavailable';
        }

        $application = Application::withoutGlobalScopes()
            ->where('id', $applicationId)
            ->where('company_id', $companyId)
            ->first();
        if (! $application instanceof Application) {
            return 'unavailable';
        }

        $hasVideoInput = VideoResponse::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('application_id', $applicationId)
            ->where(function ($query): void {
                $query->whereNotNull('transcript_text')
                    ->orWhereNotNull('video_file_url');
            })
            ->exists();

        return $hasVideoInput ? 'ready_for_analysis' : 'pending_input';
    }

    private function persistCandidateAnalysis(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $applicationId = data_get($request->request_payload, 'application_id');
        if (! is_string($applicationId) || $applicationId === '') {
            return;
        }

        $companyId = (string) $request->company_id;

        ApplicationScoring::withoutGlobalScopes()->updateOrCreate(
            ['application_id' => $applicationId],
            [
                'company_id' => $companyId,
                'global_match_score' => (string) ((float) data_get($output, 'global_match_score', 0)),
                'vrin_json' => data_get($output, 'vrin', [
                    'acquired_skills' => [],
                    'missing_skills' => [],
                ]),
                'xai_summary' => (string) data_get($output, 'xai_summary', 'Not scored yet.'),
                'updated_at' => now(),
            ]
        );

        $ocean = data_get($output, 'ocean', []);
        $salary = data_get($output, 'salary', []);

        $matchPct = data_get($output, 'match_percentage');
        $salaryFit = data_get($salary, 'fit_score');

        UnifiedInterviewReport::withoutGlobalScopes()->updateOrCreate(
            ['application_id' => $applicationId],
            [
                'company_id' => $companyId,
                'ai_full_payload' => $output,
                'xai_summary' => (string) data_get($output, 'xai_summary', ''),
                'ocean_openness' => data_get($ocean, 'openness'),
                'ocean_conscientiousness' => data_get($ocean, 'conscientiousness'),
                'ocean_extraversion' => data_get($ocean, 'extraversion'),
                'ocean_agreeableness' => data_get($ocean, 'agreeableness'),
                'ocean_neuroticism' => data_get($ocean, 'neuroticism'),
                'generic_motivation' => (bool) data_get($output, 'generic_motivation', data_get($output, 'is_generic_motivation', false)),
                'match_percentage' => is_numeric($matchPct) ? (string) ((float) $matchPct) : null,
                'salary_expected_min' => data_get($salary, 'expected_min'),
                'salary_expected_max' => data_get($salary, 'expected_max'),
                'salary_currency' => data_get($salary, 'currency'),
                'salary_fit_score' => is_numeric($salaryFit) ? (string) ((float) $salaryFit) : null,
                'updated_at' => now(),
            ]
        );

        $this->refreshCandidateAnalysis($companyId, $applicationId);
    }

    private function persistSentimentAnalysis(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $sourceType = trim((string) data_get($request->request_payload, 'source_type', ''));
        $sourceId = trim((string) data_get($request->request_payload, 'source_id', ''));

        if ($sourceType === '' || $sourceId === '') {
            return;
        }

        $score = $this->normalizeSentimentScore(data_get($output, 'score'));
        $themes = $this->normalizeSentimentThemes(data_get($output, 'themes', []));
        $riskLevel = $this->normalizeSentimentRisk(
            riskLevel: data_get($output, 'risk_level'),
            score: $score
        );

        $result = SentimentResult::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => (string) $request->company_id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'sentiment_score' => $score,
                'top_themes_json' => $themes,
                'risk_level' => $riskLevel,
                'created_at' => now(),
            ]
        );

        $this->employerBrandAlerts->evaluateFromSentimentResult($result);
    }

    private function normalizeSentimentScore(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) round(max(-1, min(1, (float) $value)), 4);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSentimentThemes(mixed $themes): array
    {
        if (! is_array($themes)) {
            return [];
        }

        return collect($themes)
            ->map(static fn (mixed $theme): string => trim((string) $theme))
            ->filter(static fn (string $theme): bool => $theme !== '')
            ->map(static fn (string $theme): string => Str::limit(Str::lower($theme), 60, ''))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function normalizeSentimentRisk(mixed $riskLevel, ?float $score): string
    {
        $normalized = Str::lower(trim((string) $riskLevel));
        if (in_array($normalized, SentimentResult::riskLevels(), true)) {
            return $normalized;
        }

        if (! is_numeric($score)) {
            return SentimentResult::RISK_PENDING;
        }

        return match (true) {
            $score <= -0.75 => SentimentResult::RISK_CRITICAL,
            $score <= -0.45 => SentimentResult::RISK_HIGH,
            $score <= -0.20 => SentimentResult::RISK_MEDIUM,
            default => SentimentResult::RISK_LOW,
        };
    }

    private function persistVideoResponseMetrics(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $responseId = data_get($request->request_payload, 'video_response_id');
        if (! is_string($responseId) || $responseId === '') {
            return;
        }

        $videoResponse = VideoResponse::withoutGlobalScopes()
            ->where('id', $responseId)
            ->where('company_id', $request->company_id)
            ->first();

        if (! $videoResponse instanceof VideoResponse) {
            return;
        }

        $transcript = trim((string) data_get($output, 'transcript_text', (string) ($videoResponse->transcript_text ?? '')));
        $pauses = data_get($output, 'pauses_count');
        $speechRate = data_get($output, 'speech_rate_estimate');
        $fillerRatio = data_get($output, 'filler_ratio_estimate');

        $videoResponse->forceFill([
            'transcript_text' => $transcript !== '' ? $transcript : null,
            'pauses_count' => is_numeric($pauses)
                ? max(0, (int) $pauses)
                : $videoResponse->pauses_count,
            'speech_rate_estimate' => is_numeric($speechRate)
                ? (string) round(max(0, min(800, (float) $speechRate)), 2)
                : $videoResponse->speech_rate_estimate,
            'filler_ratio_estimate' => is_numeric($fillerRatio)
                ? (string) round(max(0, min(1, (float) $fillerRatio)), 4)
                : $videoResponse->filler_ratio_estimate,
        ])->save();
    }

    private function persistAsyncVideoUnifiedReport(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $applicationId = data_get($request->request_payload, 'application_id');
        if (! is_string($applicationId) || $applicationId === '') {
            return;
        }

        $companyId = (string) $request->company_id;
        $globalMatchScore = data_get($output, 'global_match_score');
        if (! is_numeric($globalMatchScore)) {
            $globalMatchScore = data_get($output, 'match_percentage', 0);
        }
        $globalMatchScore = (string) round(max(0, min(100, (float) $globalMatchScore)), 2);

        $xaiSummary = trim((string) data_get($output, 'xai_summary', 'Not scored yet.'));
        if ($xaiSummary === '') {
            $xaiSummary = 'Not scored yet.';
        }

        $vrin = data_get($output, 'vrin');
        if (! is_array($vrin)) {
            $vrin = [
                'acquired_skills' => [],
                'missing_skills' => [],
            ];
        }

        ApplicationScoring::withoutGlobalScopes()->updateOrCreate(
            ['application_id' => $applicationId],
            [
                'company_id' => $companyId,
                'global_match_score' => $globalMatchScore,
                'vrin_json' => $vrin,
                'xai_summary' => $xaiSummary,
                'updated_at' => now(),
            ]
        );

        $ocean = data_get($output, 'ocean');
        if (! is_array($ocean)) {
            $ocean = [];
        }
        $salary = data_get($output, 'salary');
        if (! is_array($salary)) {
            $salary = [];
        }
        $salaryCurrency = trim((string) data_get($salary, 'currency', ''));

        $normalizeScore = static fn (mixed $value): ?int => is_numeric($value)
            ? (int) round(max(0, min(100, (float) $value)))
            : null;

        UnifiedInterviewReport::withoutGlobalScopes()->updateOrCreate(
            ['application_id' => $applicationId],
            [
                'company_id' => $companyId,
                'ai_full_payload' => $output,
                'xai_summary' => $xaiSummary,
                'ocean_openness' => $normalizeScore(data_get($ocean, 'openness')),
                'ocean_conscientiousness' => $normalizeScore(data_get($ocean, 'conscientiousness')),
                'ocean_extraversion' => $normalizeScore(data_get($ocean, 'extraversion')),
                'ocean_agreeableness' => $normalizeScore(data_get($ocean, 'agreeableness')),
                'ocean_neuroticism' => $normalizeScore(data_get($ocean, 'neuroticism')),
                'generic_motivation' => (bool) data_get($output, 'generic_motivation', data_get($output, 'is_generic_motivation', false)),
                'match_percentage' => is_numeric(data_get($output, 'match_percentage'))
                    ? (string) round(max(0, min(100, (float) data_get($output, 'match_percentage'))), 2)
                    : null,
                'salary_expected_min' => is_numeric(data_get($salary, 'expected_min'))
                    ? (int) round((float) data_get($salary, 'expected_min'))
                    : null,
                'salary_expected_max' => is_numeric(data_get($salary, 'expected_max'))
                    ? (int) round((float) data_get($salary, 'expected_max'))
                    : null,
                'salary_currency' => $salaryCurrency !== '' ? $salaryCurrency : null,
                'salary_fit_score' => is_numeric(data_get($salary, 'fit_score'))
                    ? (string) round(max(0, min(100, (float) data_get($salary, 'fit_score'))), 2)
                    : null,
                'updated_at' => now(),
            ]
        );

        $this->refreshCandidateAnalysis($companyId, $applicationId);
    }

    private function persistSjtScoring(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $responseId = data_get($request->request_payload, 'sjt_response_id');
        if (! is_string($responseId) || $responseId === '') {
            return;
        }

        $response = SjtResponse::withoutGlobalScopes()
            ->where('id', $responseId)
            ->where('company_id', $request->company_id)
            ->first();

        if (! $response instanceof SjtResponse) {
            return;
        }

        $score = data_get($output, 'score');
        $normalizedScore = is_numeric($score)
            ? (string) round(max(0, min(100, (float) $score)), 2)
            : null;

        $normalizeSignal = static function (mixed $value): string {
            $normalized = Str::lower(trim((string) $value));
            if (in_array($normalized, ['high', 'medium', 'low'], true)) {
                return $normalized;
            }

            return 'medium';
        };

        $signals = [
            'accountability' => $normalizeSignal(data_get($output, 'signals.accountability')),
            'solution_orientation' => $normalizeSignal(data_get($output, 'signals.solution_orientation')),
            'tone' => $normalizeSignal(data_get($output, 'signals.tone')),
        ];

        $feedback = data_get($output, 'feedback');
        if (! is_array($feedback)) {
            $feedback = [];
        }

        $feedback['signals'] = $signals;

        $response->forceFill([
            'ai_score' => $normalizedScore,
            'ai_feedback_json' => $feedback,
            'updated_at' => now(),
        ])->save();

        $applicationId = (string) $response->application_id;
        if ($applicationId === '' || ! is_numeric($normalizedScore)) {
            return;
        }

        $sjtResponses = SjtResponse::withoutGlobalScopes()
            ->where('company_id', $request->company_id)
            ->where('application_id', $applicationId)
            ->whereNotNull('ai_score')
            ->get(['ai_score', 'updated_at']);

        if ($sjtResponses->isEmpty()) {
            return;
        }

        $sjtAverage = round((float) $sjtResponses->avg(
            static fn (SjtResponse $sjtResponse): float => (float) $sjtResponse->ai_score
        ), 2);

        $existingScoring = ApplicationScoring::withoutGlobalScopes()
            ->where('application_id', $applicationId)
            ->where('company_id', $request->company_id)
            ->first();

        $existingVrin = is_array($existingScoring?->vrin_json)
            ? $existingScoring->vrin_json
            : [];

        $acquiredSkills = collect((array) data_get($existingVrin, 'acquired_skills', []))
            ->map(static fn (mixed $skill): string => trim((string) $skill))
            ->filter(static fn (string $skill): bool => $skill !== '')
            ->values()
            ->all();
        $missingSkills = collect((array) data_get($existingVrin, 'missing_skills', []))
            ->map(static fn (mixed $skill): string => trim((string) $skill))
            ->filter(static fn (string $skill): bool => $skill !== '')
            ->values()
            ->all();

        $baseScore = data_get($existingVrin, 'base_global_match_score');
        if (! is_numeric($baseScore) && is_numeric($existingScoring?->global_match_score)) {
            $baseScore = (float) $existingScoring->global_match_score;
        }

        $overallScore = is_numeric($baseScore)
            ? round((((float) $baseScore) * 0.8) + ($sjtAverage * 0.2), 2)
            : $sjtAverage;

        $xaiSummary = trim((string) ($existingScoring?->xai_summary ?? ''));
        $sjtSummaryLine = 'Situational Judgment signals have been incorporated into your overall score.';
        if ($xaiSummary === '' || Str::lower($xaiSummary) === 'not scored yet.') {
            $xaiSummary = $sjtSummaryLine;
        } elseif (! Str::contains(Str::lower($xaiSummary), 'situational judgment')) {
            $xaiSummary .= ' '.$sjtSummaryLine;
        }

        ApplicationScoring::withoutGlobalScopes()->updateOrCreate(
            ['application_id' => $applicationId],
            [
                'company_id' => (string) $request->company_id,
                'global_match_score' => $overallScore,
                'vrin_json' => [
                    'acquired_skills' => $acquiredSkills,
                    'missing_skills' => $missingSkills,
                    'base_global_match_score' => is_numeric($baseScore)
                        ? round((float) $baseScore, 2)
                        : null,
                    'sjt_signals' => [
                        'average_score' => $sjtAverage,
                        'latest_score' => $normalizedScore,
                        'scored_responses' => $sjtResponses->count(),
                        'accountability' => $signals['accountability'],
                        'solution_orientation' => $signals['solution_orientation'],
                        'tone' => $signals['tone'],
                    ],
                ],
                'xai_summary' => $xaiSummary,
                'updated_at' => now(),
            ]
        );

        $this->refreshCandidateAnalysis((string) $request->company_id, $applicationId);
    }

    private function persistStrategyLabBrief(AiRequest $request, mixed $output): void
    {
        $briefId = data_get($request->request_payload, 'strategy_lab_brief_id');
        if (! is_string($briefId) || $briefId === '') {
            return;
        }

        $brief = StrategyLabBrief::withoutGlobalScopes()
            ->where('id', $briefId)
            ->where('company_id', $request->company_id)
            ->first();

        if (! $brief instanceof StrategyLabBrief) {
            return;
        }

        $body = trim(is_array($output) ? (string) json_encode($output, JSON_UNESCAPED_UNICODE) : (string) $output);
        if ($body === '') {
            $body = 'No brief content generated.';
        }

        $pdfContent = $this->buildSimplePdfDocument(
            title: (string) $brief->brief_title,
            body: $body
        );

        $path = "private/strategy-lab/briefs/{$brief->company_id}/{$brief->application_id}/{$brief->id}.pdf";
        Storage::disk('local')->put($path, $pdfContent);

        $brief->forceFill([
            'brief_pdf_url' => $path,
            'generated_ai_request_id' => $request->id,
            'updated_at' => now(),
        ])->save();
    }

    private function persistStrategyLabSummary(AiRequest $request, mixed $output): void
    {
        if (! is_array($output)) {
            return;
        }

        $applicationId = data_get($request->request_payload, 'application_id');
        if (! is_string($applicationId) || $applicationId === '') {
            return;
        }

        $overallRecommendation = trim((string) data_get($output, 'overall_recommendation', ''));

        StrategyLabAiSummary::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $request->company_id,
                'application_id' => $applicationId,
            ],
            [
                'executive_summary_text' => (string) data_get($output, 'executive_summary_text', ''),
                'strengths_json' => array_values(array_filter((array) data_get($output, 'strengths_json', []))),
                'weaknesses_json' => array_values(array_filter((array) data_get($output, 'weaknesses_json', []))),
                'creativity_score' => (string) round((float) data_get($output, 'creativity_score', 0), 2),
                'overall_recommendation' => $overallRecommendation !== '' ? $overallRecommendation : null,
                'updated_at' => now(),
            ]
        );

        $this->refreshCandidateAnalysis((string) $request->company_id, $applicationId);
    }

    private function persistRejectionDraft(AiRequest $request, mixed $output): void
    {
        $rejectionDraftId = data_get($request->request_payload, 'rejection_draft_id');
        if (! is_string($rejectionDraftId) || $rejectionDraftId === '') {
            return;
        }

        $rejectionDraft = RejectionDraft::withoutGlobalScopes()
            ->where('id', $rejectionDraftId)
            ->where('company_id', $request->company_id)
            ->first();

        if (! $rejectionDraft instanceof RejectionDraft) {
            return;
        }

        if ($rejectionDraft->status === RejectionDraft::STATUS_SENT) {
            return;
        }

        $generated = trim(is_array($output) ? (string) json_encode($output, JSON_UNESCAPED_UNICODE) : (string) $output);
        if ($generated === '') {
            return;
        }

        $subject = trim((string) $rejectionDraft->draft_subject);
        $body = $generated;

        if (preg_match('/^\s*subject\s*:\s*(.+)$/im', $generated, $matches) === 1) {
            $parsedSubject = trim((string) ($matches[1] ?? ''));
            if ($parsedSubject !== '') {
                $subject = $parsedSubject;
            }

            $body = trim((string) preg_replace('/^\s*subject\s*:\s*.+$\R?/im', '', $generated, 1));
        }

        if (Str::startsWith(Str::lower($body), 'body:')) {
            $body = trim((string) Str::of($body)->after('body:'));
        }

        if ($subject === '') {
            $subject = trim((string) $rejectionDraft->draft_subject);
        }

        if ($body === '') {
            $body = trim((string) $rejectionDraft->draft_body);
        }

        $xaiReasonText = trim((string) data_get($request->request_payload, 'xai_reason_text', (string) $rejectionDraft->xai_reason_text));
        if ($xaiReasonText === '') {
            $xaiReasonText = trim((string) $rejectionDraft->xai_reason_text);
        }

        $rejectionDraft->forceFill([
            'draft_subject' => $subject,
            'draft_body' => $body,
            'xai_reason_text' => $xaiReasonText,
            'status' => $rejectionDraft->status === RejectionDraft::STATUS_APPROVED
                ? RejectionDraft::STATUS_APPROVED
                : RejectionDraft::STATUS_DRAFT,
            'updated_at' => now(),
        ])->save();
    }

    private function refreshCandidateAnalysis(string $companyId, ?string $applicationId): void
    {
        if (! is_string($applicationId) || trim($applicationId) === '') {
            return;
        }

        try {
            $this->candidateAnalysisService->recomputeForApplicationId(
                companyId: $companyId,
                applicationId: $applicationId
            );
        } catch (Throwable) {
            // Never fail AI persistence if deterministic analysis refresh fails.
        }
    }

    private function buildSimplePdfDocument(string $title, string $body): string
    {
        $lines = collect(preg_split('/\r\n|\n|\r/', $body) ?: [])
            ->flatMap(function (string $line): array {
                $wrapped = wordwrap(trim($line), 92, "\n", true);
                return preg_split('/\n/', $wrapped ?: '') ?: [''];
            })
            ->map(fn (string $line): string => $this->escapePdfText($line))
            ->take(42)
            ->values();

        $titleEscaped = $this->escapePdfText($title);
        $stream = "BT\n/F1 16 Tf\n1 0 0 1 50 770 Tm\n({$titleEscaped}) Tj\n";
        $stream .= "/F1 11 Tf\n";

        $y = 742;
        foreach ($lines as $line) {
            $stream .= "1 0 0 1 50 {$y} Tm\n({$line}) Tj\n";
            $y -= 16;
        }
        $stream .= "ET";

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[] = "5 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $value
        );
    }
}
