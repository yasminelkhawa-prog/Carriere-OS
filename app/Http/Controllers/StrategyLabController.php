<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Interview;
use App\Models\JobPipelineStage;
use App\Models\StrategyLabAiSummary;
use App\Models\StrategyLabBrief;
use App\Models\StrategyLabSubmission;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use ZipArchive;

class StrategyLabController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly AiRequestService $aiRequestService,
        private readonly SensitiveEventRecorder $sensitiveEvents
    ) {
    }

    public function assign(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeRecruiterAction($request, $application);
        $application->loadMissing(['candidate', 'job', 'currentStage', 'strategyLabBrief', 'scoring', 'interviews']);

        $eligibilityError = self::strategyLabEligibilityError($application);
        if ($eligibilityError !== '') {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', $eligibilityError);
        }

        $validated = $request->validate([
            'brief_title' => ['required', 'string', 'max:255'],
        ], [
            'brief_title.required' => __('strategy_lab.validation.brief_title_required'),
        ]);

        $issuedAt = now();
        $deadline = $issuedAt->copy()->addHours(48);
        $briefTitle = trim((string) $validated['brief_title']);

        DB::transaction(function () use ($application, $companyId, $actor, $deadline, $briefTitle, $issuedAt): void {
            $brief = StrategyLabBrief::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'application_id' => (string) $application->id,
                ],
                [
                    'brief_title' => $briefTitle,
                    'brief_pdf_url' => null,
                    'deadline_at' => $deadline,
                    'status' => StrategyLabBrief::STATUS_ASSIGNED,
                    'final_decision_status' => null,
                    'final_decision_note' => null,
                    'final_decision_by_user_id' => null,
                    'final_decision_at' => null,
                    'generated_ai_request_id' => null,
                ]
            );

            $requestPayload = [
                'application_id' => (string) $application->id,
                'strategy_lab_brief_id' => (string) $brief->id,
                'brief_title' => $briefTitle,
                'prompt' => implode("\n", [
                    'Generate a concise mini-project brief personalized to this shortlisted top-decile candidate.',
                    'Return plain text only. No markdown.',
                    'Title: '.$briefTitle,
                    'Job title: '.(string) ($application->job?->title ?? 'Unknown job'),
                    'Candidate name: '.(string) ($application->candidate?->full_name ?? 'Candidate'),
                    'Application source: '.(string) ($application->source_type ?? 'unknown'),
                    'Current stage: '.(string) ($application->currentStage?->stage_label ?? 'unknown'),
                    'Global match score (if available): '.(string) ($application->scoring?->global_match_score ?? 'N/A'),
                    'Include objective, constraints, deliverables, evaluation criteria, and one realistic challenge example (e.g., guerrilla marketing strategy with EUR0 budget) when relevant to the role.',
                ]),
                'output_mode' => 'text',
            ];

            $aiRequest = $this->aiRequestService->queueRequest(
                companyId: $companyId,
                requestType: 'strategy_lab_brief_generation',
                requestPayload: $requestPayload,
                promptVersion: 'strategy_lab_brief_v1'
            );

            $brief->forceFill([
                'generated_ai_request_id' => $aiRequest->id,
                'updated_at' => now(),
            ])->save();

            StrategyLabAiSummary::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('application_id', $application->id)
                ->delete();

            $this->recordActivityEvent($application, 'strategy_lab.assigned', [
                'brief_id' => (string) $brief->id,
                'brief_title' => $briefTitle,
                'issued_at' => $issuedAt->toISOString(),
                'deadline_at' => $deadline->toISOString(),
                'ai_request_id' => (string) $aiRequest->id,
            ], $actor);

            $this->sensitiveEvents->record(
                actionType: 'strategy_lab.assigned',
                entityType: 'application',
                entityId: (string) $application->id,
                metadata: [
                    'brief_id' => (string) $brief->id,
                    'ai_request_id' => (string) $aiRequest->id,
                ],
                actor: $actor
            );
        });

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('strategy_lab.messages.assigned'));
    }

    public function extendDeadline(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeRecruiterAction($request, $application);

        $brief = StrategyLabBrief::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('application_id', $application->id)
            ->first();

        if (! $brief instanceof StrategyLabBrief) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('strategy_lab.messages.brief_not_assigned'));
        }

        $validated = $request->validate([
            'deadline_at' => ['required', 'date', 'after:now'],
        ], [
            'deadline_at.required' => __('strategy_lab.validation.deadline_required'),
            'deadline_at.after' => __('strategy_lab.validation.deadline_future'),
        ]);

        $deadline = Carbon::parse((string) $validated['deadline_at']);
        $brief->forceFill([
            'deadline_at' => $deadline,
            'updated_at' => now(),
        ])->save();

        $this->recordActivityEvent($application, 'strategy_lab.deadline_extended', [
            'brief_id' => (string) $brief->id,
            'deadline_at' => $deadline->toISOString(),
        ], $actor);

        $this->sensitiveEvents->record(
            actionType: 'strategy_lab.deadline_extended',
            entityType: 'application',
            entityId: (string) $application->id,
            metadata: ['brief_id' => (string) $brief->id, 'deadline_at' => $deadline->toISOString()],
            actor: $actor
        );

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('strategy_lab.messages.deadline_extended'));
    }

    public function markReviewed(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeRecruiterAction($request, $application);

        $brief = StrategyLabBrief::withoutGlobalScopes()
            ->with(['submission', 'aiSummary'])
            ->where('company_id', $companyId)
            ->where('application_id', $application->id)
            ->first();

        if (! $brief instanceof StrategyLabBrief) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('strategy_lab.messages.brief_not_assigned'));
        }

        if (! $brief->submission instanceof StrategyLabSubmission || ! $brief->aiSummary instanceof StrategyLabAiSummary) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('strategy_lab.messages.review_requirements_not_met'));
        }

        $brief->forceFill([
            'status' => StrategyLabBrief::STATUS_REVIEWED,
            'updated_at' => now(),
        ])->save();

        $this->recordActivityEvent($application, 'strategy_lab.reviewed', [
            'brief_id' => (string) $brief->id,
        ], $actor);

        $this->sensitiveEvents->record(
            actionType: 'strategy_lab.reviewed',
            entityType: 'application',
            entityId: (string) $application->id,
            metadata: ['brief_id' => (string) $brief->id],
            actor: $actor
        );

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('strategy_lab.messages.reviewed'));
    }

    public function setFinalDecision(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeRecruiterAction($request, $application);

        $brief = StrategyLabBrief::withoutGlobalScopes()
            ->with(['submission', 'aiSummary'])
            ->where('company_id', $companyId)
            ->where('application_id', $application->id)
            ->first();

        if (! $brief instanceof StrategyLabBrief) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('strategy_lab.messages.brief_not_assigned'));
        }

        if (! $brief->submission instanceof StrategyLabSubmission || ! $brief->aiSummary instanceof StrategyLabAiSummary) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('strategy_lab.messages.review_requirements_not_met'));
        }

        if ((string) $brief->status !== StrategyLabBrief::STATUS_REVIEWED) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('strategy_lab.messages.review_first_for_final_decision'));
        }

        $validated = $request->validate([
            'decision_status' => ['required', Rule::in(StrategyLabBrief::decisionStatuses())],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'decision_status.required' => __('strategy_lab.validation.final_decision_status_required'),
            'decision_status.in' => __('strategy_lab.validation.final_decision_status_invalid'),
            'decision_note.max' => __('strategy_lab.validation.final_decision_note_max'),
        ]);

        $decisionStatus = (string) $validated['decision_status'];
        $decisionNote = trim((string) ($validated['decision_note'] ?? ''));

        $brief->forceFill([
            'final_decision_status' => $decisionStatus,
            'final_decision_note' => $decisionNote !== '' ? $decisionNote : null,
            'final_decision_by_user_id' => $actor->id,
            'final_decision_at' => now(),
            'updated_at' => now(),
        ])->save();

        $this->recordActivityEvent($application, 'strategy_lab.final_decision_set', [
            'brief_id' => (string) $brief->id,
            'decision_status' => $decisionStatus,
        ], $actor);

        $this->sensitiveEvents->record(
            actionType: 'strategy_lab.final_decision_set',
            entityType: 'application',
            entityId: (string) $application->id,
            metadata: [
                'brief_id' => (string) $brief->id,
                'decision_status' => $decisionStatus,
            ],
            actor: $actor
        );

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('strategy_lab.messages.final_decision_saved'));
    }

    public function submitFromPortal(Request $request, Company $company, Application $application): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless((string) $company->id === (string) $application->company_id, 404);

        $candidate = Candidate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        abort_unless($candidate instanceof Candidate, 403);
        abort_unless((string) $application->candidate_id === (string) $candidate->id, 403);

        $membership = $actor->memberships()
            ->where('company_id', $company->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->first();
        abort_unless($membership instanceof CompanyMembership, 403);

        $application->loadMissing(['currentStage', 'job', 'interviews']);
        $eligibilityError = self::strategyLabEligibilityError($application);
        if ($eligibilityError !== '') {
            return redirect()
                ->route('candidate.portal', ['company' => $company->slug])
                ->with('error', $eligibilityError);
        }

        $brief = StrategyLabBrief::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->first();

        if (! $brief instanceof StrategyLabBrief) {
            return redirect()
                ->route('candidate.portal', ['company' => $company->slug])
                ->with('error', __('strategy_lab.messages.brief_not_assigned'));
        }

        if (! is_string($brief->brief_pdf_url) || trim($brief->brief_pdf_url) === '') {
            return redirect()
                ->route('candidate.portal', ['company' => $company->slug])
                ->with('error', __('strategy_lab.messages.brief_processing'));
        }

        if ($brief->deadline_at instanceof Carbon && $brief->deadline_at->isPast()) {
            return redirect()
                ->route('candidate.portal', ['company' => $company->slug])
                ->with('error', __('strategy_lab.messages.deadline_passed'));
        }

        $validated = $request->validate([
            'submission_type' => ['required', Rule::in(StrategyLabSubmission::types())],
            // Extension-based validation avoids MIME inconsistencies across office formats.
            'submission_file' => ['required', 'file', 'extensions:pdf,doc,docx,ppt,pptx,odp,key,txt', 'max:20480'],
        ], [
            'submission_type.required' => __('strategy_lab.validation.submission_type_required'),
            'submission_file.required' => __('strategy_lab.validation.file_required'),
            'submission_file.extensions' => __('strategy_lab.validation.file_extensions'),
            'submission_file.max' => __('strategy_lab.validation.file_max'),
        ]);

        $submissionFile = $request->file('submission_file');
        abort_unless($submissionFile !== null, 422);

        DB::transaction(function () use ($application, $company, $brief, $validated, $submissionFile, $actor): void {
            $path = $submissionFile->store("private/strategy-lab/submissions/{$company->id}/{$application->id}", 'local');
            $submissionExcerpt = $this->extractSubmissionPreview(
                storedPath: $path,
                originalExtension: (string) $submissionFile->getClientOriginalExtension()
            );

            $submission = StrategyLabSubmission::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'application_id' => $application->id,
                ],
                [
                    'submission_type' => (string) $validated['submission_type'],
                    'file_url' => $path,
                    'original_filename' => (string) $submissionFile->getClientOriginalName(),
                    'submitted_at' => now(),
                ]
            );

            $brief->forceFill([
                'status' => StrategyLabBrief::STATUS_SUBMITTED,
                'final_decision_status' => null,
                'final_decision_note' => null,
                'final_decision_by_user_id' => null,
                'final_decision_at' => null,
                'updated_at' => now(),
            ])->save();

            $aiRequest = $this->aiRequestService->queueRequest(
                companyId: (string) $company->id,
                requestType: 'strategy_lab_executive_summary',
                requestPayload: [
                    'application_id' => (string) $application->id,
                    'strategy_lab_submission_id' => (string) $submission->id,
                    'output_mode' => 'json_schema',
                    'prompt' => implode("\n", [
                        'Generate an executive summary for a Strategy Lab submission.',
                        'Use the extracted submission excerpt when available. Infer cautiously if excerpt is limited.',
                        'Submission type: '.$submission->submission_type,
                        'Original filename: '.$submission->original_filename,
                        'Submission excerpt: '.($submissionExcerpt !== ''
                            ? $submissionExcerpt
                            : 'No parseable excerpt was extracted from this file.'),
                        'Summarize strategic strengths, weaknesses, and assign a creativity score on a 0-10 scale.',
                        'Return strict JSON only.',
                    ]),
                    'json_schema' => [
                        'required' => ['executive_summary_text', 'strengths_json', 'weaknesses_json', 'creativity_score', 'overall_recommendation'],
                        'properties' => [
                            'executive_summary_text' => ['type' => 'string'],
                            'strengths_json' => ['type' => 'array'],
                            'weaknesses_json' => ['type' => 'array'],
                            'creativity_score' => ['type' => 'number'],
                            'overall_recommendation' => ['type' => 'string'],
                        ],
                    ],
                ],
                promptVersion: 'strategy_lab_summary_v1'
            );

            $this->recordActivityEvent($application, 'strategy_lab.submitted', [
                'brief_id' => (string) $brief->id,
                'submission_id' => (string) $submission->id,
                'submission_type' => $submission->submission_type,
                'ai_request_id' => (string) $aiRequest->id,
            ], $actor);

            $this->sensitiveEvents->record(
                actionType: 'strategy_lab.submitted',
                entityType: 'application',
                entityId: (string) $application->id,
                metadata: ['brief_id' => (string) $brief->id, 'submission_id' => (string) $submission->id],
                actor: $actor
            );
        });

        return redirect()
            ->route('candidate.portal', ['company' => $company->slug])
            ->with('status', __('strategy_lab.messages.submitted'));
    }

    public static function signedBriefUrl(StrategyLabBrief $brief): string
    {
        return URL::temporarySignedRoute(
            'media.strategy-lab-brief',
            now()->addMinutes(15),
            ['strategyLabBrief' => $brief->id]
        );
    }

    public static function signedSubmissionUrl(StrategyLabSubmission $submission): string
    {
        return URL::temporarySignedRoute(
            'media.strategy-lab-submission',
            now()->addMinutes(15),
            ['strategyLabSubmission' => $submission->id]
        );
    }

    public static function isShortlistedStage(?JobPipelineStage $stage): bool
    {
        return self::isPriorityStage($stage);
    }

    public static function isPriorityStage(?JobPipelineStage $stage): bool
    {
        if (! $stage instanceof JobPipelineStage) {
            return false;
        }

        $key = strtolower(trim((string) $stage->stage_key));
        $label = strtolower(trim((string) $stage->stage_label));

        if (in_array($key, ['shortlist', 'shortlisted', 'high_priority', 'high-priority', 'top10', 'top_10'], true)) {
            return true;
        }

        return (bool) preg_match('/short\s*list|high\s*priority|top\s*10/', $label);
    }

    public static function canAccessStrategyLab(Application $application): bool
    {
        if (! self::isPriorityStage($application->currentStage)) {
            return false;
        }

        if (! self::isTopTenWithinPriorityBucket($application)) {
            return false;
        }

        return self::hasCompletedZoomInterview($application);
    }

    public static function strategyLabEligibilityError(Application $application): string
    {
        if (! self::isPriorityStage($application->currentStage)) {
            return __('strategy_lab.messages.shortlist_required');
        }

        if (! self::isTopTenWithinPriorityBucket($application)) {
            return __('strategy_lab.messages.top_ten_required');
        }

        if (! self::hasCompletedZoomInterview($application)) {
            return __('strategy_lab.messages.zoom_interview_required');
        }

        return '';
    }

    private static function isTopTenWithinPriorityBucket(Application $application): bool
    {
        $rankedApplicationIds = self::priorityBucketRankedApplicationIds($application);
        if ($rankedApplicationIds->isEmpty()) {
            return false;
        }

        $topCount = max(1, (int) ceil($rankedApplicationIds->count() * 0.10));
        $topIds = $rankedApplicationIds->take($topCount);

        return $topIds->contains((string) $application->id);
    }

    private static function hasCompletedZoomInterview(Application $application): bool
    {
        if ($application->relationLoaded('interviews')) {
            $loadedInterviews = $application->interviews;
            if ($loadedInterviews instanceof Collection) {
                if ($loadedInterviews->contains(
                    static fn (Interview $interview): bool => self::isCompletedZoomInterview($interview)
                )) {
                    return true;
                }
            }
        }

        return Interview::withoutGlobalScopes()
            ->where('company_id', (string) $application->company_id)
            ->where('application_id', (string) $application->id)
            ->where('status', Interview::STATUS_COMPLETED)
            ->where(function ($query): void {
                $query
                    ->where('location_type', Interview::LOCATION_ZOOM)
                    ->orWhereRaw('LOWER(COALESCE(meeting_link, \'\')) LIKE ?', ['%zoom.us%'])
                    ->orWhereRaw('LOWER(COALESCE(meeting_link, \'\')) LIKE ?', ['%zoom.com%']);
            })
            ->exists();
    }

    private static function isCompletedZoomInterview(Interview $interview): bool
    {
        if ((string) $interview->status !== Interview::STATUS_COMPLETED) {
            return false;
        }

        $locationType = strtolower(trim((string) $interview->location_type));
        if ($locationType === Interview::LOCATION_ZOOM) {
            return true;
        }

        $meetingLink = strtolower(trim((string) ($interview->meeting_link ?? '')));

        return str_contains($meetingLink, 'zoom.us') || str_contains($meetingLink, 'zoom.com');
    }

    private function extractSubmissionPreview(string $storedPath, string $originalExtension = ''): string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($storedPath)) {
            return '';
        }

        $binary = (string) $disk->get($storedPath);
        if ($binary === '') {
            return '';
        }

        $extension = strtolower(trim($originalExtension));
        if ($extension === '') {
            $extension = strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION));
        }

        try {
            return match ($extension) {
                'txt' => $this->normalizeExtractedText($binary),
                'docx' => $this->extractTextFromZipBinary($binary, static fn (string $name): bool => $name === 'word/document.xml'),
                'pptx' => $this->extractTextFromZipBinary($binary, static fn (string $name): bool => (bool) preg_match('/^ppt\/slides\/slide\d+\.xml$/', $name)),
                'odp', 'key' => $this->extractTextFromZipBinary($binary, static fn (string $name): bool => $name === 'content.xml'),
                'pdf' => $this->extractTextFromPdfBinary($binary),
                default => '',
            };
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param \Closure(string): bool $entryMatcher
     */
    private function extractTextFromZipBinary(string $binary, \Closure $entryMatcher): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'strategy-lab-');
        if (! is_string($tempPath) || $tempPath === '') {
            return '';
        }

        file_put_contents($tempPath, $binary);

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            return '';
        }

        $buffer = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if (! $entryMatcher($name)) {
                    continue;
                }

                $content = $zip->getFromIndex($index);
                if (! is_string($content) || $content === '') {
                    continue;
                }

                $decoded = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_XML1);
                if ($decoded !== '') {
                    $buffer[] = $decoded;
                }
            }
        } finally {
            $zip->close();
            @unlink($tempPath);
        }

        return $this->normalizeExtractedText(implode("\n", $buffer));
    }

    private function extractTextFromPdfBinary(string $raw): string
    {
        preg_match_all('/\(([^)]{1,240})\)\s*Tj/s', $raw, $matches);
        $chunks = array_map(
            static fn (string $chunk): string => preg_replace('/\\\\[nrt]/', ' ', $chunk) ?? '',
            (array) ($matches[1] ?? [])
        );

        if ($chunks === []) {
            return '';
        }

        return $this->normalizeExtractedText(implode(' ', $chunks));
    }

    private function normalizeExtractedText(string $text): string
    {
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $text) ?? '';
        $collapsed = preg_replace('/\s+/u', ' ', $sanitized) ?? '';

        return trim(mb_substr($collapsed, 0, 3200));
    }

    /**
     * @return Collection<int, string>
     */
    private static function priorityBucketRankedApplicationIds(Application $application): Collection
    {
        return Application::withoutGlobalScopes()
            ->from('applications')
            ->join('job_pipeline_stages as stages', 'stages.id', '=', 'applications.current_stage_id')
            ->leftJoin('application_scorings as scoring', 'scoring.application_id', '=', 'applications.id')
            ->where('applications.company_id', (string) $application->company_id)
            ->where('applications.job_id', (string) $application->job_id)
            ->whereIn('applications.status', [Application::STATUS_ACTIVE, Application::STATUS_HIRED])
            ->where(function ($query): void {
                $query
                    ->whereRaw('LOWER(stages.stage_key) IN (?, ?, ?, ?, ?, ?)', [
                        'shortlist',
                        'shortlisted',
                        'high_priority',
                        'high-priority',
                        'top10',
                        'top_10',
                    ])
                    ->orWhereRaw('LOWER(stages.stage_label) LIKE ?', ['%shortlist%'])
                    ->orWhereRaw('LOWER(stages.stage_label) LIKE ?', ['%high priority%'])
                    ->orWhereRaw('LOWER(stages.stage_label) LIKE ?', ['%top 10%'])
                    ->orWhereRaw('LOWER(stages.stage_label) LIKE ?', ['%top10%']);
            })
            ->orderByRaw('COALESCE(scoring.global_match_score, 0) DESC')
            ->orderBy('applications.created_at')
            ->pluck('applications.id');
    }

    private function authorizeRecruiterAction(Request $request, Application $application): array
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 403);
        abort_unless((string) $application->company_id === (string) $companyId, 403);

        if (! $actor->isSuperadmin()) {
            $allowed = $actor->memberships()
                ->where('company_id', $companyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->whereIn('company_role', [
                    CompanyMembership::ROLE_COMPANY_ADMIN,
                    CompanyMembership::ROLE_RECRUITER,
                    CompanyMembership::ROLE_MANAGER,
                ])->exists();
            abort_unless($allowed, 403);
        }

        return [$actor, $companyId];
    }

    /**
     * @return array<string, string>
     */
    private function backQuery(Request $request, Application $application): array
    {
        return array_filter([
            'application_id' => (string) $application->id,
            'q' => (string) $request->input('q', ''),
            'job_id' => (string) $request->input('job_id', ''),
            'stage_id' => (string) $request->input('stage_id', ''),
            'status' => (string) $request->input('status', ''),
            'source_type' => (string) $request->input('source_type', ''),
            'company_id' => (string) $request->input('company_id', $request->query('company_id', '')),
        ], fn ($value) => $value !== '');
    }

    private function recordActivityEvent(Application $application, string $eventType, array $payload, ?User $actor): void
    {
        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => $application->company_id,
            'application_id' => $application->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_user_id' => $actor?->id,
            'created_at' => now(),
        ]);
    }
}
