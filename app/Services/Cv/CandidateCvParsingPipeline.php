<?php

namespace App\Services\Cv;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Job;
use App\Services\Ai\AiRequestService;
use App\Support\Jobs\JobDescriptionContentRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CandidateCvParsingPipeline
{
    public const PARSER_VERSION = 'cv_parsing_v4';

    public function __construct(
        private readonly AiRequestService $aiRequestService,
        private readonly JobDescriptionContentRenderer $descriptionRenderer
    ) {
    }

    public function queueForApplication(
        Application $application,
        ?CandidateDocument $resumeDocument = null,
        string $trigger = 'candidate_import'
    ): ?AiRequest {
        if ((string) $application->candidate_id === '' || (string) $application->company_id === '') {
            return null;
        }

        $candidate = Candidate::withoutGlobalScopes()
            ->where('id', (string) $application->candidate_id)
            ->where('company_id', (string) $application->company_id)
            ->first();

        if (! $candidate instanceof Candidate) {
            return null;
        }

        $resume = $resumeDocument;
        if (! $resume instanceof CandidateDocument) {
            $resume = $this->latestResumeDocument(
                companyId: (string) $application->company_id,
                candidateId: (string) $application->candidate_id
            );
        }

        if (! $resume instanceof CandidateDocument) {
            return null;
        }

        return $this->queueSingleParse(
            candidate: $candidate,
            application: $application,
            resumeDocument: $resume,
            trigger: $trigger
        );
    }

    public function queueForResumeDocument(
        CandidateDocument $resumeDocument,
        string $trigger = 'cv_upload'
    ): int {
        if ((string) $resumeDocument->document_type !== CandidateDocument::TYPE_RESUME) {
            return 0;
        }

        $candidate = Candidate::withoutGlobalScopes()
            ->where('id', (string) $resumeDocument->candidate_id)
            ->where('company_id', (string) $resumeDocument->company_id)
            ->first();

        if (! $candidate instanceof Candidate) {
            return 0;
        }

        $applications = Application::withoutGlobalScopes()
            ->where('company_id', (string) $resumeDocument->company_id)
            ->where('candidate_id', (string) $resumeDocument->candidate_id)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get(['id', 'company_id', 'candidate_id', 'job_id', 'current_stage_id', 'status', 'source_type', 'created_at', 'updated_at']);

        if ($applications->isEmpty()) {
            $this->queueSingleParse(
                candidate: $candidate,
                application: null,
                resumeDocument: $resumeDocument,
                trigger: $trigger
            );

            return 1;
        }

        $queued = 0;
        foreach ($applications as $application) {
            $request = $this->queueSingleParse(
                candidate: $candidate,
                application: $application,
                resumeDocument: $resumeDocument,
                trigger: $trigger
            );
            if ($request instanceof AiRequest) {
                $queued++;
            }
        }

        return $queued;
    }

    private function latestResumeDocument(string $companyId, string $candidateId): ?CandidateDocument
    {
        return CandidateDocument::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('candidate_id', $candidateId)
            ->where('document_type', CandidateDocument::TYPE_RESUME)
            ->orderByDesc('created_at')
            ->first();
    }

    private function queueSingleParse(
        Candidate $candidate,
        ?Application $application,
        CandidateDocument $resumeDocument,
        string $trigger
    ): ?AiRequest {
        $companyId = (string) $candidate->company_id;
        $candidateId = (string) $candidate->id;
        if ($companyId === '' || $candidateId === '') {
            return null;
        }

        $job = null;
        if ($application instanceof Application && (string) $application->job_id !== '') {
            $job = Job::withoutGlobalScopes()
                ->with('descriptionBlocks:id,job_id,block_type,block_content_json,display_order')
                ->where('id', (string) $application->job_id)
                ->where('company_id', $companyId)
                ->first();
        }

        $resumePdf = $this->resumePdfPayload($resumeDocument);
        if ($resumePdf === null) {
            Log::warning('Skipping CV parsing because the resume PDF could not be prepared.', [
                'company_id' => $companyId,
                'candidate_id' => $candidateId,
                'application_id' => $application instanceof Application ? (string) $application->id : null,
                'resume_document_id' => (string) $resumeDocument->id,
            ]);

            return null;
        }

        $applicationId = $application instanceof Application ? (string) $application->id : null;
        $jobId = $job instanceof Job ? (string) $job->id : null;
        $parseSignature = hash('sha256', implode('|', [
            self::PARSER_VERSION,
            $candidateId,
            $applicationId ?? 'none',
            $jobId ?? 'none',
            (string) $resumeDocument->id,
            $resumePdf['sha256'],
        ]));

        $cachePath = 'cv_cache/' . $resumePdf['sha256'] . '.json';
        if (Storage::disk('local')->exists($cachePath)) {
            $cachedJson = Storage::disk('local')->get($cachePath);
            $parsedOutput = json_decode((string) $cachedJson, true);

            if (is_array($parsedOutput)) {
                $request = AiRequest::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'request_type' => 'cv_parsing',
                    'input_hash' => hash('sha256', 'cached_' . $parseSignature),
                    'status' => AiRequest::STATUS_SUCCEEDED,
                    'model_name' => 'precomputed-cache',
                    'prompt_version' => self::PARSER_VERSION,
                    'request_payload' => [
                        'candidate_id' => $candidateId,
                        'application_id' => $applicationId,
                        'job_id' => $jobId,
                        'resume_document_id' => (string) $resumeDocument->id,
                        'source_document_sha256' => $resumePdf['sha256'],
                        'parser_version' => self::PARSER_VERSION,
                        'parse_trigger' => trim($trigger) !== '' ? trim($trigger) : 'cv_upload',
                        'parse_signature' => $parseSignature,
                    ],
                    'response_payload' => [
                        'mode' => 'json_schema',
                        'output' => $parsedOutput,
                        'attempts' => [['status' => 'cached']],
                    ],
                    'started_at' => now(),
                    'finished_at' => now(),
                    'created_at' => now(),
                ]);

                // Immediately trigger the success side effects to save the data
                app(\App\Services\Ai\AiRequestService::class)->persistCvParsingResult($request, $parsedOutput);

                return $request;
            }
        }

        if ($this->hasLiveOrSucceededRequest($companyId, $parseSignature)) {
            return null;
        }

        $payload = [
            'output_mode' => 'json_schema',
            'candidate_id' => $candidateId,
            'application_id' => $applicationId,
            'job_id' => $jobId,
            'resume_document_id' => (string) $resumeDocument->id,
            'source_document_sha256' => $resumePdf['sha256'],
            'parser_version' => self::PARSER_VERSION,
            'parse_trigger' => trim($trigger) !== '' ? trim($trigger) : 'cv_upload',
            'parse_signature' => $parseSignature,
            'candidate_snapshot' => [
                'full_name' => (string) $candidate->full_name,
                'email' => (string) $candidate->email,
                'phone' => (string) ($candidate->phone ?? ''),
                'location' => (string) ($candidate->location ?? ''),
            ],
            'resume_meta' => $resumePdf['meta'],
            'prompt' => $this->buildPrompt(
                candidate: $candidate,
                application: $application,
                job: $job
            ),
            'json_schema' => $this->jsonSchema(),
        ];

        try {
            return $this->aiRequestService->queueRequest(
                companyId: $companyId,
                requestType: 'cv_parsing',
                requestPayload: $payload,
                promptVersion: self::PARSER_VERSION
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed to queue CV parsing request.', [
                'company_id' => $companyId,
                'candidate_id' => $candidateId,
                'application_id' => $applicationId,
                'resume_document_id' => (string) $resumeDocument->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function hasLiveOrSucceededRequest(string $companyId, string $parseSignature): bool
    {
        return AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'cv_parsing')
            ->where('request_payload->parse_signature', $parseSignature)
            ->whereIn('status', [
                AiRequest::STATUS_QUEUED,
                AiRequest::STATUS_RUNNING,
                AiRequest::STATUS_SUCCEEDED,
            ])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonSchema(): array
    {
        return [
            'required' => [
                'summary',
                'profile',
                'languages',
                'hard_skills',
                'soft_skills',
                'tools_frameworks',
                'total_years_experience',
                'experience',
                'employment_chronology',
                'certifications',
                'projects',
                'education',
                'honors',
                'role_keywords',
                'parsed_metadata',
                'flags',
            ],
            'properties' => [
                'summary' => ['type' => 'string'],
                'profile' => ['type' => 'object'],
                'languages' => ['type' => 'array'],
                'hard_skills' => ['type' => 'array'],
                'soft_skills' => ['type' => 'array'],
                'tools_frameworks' => ['type' => 'array'],
                'total_years_experience' => ['type' => 'number'],
                'experience' => ['type' => 'array'],
                'employment_chronology' => ['type' => 'array'],
                'certifications' => ['type' => 'array'],
                'projects' => ['type' => 'array'],
                'education' => ['type' => 'array'],
                'honors' => ['type' => 'array'],
                'role_keywords' => ['type' => 'array'],
                'parsed_metadata' => ['type' => 'object'],
                'flags' => ['type' => 'object'],
            ],
        ];
    }

    /**
     * The prompt pushes the model to return complete, structured data and to mark uncertain values as unknown.
     */
    private function buildPrompt(
        Candidate $candidate,
        ?Application $application,
        ?Job $job
    ): string {
        $jobTitle = trim((string) ($job?->title ?? 'Unknown role'));
        $jobSummary = $this->jobSummary($job);
        $applicationStatus = trim((string) ($application?->status ?? 'unknown'));
        $applicationSource = trim((string) ($application?->source_type ?? 'unknown'));

        return implode("\n", [
            'You are an advanced, strictly impartial HR-Tech AI for CV parsing and job-alignment extraction.',
            'Return only strict JSON matching the provided schema. Never return markdown fences, XML, or explanatory text.',
            '',
            '<objective>',
            'Analyze the attached candidate CV PDF against the job description context.',
            'Extract evidence-based hard skills, soft skills, tools, experience chronology, certifications, education, and role-aligned keywords.',
            'Use semantic recognition instead of exact keyword matching, but never hallucinate unsupported skills or achievements.',
            'Use the attached PDF as the source of truth, including visible document structure, headings, tables, and formatting cues when helpful.',
            '</objective>',
            '',
            '<anti_bias_guardrails>',
            '1. BLIND EVALUATION: Ignore candidate name, gender, age, nationality, university prestige, and geographic location when assessing role fit.',
            '2. SEMANTIC RECOGNITION: Recognize synonyms and contextual evidence of skills, ownership, leadership, collaboration, and domain experience.',
            '3. EVIDENCE-BASED: Every extracted skill or keyword must be grounded in the attached CV PDF or explicit structured context.',
            '4. UNCERTAINTY HANDLING: If data is missing or ambiguous, keep the schema intact and return null, empty arrays, or unknown values.',
            '</anti_bias_guardrails>',
            '',
            '<analysis_instructions>',
            '1. Deconstruct the job description into must-have technical requirements, likely soft-skill expectations, and experience signals.',
            '2. Cross-reference the CV PDF against those requirements using semantic understanding rather than exact token overlap.',
            '3. Quantify relevant experience specific to the role; exclude clearly unrelated experience from the role-focused estimate when possible.',
            '4. Extract only parsable structured JSON fields required by the schema. Do not output hidden reasoning, but let the extraction reflect the private analysis.',
            '</analysis_instructions>',
            '',
            '<output_rules>',
            'If data is missing, keep structure and return null/empty values instead of failing.',
            'Education is critical: output one object per school with exact school name when present.',
            'Allowed education school_category values: top_school, grande_ecole, regular_university, faculty, unknown.',
            'If category is uncertain, return unknown.',
            'Use date strings in YYYY-MM or YYYY-MM-DD when possible; else null.',
            'Infer gender only when explicit; otherwise use unknown.',
            'For parsed_metadata.school_background_tier, use one of: top_school, grande_ecole, regular_university, faculty, mixed, unknown.',
            'Role-specific keywords must be aligned to the target role and CV evidence.',
            'Prefer normalized skill labels such as Laravel, PHP, AWS, Tableau, Python, Communication, Leadership, Project Management when supported.',
            '</output_rules>',
            '',
            'Candidate context:',
            'full_name: '.trim((string) $candidate->full_name),
            'email: '.trim((string) $candidate->email),
            'phone: '.trim((string) ($candidate->phone ?? '')),
            'location: '.trim((string) ($candidate->location ?? '')),
            '',
            'Job context:',
            'job_title: '.$jobTitle,
            'job_summary: '.$jobSummary,
            'application_status: '.$applicationStatus,
            'application_source: '.$applicationSource,
            '',
            'The candidate CV PDF is attached as a separate document part in this request.',
        ]);
    }

    private function jobSummary(?Job $job): string
    {
        if (! $job instanceof Job) {
            return '';
        }

        return $this->descriptionRenderer->renderPlainText($job, 3000);
    }

    /**
     * @return array{sha256: string, meta: array<string, mixed>}|null
     */
    private function resumePdfPayload(CandidateDocument $resumeDocument): ?array
    {
        $path = trim((string) $resumeDocument->file_url);
        $extension = Str::lower((string) pathinfo((string) $resumeDocument->original_filename, PATHINFO_EXTENSION));
        $mimeType = Str::lower(trim((string) $resumeDocument->mime_type));

        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        if ($extension !== 'pdf' && ! str_contains($mimeType, 'application/pdf')) {
            return null;
        }

        try {
            $raw = (string) Storage::disk('local')->get($path);
        } catch (\Throwable) {
            return null;
        }

        if ($raw === '') {
            return null;
        }

        return [
            'sha256' => hash('sha256', $raw),
            'meta' => [
                'extension' => 'pdf',
                'mime' => 'application/pdf',
                'size_bytes' => (int) ($resumeDocument->file_size_bytes ?? strlen($raw)),
                'original_filename' => (string) $resumeDocument->original_filename,
            ],
        ];
    }
}
