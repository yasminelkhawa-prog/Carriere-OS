<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\VideoConfig;
use App\Models\VideoQuestion;
use App\Models\VideoResponse;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VideoInterviewController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly AiRequestService $aiRequestService,
        private readonly SensitiveEventRecorder $sensitiveEvents
    ) {
    }

    public function stories(Request $request, Company $company, Application $application): View|RedirectResponse
    {
        [$actor, $candidate, $config] = $this->resolveCandidateStoriesContext($request, $company, $application);

        if (! $actor instanceof User || ! $candidate instanceof Candidate) {
            abort(403);
        }

        if (! $config instanceof VideoConfig) {
            return view('candidate.assessments.video-stories', [
                'company' => $company,
                'application' => $application,
                'config' => null,
                'questions' => collect(),
                'currentQuestion' => null,
                'currentAttempts' => collect(),
                'latestResponsesByQuestionId' => collect(),
                'progress' => ['answered' => 0, 'total' => 0, 'percent' => 0],
                'latestUnifiedRequest' => null,
            ]);
        }

        $questions = $config->questions()->get();
        $allResponses = VideoResponse::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->whereIn('question_id', $questions->pluck('id'))
            ->orderBy('attempt_number')
            ->get();

        $latestResponsesByQuestionId = $allResponses
            ->groupBy(fn (VideoResponse $response): string => (string) $response->question_id)
            ->map(fn (Collection $responses): VideoResponse => $responses->sortByDesc('attempt_number')->first());

        $answeredCount = $latestResponsesByQuestionId->count();
        $totalCount = $questions->count();
        $isCompleted = $totalCount > 0 && $answeredCount >= $totalCount;

        if ($isCompleted) {
            return redirect()
                ->route('candidate.portal', ['company' => $company->slug])
                ->with('status', __('video_assessment.stories.messages.completed_message'));
        }

        $questionId = (string) $request->query('question_id', '');
        $currentQuestion = $this->resolveCurrentQuestion($questions, $latestResponsesByQuestionId, $questionId);

        $currentAttempts = collect();
        if ($currentQuestion instanceof VideoQuestion) {
            $currentAttempts = $allResponses
                ->where('question_id', $currentQuestion->id)
                ->sortByDesc('attempt_number')
                ->values();
        }

        $latestUnifiedRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('request_type', 'async_video_unified_report')
            ->where('request_payload->application_id', (string) $application->id)
            ->latest('created_at')
            ->first();

        return view('candidate.assessments.video-stories', [
            'company' => $company,
            'application' => $application,
            'config' => $config,
            'questions' => $questions,
            'currentQuestion' => $currentQuestion,
            'currentAttempts' => $currentAttempts,
            'latestResponsesByQuestionId' => $latestResponsesByQuestionId,
            'progress' => [
                'answered' => $answeredCount,
                'total' => $totalCount,
                'percent' => $totalCount > 0 ? (int) round(($answeredCount / $totalCount) * 100) : 0,
            ],
            'latestUnifiedRequest' => $latestUnifiedRequest,
        ]);
    }

    public function submitStoryQuestion(
        Request $request,
        Company $company,
        Application $application,
        VideoQuestion $videoQuestion
    ): RedirectResponse {
        [$actor, $candidate, $config] = $this->resolveCandidateStoriesContext($request, $company, $application);

        if (! $actor instanceof User || ! $candidate instanceof Candidate || ! $config instanceof VideoConfig) {
            abort(403);
        }

        abort_unless((string) $videoQuestion->company_id === (string) $company->id, 403);
        abort_unless((string) $videoQuestion->config_id === (string) $config->id, 403);

        $attemptsUsed = VideoResponse::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->where('question_id', $videoQuestion->id)
            ->count();

        $maxAttempts = max(1, ((int) $config->retries_allowed) + 1);
        if ($attemptsUsed >= $maxAttempts) {
            return redirect()
                ->route('candidate.video-stories', [
                    'company' => $company->slug,
                    'application' => $application->id,
                    'question_id' => $videoQuestion->id,
                ])->with('error', __('video_assessment.stories.messages.retry_limit'));
        }

        $validated = $request->validate([
            'read_time_completed' => ['required', 'accepted'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:'.(int) $config->answer_time_seconds],
            'video_file' => ['required', 'file', 'extensions:mp4,webm,ogg,mov', 'max:51200'],
            'pauses_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'speech_rate_estimate' => ['nullable', 'numeric', 'min:0', 'max:800'],
            'filler_ratio_estimate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'transcript_text' => ['nullable', 'string', 'max:10000'],
            'action' => ['nullable', Rule::in(['next', 'retry'])],
        ], [
            'read_time_completed.accepted' => __('video_assessment.stories.messages.read_timer_required'),
            'duration_seconds.max' => __('video_assessment.stories.messages.answer_timer_exceeded'),
        ]);

        $videoFile = $request->file('video_file');
        abort_unless($videoFile !== null, 422);
        $nextAttempt = $attemptsUsed + 1;

        $response = DB::transaction(function () use (
            $company,
            $application,
            $videoQuestion,
            $videoFile,
            $nextAttempt,
            $validated,
            $actor
        ): VideoResponse {
            $path = $videoFile->store(
                "private/video-interview/{$company->id}/{$application->id}/{$videoQuestion->id}",
                'local'
            );

            $response = VideoResponse::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'question_id' => $videoQuestion->id,
                'attempt_number' => $nextAttempt,
                'video_file_url' => $path,
                'duration_seconds' => (int) $validated['duration_seconds'],
                'pauses_count' => array_key_exists('pauses_count', $validated) ? (int) $validated['pauses_count'] : null,
                'speech_rate_estimate' => array_key_exists('speech_rate_estimate', $validated)
                    ? round((float) $validated['speech_rate_estimate'], 2)
                    : null,
                'filler_ratio_estimate' => array_key_exists('filler_ratio_estimate', $validated)
                    ? round((float) $validated['filler_ratio_estimate'], 4)
                    : null,
                'transcript_text' => isset($validated['transcript_text']) ? trim((string) $validated['transcript_text']) : null,
                'created_at' => now(),
            ]);

            $this->recordActivityEvent($application, 'video.response_submitted', [
                'question_id' => (string) $videoQuestion->id,
                'attempt_number' => $nextAttempt,
                'video_response_id' => (string) $response->id,
            ], $actor);

            $this->aiRequestService->queueRequest(
                companyId: (string) $company->id,
                requestType: 'video_response_metrics',
                requestPayload: [
                    'application_id' => (string) $application->id,
                    'question_id' => (string) $videoQuestion->id,
                    'video_response_id' => (string) $response->id,
                    'output_mode' => 'json_schema',
                    'prompt' => implode("\n", [
                        'Extract transcript and speech metrics for this async interview response.',
                        'Return strict JSON only.',
                        'Question: '.$videoQuestion->question_text,
                        'Existing transcript hint: '.(string) ($response->transcript_text ?? ''),
                        'Recorded duration seconds: '.$response->duration_seconds,
                    ]),
                    'json_schema' => [
                        'required' => ['transcript_text', 'pauses_count', 'speech_rate_estimate', 'filler_ratio_estimate'],
                        'properties' => [
                            'transcript_text' => ['type' => 'string'],
                            'pauses_count' => ['type' => 'integer'],
                            'speech_rate_estimate' => ['type' => 'number'],
                            'filler_ratio_estimate' => ['type' => 'number'],
                        ],
                    ],
                ],
                promptVersion: 'video_response_metrics_v1'
            );

            return $response;
        });

        $this->queueUnifiedReportIfComplete($company->id, $application, $config, $actor);

        $questions = $config->questions()->get();
        $latestByQuestion = VideoResponse::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->whereIn('question_id', $questions->pluck('id'))
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy(fn (VideoResponse $item): string => (string) $item->question_id)
            ->map(fn (Collection $items): ?VideoResponse => $items->first());

        $action = (string) ($validated['action'] ?? 'next');
        $remainingAttempts = $maxAttempts - $nextAttempt;
        $nextQuestionId = $videoQuestion->id;

        if ($action === 'retry' && $remainingAttempts > 0) {
            $nextQuestionId = $videoQuestion->id;
        } else {
            $unanswered = $questions->first(
                fn (VideoQuestion $question): bool => ! $latestByQuestion->has((string) $question->id)
            );
            if ($unanswered instanceof VideoQuestion) {
                $nextQuestionId = (string) $unanswered->id;
            } else {
                return redirect()
                    ->route('candidate.portal', ['company' => $company->slug])
                    ->with('status', __('video_assessment.stories.messages.completed_message'));
            }
        }

        return redirect()
            ->route('candidate.video-stories', [
                'company' => $company->slug,
                'application' => $application->id,
                'question_id' => $nextQuestionId,
            ])
            ->with('status', __('video_assessment.stories.messages.saved'));
    }

    public function retryUnifiedReport(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeRecruiterAction($request, $application);

        $latestRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'async_video_unified_report')
            ->where('request_payload->application_id', (string) $application->id)
            ->latest('created_at')
            ->first();

        if ($latestRequest instanceof AiRequest && $latestRequest->status === AiRequest::STATUS_FAILED) {
            $this->aiRequestService->retry($latestRequest);
        } else {
            $config = VideoConfig::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('job_id', $application->job_id)
                ->latest('created_at')
                ->first();
            if ($config instanceof VideoConfig) {
                $this->queueUnifiedReportIfComplete($companyId, $application, $config, $actor);
            }
        }

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.flash.analysis_requested'));
    }

    public static function signedVideoResponseUrl(VideoResponse $response): string
    {
        return URL::temporarySignedRoute(
            'media.video-response',
            now()->addMinutes(15),
            ['videoInterviewResponse' => $response->id]
        );
    }

    /**
     * @return array{0: User|null, 1: Candidate|null, 2: VideoConfig|null}
     */
    private function resolveCandidateStoriesContext(Request $request, Company $company, Application $application): array
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return [null, null, null];
        }

        abort_unless((string) $application->company_id === (string) $company->id, 404);

        $candidate = Candidate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if (! $candidate instanceof Candidate || (string) $application->candidate_id !== (string) $candidate->id) {
            abort(403);
        }

        $membership = $actor->memberships()
            ->where('company_id', $company->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', CompanyMembership::ROLE_CANDIDATE)
            ->exists();
        abort_unless($membership, 403);

        $config = VideoConfig::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('job_id', $application->job_id)
            ->latest('created_at')
            ->first();

        return [$actor, $candidate, $config];
    }

    private function resolveCurrentQuestion(
        Collection $questions,
        Collection $latestResponsesByQuestionId,
        string $questionId
    ): ?VideoQuestion {
        if ($questions->isEmpty()) {
            return null;
        }

        if ($questionId !== '') {
            $target = $questions->first(fn (VideoQuestion $question): bool => (string) $question->id === $questionId);
            if ($target instanceof VideoQuestion) {
                return $target;
            }
        }

        $firstUnanswered = $questions->first(
            fn (VideoQuestion $question): bool => ! $latestResponsesByQuestionId->has((string) $question->id)
        );

        return $firstUnanswered instanceof VideoQuestion
            ? $firstUnanswered
            : $questions->first();
    }

    private function queueUnifiedReportIfComplete(string $companyId, Application $application, VideoConfig $config, ?User $actor): void
    {
        $questions = $config->questions()->get();
        if ($questions->isEmpty()) {
            return;
        }

        $latestByQuestion = VideoResponse::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('application_id', $application->id)
            ->whereIn('question_id', $questions->pluck('id'))
            ->orderByDesc('attempt_number')
            ->get()
            ->groupBy(fn (VideoResponse $item): string => (string) $item->question_id)
            ->map(fn (Collection $items): ?VideoResponse => $items->first());

        if ($latestByQuestion->count() < $questions->count()) {
            return;
        }

        $latestRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'async_video_unified_report')
            ->where('request_payload->application_id', (string) $application->id)
            ->latest('created_at')
            ->first();

        if ($latestRequest instanceof AiRequest && in_array($latestRequest->status, [
            AiRequest::STATUS_QUEUED,
            AiRequest::STATUS_RUNNING,
            AiRequest::STATUS_SUCCEEDED,
        ], true)) {
            return;
        }

        $segments = $questions->map(function (VideoQuestion $question) use ($latestByQuestion): array {
            /** @var VideoResponse|null $latest */
            $latest = $latestByQuestion->get((string) $question->id);

            return [
                'question_id' => (string) $question->id,
                'question' => $question->question_text,
                'attempt_number' => $latest?->attempt_number,
                'duration_seconds' => $latest?->duration_seconds,
                'pauses_count' => $latest?->pauses_count,
                'speech_rate_estimate' => $latest?->speech_rate_estimate,
                'filler_ratio_estimate' => $latest?->filler_ratio_estimate,
                'transcript_text' => (string) ($latest?->transcript_text ?? ''),
            ];
        })->values()->all();

        $this->aiRequestService->queueRequest(
            companyId: $companyId,
            requestType: 'async_video_unified_report',
            requestPayload: [
                'application_id' => (string) $application->id,
                'job_id' => (string) $application->job_id,
                'output_mode' => 'json_schema',
                'segments' => $segments,
                'prompt' => implode("\n", [
                    'Generate a unified strict JSON candidate report from async video interview segments.',
                    'Include technical and psychometric signals with brief XAI summary.',
                    'Return strict JSON only and ensure numeric fields are bounded 0-100 where relevant.',
                    'Fields required: xai_summary, ocean, match_percentage, salary, generic_motivation, vrin, global_match_score.',
                ]),
                'json_schema' => [
                    'required' => [
                        'xai_summary',
                        'ocean',
                        'match_percentage',
                        'salary',
                        'generic_motivation',
                        'vrin',
                        'global_match_score',
                    ],
                    'properties' => [
                        'xai_summary' => ['type' => 'string'],
                        'ocean' => ['type' => 'object'],
                        'match_percentage' => ['type' => 'number'],
                        'salary' => ['type' => 'object'],
                        'generic_motivation' => ['type' => 'boolean'],
                        'vrin' => ['type' => 'object'],
                        'global_match_score' => ['type' => 'number'],
                    ],
                ],
            ],
            promptVersion: 'async_video_unified_report_v1'
        );

        $this->recordActivityEvent($application, 'video.unified_report_queued', [
            'config_id' => (string) $config->id,
            'questions_count' => $questions->count(),
        ], $actor);
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
