<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationStageHistory;
use App\Models\ApplicationTask;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Contract;
use App\Models\Interview;
use App\Models\InterviewParticipant;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Offer;
use App\Models\OnboardingDocument;
use App\Models\OnboardingSchedule;
use App\Models\OnboardingTask;
use App\Models\RejectionDraft;
use App\Models\UserSecureSetting;
use App\Models\User;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\Ai\AiRequestService;
use App\Services\Communication\CommunicationEngineService;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CandidateWorkspaceController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly SensitiveEventRecorder $sensitiveEvents,
        private readonly AiRequestService $aiRequestService,
        private readonly CommunicationEngineService $communicationEngine,
        private readonly CandidateAnalysisService $candidateAnalysisService
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, true);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'job_id' => ['nullable', 'uuid'],
            'stage_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(Application::statuses())],
            'source_type' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'application_id' => ['nullable', 'uuid'],
        ]);

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('candidates.index', [
                'requiresCompanySelection' => true,
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'applications' => collect(),
                'selectedApplication' => null,
                'selectedApplicationId' => null,
                'jobs' => collect(),
                'stages' => collect(),
                'sources' => collect(),
                'statuses' => Application::statuses(),
                'filters' => $filters,
                'selectedCompanyId' => null,
                'oceanBaseline' => null,
                'missingFeedbackCount' => 0,
                'interviewers' => collect(),
                'actorZoomLink' => '',
                'timezones' => \DateTimeZone::listIdentifiers(),
                'latestCvParsingRequest' => null,
                'canViewReverseFeedbackAggregate' => false,
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $activeRole = null;
        if (! $actor->isSuperadmin()) {
            $activeRole = $actor->memberships()
                ->where('company_id', $companyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->value('company_role');

            $canAccessWorkspace = in_array((string) $activeRole, [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_MANAGER,
            ], true);

            if (! $canAccessWorkspace) {
                return redirect()->route('home')->with('error', __('candidates.errors.workspace_for_recruiters_only'));
            }
        }

        $canViewReverseFeedbackAggregate = $this->canViewReverseFeedbackAggregate(
            actor: $actor,
            companyId: (string) $companyId,
            activeRole: is_string($activeRole) ? $activeRole : null
        );

        $query = Application::withoutGlobalScopes()
            ->with([
                'candidate',
                'job:id,title,blind_mode_active',
                'currentStage:id,stage_label,stage_key',
            ])
            ->where('applications.company_id', $companyId);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $searchPattern = '%'.Str::lower($search).'%';

            $query->where(function ($sub) use ($searchPattern): void {
                $sub->whereHas('candidate', function ($candidateQuery) use ($searchPattern): void {
                    $candidateQuery
                        ->whereRaw('LOWER(full_name) LIKE ?', [$searchPattern])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$searchPattern]);
                })->orWhereHas('job', fn ($jobQuery) => $jobQuery->whereRaw('LOWER(title) LIKE ?', [$searchPattern]));
            });
        }

        if (($filters['job_id'] ?? null) !== null) {
            $query->where('applications.job_id', $filters['job_id']);
        }
        if (($filters['stage_id'] ?? null) !== null) {
            $query->where('applications.current_stage_id', $filters['stage_id']);
        }
        if (($filters['status'] ?? null) !== null) {
            $query->where('applications.status', $filters['status']);
        }
        if (($filters['source_type'] ?? null) !== null) {
            $query->where('applications.source_type', $filters['source_type']);
        }
        if (($filters['application_id'] ?? null) !== null) {
            $query->where('applications.id', $filters['application_id']);
        }
        if (($filters['date_from'] ?? null) !== null) {
            $query->whereDate('applications.created_at', '>=', (string) $filters['date_from']);
        }
        if (($filters['date_to'] ?? null) !== null) {
            $query->whereDate('applications.created_at', '<=', (string) $filters['date_to']);
        }

        if (($filters['job_id'] ?? null) !== null || $search !== '') {
            $query->leftJoin('application_scorings', function ($join) use ($companyId): void {
                $join->on('application_scorings.application_id', '=', 'applications.id')
                    ->where('application_scorings.company_id', '=', $companyId);
            })
            ->with(['cvParsingResults' => fn ($q) => $q->latest('created_at')])
            ->select('applications.*', 'application_scorings.global_match_score');
            
            $applications = $query
                ->orderByDesc('application_scorings.global_match_score')
                ->orderByDesc('applications.updated_at')
                ->paginate(24)
                ->withQueryString();
        } else {
            $applications = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 24);
            $applications->withPath($request->url())->withQueryString();
        }

        $selectedApplicationId = (string) ($filters['application_id'] ?? '');

        $selectedApplication = null;
        $latestCvParsingRequest = null;
        if ($selectedApplicationId !== '') {
            $selectedApplication = Application::withoutGlobalScopes()
                ->with([
                    'candidate.documents' => fn ($query) => $query->latest('created_at'),
                    'cvParsingResults' => fn ($query) => $query->latest('created_at')->limit(5),
                    'job:id,title,blind_mode_active',
                    'currentStage',
                    'job.pipelineStages',
                    'notes' => fn ($query) => $query->latest('created_at')->limit(80),
                    'notes.author.profile',
                    'activityEvents' => fn ($query) => $query->latest('created_at')->limit(120),
                    'activityEvents.actor.profile',
                    'scoring',
                    'unifiedInterviewReport',
                    'interviews.participants',
                    'interviews.feedback',
                    'strategyLabBrief.generatedAiRequest',
                    'strategyLabSubmission',
                    'strategyLabAiSummary',
                    'rejectionDraft',
                    'offer',
                    'contract',
                    'onboardingDocuments',
                    'onboardingScheduleItems',
                    'onboardingTasks',
                    'videoInterviewResponses' => fn ($query) => $query
                        ->with('question')
                        ->orderBy('question_id')
                        ->orderByDesc('attempt_number'),
                ])
                ->where('company_id', $companyId)
                ->where('id', $selectedApplicationId)
                ->first();

            if ($selectedApplication instanceof Application) {
                $latestCvParsingRequest = AiRequest::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('request_type', 'cv_parsing')
                    ->where('request_payload->application_id', (string) $selectedApplication->id)
                    ->latest('created_at')
                    ->first();
            }
        }

        $jobs = \App\Models\Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->withCount(['applications' => function ($query) {
                $query->withoutGlobalScopes();
            }])
            ->orderBy('title')
            ->get(['id', 'title', 'applications_count']);

        $stages = JobPipelineStage::withoutGlobalScopes()
            ->whereIn('job_id', function ($stageQuery) use ($companyId): void {
                $stageQuery->select('id')
                    ->from('jobs')
                    ->where('company_id', $companyId);
            })
            ->orderBy('stage_label')
            ->get(['id', 'stage_label']);

        $sources = Application::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('source_type')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type');

        $interviewers = User::query()
            ->with('profile')
            ->whereHas('memberships', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)
                    ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                    ->whereIn('company_role', [
                        CompanyMembership::ROLE_COMPANY_ADMIN,
                        CompanyMembership::ROLE_RECRUITER,
                        CompanyMembership::ROLE_MANAGER,
                        CompanyMembership::ROLE_EMPLOYEE,
                    ]);
            })
            ->orderBy('email')
            ->get(['users.id', 'users.email']);

        $actorZoomLink = (string) (UserSecureSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->where('setting_key', UserSecureSetting::KEY_ZOOM_PMR_LINK)
            ->value('setting_value') ?? '');

        $oceanBaseline = null;
        $latestVideoUnifiedRequest = null;
        if ($selectedApplication instanceof Application) {
            $latestVideoUnifiedRequest = AiRequest::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('request_type', 'async_video_unified_report')
                ->where('request_payload->application_id', (string) $selectedApplication->id)
                ->latest('created_at')
                ->first();

            $oceanBaseline = \App\Models\UnifiedInterviewReport::withoutGlobalScopes()
                ->join('applications', 'applications.id', '=', 'unified_interview_reports.application_id')
                ->join('candidates', 'candidates.id', '=', 'applications.candidate_id')
                ->join('company_memberships', function ($join) use ($companyId): void {
                    $join->on('company_memberships.user_id', '=', 'candidates.user_id')
                        ->where('company_memberships.company_id', '=', $companyId)
                        ->where('company_memberships.membership_status', '=', CompanyMembership::STATUS_ACTIVE)
                        ->where('company_memberships.company_role', '=', CompanyMembership::ROLE_EMPLOYEE);
                })
                ->where('unified_interview_reports.company_id', $companyId)
                ->where('applications.job_id', $selectedApplication->job_id)
                ->selectRaw('
                    AVG(unified_interview_reports.ocean_openness) as openness,
                    AVG(unified_interview_reports.ocean_conscientiousness) as conscientiousness,
                    AVG(unified_interview_reports.ocean_extraversion) as extraversion,
                    AVG(unified_interview_reports.ocean_agreeableness) as agreeableness,
                    AVG(unified_interview_reports.ocean_neuroticism) as neuroticism
                ')
                ->first();
        }

        $missingFeedbackCount = 0;
        if ($selectedApplication instanceof Application) {
            $missingFeedbackCount = $selectedApplication->interviews->sum(function (Interview $interview): int {
                $requiredFeedback = $interview->participants->count();
                $submittedFeedback = $interview->feedback->count();
                return max(0, $requiredFeedback - $submittedFeedback);
            });
        }

        $reverseFeedbackAggregate = null;
        if ($selectedApplication instanceof Application && $canViewReverseFeedbackAggregate) {
            $aggregate = DB::table('reverse_feedback')
                ->join('applications', 'applications.id', '=', 'reverse_feedback.application_id')
                ->where('reverse_feedback.company_id', $companyId)
                ->where('applications.job_id', $selectedApplication->job_id)
                ->selectRaw('
                    COUNT(reverse_feedback.id) as total,
                    AVG(reverse_feedback.rating_clarity) as avg_clarity,
                    AVG(reverse_feedback.rating_speed) as avg_speed,
                    AVG(reverse_feedback.rating_kindness) as avg_kindness
                ')
                ->first();

            if ($aggregate !== null && (int) ($aggregate->total ?? 0) > 0) {
                $reverseFeedbackAggregate = [
                    'total' => (int) $aggregate->total,
                    'avg_clarity' => round((float) ($aggregate->avg_clarity ?? 0), 1),
                    'avg_speed' => round((float) ($aggregate->avg_speed ?? 0), 1),
                    'avg_kindness' => round((float) ($aggregate->avg_kindness ?? 0), 1),
                ];
            }
        }

        $analysisJobId = trim((string) ($filters['job_id'] ?? ($selectedApplication?->job_id ?? '')));
        $analysisSnapshot = null;
        $analysisByApplication = collect();
        if ($analysisJobId !== '') {
            try {
                $analysisSnapshot = $this->candidateAnalysisService->jobRankingSnapshot(
                    companyId: (string) $companyId,
                    jobId: $analysisJobId,
                    limit: 80,
                    synchronize: true
                );

                $analysisByApplication = collect((array) ($analysisSnapshot['rows'] ?? []))
                    ->keyBy(fn (array $row): string => (string) ($row['application_id'] ?? ''));
            } catch (\Throwable) {
                $analysisSnapshot = null;
                $analysisByApplication = collect();
            }
        } elseif ($selectedApplication instanceof Application) {
            try {
                $this->candidateAnalysisService->recomputeForApplication($selectedApplication);
            } catch (\Throwable) {
                // Do not fail workspace rendering if deterministic analysis refresh fails.
            }
        }

        $topCandidatesByJob = [];
        foreach ($jobs as $job) {
            $topApps = Application::withoutGlobalScopes()
                ->with(['candidate', 'cvParsingResults' => fn($q) => $q->latest('created_at')])
                ->leftJoin('application_scorings', function ($join) use ($companyId) {
                    $join->on('application_scorings.application_id', '=', 'applications.id')
                        ->where('application_scorings.company_id', '=', $companyId);
                })
                ->where('applications.company_id', $companyId)
                ->where('applications.job_id', $job->id)
                ->whereIn('applications.status', [Application::STATUS_ACTIVE, Application::STATUS_HIRED])
                ->orderByDesc('application_scorings.global_match_score')
                ->select('applications.*', 'application_scorings.global_match_score')
                ->take(3)
                ->get();
            if ($topApps->isNotEmpty()) {
                $topCandidatesByJob[] = [
                    'job' => $job,
                    'applications' => $topApps,
                ];
            }
        }

        return view('candidates.index', [
            'requiresCompanySelection' => false,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'applications' => $applications,
            'selectedApplication' => $selectedApplication,
            'selectedApplicationId' => $selectedApplicationId,
            'jobs' => $jobs,
            'stages' => $stages,
            'sources' => $sources,
            'statuses' => Application::statuses(),
            'filters' => $filters,
            'selectedCompanyId' => $companyId,
            'oceanBaseline' => $oceanBaseline,
            'latestVideoUnifiedRequest' => $latestVideoUnifiedRequest,
            'missingFeedbackCount' => $missingFeedbackCount,
            'reverseFeedbackAggregate' => $reverseFeedbackAggregate,
            'interviewers' => $interviewers,
            'actorZoomLink' => $actorZoomLink,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'latestCvParsingRequest' => $latestCvParsingRequest,
            'canViewReverseFeedbackAggregate' => $canViewReverseFeedbackAggregate,
            'analysisJobId' => $analysisJobId !== '' ? $analysisJobId : null,
            'analysisSnapshot' => $analysisSnapshot,
            'analysisByApplication' => $analysisByApplication,
            'topCandidatesByJob' => $topCandidatesByJob,
        ]);
    }

    public function askAssistant(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 403);

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

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:600'],
            'application_id' => ['nullable', 'uuid'],
        ]);

        $message = Str::lower(trim((string) $validated['message']));
        $globalIntent = $this->resolveRecruiterAssistantGlobalIntent($message);
        if ($globalIntent !== null) {
            return $this->buildRecruiterAssistantGlobalResponse(
                companyId: (string) $companyId,
                intent: $globalIntent
            );
        }

        $applicationQuery = Application::withoutGlobalScopes()
            ->with([
                'candidate:id,full_name,email',
                'job:id,title',
                'currentStage:id,stage_label,stage_key,is_terminal',
                'offer',
                'contract',
                'onboardingTasks',
                'interviews.participants',
                'interviews.feedback',
            ])
            ->where('company_id', $companyId);

        $applicationId = (string) ($validated['application_id'] ?? '');
        if ($applicationId !== '') {
            $applicationQuery->where('id', $applicationId);
        }

        $application = $applicationQuery
            ->orderByDesc('updated_at')
            ->first();

        if (! $application instanceof Application) {
            return response()->json([
                'ok' => true,
                'answer' => __('candidates.assistant.messages.no_application_or_global'),
                'intent' => 'none',
                'summary' => null,
            ]);
        }

        $interviews = $application->interviews instanceof \Illuminate\Support\Collection
            ? $application->interviews->sortBy('scheduled_start_at')->values()
            : collect();

        $videoConfig = \App\Models\VideoConfig::withoutGlobalScopes()
            ->with('questions:id,config_id')
            ->where('company_id', $companyId)
            ->where('job_id', (string) $application->job_id)
            ->latest('created_at')
            ->first();
        $videoQuestionIds = $videoConfig?->questions?->pluck('id')->map(static fn ($id): string => (string) $id) ?? collect();
        $videoAnswered = 0;
        $videoTotal = $videoQuestionIds->count();
        if ($videoTotal > 0) {
            $videoAnswered = \App\Models\VideoResponse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('application_id', (string) $application->id)
                ->whereIn('question_id', $videoQuestionIds->all())
                ->distinct()
                ->count('question_id');
        }

        $sjtScenarioIds = \App\Models\SjtScenario::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($application): void {
                $query->whereNull('job_id')
                    ->orWhere('job_id', (string) $application->job_id);
            })
            ->where('is_active', true)
            ->pluck('id');

        $sjtTotal = $sjtScenarioIds->count();
        $sjtAnswered = 0;
        $sjtScored = 0;
        if ($sjtTotal > 0) {
            $sjtResponses = \App\Models\SjtResponse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('application_id', (string) $application->id)
                ->whereIn('scenario_id', $sjtScenarioIds->all())
                ->get(['response_text', 'ai_score']);

            $sjtAnswered = $sjtResponses
                ->filter(fn (\App\Models\SjtResponse $response): bool => trim((string) $response->response_text) !== '')
                ->count();
            $sjtScored = $sjtResponses
                ->filter(fn (\App\Models\SjtResponse $response): bool => $response->ai_score !== null)
                ->count();
        }

        $recentActivity = ApplicationActivityEvent::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('application_id', (string) $application->id)
            ->latest('created_at')
            ->limit(5)
            ->get(['event_type', 'created_at']);

        $upcomingInterview = $interviews->first(
            fn (Interview $interview): bool => $interview->status === Interview::STATUS_SCHEDULED
                && $interview->scheduled_start_at !== null
                && $interview->scheduled_start_at->isFuture()
        );
        $socialHubVisible = $this->candidateCanAccessSocialHub($application);
        $pendingFeedbackCount = $this->pendingFeedbackCountForApplication($application);
        $blockers = $this->offerOnboardingBlockersForApplication($application);

        $intent = 'summary';
        if ((bool) preg_match('/\b(status|stage|pipeline)\b/', $message)) {
            $intent = 'status';
        } elseif ((bool) preg_match('/\b(interview|zoom|meeting)\b/', $message)) {
            $intent = 'interview';
        } elseif ((bool) preg_match('/\b(feedback|interviewer notes|review notes|missing feedback)\b/', $message)) {
            $intent = 'feedback';
        } elseif ((bool) preg_match('/\b(blocker|blocked|blocking|contract|signature|offer|onboarding|task)\b/', $message)) {
            $intent = 'blockers';
        } elseif ((bool) preg_match('/\b(assessment|test|sjt|story|stories|video)\b/', $message)) {
            $intent = 'assessment';
        } elseif ((bool) preg_match('/\b(activity|portal|notification|timeline|event)\b/', $message)) {
            $intent = 'activity';
        } elseif ((bool) preg_match('/\b(social|hub|selected|preselected|pre-selected)\b/', $message)) {
            $intent = 'social_hub';
        }

        $candidateName = (string) ($application->candidate?->full_name ?? __('candidates.detail.not_available'));
        $jobTitle = (string) ($application->job?->title ?? __('candidates.detail.not_available'));
        $stageLabel = (string) ($application->currentStage?->stage_label ?? __('candidates.detail.not_available'));
        $statusLabel = __('candidates.list.status.'.(string) $application->status);

        $answer = match ($intent) {
            'status' => __('candidates.assistant.messages.status', [
                'candidate' => $candidateName,
                'job' => $jobTitle,
                'status' => $statusLabel,
                'stage' => $stageLabel,
            ]),
            'interview' => __('candidates.assistant.messages.interview', [
                'scheduled' => (string) $interviews->where('status', Interview::STATUS_SCHEDULED)->count(),
                'completed' => (string) $interviews->where('status', Interview::STATUS_COMPLETED)->count(),
                'upcoming' => $upcomingInterview?->scheduled_start_at
                    ? $upcomingInterview->scheduled_start_at->timezone((string) ($upcomingInterview->timezone ?: 'UTC'))->format('Y-m-d H:i')
                    : __('candidates.assistant.labels.none'),
            ]),
            'feedback' => __('candidates.assistant.messages.feedback', [
                'candidate' => $candidateName,
                'missing' => (string) $pendingFeedbackCount,
            ]),
            'blockers' => __('candidates.assistant.messages.blockers', [
                'candidate' => $candidateName,
                'count' => (string) count($blockers),
                'items' => collect($blockers)->take(3)->implode(', ') ?: __('candidates.assistant.labels.none'),
            ]),
            'assessment' => __('candidates.assistant.messages.assessment', [
                'video_answered' => (string) $videoAnswered,
                'video_total' => (string) $videoTotal,
                'sjt_answered' => (string) $sjtAnswered,
                'sjt_scored' => (string) $sjtScored,
                'sjt_total' => (string) $sjtTotal,
            ]),
            'activity' => __('candidates.assistant.messages.activity', [
                'events' => (string) $recentActivity->count(),
                'latest' => $recentActivity->first()?->created_at?->diffForHumans() ?? __('candidates.assistant.labels.none'),
            ]),
            'social_hub' => $socialHubVisible
                ? __('candidates.assistant.messages.social_hub_visible')
                : __('candidates.assistant.messages.social_hub_hidden'),
            default => __('candidates.assistant.messages.summary', [
                'candidate' => $candidateName,
                'job' => $jobTitle,
                'status' => $statusLabel,
                'stage' => $stageLabel,
                'video_answered' => (string) $videoAnswered,
                'video_total' => (string) $videoTotal,
                'sjt_answered' => (string) $sjtAnswered,
                'sjt_total' => (string) $sjtTotal,
                'social_hub' => $socialHubVisible
                    ? __('candidates.assistant.labels.visible')
                    : __('candidates.assistant.labels.hidden'),
            ]),
        };

        return response()->json([
            'ok' => true,
            'answer' => $answer,
            'intent' => $intent,
            'summary' => [
                'application_id' => (string) $application->id,
                'candidate_name' => $candidateName,
                'job_title' => $jobTitle,
                'status' => $statusLabel,
                'stage' => $stageLabel,
                'social_hub_visible' => $socialHubVisible,
                'pending_feedback' => $pendingFeedbackCount,
                'blockers' => $blockers,
            ],
        ]);
    }

    private function resolveRecruiterAssistantGlobalIntent(string $message): ?string
    {
        if ($message === '') {
            return null;
        }

        if ((bool) preg_match('/\b(feedback|interviewer notes|review notes|missing feedback|pending feedback)\b/', $message)) {
            return 'feedback';
        }

        if ((bool) preg_match('/\b(blocker|blockers|blocked|blocking|contract|signature|overdue task|tasks overdue|onboarding blockers?)\b/', $message)) {
            return 'blockers';
        }

        if ((bool) preg_match('/\b(stuck|stalled|waiting|inactive|delayed|old|pending too long)\b/', $message)) {
            return 'stalled';
        }

        if ((bool) preg_match('/\b(under analysis|analysis|review|reviewing|under review)\b/', $message)) {
            return 'analysis';
        }

        if ((bool) preg_match('/\b(interviews today|today interviews|upcoming interviews|interview schedule|zoom today|meeting today)\b/', $message)) {
            return 'interviews';
        }

        if ((bool) preg_match('/\b(offer|offers|hired|onboarding)\b/', $message)) {
            return 'offers';
        }

        if ((bool) preg_match('/\b(candidatures|candidates|applications|pipeline|overview|situation|how many|summary)\b/', $message)) {
            return 'overview';
        }

        return null;
    }

    private function buildRecruiterAssistantGlobalResponse(string $companyId, string $intent): JsonResponse
    {
        $applications = Application::withoutGlobalScopes()
            ->with([
                'candidate:id,full_name,email',
                'job:id,title',
                'currentStage:id,stage_label,stage_key,is_terminal',
                'offer',
                'contract',
                'onboardingTasks',
            ])
            ->where('company_id', $companyId)
            ->orderByDesc('updated_at')
            ->get();

        if ($applications->isEmpty()) {
            return response()->json([
                'ok' => true,
                'answer' => __('candidates.assistant.messages.no_applications_global'),
                'intent' => 'global_'.$intent,
                'summary' => [
                    'scope' => 'global',
                    'total_applications' => 0,
                ],
            ]);
        }

        $applicationIds = $applications->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $now = now();

        $interviews = Interview::withoutGlobalScopes()
            ->with(['participants:id,interview_id,user_id', 'feedback:id,interview_id,author_user_id'])
            ->where('company_id', $companyId)
            ->whereIn('application_id', $applicationIds)
            ->get(['id', 'application_id', 'status', 'scheduled_start_at', 'scheduled_end_at', 'timezone']);

        $latestActivityByApplication = ApplicationActivityEvent::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('application_id', $applicationIds)
            ->orderByDesc('created_at')
            ->get(['application_id', 'created_at'])
            ->groupBy('application_id')
            ->map(static fn ($items) => $items->first());

        $analysisStatuses = AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('request_type', ['candidate_analysis', 'async_video_unified_report'])
            ->orderByDesc('created_at')
            ->get(['status', 'request_payload'])
            ->groupBy(static fn (AiRequest $request): string => (string) data_get($request->request_payload, 'application_id'))
            ->map(static fn ($items) => $items->first());

        $statusCounts = $applications
            ->groupBy(fn (Application $application): string => (string) $application->status)
            ->map(fn ($items): int => $items->count());

        $stageCounts = $applications
            ->groupBy(fn (Application $application): string => (string) ($application->currentStage?->stage_label ?? __('candidates.detail.not_available')))
            ->map(fn ($items): int => $items->count())
            ->sortDesc()
            ->take(3);

        $upcomingInterviews = $interviews
            ->filter(static fn (Interview $interview): bool => (string) $interview->status === Interview::STATUS_SCHEDULED
                && $interview->scheduled_start_at !== null
                && $interview->scheduled_start_at->isFuture())
            ->sortBy('scheduled_start_at')
            ->values();

        $todayInterviews = $upcomingInterviews
            ->filter(fn (Interview $interview): bool => $interview->scheduled_start_at?->isSameDay($now) ?? false)
            ->values();

        $analysisPending = $analysisStatuses
            ->filter(fn ($request): bool => $request instanceof AiRequest
                && in_array((string) $request->status, [AiRequest::STATUS_QUEUED, AiRequest::STATUS_RUNNING], true))
            ->count();

        $offerOrHiredApplications = $applications
            ->filter(function (Application $application): bool {
                $stageText = Str::lower(trim(implode(' ', [
                    (string) ($application->currentStage?->stage_key ?? ''),
                    (string) ($application->currentStage?->stage_label ?? ''),
                ])));

                return (string) $application->status === Application::STATUS_HIRED
                    || Str::contains($stageText, ['offer', 'hired', 'hire', 'onboard']);
            })
            ->values();

        $stalledApplications = $applications
            ->filter(function (Application $application) use ($latestActivityByApplication, $now): bool {
                $latestActivity = $latestActivityByApplication->get((string) $application->id);
                $latestAt = $latestActivity?->created_at ?? $application->updated_at ?? $application->created_at;

                return $latestAt !== null && $latestAt->lt($now->copy()->subDays(5));
            })
            ->sortBy(fn (Application $application) => $latestActivityByApplication->get((string) $application->id)?->created_at ?? $application->updated_at)
            ->values();

        $interviewsByApplication = $interviews->groupBy(fn (Interview $interview): string => (string) $interview->application_id);
        $pendingFeedbackByApplication = $applications
            ->mapWithKeys(function (Application $application) use ($interviewsByApplication): array {
                $application->setRelation('interviews', $interviewsByApplication->get((string) $application->id, collect()));

                return [(string) $application->id => $this->pendingFeedbackCountForApplication($application)];
            });
        $pendingFeedbackApplications = $applications
            ->filter(fn (Application $application): bool => (int) ($pendingFeedbackByApplication->get((string) $application->id) ?? 0) > 0)
            ->values();
        $blockersByApplication = $applications
            ->mapWithKeys(fn (Application $application): array => [
                (string) $application->id => $this->offerOnboardingBlockersForApplication($application),
            ]);
        $blockedApplications = $applications
            ->filter(fn (Application $application): bool => count((array) $blockersByApplication->get((string) $application->id, [])) > 0)
            ->values();

        $answer = match ($intent) {
            'feedback' => __('candidates.assistant.messages.global_feedback', [
                'count' => (string) $pendingFeedbackApplications->count(),
                'missing' => (string) $pendingFeedbackByApplication->sum(),
                'sample' => $pendingFeedbackApplications->take(3)
                    ->map(function (Application $application) use ($pendingFeedbackByApplication): string {
                        return (string) ($application->candidate?->full_name ?? __('candidates.detail.not_available'))
                            .' ('.(int) ($pendingFeedbackByApplication->get((string) $application->id) ?? 0).')';
                    })
                    ->implode(', ') ?: __('candidates.assistant.labels.none'),
            ]),
            'blockers' => __('candidates.assistant.messages.global_blockers', [
                'count' => (string) $blockedApplications->count(),
                'sample' => $blockedApplications->take(3)
                    ->map(function (Application $application) use ($blockersByApplication): string {
                        $blockers = collect((array) $blockersByApplication->get((string) $application->id, []))
                            ->take(2)
                            ->implode(', ');

                        return (string) ($application->candidate?->full_name ?? __('candidates.detail.not_available'))
                            .($blockers !== '' ? ': '.$blockers : '');
                    })
                    ->implode('; ') ?: __('candidates.assistant.labels.none'),
            ]),
            'analysis' => __('candidates.assistant.messages.global_analysis', [
                'count' => (string) $analysisPending,
                'total' => (string) $applications->count(),
            ]),
            'interviews' => __('candidates.assistant.messages.global_interviews', [
                'today' => (string) $todayInterviews->count(),
                'upcoming' => (string) $upcomingInterviews->count(),
                'next' => $upcomingInterviews->first()?->scheduled_start_at?->format('Y-m-d H:i') ?? __('candidates.assistant.labels.none'),
            ]),
            'offers' => __('candidates.assistant.messages.global_offers', [
                'count' => (string) $offerOrHiredApplications->count(),
                'sample' => $offerOrHiredApplications->take(2)
                    ->map(fn (Application $application): string => (string) ($application->candidate?->full_name ?? __('candidates.detail.not_available')))
                    ->implode(', ') ?: __('candidates.assistant.labels.none'),
            ]),
            'stalled' => __('candidates.assistant.messages.global_stalled', [
                'count' => (string) $stalledApplications->count(),
                'sample' => $stalledApplications->take(2)
                    ->map(function (Application $application) use ($latestActivityByApplication): string {
                        $latest = $latestActivityByApplication->get((string) $application->id)?->created_at;
                        return (string) ($application->candidate?->full_name ?? __('candidates.detail.not_available'))
                            .' ('.($latest?->diffForHumans() ?? __('candidates.assistant.labels.none')).')';
                    })
                    ->implode(', ') ?: __('candidates.assistant.labels.none'),
            ]),
            default => __('candidates.assistant.messages.global_overview', [
                'total' => (string) $applications->count(),
                'active' => (string) ($statusCounts->get(Application::STATUS_ACTIVE) ?? 0),
                'hired' => (string) ($statusCounts->get(Application::STATUS_HIRED) ?? 0),
                'rejected' => (string) ($statusCounts->get(Application::STATUS_REJECTED) ?? 0),
                'top_stages' => $stageCounts->map(fn (int $count, string $stage): string => $stage.' ('.$count.')')->implode(', '),
            ]),
        };

        return response()->json([
            'ok' => true,
            'answer' => $answer,
            'intent' => 'global_'.$intent,
            'summary' => [
                'scope' => 'global',
                'total_applications' => $applications->count(),
                'active' => (int) ($statusCounts->get(Application::STATUS_ACTIVE) ?? 0),
                'hired' => (int) ($statusCounts->get(Application::STATUS_HIRED) ?? 0),
                'rejected' => (int) ($statusCounts->get(Application::STATUS_REJECTED) ?? 0),
                'analysis_pending' => $analysisPending,
                'upcoming_interviews' => $upcomingInterviews->count(),
                'stalled_applications' => $stalledApplications->count(),
                'pending_feedback_applications' => $pendingFeedbackApplications->count(),
                'pending_feedback_items' => (int) $pendingFeedbackByApplication->sum(),
                'blocked_applications' => $blockedApplications->count(),
            ],
        ]);
    }

    private function pendingFeedbackCountForApplication(Application $application): int
    {
        $interviews = $application->relationLoaded('interviews')
            ? collect($application->interviews)
            : collect();

        return $interviews
            ->filter(fn (Interview $interview): bool => (string) $interview->status === Interview::STATUS_COMPLETED)
            ->sum(function (Interview $interview): int {
                $participants = $interview->relationLoaded('participants') ? $interview->participants : collect();
                $feedback = $interview->relationLoaded('feedback') ? $interview->feedback : collect();

                return max(0, $participants->count() - $feedback->count());
            });
    }

    /**
     * @return array<int, string>
     */
    private function offerOnboardingBlockersForApplication(Application $application): array
    {
        $stageText = Str::lower(trim(implode(' ', [
            (string) ($application->currentStage?->stage_key ?? ''),
            (string) ($application->currentStage?->stage_label ?? ''),
        ])));
        $inOfferOrOnboarding = (string) $application->status === Application::STATUS_HIRED
            || Str::contains($stageText, ['offer', 'hired', 'hire', 'onboard']);

        if (! $inOfferOrOnboarding) {
            return [];
        }

        $blockers = [];
        if (! $application->offer instanceof Offer) {
            $blockers[] = __('candidates.assistant.blockers.offer_missing');
        } elseif ((string) $application->offer->offer_status !== Offer::STATUS_ACCEPTED) {
            $blockers[] = __('candidates.assistant.blockers.offer_not_accepted', [
                'status' => Str::headline((string) $application->offer->offer_status),
            ]);
        }

        if (! $application->contract instanceof Contract) {
            $blockers[] = __('candidates.assistant.blockers.contract_missing');
        } elseif ((string) $application->contract->contract_status !== Contract::STATUS_SIGNED) {
            $blockers[] = __('candidates.assistant.blockers.contract_not_signed', [
                'status' => Str::headline((string) $application->contract->contract_status),
            ]);
        }

        $openTasks = $application->relationLoaded('onboardingTasks')
            ? $application->onboardingTasks->filter(fn (OnboardingTask $task): bool => ! (bool) $task->is_completed)
            : collect();
        $overdueTasks = $openTasks
            ->filter(fn (OnboardingTask $task): bool => $task->due_at !== null && $task->due_at->isPast());

        if ($overdueTasks->isNotEmpty()) {
            $blockers[] = __('candidates.assistant.blockers.overdue_tasks', ['count' => (string) $overdueTasks->count()]);
        } elseif ($openTasks->isNotEmpty()) {
            $blockers[] = __('candidates.assistant.blockers.open_tasks', ['count' => (string) $openTasks->count()]);
        }

        return collect($blockers)->unique()->values()->all();
    }

    public function storeComment(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeApplicationAction($request, $application);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => __('candidates.validation.comment_required'),
            'body.max' => __('candidates.validation.comment_max'),
        ]);

        $commentBody = trim((string) $validated['body']);
        if ($commentBody === '') {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->withErrors(['body' => __('candidates.validation.comment_required')])
                ->withInput();
        }

        $note = $application->notes()->create([
            'company_id' => $companyId,
            'author_user_id' => $actor->id,
            'body' => $commentBody,
            'created_at' => now(),
        ]);

        $this->recordActivityEvent($application, 'comment.added', ['note_id' => $note->id], $actor);
        $this->sensitiveEvents->record('candidate.comment_added', 'application', (string) $application->id, ['note_id' => $note->id], $actor);

        return redirect()->route('candidates.index', $this->backQuery($request, $application))->with('status', __('candidates.flash.comment_added'));
    }

    public function moveStage(Request $request, Application $application): RedirectResponse
    {
        [$actor] = $this->authorizeApplicationAction($request, $application);

        $validated = $request->validate([
            'stage_id' => ['required', 'uuid'],
        ]);

        $stage = JobPipelineStage::withoutGlobalScopes()
            ->where('id', $validated['stage_id'])
            ->where('job_id', $application->job_id)
            ->firstOrFail();

        $oldStageId = (string) $application->current_stage_id;
        $oldStatus = (string) $application->status;
        $nextStatus = $this->resolveApplicationStatusFromStage($application, $stage);

        $application->update([
            'current_stage_id' => $stage->id,
            'status' => $nextStatus,
        ]);

        $jobPipelineStages = JobPipelineStage::withoutGlobalScopes()
            ->where('job_id', $application->job_id)
            ->orderBy('order', 'asc')
            ->get();
        $lastStageInJob = $jobPipelineStages->last();

        if ($lastStageInJob && (string) $stage->id === (string) $lastStageInJob->id) {
            \App\Models\Job::withoutGlobalScopes()
                ->where('id', $application->job_id)
                ->update(['status' => \App\Models\Job::STATUS_ARCHIVED]);
        }

        ApplicationStageHistory::withoutGlobalScopes()->create([
            'company_id' => (string) $application->company_id,
            'application_id' => (string) $application->id,
            'from_stage_id' => $oldStageId !== '' ? $oldStageId : null,
            'to_stage_id' => (string) $stage->id,
            'actor_user_id' => (string) $actor->id,
            'reason' => null,
            'created_at' => now(),
        ]);

        $this->recordActivityEvent($application, 'stage.changed', [
            'from_stage_id' => $oldStageId,
            'to_stage_id' => (string) $stage->id,
            'to_stage_label' => $stage->stage_label,
            'from_status' => $oldStatus,
            'to_status' => $nextStatus,
        ], $actor);

        $this->sensitiveEvents->stageChanged((string) $application->id, [
            'from_stage_id' => $oldStageId,
            'to_stage_id' => (string) $stage->id,
        ], $actor);

        $onboardingError = $this->queueOnboardingWelcomeIfEligible(
            application: $application,
            toStage: $stage,
            actor: $actor
        );

        $redirect = redirect()->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.flash.stage_moved'));

        if ($onboardingError !== null) {
            $redirect->with('error', $onboardingError);
        }

        return $redirect;
    }

    private function resolveApplicationStatusFromStage(Application $application, JobPipelineStage $stage): string
    {
        $stageText = Str::lower(trim(implode(' ', [
            (string) $stage->stage_key,
            (string) $stage->stage_label,
        ])));

        if ($stageText !== '' && preg_match('/\b(reject|rejected|declin|disqualif|not selected)\b/', $stageText) === 1) {
            return Application::STATUS_REJECTED;
        }

        if ($stageText !== '' && preg_match('/\b(hire|hired|onboard|onboarding)\b/', $stageText) === 1) {
            return Application::STATUS_HIRED;
        }

        if ($stageText !== '' && preg_match('/\b(withdraw|withdrawn)\b/', $stageText) === 1) {
            return Application::STATUS_WITHDRAWN;
        }

        if (! (bool) $stage->is_terminal) {
            return Application::STATUS_ACTIVE;
        }

        $currentStatus = (string) $application->status;
        if (in_array($currentStatus, Application::statuses(), true)) {
            return $currentStatus;
        }

        return Application::STATUS_ACTIVE;
    }

    public function scheduleInterview(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        [$actor] = $this->authorizeApplicationAction($request, $application);

        $validated = $request->validate([
            'scheduled_for' => ['required', 'date'],
            'channel' => ['nullable', 'string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'timezone' => ['required', 'timezone'],
            'interview_type' => ['nullable', 'string', 'max:120'],
            'interviewer_user_ids' => ['required', 'array', 'min:1'],
            'interviewer_user_ids.*' => ['uuid'],
            'meeting_link' => ['nullable', 'url', 'max:1024'],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'location_type' => ['nullable', Rule::in(Interview::locationTypes())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'admin_override_past' => ['nullable', 'boolean'],
        ]);

        $durationMinutes = (int) ($validated['duration_minutes'] ?? 60);
        $timezone = (string) $validated['timezone'];
        $startAt = Carbon::parse((string) $validated['scheduled_for'], $timezone);
        $allowPast = $request->boolean('admin_override_past')
            && ($actor->isSuperadmin() || $actor->hasRole(User::ROLE_COMPANY_ADMIN));
        if (! $allowPast && $startAt->isPast()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('interviews.validation.past_not_allowed')], 422);
            }
            return redirect()->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('interviews.validation.past_not_allowed'));
        }
        $endAt = $startAt->copy()->addMinutes($durationMinutes);

        $interviewerIds = array_values(array_unique(array_map('strval', $validated['interviewer_user_ids'] ?? [])));

        $companyId = (string) $application->company_id;
        $allowedInterviewerIds = User::query()
            ->whereIn('id', $interviewerIds)
            ->whereHas('memberships', fn ($q) => $q
                ->where('company_id', $companyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($allowedInterviewerIds === []) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('interviews.validation.interviewer_required')], 422);
            }
            return redirect()->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('interviews.validation.interviewer_required'));
        }

        $locationType = (string) ($validated['location_type'] ?? Interview::LOCATION_ZOOM);
        $locationAddress = trim((string) ($validated['location_address'] ?? ''));
        if ($locationType === Interview::LOCATION_IN_PERSON && $locationAddress === '') {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('interviews.validation.location_address_required_in_person')], 422);
            }
            return redirect()->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('interviews.validation.location_address_required_in_person'));
        }

        $meetingLink = trim((string) ($validated['meeting_link'] ?? ''));
        if ($locationType === Interview::LOCATION_ZOOM && $meetingLink === '') {
            $zoomSetting = UserSecureSetting::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('user_id', $actor->id)
                ->where('setting_key', UserSecureSetting::KEY_ZOOM_PMR_LINK)
                ->first();

            if ($zoomSetting instanceof UserSecureSetting) {
                $meetingLink = trim((string) $zoomSetting->setting_value);
            }
        }

        if ($locationType !== Interview::LOCATION_IN_PERSON) {
            $locationAddress = '';
        }

        $channel = $this->resolveInterviewChannel(
            locationType: $locationType,
            channel: (string) ($validated['channel'] ?? '')
        );
        $scheduledForText = $this->formatInterviewScheduleText($startAt, $durationMinutes, $timezone);

        $interview = Interview::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'application_id' => (string) $application->id,
            'interview_type' => (string) ($validated['interview_type'] ?? 'screening'),
            'scheduled_start_at' => $startAt->clone()->utc(),
            'scheduled_end_at' => $endAt->clone()->utc(),
            'timezone' => $timezone,
            'location_type' => $locationType,
            'meeting_link' => $meetingLink !== '' ? $meetingLink : null,
            'location_address' => $locationAddress !== '' ? $locationAddress : null,
            'status' => Interview::STATUS_SCHEDULED,
            'created_by_user_id' => (string) $actor->id,
        ]);

        foreach ($allowedInterviewerIds as $interviewerId) {
            InterviewParticipant::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'interview_id' => (string) $interview->id,
                'user_id' => $interviewerId,
                'participant_role' => 'interviewer',
                'created_at' => now(),
            ]);
        }

        $payload = array_merge($validated, [
            'interview_id' => (string) $interview->id,
            'location_type' => $locationType,
            'location_address' => $locationAddress !== '' ? $locationAddress : null,
            'channel' => $channel,
        ]);
        $this->recordActivityEvent($application, 'interview.scheduled', $payload, $actor);
        $this->sensitiveEvents->record('candidate.interview_scheduled', 'application', (string) $application->id, $payload, $actor);
        $emailError = $this->queueInterviewConfirmationEmail(
            application: $application,
            interview: $interview,
            scheduledForText: $scheduledForText,
            channel: $channel,
            locationType: $locationType,
            meetingLink: $meetingLink !== '' ? $meetingLink : null,
            locationAddress: $locationAddress !== '' ? $locationAddress : null,
            actor: $actor
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => __('candidates.flash.interview_scheduled'),
                'interview' => [
                    'id' => $interview->id,
                    'title' => 'Entretien avec ' . ($application->candidate?->full_name ?? 'Candidat'),
                    'start' => $interview->scheduled_start_at->toIso8601String(),
                    'end' => $interview->scheduled_end_at->toIso8601String(),
                    'url' => route('interviews.show', ['interview' => $interview->id, 'company_id' => $companyId]),
                    'meeting_link' => $interview->meeting_link
                ]
            ]);
        }

        $redirect = redirect()->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.flash.interview_scheduled'));

        if ($emailError !== null) {
            $redirect->with('error', $emailError);
        }

        return $redirect;
    }

    public function requestFeedback(Request $request, Application $application): RedirectResponse
    {
        [$actor] = $this->authorizeApplicationAction($request, $application);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = ['message' => (string) ($validated['message'] ?? '')];
        $this->recordActivityEvent($application, 'feedback.requested', $payload, $actor);
        $this->sensitiveEvents->record('candidate.feedback_requested', 'application', (string) $application->id, $payload, $actor);

        return redirect()->route('candidates.index', $this->backQuery($request, $application))->with('status', __('candidates.flash.feedback_requested'));
    }

    public function reject(Request $request, Application $application): RedirectResponse
    {
        [$actor] = $this->authorizeApplicationAction($request, $application);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'draft_subject' => ['nullable', 'string', 'max:1000'],
            'draft_body' => ['nullable', 'string', 'max:8000'],
            'xai_reason_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->loadMissing(['job', 'scoring']);
        $reason = trim((string) $validated['reason']);
        $jobTitle = (string) ($application->job?->title ?? '');

        $xaiReason = trim((string) ($validated['xai_reason_text'] ?? ''));
        if ($xaiReason === '') {
            $xaiReason = trim((string) ($application->scoring?->xai_summary ?? ''));
        }
        if ($xaiReason === '') {
            $xaiReason = $reason;
        }

        $draftSubject = trim((string) ($validated['draft_subject'] ?? ''));
        if ($draftSubject === '') {
            $draftSubject = __('kanban.mail.rejection_subject', [
                'job' => $jobTitle !== '' ? $jobTitle : __('candidates.detail.not_available'),
            ]);
        }

        $draftBody = trim((string) ($validated['draft_body'] ?? ''));
        if ($draftBody === '') {
            $draftBody = __('kanban.rejection.default_draft', [
                'job' => $jobTitle !== '' ? $jobTitle : __('candidates.detail.not_available'),
                'reason' => $xaiReason,
            ]);
        }

        $rejectionDraft = RejectionDraft::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => (string) $application->company_id,
                'application_id' => (string) $application->id,
            ],
            [
                'draft_subject' => $draftSubject,
                'draft_body' => $draftBody,
                'xai_reason_text' => $xaiReason,
                'status' => RejectionDraft::STATUS_DRAFT,
            ]
        );

        $this->aiRequestService->queueRequest(
            companyId: (string) $application->company_id,
            requestType: 'rejection_draft',
            requestPayload: [
                'application_id' => (string) $application->id,
                'rejection_draft_id' => (string) $rejectionDraft->id,
                'candidate_id' => (string) $application->candidate_id,
                'job_id' => (string) $application->job_id,
                'reason' => $reason,
                'xai_reason_text' => $xaiReason,
                'output_mode' => 'text',
                'prompt' => implode("\n", [
                    'Write a professional rejection email draft.',
                    'Include concise rationale aligned with provided XAI reason text.',
                    'Return plain text only.',
                    'Subject: '.$draftSubject,
                    'XAI reason: '.$xaiReason,
                ]),
            ],
            promptVersion: 'rejection_draft_v1'
        );

        $application->update(['status' => Application::STATUS_REJECTED]);

        $payload = [
            'reason' => $reason,
            'rejection_draft_id' => (string) $rejectionDraft->id,
            'draft_subject' => $draftSubject,
            'draft_body' => $draftBody,
            'xai_reason_text' => $xaiReason,
            'send_rejection_now' => false,
        ];
        $this->recordActivityEvent($application, 'application.rejected', $payload, $actor);
        $this->sensitiveEvents->stageChanged((string) $application->id, ['status' => Application::STATUS_REJECTED, 'reason' => $reason], $actor);
        $this->sensitiveEvents->record('candidate.rejected', 'application', (string) $application->id, $payload, $actor);

        return redirect()->route('candidates.index', $this->backQuery($request, $application))->with('status', __('candidates.flash.rejected'));
    }

    public function saveOffer(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeApplicationAction($request, $application);

        if ($application->status !== Application::STATUS_HIRED) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('candidates.onboarding.errors.hired_required'));
        }

        $validated = $request->validate([
            'offer_status' => ['required', Rule::in(Offer::statuses())],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'start_date' => ['nullable', 'date'],
        ]);

        $offer = Offer::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => (string) $companyId,
                'application_id' => (string) $application->id,
            ],
            [
                'offer_status' => (string) $validated['offer_status'],
                'salary_amount' => $validated['salary_amount'] ?? null,
                'currency' => Str::upper(trim((string) $validated['currency'])),
                'start_date' => $validated['start_date'] ?? null,
            ]
        );

        $payload = [
            'offer_id' => (string) $offer->id,
            'offer_status' => (string) $offer->offer_status,
            'salary_amount' => $offer->salary_amount !== null ? (string) $offer->salary_amount : null,
            'currency' => (string) $offer->currency,
            'start_date' => $offer->start_date?->toDateString(),
        ];
        $this->recordActivityEvent($application, 'onboarding.offer_saved', $payload, $actor);
        $this->sensitiveEvents->record('onboarding.offer_saved', 'offer', (string) $offer->id, $payload, $actor);

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.onboarding.flash.offer_saved'));
    }

    public function saveContract(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeApplicationAction($request, $application);

        if ($application->status !== Application::STATUS_HIRED) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('candidates.onboarding.errors.hired_required'));
        }

        $validated = $request->validate([
            'contract_status' => ['required', Rule::in([Contract::STATUS_DRAFT, Contract::STATUS_SENT])],
            'signature_method' => ['required', Rule::in([Contract::SIGNATURE_METHOD_TYPED])],
            'contract_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $existing = Contract::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('application_id', $application->id)
            ->first();

        $filePath = (string) ($existing?->contract_file_url ?? '');
        if ($request->hasFile('contract_file')) {
            $file = $request->file('contract_file');
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $fileName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
            $filePath = $file->storeAs(
                'private/onboarding/contracts/'.$companyId.'/'.$application->id,
                $fileName,
                'local'
            );
        }

        if ($filePath === '') {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('candidates.onboarding.errors.contract_file_required'));
        }

        $auditMetadata = (array) ($existing?->audit_metadata_json ?? []);
        $auditMetadata['updated_by'] = (string) $actor->id;
        $auditMetadata['updated_at'] = now()->toIso8601String();

        $contract = Contract::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => (string) $companyId,
                'application_id' => (string) $application->id,
            ],
            [
                'contract_file_url' => $filePath,
                'contract_status' => (string) $validated['contract_status'],
                'signed_at' => null,
                'signer_user_id' => null,
                'signature_method' => (string) $validated['signature_method'],
                'audit_metadata_json' => $auditMetadata,
            ]
        );

        $payload = [
            'contract_id' => (string) $contract->id,
            'contract_status' => (string) $contract->contract_status,
            'signature_method' => (string) $contract->signature_method,
            'contract_file_url' => (string) $contract->contract_file_url,
        ];
        $this->recordActivityEvent($application, 'onboarding.contract_saved', $payload, $actor);
        $this->sensitiveEvents->record('onboarding.contract_saved', 'contract', (string) $contract->id, $payload, $actor);

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.onboarding.flash.contract_saved'));
    }

    public function uploadOnboardingDocument(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeApplicationAction($request, $application);

        if ($application->status !== Application::STATUS_HIRED) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('candidates.onboarding.errors.hired_required'));
        }

        $validated = $request->validate([
            'doc_type' => ['required', Rule::in(OnboardingDocument::types())],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:10240'],
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $fileName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
        $filePath = $file->storeAs(
            'private/onboarding/documents/'.$companyId.'/'.$application->id,
            $fileName,
            'local'
        );

        $document = OnboardingDocument::withoutGlobalScopes()->create([
            'company_id' => (string) $companyId,
            'application_id' => (string) $application->id,
            'doc_type' => (string) $validated['doc_type'],
            'file_url' => $filePath,
            'created_at' => now(),
        ]);

        $payload = [
            'onboarding_document_id' => (string) $document->id,
            'doc_type' => (string) $document->doc_type,
            'file_url' => (string) $document->file_url,
        ];
        $this->recordActivityEvent($application, 'onboarding.document_uploaded', $payload, $actor);
        $this->sensitiveEvents->record('onboarding.document_uploaded', 'onboarding_document', (string) $document->id, $payload, $actor);

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.onboarding.flash.document_uploaded'));
    }

    public function storeOnboardingSchedule(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeApplicationAction($request, $application);

        if ($application->status !== Application::STATUS_HIRED) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('candidates.onboarding.errors.hired_required'));
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $scheduleItem = OnboardingSchedule::withoutGlobalScopes()->create([
            'company_id' => (string) $companyId,
            'application_id' => (string) $application->id,
            'title' => trim((string) $validated['title']),
            'start_at' => Carbon::parse((string) $validated['start_at'])->utc(),
            'end_at' => Carbon::parse((string) $validated['end_at'])->utc(),
            'location' => trim((string) ($validated['location'] ?? '')) ?: null,
            'created_at' => now(),
        ]);

        $payload = [
            'onboarding_schedule_id' => (string) $scheduleItem->id,
            'title' => (string) $scheduleItem->title,
            'start_at' => $scheduleItem->start_at?->toIso8601String(),
            'end_at' => $scheduleItem->end_at?->toIso8601String(),
            'location' => (string) ($scheduleItem->location ?? ''),
        ];
        $this->recordActivityEvent($application, 'onboarding.schedule_added', $payload, $actor);
        $this->sensitiveEvents->record('onboarding.schedule_added', 'onboarding_schedule', (string) $scheduleItem->id, $payload, $actor);

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.onboarding.flash.schedule_saved'));
    }

    public function storeOnboardingTask(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeApplicationAction($request, $application);

        if ($application->status !== Application::STATUS_HIRED) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('candidates.onboarding.errors.hired_required'));
        }

        $validated = $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = OnboardingTask::withoutGlobalScopes()->create([
            'company_id' => (string) $companyId,
            'application_id' => (string) $application->id,
            'task_name' => trim((string) $validated['task_name']),
            'due_at' => ($validated['due_at'] ?? null) !== null
                ? Carbon::parse((string) $validated['due_at'])->utc()
                : null,
            'is_completed' => false,
        ]);

        $payload = [
            'onboarding_task_id' => (string) $task->id,
            'task_name' => (string) $task->task_name,
            'due_at' => $task->due_at?->toIso8601String(),
            'is_completed' => (bool) $task->is_completed,
        ];
        $this->recordActivityEvent($application, 'onboarding.task_added', $payload, $actor);
        $this->sensitiveEvents->record('onboarding.task_added', 'onboarding_task', (string) $task->id, $payload, $actor);

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.onboarding.flash.task_saved'));
    }

    public function toggleOnboardingTask(Request $request, Application $application, OnboardingTask $onboardingTask): RedirectResponse
    {
        [$actor] = $this->authorizeApplicationAction($request, $application);
        abort_unless((string) $onboardingTask->application_id === (string) $application->id, 404);
        abort_unless((string) $onboardingTask->company_id === (string) $application->company_id, 404);

        if ($application->status !== Application::STATUS_HIRED) {
            return redirect()
                ->route('candidates.index', $this->backQuery($request, $application))
                ->with('error', __('candidates.onboarding.errors.hired_required'));
        }

        $onboardingTask->update([
            'is_completed' => ! $onboardingTask->is_completed,
        ]);

        $payload = [
            'onboarding_task_id' => (string) $onboardingTask->id,
            'task_name' => (string) $onboardingTask->task_name,
            'is_completed' => (bool) $onboardingTask->is_completed,
        ];
        $this->recordActivityEvent($application, 'onboarding.task_toggled', $payload, $actor);
        $this->sensitiveEvents->record('onboarding.task_toggled', 'onboarding_task', (string) $onboardingTask->id, $payload, $actor);

        return redirect()
            ->route('candidates.index', $this->backQuery($request, $application))
            ->with('status', __('candidates.onboarding.flash.task_toggled'));
    }

    public function requestAnalysis(Request $request, Application $application): RedirectResponse
    {
        [$actor] = $this->authorizeApplicationAction($request, $application);

        $this->candidateAnalysisService->recomputeForApplicationId(
            companyId: (string) $application->company_id,
            applicationId: (string) $application->id
        );

        $analysisPayload = [
            'mode' => 'deterministic_refresh',
            'application_id' => (string) $application->id,
        ];
        $this->recordActivityEvent($application, 'analysis.requested', $analysisPayload, $actor);
        $this->sensitiveEvents->record('candidate.analysis_requested', 'application', (string) $application->id, $analysisPayload, $actor);

        return redirect()->route('candidates.index', $this->backQuery($request, $application))->with('status', __('candidates.flash.analysis_requested'));
    }

    public function kanban(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, true);
        $validated = $request->validate([
            'job_id' => ['nullable', 'uuid'],
        ]);

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('candidates.kanban', [
                'requiresCompanySelection' => true,
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'selectedCompanyId' => null,
                'jobs' => collect(),
                'selectedJob' => null,
                'stages' => collect(),
                'boardStages' => collect(),
                'cardsByStage' => [],
                'interviewers' => collect(),
                'actorZoomLink' => '',
                'timezones' => \DateTimeZone::listIdentifiers(),
                'pipelineBlocked' => false,
                'pipelineIssue' => null,
                'pipelineFixUrl' => null,
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        if (! $actor->isSuperadmin()) {
            $canAccessWorkspace = $actor->memberships()
                ->where('company_id', $companyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->whereIn('company_role', [
                    CompanyMembership::ROLE_COMPANY_ADMIN,
                    CompanyMembership::ROLE_RECRUITER,
                    CompanyMembership::ROLE_MANAGER,
                ])
                ->exists();

            if (! $canAccessWorkspace) {
                return redirect()->route('home')->with('error', __('candidates.errors.workspace_for_recruiters_only'));
            }
        }

        $jobs = \App\Models\Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('title')
            ->get(['id', 'title']);

        $interviewers = User::query()
            ->whereHas('memberships', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId)
                    ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                    ->whereIn('company_role', [
                        CompanyMembership::ROLE_COMPANY_ADMIN,
                        CompanyMembership::ROLE_RECRUITER,
                        CompanyMembership::ROLE_MANAGER,
                        CompanyMembership::ROLE_EMPLOYEE,
                    ]);
            })
            ->with('profile:user_id,full_name')
            ->orderBy('email')
            ->get(['id', 'email']);

        $actorZoomLink = UserSecureSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->where('setting_key', UserSecureSetting::KEY_ZOOM_PMR_LINK)
            ->value('setting_value');

        $selectedJobId = (string) ($validated['job_id'] ?? '');
        if ($selectedJobId === '' && $jobs->isNotEmpty()) {
            $latestApp = \App\Models\Application::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('status', \App\Models\Application::STATUS_ACTIVE)
                ->latest('updated_at')
                ->first();
            $selectedJobId = $latestApp ? (string) $latestApp->job_id : (string) $jobs->first()->id;
        }

        $selectedJob = $jobs->firstWhere('id', $selectedJobId);
        $stages = collect();
        $boardStages = collect();
        $cardsByStage = [];
        $pipelineBlocked = false;
        $pipelineIssue = null;
        $pipelineFixUrl = null;

        if ($selectedJobId !== '') {
            $stages = JobPipelineStage::withoutGlobalScopes()
                ->where('job_id', $selectedJobId)
                ->orderBy('display_order')
                ->get(['id', 'stage_key', 'stage_label', 'is_terminal']);

            $pipelineIssue = $this->pipelineMisconfigurationIssue($stages);
            $pipelineBlocked = $pipelineIssue !== null;
            $pipelineFixUrl = $pipelineBlocked ? route('jobs.show', ['job' => $selectedJobId]) : null;

            if (! $pipelineBlocked) {
                $applications = Application::withoutGlobalScopes()
                    ->with([
                        'candidate',
                        'job:id,title,blind_mode_active',
                        'scoring',
                        'rejectionDraft',
                        'activityEvents' => fn ($q) => $q->latest('created_at')->limit(1),
                    ])
                    ->where('company_id', $companyId)
                    ->where('job_id', $selectedJobId)
                    ->orderByDesc('updated_at')
                    ->get();

                $hasExplicitRejectedStage = $stages->contains(function (JobPipelineStage $stage): bool {
                    $stageKey = Str::lower((string) $stage->stage_key);
                    $stageLabel = Str::lower((string) $stage->stage_label);

                    return str_contains($stageKey, 'reject')
                        || str_contains($stageLabel, 'reject')
                        || str_contains($stageLabel, 'rejet');
                });

                $rejectedVirtualStage = null;
                $applicationsForRegularStages = $applications;

                if (! $hasExplicitRejectedStage) {
                    /** @var JobPipelineStage|null $terminalFallbackStage */
                    $terminalFallbackStage = $stages
                        ->filter(fn (JobPipelineStage $stage): bool => (bool) $stage->is_terminal)
                        ->last();
                    if ($terminalFallbackStage instanceof JobPipelineStage) {
                        $rejectedVirtualStage = (object) [
                            'id' => '__rejected_virtual__',
                            'target_stage_id' => (string) $terminalFallbackStage->id,
                            'stage_key' => 'rejected_virtual',
                            'stage_label' => __('kanban.board.rejected_lane'),
                            'is_terminal' => true,
                            'is_virtual' => true,
                        ];

                        $cardsByStage[(string) $rejectedVirtualStage->id] = $applications
                            ->where('status', Application::STATUS_REJECTED)
                            ->values();

                        $applicationsForRegularStages = $applications
                            ->where('status', '!=', Application::STATUS_REJECTED)
                            ->values();
                    }
                }

                foreach ($stages as $stage) {
                    $cardsByStage[(string) $stage->id] = $applicationsForRegularStages
                        ->where('current_stage_id', $stage->id)
                        ->values();
                }

                $boardStages = $stages
                    ->map(fn (JobPipelineStage $stage) => (object) [
                        'id' => (string) $stage->id,
                        'target_stage_id' => (string) $stage->id,
                        'stage_key' => (string) $stage->stage_key,
                        'stage_label' => (string) $stage->stage_label,
                        'is_terminal' => (bool) $stage->is_terminal,
                        'is_virtual' => false,
                    ])
                    ->values();

                if ($rejectedVirtualStage !== null) {
                    $insertIndex = max(0, $boardStages->count() - 1);
                    $boardStages->splice($insertIndex, 0, [$rejectedVirtualStage]);
                }
            }
        }

        return view('candidates.kanban', [
            'requiresCompanySelection' => false,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId,
            'jobs' => $jobs,
            'selectedJob' => $selectedJob,
            'stages' => $stages,
            'boardStages' => $boardStages,
            'cardsByStage' => $cardsByStage,
            'interviewers' => $interviewers,
            'actorZoomLink' => $actorZoomLink,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'pipelineBlocked' => $pipelineBlocked,
            'pipelineIssue' => $pipelineIssue,
            'pipelineFixUrl' => $pipelineFixUrl,
        ]);
    }

    public function transition(Request $request, Application $application): RedirectResponse
    {
        [$actor, $companyId] = $this->authorizeApplicationAction($request, $application);

        $validated = $request->validate([
            'to_stage_id' => ['required', 'uuid'],
            'transition_type' => ['nullable', Rule::in(['interview', 'rejected', 'standard'])],
            'confirm_terminal' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'scheduled_for' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'timezone' => ['nullable', 'timezone'],
            'interview_type' => ['nullable', 'string', 'max:120'],
            'interviewer_user_ids' => ['nullable', 'array'],
            'interviewer_user_ids.*' => ['uuid'],
            'channel' => ['nullable', 'string', 'max:120'],
            'meeting_link' => ['nullable', 'url', 'max:1024'],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'location_type' => ['nullable', Rule::in(Interview::locationTypes())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'admin_override_past' => ['nullable', 'boolean'],
            'send_rejection_now' => ['nullable', 'boolean'],
            'draft_subject' => ['nullable', 'string', 'max:1000'],
            'draft_body' => ['nullable', 'string', 'max:8000'],
            'xai_reason_text' => ['nullable', 'string', 'max:2000'],
            'job_id' => ['nullable', 'uuid'],
            'company_id' => ['nullable', 'uuid'],
        ]);

        $toStage = JobPipelineStage::withoutGlobalScopes()
            ->where('id', $validated['to_stage_id'])
            ->where('job_id', $application->job_id)
            ->first();

        if (! $toStage instanceof JobPipelineStage) {
            return $this->transitionBackWithToast($request, __('kanban.errors.invalid_drop'));
        }

        $jobStages = JobPipelineStage::withoutGlobalScopes()
            ->where('job_id', $application->job_id)
            ->get(['id', 'is_terminal']);

        if ($this->pipelineMisconfigurationIssue($jobStages) !== null) {
            return $this->transitionBackWithToast($request, __('kanban.errors.pipeline_misconfigured'));
        }

        $fromStage = JobPipelineStage::withoutGlobalScopes()->find($application->current_stage_id);
        if ((string) $application->current_stage_id === (string) $toStage->id) {
            return $this->transitionBackWithToast($request, __('kanban.errors.same_stage'));
        }

        if ($toStage->is_terminal && ! $request->boolean('confirm_terminal')) {
            return $this->transitionBackWithToast($request, __('kanban.errors.confirm_terminal_required'));
        }

        $transitionType = (string) ($validated['transition_type'] ?? 'standard');
        $interviewPayload = null;
        $rejectionPayload = null;

        if ($transitionType === 'interview') {
            if (($validated['scheduled_for'] ?? null) === null) {
                return $this->transitionBackWithToast($request, __('kanban.errors.interview_schedule_required'));
            }

            $timezone = trim((string) ($validated['timezone'] ?? ''));
            if ($timezone === '') {
                return $this->transitionBackWithToast($request, __('interviews.validation.timezone_required'));
            }

            $startAt = Carbon::parse((string) $validated['scheduled_for'], $timezone);
            $allowPast = $request->boolean('admin_override_past')
                && ($actor->isSuperadmin() || $actor->hasRole(User::ROLE_COMPANY_ADMIN));
            if (! $allowPast && $startAt->isPast()) {
                return $this->transitionBackWithToast($request, __('interviews.validation.past_not_allowed'));
            }

            $interviewerIds = array_values(array_unique(array_map('strval', $validated['interviewer_user_ids'] ?? [])));
            if ($interviewerIds === []) {
                return $this->transitionBackWithToast($request, __('interviews.validation.interviewer_required'));
            }

            $allowedInterviewerIds = User::query()
                ->whereIn('id', $interviewerIds)
                ->whereHas('memberships', fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->where('membership_status', CompanyMembership::STATUS_ACTIVE))
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            if ($allowedInterviewerIds === []) {
                return $this->transitionBackWithToast($request, __('interviews.validation.interviewer_required'));
            }

            $locationType = (string) ($validated['location_type'] ?? Interview::LOCATION_ZOOM);
            $locationAddress = trim((string) ($validated['location_address'] ?? ''));
            if ($locationType === Interview::LOCATION_IN_PERSON && $locationAddress === '') {
                return $this->transitionBackWithToast($request, __('interviews.validation.location_address_required_in_person'));
            }

            $meetingLink = trim((string) ($validated['meeting_link'] ?? ''));
            if ($locationType === Interview::LOCATION_ZOOM && $meetingLink === '') {
                $meetingLink = trim((string) UserSecureSetting::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('user_id', $actor->id)
                    ->where('setting_key', UserSecureSetting::KEY_ZOOM_PMR_LINK)
                    ->value('setting_value'));
            }

            if ($locationType !== Interview::LOCATION_IN_PERSON) {
                $locationAddress = '';
            }

            $channel = $this->resolveInterviewChannel(
                locationType: $locationType,
                channel: (string) ($validated['channel'] ?? '')
            );

            $interviewPayload = [
                'timezone' => $timezone,
                'scheduled_for' => $startAt,
                'channel' => $channel,
                'duration_minutes' => (int) ($validated['duration_minutes'] ?? 60),
                'interview_type' => (string) ($validated['interview_type'] ?? 'screening'),
                'location_type' => $locationType,
                'meeting_link' => $meetingLink,
                'location_address' => $locationAddress,
                'allowed_interviewer_ids' => $allowedInterviewerIds,
                'notes' => (string) ($validated['notes'] ?? ''),
            ];
        }

        if ($transitionType === 'rejected') {
            $reason = trim((string) ($validated['reason'] ?? ''));
            if ($reason === '') {
                return $this->transitionBackWithToast($request, __('kanban.errors.rejection_reason_required'));
            }

            $jobTitle = (string) ($application->job?->title ?? '');
            $xaiReason = trim((string) ($validated['xai_reason_text'] ?? ''));
            if ($xaiReason === '') {
                $xaiReason = trim((string) ($application->scoring?->xai_summary ?? ''));
            }
            if ($xaiReason === '') {
                $xaiReason = $reason;
            }

            $draftSubject = trim((string) ($validated['draft_subject'] ?? ''));
            if ($draftSubject === '') {
                $draftSubject = __('kanban.mail.rejection_subject', ['job' => $jobTitle !== '' ? $jobTitle : __('candidates.detail.not_available')]);
            }

            $draftBody = trim((string) ($validated['draft_body'] ?? ''));
            if ($draftBody === '') {
                $draftBody = __('kanban.rejection.default_draft', [
                    'job' => $jobTitle !== '' ? $jobTitle : __('candidates.detail.not_available'),
                    'reason' => $xaiReason,
                ]);
            }

            $rejectionPayload = [
                'reason' => $reason,
                'draft_subject' => $draftSubject,
                'draft_body' => $draftBody,
                'xai_reason_text' => $xaiReason,
                'send_rejection_now' => $request->boolean('send_rejection_now'),
            ];
        }

        $activityPayload = [
            'from_stage_id' => $fromStage?->id,
            'to_stage_id' => $toStage->id,
            'to_stage_label' => $toStage->stage_label,
            'reason' => $validated['reason'] ?? null,
        ];
        $interview = null;
        $rejectionDraft = null;

        DB::transaction(function () use (
            &$application,
            $companyId,
            $toStage,
            $fromStage,
            $actor,
            $validated,
            $transitionType,
            $interviewPayload,
            $rejectionPayload,
            &$activityPayload,
            &$interview,
            &$rejectionDraft,
            $request
        ): void {
            if ($transitionType === 'interview' && is_array($interviewPayload)) {
                $scheduledFor = $interviewPayload['scheduled_for'];
                $interview = Interview::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'application_id' => (string) $application->id,
                    'interview_type' => $interviewPayload['interview_type'],
                    'scheduled_start_at' => $scheduledFor->clone()->utc(),
                    'scheduled_end_at' => $scheduledFor->clone()->addMinutes($interviewPayload['duration_minutes'])->utc(),
                    'timezone' => $interviewPayload['timezone'],
                    'location_type' => $interviewPayload['location_type'],
                    'meeting_link' => $interviewPayload['meeting_link'] !== '' ? $interviewPayload['meeting_link'] : null,
                    'location_address' => $interviewPayload['location_address'] !== '' ? $interviewPayload['location_address'] : null,
                    'status' => Interview::STATUS_DRAFT,
                    'created_by_user_id' => $actor->id,
                ]);

                foreach ($interviewPayload['allowed_interviewer_ids'] as $interviewerId) {
                    InterviewParticipant::withoutGlobalScopes()->create([
                        'company_id' => $companyId,
                        'interview_id' => (string) $interview->id,
                        'user_id' => $interviewerId,
                        'participant_role' => 'interviewer',
                        'created_at' => now(),
                    ]);
                }

                ApplicationTask::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'application_id' => $application->id,
                    'title' => __('kanban.tasks.interview_prep'),
                    'description' => $interviewPayload['notes'] !== '' ? $interviewPayload['notes'] : null,
                    'owner_user_id' => $actor->id,
                    'due_at' => $scheduledFor,
                    'status' => ApplicationTask::STATUS_OPEN,
                ]);

                $interview->update([
                    'status' => Interview::STATUS_SCHEDULED,
                ]);

                $activityPayload['scheduled_for'] = $scheduledFor->toISOString();
                $activityPayload['channel'] = $interviewPayload['channel'];
                $activityPayload['interview_id'] = (string) $interview->id;
                $activityPayload['notes'] = $interviewPayload['notes'];
                $activityPayload['location_type'] = $interviewPayload['location_type'];
                $activityPayload['location_address'] = $interviewPayload['location_address'] !== '' ? $interviewPayload['location_address'] : null;
            }

            if ($transitionType === 'rejected' && is_array($rejectionPayload)) {
                $rejectionDraft = RejectionDraft::withoutGlobalScopes()->updateOrCreate(
                    [
                        'company_id' => (string) $companyId,
                        'application_id' => (string) $application->id,
                    ],
                    [
                        'draft_subject' => $rejectionPayload['draft_subject'],
                        'draft_body' => $rejectionPayload['draft_body'],
                        'xai_reason_text' => $rejectionPayload['xai_reason_text'],
                        'status' => RejectionDraft::STATUS_DRAFT,
                    ]
                );

                $activityPayload['rejection_draft_id'] = (string) $rejectionDraft->id;
                $activityPayload['draft_subject'] = $rejectionPayload['draft_subject'];
                $activityPayload['draft_body'] = $rejectionPayload['draft_body'];
                $activityPayload['xai_reason_text'] = $rejectionPayload['xai_reason_text'];
                $activityPayload['send_rejection_now'] = $rejectionPayload['send_rejection_now'];

                $this->aiRequestService->queueRequest(
                    companyId: (string) $companyId,
                    requestType: 'rejection_draft',
                    requestPayload: [
                        'application_id' => (string) $application->id,
                        'rejection_draft_id' => (string) $rejectionDraft->id,
                        'candidate_id' => (string) $application->candidate_id,
                        'job_id' => (string) $application->job_id,
                        'reason' => $rejectionPayload['reason'],
                        'xai_reason_text' => $rejectionPayload['xai_reason_text'],
                        'output_mode' => 'text',
                        'prompt' => implode("\n", [
                            'Write a professional rejection email draft.',
                            'Include concise rationale aligned with provided XAI reason text.',
                            'Return plain text only.',
                            'Subject: '.$rejectionPayload['draft_subject'],
                            'XAI reason: '.$rejectionPayload['xai_reason_text'],
                        ]),
                    ],
                    promptVersion: 'rejection_draft_v1'
                );

                $application->update(['status' => Application::STATUS_REJECTED]);
            }

            $application->update(['current_stage_id' => $toStage->id]);

            $jobPipelineStages = JobPipelineStage::withoutGlobalScopes()
                ->where('job_id', $application->job_id)
                ->orderBy('display_order', 'asc')
                ->get();
            $lastStageInJob = $jobPipelineStages->last();

            if ($lastStageInJob && (string) $toStage->id === (string) $lastStageInJob->id) {
                \App\Models\Job::withoutGlobalScopes()
                    ->where('id', $application->job_id)
                    ->update(['status' => \App\Models\Job::STATUS_ARCHIVED]);
            }

            ApplicationStageHistory::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'application_id' => $application->id,
                'from_stage_id' => $fromStage?->id,
                'to_stage_id' => $toStage->id,
                'actor_user_id' => $actor->id,
                'reason' => $validated['reason'] ?? null,
                'created_at' => now(),
            ]);

            $this->recordActivityEvent($application, 'stage.changed', $activityPayload, $actor);

            if ($transitionType === 'interview') {
                $this->recordActivityEvent($application, 'interview.scheduled', [
                    'scheduled_for' => $activityPayload['scheduled_for'] ?? null,
                    'channel' => $activityPayload['channel'] ?? null,
                ], $actor);
                $this->sensitiveEvents->record('candidate.interview_scheduled', 'application', (string) $application->id, $activityPayload, $actor);
            }

            if ($transitionType === 'rejected') {
                $this->recordActivityEvent($application, 'application.rejected', [
                    'reason' => $validated['reason'] ?? null,
                    'send_rejection_now' => $request->boolean('send_rejection_now'),
                ], $actor);
                $this->sensitiveEvents->record('candidate.rejected', 'application', (string) $application->id, $activityPayload, $actor);
            }

            $this->sensitiveEvents->stageChanged((string) $application->id, $activityPayload, $actor);
        });

        $emailError = null;
        if ($transitionType === 'interview' && $interview instanceof Interview && is_array($interviewPayload)) {
            $emailError = $this->queueInterviewConfirmationEmail(
                application: $application,
                interview: $interview,
                scheduledForText: $this->formatInterviewScheduleText(
                    $interviewPayload['scheduled_for'],
                    (int) $interviewPayload['duration_minutes'],
                    (string) $interviewPayload['timezone']
                ),
                channel: $interviewPayload['channel'],
                locationType: $interviewPayload['location_type'],
                meetingLink: $interviewPayload['meeting_link'] !== '' ? $interviewPayload['meeting_link'] : null,
                locationAddress: $interviewPayload['location_address'] !== '' ? $interviewPayload['location_address'] : null,
                actor: $actor
            );
        }

        if (
            $transitionType === 'rejected'
            && is_array($rejectionPayload)
            && $rejectionPayload['send_rejection_now']
            && $rejectionDraft instanceof RejectionDraft
        ) {
            $emailError = $this->queueRejectionEmail(
                application: $application,
                rejectionDraft: $rejectionDraft,
                actor: $actor
            );
        }

        $onboardingError = null;
        if ($transitionType !== 'rejected') {
            $onboardingError = $this->queueOnboardingWelcomeIfEligible(
                application: $application,
                toStage: $toStage,
                actor: $actor
            );
        }

        $redirect = redirect()->route('candidates.kanban', array_filter([
            'company_id' => (string) ($validated['company_id'] ?? ''),
            'job_id' => (string) ($validated['job_id'] ?? ''),
        ]))->with('status', __('kanban.flash.stage_updated'));

        if ($emailError !== null) {
            $redirect->with('error', $emailError);
        } elseif ($onboardingError !== null) {
            $redirect->with('error', $onboardingError);
        }

        return $redirect;
    }

    private function pipelineMisconfigurationIssue(\Illuminate\Support\Collection $stages): ?string
    {
        if ($stages->isEmpty()) {
            return __('kanban.pipeline.errors.no_stages');
        }

        if ($stages->where('is_terminal', true)->count() < 1) {
            return __('kanban.pipeline.errors.terminal_required');
        }

        if ($stages->where('is_terminal', false)->count() < 1) {
            return __('kanban.pipeline.errors.non_terminal_required');
        }

        return null;
    }

    private function queueInterviewConfirmationEmail(
        Application $application,
        Interview $interview,
        string $scheduledForText,
        string $channel,
        string $locationType,
        ?string $meetingLink,
        ?string $locationAddress,
        ?User $actor
    ): ?string {
        $application->loadMissing(['candidate.user.profile', 'job']);

        $candidateEmail = trim((string) ($application->candidate?->email ?? ''));
        if ($candidateEmail === '') {
            return __('communications.errors.missing_candidate_email');
        }

        $language = $this->resolveCandidateLocale($application);
        $locationLabel = $this->resolveInterviewLocationLabel($locationType, $language);
        $locationValue = $this->resolveInterviewLocationValue($locationType, $meetingLink, $locationAddress);

        $outcome = $this->communicationEngine->queueTemplateEmail(
            companyId: (string) $application->company_id,
            templateKey: 'interview_confirmation',
            toEmail: $candidateEmail,
            toName: (string) ($application->candidate?->full_name ?? ''),
            language: $language,
            variables: [
                'candidate_name' => (string) ($application->candidate?->full_name ?? ''),
                'job_title' => (string) ($application->job?->title ?? ''),
                'scheduled_for' => $scheduledForText,
                'channel' => $channel,
                'meeting_link' => $locationValue !== '' ? $locationValue : '-',
                'location_label' => $locationLabel,
                'location_value' => $locationValue !== '' ? $locationValue : '-',
            ],
            relatedEntityType: 'interview',
            relatedEntityId: (string) $interview->id
        );

        if (! $outcome['ok']) {
            return (string) ($outcome['error'] ?? __('communications.errors.template_not_found'));
        }

        $messageId = (string) Str::uuid();
        $payload = [
            'message_id' => $messageId,
            'template' => 'interview_confirmation',
            'recipient' => $candidateEmail,
            'application_id' => (string) $application->id,
            'interview_id' => (string) $interview->id,
            'outbox_id' => (string) ($outcome['log']?->id ?? ''),
        ];
        $this->recordActivityEvent($application, 'email.sent', $payload, $actor);
        $this->sensitiveEvents->emailSent($messageId, $payload, $actor);

        return null;
    }

    private function resolveInterviewChannel(string $locationType, string $channel): string
    {
        $normalizedChannel = trim($channel);
        if ($normalizedChannel !== '') {
            return $normalizedChannel;
        }

        return match ($locationType) {
            Interview::LOCATION_IN_PERSON => __('interviews.location_types.in_person'),
            Interview::LOCATION_OTHER => __('interviews.location_types.other'),
            default => __('kanban.default_channel'),
        };
    }

    private function formatInterviewScheduleText(Carbon $startAt, int $durationMinutes, string $timezone): string
    {
        $endAt = $startAt->copy()->addMinutes(max(1, $durationMinutes));

        return sprintf(
            '%s - %s (%s)',
            $startAt->format('Y-m-d H:i'),
            $endAt->format('H:i'),
            $timezone
        );
    }

    private function resolveInterviewLocationLabel(string $locationType, string $language): string
    {
        $key = $locationType === Interview::LOCATION_IN_PERSON
            ? 'interviews.fields.location_address'
            : 'interviews.fields.meeting_link';

        return trans($key, [], $language);
    }

    private function resolveInterviewLocationValue(string $locationType, ?string $meetingLink, ?string $locationAddress): string
    {
        return $locationType === Interview::LOCATION_IN_PERSON
            ? trim((string) $locationAddress)
            : trim((string) $meetingLink);
    }

    private function queueRejectionEmail(
        Application $application,
        RejectionDraft $rejectionDraft,
        ?User $actor
    ): ?string {
        $application->loadMissing(['candidate.user.profile', 'job']);

        $candidateEmail = trim((string) ($application->candidate?->email ?? ''));
        if ($candidateEmail === '') {
            return __('communications.errors.missing_candidate_email');
        }

        $rejectionDraft->forceFill([
            'status' => RejectionDraft::STATUS_APPROVED,
            'updated_at' => now(),
        ])->save();

        $outcome = $this->communicationEngine->queueTemplateEmail(
            companyId: (string) $application->company_id,
            templateKey: 'rejection_decision',
            toEmail: $candidateEmail,
            toName: (string) ($application->candidate?->full_name ?? ''),
            language: $this->resolveCandidateLocale($application),
            variables: [
                'candidate_name' => (string) ($application->candidate?->full_name ?? ''),
                'job_title' => (string) ($application->job?->title ?? ''),
                'draft_body' => (string) $rejectionDraft->draft_body,
                'xai_reason' => (string) $rejectionDraft->xai_reason_text,
            ],
            relatedEntityType: 'rejection_draft',
            relatedEntityId: (string) $rejectionDraft->id
        );

        if (! $outcome['ok']) {
            return (string) ($outcome['error'] ?? __('communications.errors.template_not_found'));
        }

        $messageId = (string) Str::uuid();
        $payload = [
            'message_id' => $messageId,
            'template' => 'rejection_decision',
            'recipient' => $candidateEmail,
            'application_id' => (string) $application->id,
            'rejection_draft_id' => (string) $rejectionDraft->id,
            'outbox_id' => (string) ($outcome['log']?->id ?? ''),
        ];
        $this->recordActivityEvent($application, 'email.sent', $payload, $actor);
        $this->sensitiveEvents->emailSent($messageId, $payload, $actor);

        return null;
    }

    private function queueOnboardingWelcomeIfEligible(Application $application, JobPipelineStage $toStage, ?User $actor): ?string
    {
        $stageKey = Str::lower((string) $toStage->stage_key);
        if (! str_contains($stageKey, 'hire') && ! str_contains($stageKey, 'onboard')) {
            return null;
        }

        $application->loadMissing(['candidate.user.profile', 'job']);
        if ($application->status !== Application::STATUS_HIRED) {
            $application->update(['status' => Application::STATUS_HIRED]);
        }

        $alreadyQueued = \App\Models\EmailOutboxLog::withoutGlobalScopes()
            ->where('company_id', $application->company_id)
            ->where('template_key', 'onboarding_welcome_after_signing')
            ->where('related_entity_type', 'application')
            ->where('related_entity_id', (string) $application->id)
            ->whereIn('status', [\App\Models\EmailOutboxLog::STATUS_QUEUED, \App\Models\EmailOutboxLog::STATUS_SENT])
            ->exists();

        if ($alreadyQueued) {
            return null;
        }

        $candidateEmail = trim((string) ($application->candidate?->email ?? ''));
        if ($candidateEmail === '') {
            return __('communications.errors.missing_candidate_email');
        }

        $outcome = $this->communicationEngine->queueTemplateEmail(
            companyId: (string) $application->company_id,
            templateKey: 'onboarding_welcome_after_signing',
            toEmail: $candidateEmail,
            toName: (string) ($application->candidate?->full_name ?? ''),
            language: $this->resolveCandidateLocale($application),
            variables: [
                'candidate_name' => (string) ($application->candidate?->full_name ?? ''),
                'company_name' => (string) ($application->company?->name ?? ''),
                'job_title' => (string) ($application->job?->title ?? ''),
            ],
            relatedEntityType: 'application',
            relatedEntityId: (string) $application->id
        );

        if (! $outcome['ok']) {
            return (string) ($outcome['error'] ?? __('communications.errors.template_not_found'));
        }

        $messageId = (string) Str::uuid();
        $payload = [
            'message_id' => $messageId,
            'template' => 'onboarding_welcome_after_signing',
            'recipient' => $candidateEmail,
            'application_id' => (string) $application->id,
            'outbox_id' => (string) ($outcome['log']?->id ?? ''),
        ];
        $this->recordActivityEvent($application, 'email.sent', $payload, $actor);
        $this->sensitiveEvents->emailSent($messageId, $payload, $actor);

        return null;
    }

    private function resolveCandidateLocale(Application $application): string
    {
        $locale = Str::lower((string) ($application->candidate?->user?->profile?->locale ?? config('app.locale', 'en')));
        return in_array($locale, ['en', 'fr'], true) ? $locale : 'en';
    }

    private function authorizeApplicationAction(Request $request, Application $application): array
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 403);
        abort_unless((string) $application->company_id === (string) $companyId, 403);

        if (! $actor->isSuperadmin()) {
            $allowed = $actor->memberships()
                ->where('company_id', $companyId)
                ->where('membership_status', \App\Models\CompanyMembership::STATUS_ACTIVE)
                ->whereIn('company_role', [
                    \App\Models\CompanyMembership::ROLE_COMPANY_ADMIN,
                    \App\Models\CompanyMembership::ROLE_RECRUITER,
                    \App\Models\CompanyMembership::ROLE_MANAGER,
                ])->exists();
            abort_unless($allowed, 403);
        }

        return [$actor, $companyId];
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

    private function transitionBackWithToast(Request $request, string $message): RedirectResponse
    {
        return redirect()->route('candidates.kanban', array_filter([
            'company_id' => (string) $request->input('company_id', $request->query('company_id', '')),
            'job_id' => (string) $request->input('job_id', $request->query('job_id', '')),
        ]))
            ->with('error', $message);
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
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
            'company_id' => (string) $request->input('company_id', $request->query('company_id', '')),
        ], fn ($value) => $value !== '');
    }

    private function candidateCanAccessSocialHub(Application $application): bool
    {
        return $application instanceof Application;
    }

    private function canViewReverseFeedbackAggregate(User $actor, string $companyId, ?string $activeRole = null): bool
    {
        if ($actor->isSuperadmin()) {
            return true;
        }

        $role = $activeRole;
        if (! is_string($role) || $role === '') {
            $role = (string) $actor->memberships()
                ->where('company_id', $companyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->value('company_role');
        }

        return in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_MANAGER,
        ], true);
    }

    public static function maskIdentity(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $masked = array_map(function (string $part): string {
            $first = mb_substr($part, 0, 1);
            return $first.'***';
        }, array_filter($parts));

        return $masked !== [] ? implode(' ', $masked) : '***';
    }

    public static function shouldMaskIdentity(?Job $job, ?string $stageKey = null, ?string $stageLabel = null): bool
    {
        if (! $job instanceof Job || ! $job->blind_mode_active) {
            return false;
        }

        return self::isScreeningStage($stageKey, $stageLabel);
    }

    public static function maskedCandidateIdentifier(string $applicationId): string
    {
        $normalized = Str::upper(Str::replace('-', '', trim($applicationId)));
        $suffix = Str::substr($normalized, 0, 8);

        return 'CID-'.$suffix;
    }

    public static function isScreeningStage(?string $stageKey = null, ?string $stageLabel = null): bool
    {
        $normalizedKey = Str::lower(trim((string) $stageKey));
        $normalizedLabel = Str::lower(trim((string) $stageLabel));

        return str_contains($normalizedKey, 'screen')
            || str_contains($normalizedLabel, 'screen')
            || str_contains($normalizedLabel, 'preselect')
            || str_contains($normalizedLabel, 'pre-select')
            || str_contains($normalizedLabel, 'preselection');
    }

    public static function signedDocumentUrl(\App\Models\CandidateDocument $document): string
    {
        return URL::temporarySignedRoute(
            'media.candidate-document',
            now()->addMinutes(15),
            ['candidateDocument' => $document->id]
        );
    }

    public static function signedContractUrl(Contract $contract): string
    {
        return URL::temporarySignedRoute(
            'media.contract',
            now()->addMinutes(15),
            ['contract' => $contract->id]
        );
    }

    public static function signedOnboardingDocumentUrl(OnboardingDocument $document): string
    {
        return URL::temporarySignedRoute(
            'media.onboarding-document',
            now()->addMinutes(15),
            ['onboardingDocument' => $document->id]
        );
    }
}

