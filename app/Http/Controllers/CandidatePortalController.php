<?php

namespace App\Http\Controllers;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyValue;
use App\Models\Contract;
use App\Models\FaqItem;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Offer;
use App\Models\OnboardingDocument;
use App\Models\OnboardingTask;
use App\Models\ReverseFeedback;
use App\Models\SjtResponse;
use App\Models\SjtScenario;
use App\Models\SocialPost;
use App\Models\StrategyLabBrief;
use App\Models\User;
use App\Models\VideoConfig;
use App\Models\VideoResponse;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\Communication\CommunicationEngineService;
use App\Services\SocialHub\SocialHubService;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CandidatePortalController extends Controller
{
    public function __construct(
        private readonly CommunicationEngineService $communicationEngine,
        private readonly SensitiveEventRecorder $sensitiveEvents,
        private readonly SocialHubService $socialHubService
    ) {
    }

    public function show(Request $request, Company $company): View|RedirectResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;
        return view('candidate.dashboard', $this->buildCandidatePortalViewData($company, $candidate));
    }

    public function applications(Request $request, Company $company): View|RedirectResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;

        return view('candidate.applications', $this->buildCandidatePortalViewData($company, $candidate));
    }

    public function updates(Request $request, Company $company): View|RedirectResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;

        return view('candidate.updates', $this->buildCandidatePortalViewData($company, $candidate));
    }

    public function account(Request $request, Company $company): View|RedirectResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;

        return view('candidate.account', $this->buildCandidatePortalViewData($company, $candidate));
    }

    public function statusTracker(Request $request, Company $company): JsonResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            abort(401);
        }

        [$candidate] = $context;

        $applications = Application::withoutGlobalScopes()
            ->with([
                'job:id,title,status,department_id',
                'currentStage:id,stage_label,stage_key,is_terminal',
                'interviews' => fn ($query) => $query
                    ->select([
                        'id',
                        'application_id',
                        'location_type',
                        'meeting_link',
                        'status',
                        'scheduled_start_at',
                        'scheduled_end_at',
                        'timezone',
                    ])
                    ->orderBy('scheduled_start_at'),
                'strategyLabBrief' => fn ($query) => $query->with(['submission', 'aiSummary', 'application.job:id,title']),
                'contract',
                'onboardingTasks',
                'reverseFeedback',
                'scoring:id,application_id,global_match_score,vrin_json,source_status_json,xai_summary,analysis_status,updated_at',
                'unifiedInterviewReport:id,application_id,xai_summary,ocean_extraversion,ocean_agreeableness,updated_at',
                'sjtResponses:id,application_id,ai_score,ai_feedback_json,updated_at',
            ])
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->orderByDesc('created_at')
            ->get();

        $videoAssessments = $this->buildVideoAssessments((string) $company->id, $applications);
        $sjtAssessments = $this->buildSjtAssessments((string) $company->id, $applications);
        $videoByApplication = collect($videoAssessments)->keyBy(
            static fn (array $item): string => (string) data_get($item, 'application.id')
        );
        $sjtByApplication = collect($sjtAssessments)->keyBy(
            static fn (array $item): string => (string) data_get($item, 'application.id')
        );

        $nextSteps = $applications->mapWithKeys(
            fn (Application $application): array => [
                (string) $application->id => $this->resolveNextStep(
                    $application,
                    $videoByApplication->get((string) $application->id),
                    $sjtByApplication->get((string) $application->id)
                ),
            ]
        );

        $trackers = $this->buildStatusTrackers(
            companyId: (string) $company->id,
            applications: $applications,
            nextSteps: $nextSteps
        );

        return response()->json([
            'ok' => true,
            'updated_at' => now()->toIso8601String(),
            'trackers' => $trackers->values()->all(),
        ]);
    }

    public function faq(Request $request, Company $company): View|RedirectResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $selectedCategory = trim((string) ($filters['category'] ?? ''));
        $searchTerm = trim((string) ($filters['q'] ?? ''));

        $faqQuery = FaqItem::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_published', true);

        if ($selectedCategory !== '') {
            $faqQuery->where('category', $selectedCategory);
        }

        if ($searchTerm !== '') {
            $faqQuery->where(function ($query) use ($searchTerm): void {
                $query->where('question', 'like', '%'.$searchTerm.'%')
                    ->orWhere('answer', 'like', '%'.$searchTerm.'%');
            });
        }

        $faqs = $faqQuery
            ->orderBy('category')
            ->orderBy('question')
            ->paginate(20)
            ->withQueryString();

        $categories = FaqItem::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_published', true)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->orderBy('category')
            ->distinct()
            ->pluck('category');

        return view('candidate.faq', [
            'company' => $company,
            'faqs' => $faqs,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'searchTerm' => $searchTerm,
        ]);
    }

    private function buildCandidatePortalViewData(Company $company, Candidate $candidate): array
    {
        $applications = Application::withoutGlobalScopes()
            ->with([
                'job:id,title,status,department_id',
                'job.department:id,name',
                'currentStage:id,stage_label,stage_key,is_terminal',
                'reverseFeedback',
                'interviews' => fn ($query) => $query
                    ->select([
                        'id',
                        'application_id',
                        'location_type',
                        'meeting_link',
                        'status',
                        'scheduled_start_at',
                        'scheduled_end_at',
                        'timezone',
                    ])
                    ->orderBy('scheduled_start_at'),
                'strategyLabBrief' => fn ($query) => $query->with(['submission', 'aiSummary', 'application.job:id,title']),
                'offer',
                'contract',
                'onboardingDocuments',
                'onboardingScheduleItems',
                'onboardingTasks',
                'scoring:id,application_id,global_match_score,vrin_json,source_status_json,xai_summary,analysis_status,updated_at',
                'unifiedInterviewReport:id,application_id,xai_summary,ocean_extraversion,ocean_agreeableness,updated_at',
                'sjtResponses:id,application_id,ai_score,ai_feedback_json,updated_at',
            ])
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->orderByDesc('created_at')
            ->get();

        $eligibleStrategyLabApplicationIds = $applications
            ->filter(static fn (Application $application): bool => StrategyLabController::canAccessStrategyLab($application))
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values();

        $appliedJobIds = $applications->pluck('job_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $openJobsQuery = Job::withoutGlobalScopes()
            ->with('department:id,name')
            ->where('company_id', $company->id)
            ->where('status', Job::STATUS_PUBLISHED);

        $totalOpenJobs = (clone $openJobsQuery)->count();
        $openJobs = $openJobsQuery
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $values = CompanyValue::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        $faqs = FaqItem::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_published', true)
            ->orderBy('category')
            ->orderBy('question')
            ->limit(8)
            ->get();

        $strategyLabBriefs = StrategyLabBrief::withoutGlobalScopes()
            ->with(['application.job:id,title', 'submission', 'aiSummary'])
            ->where('company_id', $company->id)
            ->when(
                $eligibleStrategyLabApplicationIds->isNotEmpty(),
                fn ($query) => $query->whereIn('application_id', $eligibleStrategyLabApplicationIds->all()),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->orderByDesc('created_at')
            ->get();

        $videoAssessments = $this->buildVideoAssessments((string) $company->id, $applications);
        $sjtAssessments = $this->buildSjtAssessments((string) $company->id, $applications);

        $videoByApplication = collect($videoAssessments)->keyBy(
            static fn (array $item): string => (string) data_get($item, 'application.id')
        );
        $sjtByApplication = collect($sjtAssessments)->keyBy(
            static fn (array $item): string => (string) data_get($item, 'application.id')
        );

        $nextSteps = $applications->mapWithKeys(
            fn (Application $application): array => [
                (string) $application->id => $this->resolveNextStep(
                    $application,
                    $videoByApplication->get((string) $application->id),
                    $sjtByApplication->get((string) $application->id)
                ),
            ]
        );

        $statusTrackers = $this->buildStatusTrackers(
            companyId: (string) $company->id,
            applications: $applications,
            nextSteps: $nextSteps
        );
        $xaiInsights = $this->buildXaiInsights($applications);

        $socialHubEligibleApplications = $applications
            ->filter(fn (Application $application): bool => $this->isPreselectedForSocialHub($application))
            ->values();
        $canAccessSocialHub = true;
        $socialHubEligibleCount = $socialHubEligibleApplications->count();
        $socialHubPrimarySource = $socialHubEligibleApplications->first();
        $socialHubPreviewPosts = SocialPost::withoutGlobalScopes()
            ->with('author.profile')
            ->where('company_id', (string) $company->id)
            ->where('visibility', SocialPost::VISIBILITY_PUBLIC)
            ->whereIn('type', $this->candidateVisibleSocialPostTypes())
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();
        $portalNotifications = $this->buildPortalNotifications(
            companyId: (string) $company->id,
            applications: $applications,
            videoAssessments: $videoAssessments,
            sjtAssessments: $sjtAssessments,
            canAccessSocialHub: $canAccessSocialHub
        );
        $reverseFeedbackEligibility = $applications->mapWithKeys(
            fn (Application $application): array => [
                (string) $application->id => $this->isReverseFeedbackEligible($application),
            ]
        );
        $hiredFlowApplications = $applications->mapWithKeys(
            fn (Application $application): array => [
                (string) $application->id => $this->isInHiredFlow($application),
            ]
        );

        return [
            'company' => $company,
            'candidate' => $candidate,
            'applications' => $applications,
            'nextSteps' => $nextSteps,
            'openJobs' => $openJobs,
            'totalOpenJobs' => $totalOpenJobs,
            'appliedJobIds' => $appliedJobIds,
            'values' => $values,
            'faqs' => $faqs,
            'strategyLabBriefs' => $strategyLabBriefs,
            'videoAssessments' => $videoAssessments,
            'sjtAssessments' => $sjtAssessments,
            'statusTrackers' => $statusTrackers,
            'xaiInsights' => $xaiInsights,
            'canAccessSocialHub' => $canAccessSocialHub,
            'socialHubEligibleCount' => $socialHubEligibleCount,
            'socialHubPrimarySource' => $socialHubPrimarySource,
            'socialHubPreviewPosts' => $socialHubPreviewPosts,
            'portalNotifications' => $portalNotifications,
            'reverseFeedbackEligibility' => $reverseFeedbackEligibility,
            'hiredFlowApplications' => $hiredFlowApplications,
        ];
    }

    public function updatePassword(Request $request, Company $company): RedirectResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => __('candidate_portal.security.errors.current_password_required'),
            'password.required' => __('candidate_portal.security.errors.password_required'),
            'password.min' => __('candidate_portal.security.errors.password_min'),
            'password.confirmed' => __('candidate_portal.security.errors.password_confirmed'),
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('candidate_portal.security.errors.current_password_invalid'),
            ]);
        }

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
        ])->save();

        return redirect()
            ->route('candidate.account', ['company' => $company->slug])
            ->with('status', __('candidate_portal.security.password_updated'));
    }

    public function askGuide(Request $request, Company $company): JsonResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            abort(401);
        }
        [$candidate] = $context;

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:600'],
        ]);

        $message = trim((string) $validated['message']);

        if ($this->isBlockedGuidePrompt($message)) {
            return response()->json([
                'ok' => true,
                'refused' => true,
                'answer' => __('candidate_portal.guider.refusal_assessment_answers'),
                'source' => null,
            ]);
        }

        $isStatusIntent = $this->isStatusGuidePrompt($message);
        $isValuesIntent = $this->isValuesGuidePrompt($message);
        $isSalaryIntent = $this->isSalaryGuidePrompt($message);
        $isGuidanceIntent = $this->isGuidanceGuidePrompt($message);
        $isProcessIntent = $this->isProcessGuidePrompt($message);

        $applications = collect();
        $nextSteps = collect();
        if ($isStatusIntent || $isSalaryIntent || $isGuidanceIntent || $isProcessIntent) {
            $applications = $this->loadGuideApplications($company, $candidate);
            if ($applications->isNotEmpty()) {
                $nextSteps = $this->buildGuideNextSteps($company, $applications);
            }
        }

        if ($isStatusIntent) {
            if ($applications->isEmpty()) {
                return response()->json([
                    'ok' => true,
                    'refused' => false,
                    'answer' => __('candidate_portal.guider.status_no_applications'),
                    'source' => null,
                ]);
            }

            return response()->json([
                'ok' => true,
                'refused' => false,
                'answer' => $this->buildGuideStatusAnswer($applications, $nextSteps),
                'source' => [
                    'question' => __('candidate_portal.guider.sources.personal_status'),
                    'category' => __('candidate_portal.guider.sources.portal_data'),
                ],
            ]);
        }

        if ($isValuesIntent) {
            $values = CompanyValue::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->orderBy('display_order')
                ->orderBy('title')
                ->get(['id', 'title', 'description']);

            return response()->json([
                'ok' => true,
                'refused' => false,
                'answer' => $this->buildGuideValuesAnswer($values),
                'source' => [
                    'question' => __('candidate_portal.guider.sources.company_values'),
                    'category' => __('candidate_portal.guider.sources.company_profile'),
                ],
            ]);
        }

        if ($isSalaryIntent) {
            return response()->json([
                'ok' => true,
                'refused' => false,
                'answer' => $this->buildGuideSalaryAnswer($applications),
                'source' => [
                    'question' => __('candidate_portal.guider.sources.salary_visibility'),
                    'category' => __('candidate_portal.guider.sources.portal_data'),
                ],
            ]);
        }

        if ($isGuidanceIntent) {
            return response()->json([
                'ok' => true,
                'refused' => false,
                'answer' => $this->buildGuideGuidanceAnswer($applications, $nextSteps),
                'source' => [
                    'question' => __('candidate_portal.guider.sources.personal_guidance'),
                    'category' => __('candidate_portal.guider.sources.portal_data'),
                ],
            ]);
        }

        if ($isProcessIntent) {
            return response()->json([
                'ok' => true,
                'refused' => false,
                'answer' => $this->buildGuideProcessAnswer($applications, $nextSteps),
                'source' => [
                    'question' => __('candidate_portal.guider.sources.hiring_process'),
                    'category' => __('candidate_portal.guider.sources.portal_data'),
                ],
            ]);
        }

        $faqs = FaqItem::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_published', true)
            ->get(['id', 'category', 'question', 'answer']);

        if ($faqs->isEmpty()) {
            return response()->json([
                'ok' => true,
                'refused' => false,
                'answer' => __('candidate_portal.guider.no_faqs_available'),
                'source' => null,
            ]);
        }

        $match = $this->matchFaq($message, $faqs);
        if (! $match instanceof FaqItem) {
            return response()->json([
                'ok' => true,
                'refused' => false,
                'answer' => __('candidate_portal.guider.no_match_found'),
                'source' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'refused' => false,
            'answer' => (string) $match->answer,
            'source' => [
                'question' => (string) $match->question,
                'category' => (string) $match->category,
            ],
        ]);
    }

    public function storeReverseFeedback(
        Request $request,
        Company $company,
        Application $application
    ): RedirectResponse {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;

        $application = Application::withoutGlobalScopes()
            ->with([
                'currentStage:id,stage_key,stage_label,is_terminal',
                'reverseFeedback',
                'interviews:id,application_id,location_type,meeting_link,status',
                'strategyLabBrief:id,application_id,status',
                'contract:id,application_id,contract_status,signed_at',
                'onboardingTasks:id,application_id,is_completed',
            ])
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->findOrFail($application->id);

        if (! $this->isReverseFeedbackEligible($application)) {
            throw ValidationException::withMessages([
                'feedback' => __('candidate_portal.feedback.only_terminal_allowed'),
            ]);
        }

        $validated = $request->validate([
            'rating_clarity' => ['required', 'integer', 'between:1,5'],
            'rating_speed' => ['required', 'integer', 'between:1,5'],
            'rating_kindness' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'rating_clarity.required' => __('candidate_portal.feedback.validation.rating_clarity_required'),
            'rating_clarity.between' => __('candidate_portal.feedback.validation.rating_between'),
            'rating_speed.required' => __('candidate_portal.feedback.validation.rating_speed_required'),
            'rating_speed.between' => __('candidate_portal.feedback.validation.rating_between'),
            'rating_kindness.required' => __('candidate_portal.feedback.validation.rating_kindness_required'),
            'rating_kindness.between' => __('candidate_portal.feedback.validation.rating_between'),
            'comment.max' => __('candidate_portal.feedback.validation.comment_max'),
        ]);

        $comment = trim((string) ($validated['comment'] ?? ''));
        $isAnonymous = true;

        $feedback = ReverseFeedback::withoutGlobalScopes()->firstOrCreate(
            ['application_id' => (string) $application->id],
            [
                'company_id' => (string) $company->id,
                'recruiter_user_id' => null,
                'rating_clarity' => (int) $validated['rating_clarity'],
                'rating_speed' => (int) $validated['rating_speed'],
                'rating_kindness' => (int) $validated['rating_kindness'],
                'comment' => $comment !== '' ? $comment : null,
                'is_anonymous' => $isAnonymous,
                'created_at' => now(),
            ]
        );

        if (! $feedback->wasRecentlyCreated) {
            return redirect()
                ->route('candidate.applications', ['company' => $company->slug])
                ->with('status', __('candidate_portal.feedback.already_submitted'));
        }

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'application_id' => (string) $application->id,
            'event_type' => 'candidate.reverse_feedback_submitted',
            'payload' => [
                'reverse_feedback_id' => (string) $feedback->id,
                'rating_clarity' => (int) $feedback->rating_clarity,
                'rating_speed' => (int) $feedback->rating_speed,
                'rating_kindness' => (int) $feedback->rating_kindness,
                'is_anonymous' => (bool) $feedback->is_anonymous,
            ],
            'actor_user_id' => (string) optional($request->user())->id,
            'created_at' => now(),
        ]);

        $actor = $request->user();
        $this->sensitiveEvents->record(
            actionType: 'candidate.reverse_feedback_submitted',
            entityType: 'reverse_feedback',
            entityId: (string) $feedback->id,
            metadata: [
                'application_id' => (string) $application->id,
                'rating_clarity' => (int) $feedback->rating_clarity,
                'rating_speed' => (int) $feedback->rating_speed,
                'rating_kindness' => (int) $feedback->rating_kindness,
                'is_anonymous' => true,
            ],
            actor: $actor instanceof User ? $actor : null
        );

        return redirect()
            ->route('candidate.applications', ['company' => $company->slug])
            ->with('status', __('candidate_portal.feedback.submitted'));
    }

    public function signContract(Request $request, Company $company, Application $application): RedirectResponse
    {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;

        $application = Application::withoutGlobalScopes()
            ->with(['candidate', 'company', 'job', 'contract'])
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->findOrFail($application->id);

        if (! $this->isInHiredFlow($application)) {
            return redirect()
                ->route('candidate.applications', ['company' => $company->slug])
                ->with('error', __('candidate_portal.onboarding.errors.hired_required'));
        }

        $contract = $application->contract;
        if (! $contract instanceof Contract) {
            return redirect()
                ->route('candidate.applications', ['company' => $company->slug])
                ->with('error', __('candidate_portal.onboarding.errors.contract_missing'));
        }

        if ($contract->contract_status === Contract::STATUS_SIGNED || $contract->signed_at !== null) {
            return redirect()
                ->route('candidate.applications', ['company' => $company->slug])
                ->with('status', __('candidate_portal.onboarding.contract.already_signed'));
        }

        $validated = $request->validate([
            'typed_signature' => ['required', 'string', 'max:255'],
            'acknowledgement' => ['accepted'],
        ], [
            'typed_signature.required' => __('candidate_portal.onboarding.contract.validation.signature_required'),
            'acknowledgement.accepted' => __('candidate_portal.onboarding.contract.validation.ack_required'),
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $signedAt = now();
        $audit = (array) ($contract->audit_metadata_json ?? []);
        $audit['signature'] = [
            'typed_signature' => trim((string) $validated['typed_signature']),
            'acknowledged' => true,
            'signed_at' => $signedAt->toIso8601String(),
            'signer_user_id' => (string) $user->id,
            'ip_address' => (string) ($request->ip() ?? ''),
            'user_agent' => Str::limit((string) ($request->userAgent() ?? ''), 1024, ''),
        ];

        $contract->forceFill([
            'contract_status' => Contract::STATUS_SIGNED,
            'signed_at' => $signedAt,
            'signer_user_id' => (string) $user->id,
            'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
            'audit_metadata_json' => $audit,
        ])->save();

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $application->company_id,
            'application_id' => (string) $application->id,
            'event_type' => 'contract.signed',
            'payload' => [
                'contract_id' => (string) $contract->id,
                'signed_at' => $signedAt->toIso8601String(),
                'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
            ],
            'actor_user_id' => (string) $user->id,
            'created_at' => now(),
        ]);

        $this->sensitiveEvents->contractSigned((string) $contract->id, [
            'application_id' => (string) $application->id,
            'signed_at' => $signedAt->toIso8601String(),
            'signer_user_id' => (string) $user->id,
            'ip_address' => (string) ($request->ip() ?? ''),
        ], $user);

        $emailError = $this->queueOnboardingWelcomeEmail($application, $user);
        try {
            $this->socialHubService->createContractWelcomePost($application, $user);
        } catch (\Throwable $exception) {
            Log::warning('Unable to create automated social welcome post after contract signing.', [
                'application_id' => (string) $application->id,
                'company_id' => (string) $application->company_id,
                'error' => $exception->getMessage(),
            ]);
        }

        $redirect = redirect()
            ->route('candidate.applications', ['company' => $company->slug])
            ->with('status', __('candidate_portal.onboarding.contract.signed_success'))
            ->with('onboarding_confetti', true);

        if ($emailError !== null) {
            $redirect->with('error', $emailError);
        }

        return $redirect;
    }

    public function uploadOnboardingDocument(
        Request $request,
        Company $company,
        Application $application
    ): RedirectResponse {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;

        $application = Application::withoutGlobalScopes()
            ->with('candidate')
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->findOrFail($application->id);

        if (! $this->isInHiredFlow($application)) {
            return redirect()
                ->route('candidate.applications', ['company' => $company->slug])
                ->with('error', __('candidate_portal.onboarding.errors.hired_required'));
        }

        $validated = $request->validate([
            'doc_type' => ['required', Rule::in(OnboardingDocument::types())],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:10240'],
        ]);

        $existingDocument = OnboardingDocument::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->where('doc_type', (string) $validated['doc_type'])
            ->exists();

        if ($existingDocument) {
            return redirect()
                ->route('candidate.applications', ['company' => $company->slug])
                ->with('status', __('candidate_portal.onboarding.documents.already_uploaded'));
        }

        $file = $request->file('file');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $fileName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
        $filePath = $file->storeAs(
            'private/onboarding/documents/'.$company->id.'/'.$application->id,
            $fileName,
            'local'
        );

        $document = OnboardingDocument::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'application_id' => (string) $application->id,
            'doc_type' => (string) $validated['doc_type'],
            'file_url' => $filePath,
            'created_at' => now(),
        ]);

        $actor = $request->user();
        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $application->company_id,
            'application_id' => (string) $application->id,
            'event_type' => 'onboarding.document_uploaded',
            'payload' => [
                'onboarding_document_id' => (string) $document->id,
                'doc_type' => (string) $document->doc_type,
            ],
            'actor_user_id' => $actor instanceof User ? (string) $actor->id : null,
            'created_at' => now(),
        ]);

        $this->sensitiveEvents->record(
            actionType: 'onboarding.document_uploaded',
            entityType: 'onboarding_document',
            entityId: (string) $document->id,
            metadata: [
                'application_id' => (string) $application->id,
                'doc_type' => (string) $document->doc_type,
            ],
            actor: $actor instanceof User ? $actor : null
        );

        return redirect()
            ->route('candidate.applications', ['company' => $company->slug])
            ->with('status', __('candidate_portal.onboarding.documents.uploaded'));
    }

    public function toggleOnboardingTask(
        Request $request,
        Company $company,
        Application $application,
        OnboardingTask $onboardingTask
    ): RedirectResponse {
        $context = $this->resolveCandidateContext($request, $company);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$candidate] = $context;

        $application = Application::withoutGlobalScopes()
            ->with('candidate')
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->findOrFail($application->id);

        if (! $this->isInHiredFlow($application)) {
            return redirect()
                ->route('candidate.applications', ['company' => $company->slug])
                ->with('error', __('candidate_portal.onboarding.errors.hired_required'));
        }

        $task = OnboardingTask::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('application_id', $application->id)
            ->findOrFail($onboardingTask->id);

        $isCompleted = ! (bool) $task->is_completed;
        $task->forceFill(['is_completed' => $isCompleted])->save();

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $application->company_id,
            'application_id' => (string) $application->id,
            'event_type' => 'onboarding.task_toggled',
            'payload' => [
                'onboarding_task_id' => (string) $task->id,
                'is_completed' => $isCompleted,
            ],
            'actor_user_id' => (string) optional($request->user())->id,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('candidate.applications', ['company' => $company->slug])
            ->with('status', $isCompleted
                ? __('candidate_portal.onboarding.tasks.updated_done')
                : __('candidate_portal.onboarding.tasks.updated_open'));
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

    private function queueOnboardingWelcomeEmail(Application $application, ?User $actor): ?string
    {
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

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $application->company_id,
            'application_id' => (string) $application->id,
            'event_type' => 'email.sent',
            'payload' => $payload,
            'actor_user_id' => $actor?->id,
            'created_at' => now(),
        ]);

        $this->sensitiveEvents->emailSent($messageId, $payload, $actor);

        return null;
    }

    private function resolveCandidateLocale(Application $application): string
    {
        $locale = Str::lower((string) ($application->candidate?->user?->profile?->locale ?? config('app.locale', 'en')));
        return in_array($locale, ['en', 'fr'], true) ? $locale : 'en';
    }

    /**
     * @return array{0: Candidate}|RedirectResponse
     */
    private function resolveCandidateContext(Request $request, Company $company): array|RedirectResponse
    {
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);

        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $hasCandidateMembership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', CompanyMembership::ROLE_CANDIDATE)
            ->exists();

        abort_unless($hasCandidateMembership, 403);

        $normalizedEmail = Str::lower((string) $user->email);
        $fallbackFullName = trim((string) ($user->profile?->full_name ?? Str::before($normalizedEmail, '@')));
        $fallbackFullName = $fallbackFullName !== '' ? $fallbackFullName : 'Candidate';

        $candidate = Candidate::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $candidate instanceof Candidate) {
            $candidate = Candidate::withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => (string) $company->id,
                    'email' => $normalizedEmail,
                ],
                [
                    'user_id' => (string) $user->id,
                    'full_name' => $fallbackFullName,
                    'phone' => null,
                    'location' => null,
                ]
            );
        }

        if ((string) ($candidate->user_id ?? '') !== (string) $user->id) {
            $candidate->forceFill([
                'user_id' => (string) $user->id,
            ])->save();
        }

        session(['active_company_id' => (string) $company->id]);

        return [$candidate];
    }

    /**
     * @param Collection<int, Application> $applications
     * @param Collection<string, string> $nextSteps
     * @return Collection<string, array{
     *   application_id: string,
     *   job_title: string,
     *   stage_label: string,
     *   status: string,
     *   updated_at: string,
     *   updated_human: string,
     *   steps: array<int, array{
     *     key: string,
     *     label: string,
     *     state: string,
     *     state_label: string,
     *     detail: string
     *   }>
     * }>
     */
    private function buildStatusTrackers(string $companyId, Collection $applications, Collection $nextSteps): Collection
    {
        if ($applications->isEmpty()) {
            return collect();
        }

        $applicationIds = $applications
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        $latestAnalysisByApplication = $this->latestAnalysisRequestsByApplication(
            companyId: $companyId,
            applicationIds: $applicationIds
        );

        return $applications->mapWithKeys(function (Application $application) use ($nextSteps, $latestAnalysisByApplication): array {
            $applicationId = (string) $application->id;
            $nextStep = (string) $nextSteps->get($applicationId, __('candidate_portal.applications.next_step_default'));
            $updatedAt = $application->updated_at ?? $application->created_at ?? now();

            return [
                $applicationId => [
                    'application_id' => $applicationId,
                    'job_title' => (string) ($application->job?->title ?? __('sjt.messages.unknown_job')),
                    'stage_label' => (string) ($application->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage')),
                    'status' => (string) $application->status,
                    'updated_at' => $updatedAt->toIso8601String(),
                    'updated_human' => $updatedAt->diffForHumans(),
                    'steps' => $this->buildStatusStepsForApplication(
                        application: $application,
                        nextStep: $nextStep,
                        latestAnalysisRequest: $latestAnalysisByApplication->get($applicationId)
                    ),
                ],
            ];
        });
    }

    /**
     * @param array<int, string> $applicationIds
     * @return Collection<string, AiRequest>
     */
    private function latestAnalysisRequestsByApplication(string $companyId, array $applicationIds): Collection
    {
        if ($applicationIds === []) {
            return collect();
        }

        return AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('request_type', ['candidate_analysis', 'async_video_unified_report'])
            ->where(function ($query) use ($applicationIds): void {
                foreach ($applicationIds as $applicationId) {
                    $query->orWhere('request_payload->application_id', (string) $applicationId);
                }
            })
            ->orderByDesc('created_at')
            ->get(['id', 'status', 'request_type', 'request_payload', 'created_at'])
            ->groupBy(static fn (AiRequest $request): string => (string) data_get($request->request_payload, 'application_id'))
            ->map(static fn (Collection $items): ?AiRequest => $items->first())
            ->filter(static fn ($request): bool => $request instanceof AiRequest);
    }

    /**
     * @return array<int, array{
     *   key: string,
     *   label: string,
     *   state: string,
     *   state_label: string,
     *   detail: string
     * }>
     */
    private function buildStatusStepsForApplication(
        Application $application,
        string $nextStep,
        ?AiRequest $latestAnalysisRequest
    ): array {
        $now = now();
        $interviews = $application->interviews ?? collect();

        $hasInterviewScheduled = $interviews->contains(
            static fn (Interview $interview): bool => in_array(
                (string) $interview->status,
                [Interview::STATUS_SCHEDULED, Interview::STATUS_COMPLETED],
                true
            )
        );
        $hasInterviewCompleted = $interviews->contains(
            static fn (Interview $interview): bool => (string) $interview->status === Interview::STATUS_COMPLETED
        );

        $interviewInProgress = $interviews->contains(function (Interview $interview) use ($now): bool {
            if ((string) $interview->status !== Interview::STATUS_SCHEDULED || $interview->scheduled_start_at === null) {
                return false;
            }

            $start = $interview->scheduled_start_at->copy();
            $end = $interview->scheduled_end_at instanceof \Illuminate\Support\Carbon
                ? $interview->scheduled_end_at->copy()
                : $start->copy()->addMinutes(60);

            return $start->lessThanOrEqualTo($now) && $end->greaterThan($now);
        });

        $upcomingInterview = $interviews->first(function (Interview $interview): bool {
            return (string) $interview->status === Interview::STATUS_SCHEDULED
                && $interview->scheduled_start_at !== null
                && $interview->scheduled_start_at->isFuture();
        });

        $analysisStatus = (string) ($latestAnalysisRequest?->status ?? '');
        $analysisPending = in_array($analysisStatus, [AiRequest::STATUS_QUEUED, AiRequest::STATUS_RUNNING], true);

        $summaryText = trim((string) ($application->scoring?->xai_summary ?? ''));
        $hasScoringSummary = $summaryText !== '' && Str::lower($summaryText) !== 'not scored yet.';
        $analysisCompleted = $hasScoringSummary || $analysisStatus === AiRequest::STATUS_SUCCEEDED;

        $normalizedStatus = Str::lower(trim((string) $application->status));
        $isRejected = in_array($normalizedStatus, [Application::STATUS_REJECTED, Application::STATUS_WITHDRAWN], true);
        $isShortlisted = ! $isRejected && $this->isShortlistedApplication($application);
        $hasFinalOutcome = $isRejected || $isShortlisted;

        $outcomeLabel = $isRejected
            ? __('candidate_portal.status_tracker.outcomes.rejected')
            : ($isShortlisted
                ? __('candidate_portal.status_tracker.outcomes.shortlisted')
                : __('candidate_portal.status_tracker.outcomes.next_step'));

        $currentStep = 'application_received';
        if ($hasInterviewScheduled) {
            $currentStep = 'interview_scheduled';
        }
        if ($interviewInProgress) {
            $currentStep = 'interview_in_progress';
        }
        if ($analysisPending) {
            $currentStep = 'under_analysis';
        }
        if ($hasFinalOutcome || ($analysisCompleted && ! $analysisPending && ! $interviewInProgress)) {
            $currentStep = 'outcome';
        }

        $stepState = static function (string $key, bool $completed, string $currentStep): string {
            if ($key === $currentStep) {
                return 'current';
            }

            return $completed ? 'completed' : 'pending';
        };

        $formatInterviewDate = static function (?Interview $interview): string {
            if (! $interview instanceof Interview || $interview->scheduled_start_at === null) {
                return __('candidate_portal.applications.not_scheduled');
            }

            return $interview->scheduled_start_at
                ->timezone((string) ($interview->timezone ?: 'UTC'))
                ->format('Y-m-d H:i');
        };

        return [
            [
                'key' => 'application_received',
                'label' => __('candidate_portal.status_tracker.steps.application_received'),
                'state' => $stepState('application_received', true, $currentStep),
                'state_label' => __('candidate_portal.status_tracker.states.'.$stepState('application_received', true, $currentStep)),
                'detail' => __('candidate_portal.status_tracker.details.application_received', [
                    'date' => ($application->created_at ?? now())->format('Y-m-d'),
                ]),
            ],
            [
                'key' => 'interview_scheduled',
                'label' => __('candidate_portal.status_tracker.steps.interview_scheduled'),
                'state' => $stepState('interview_scheduled', $hasInterviewScheduled || $interviewInProgress || $hasInterviewCompleted, $currentStep),
                'state_label' => __('candidate_portal.status_tracker.states.'.$stepState('interview_scheduled', $hasInterviewScheduled || $interviewInProgress || $hasInterviewCompleted, $currentStep)),
                'detail' => $upcomingInterview instanceof Interview
                    ? __('candidate_portal.status_tracker.details.interview_scheduled', [
                        'date' => $formatInterviewDate($upcomingInterview),
                    ])
                    : ($hasInterviewCompleted
                        ? __('candidate_portal.status_tracker.details.interview_completed')
                        : __('candidate_portal.status_tracker.details.interview_pending')),
            ],
            [
                'key' => 'interview_in_progress',
                'label' => __('candidate_portal.status_tracker.steps.interview_in_progress'),
                'state' => $stepState('interview_in_progress', $hasInterviewCompleted, $currentStep),
                'state_label' => __('candidate_portal.status_tracker.states.'.$stepState('interview_in_progress', $hasInterviewCompleted, $currentStep)),
                'detail' => $interviewInProgress
                    ? __('candidate_portal.status_tracker.details.interview_in_progress')
                    : ($hasInterviewCompleted
                        ? __('candidate_portal.status_tracker.details.interview_finished')
                        : __('candidate_portal.status_tracker.details.interview_waiting')),
            ],
            [
                'key' => 'under_analysis',
                'label' => __('candidate_portal.status_tracker.steps.under_analysis'),
                'state' => $stepState('under_analysis', $analysisCompleted, $currentStep),
                'state_label' => __('candidate_portal.status_tracker.states.'.$stepState('under_analysis', $analysisCompleted, $currentStep)),
                'detail' => $analysisPending
                    ? __('candidate_portal.status_tracker.details.analysis_running')
                    : ($analysisCompleted
                        ? __('candidate_portal.status_tracker.details.analysis_complete')
                        : __('candidate_portal.status_tracker.details.analysis_not_started')),
            ],
            [
                'key' => 'outcome',
                'label' => __('candidate_portal.status_tracker.steps.outcome', ['outcome' => $outcomeLabel]),
                'state' => $stepState('outcome', $hasFinalOutcome, $currentStep),
                'state_label' => __('candidate_portal.status_tracker.states.'.$stepState('outcome', $hasFinalOutcome, $currentStep)),
                'detail' => $isRejected
                    ? __('candidate_portal.status_tracker.details.outcome_rejected')
                    : ($isShortlisted
                        ? __('candidate_portal.status_tracker.details.outcome_shortlisted')
                        : __('candidate_portal.status_tracker.details.outcome_next_step', ['next_step' => $nextStep])),
            ],
        ];
    }

    private function isShortlistedApplication(Application $application): bool
    {
        if ($this->isInHiredFlow($application)) {
            return true;
        }

        $stageText = Str::lower(trim(implode(' ', [
            (string) ($application->currentStage?->stage_key ?? ''),
            (string) ($application->currentStage?->stage_label ?? ''),
        ])));

        return Str::contains($stageText, [
            'shortlist',
            'shortlisted',
            'offer',
            'hired',
            'high_priority',
            'high-priority',
            'top10',
            'top_10',
            'finalist',
        ]);
    }

    /**
     * @param Collection<int, Application> $applications
     * @return Collection<string, array{
     *   score: ?float,
     *   summary: string,
     *   reasons: array<int, string>,
     *   updated_at: string,
     *   updated_human: string
     * }>
     */
    private function buildXaiInsights(Collection $applications): Collection
    {
        if ($applications->isEmpty()) {
            return collect();
        }

        return $applications->mapWithKeys(function (Application $application): array {
            $applicationId = (string) $application->id;
            $scoring = $application->scoring;
            $report = $application->unifiedInterviewReport;

            $summary = trim((string) ($scoring?->xai_summary ?? $report?->xai_summary ?? ''));
            if ($summary === '' || Str::lower($summary) === 'not scored yet.') {
                $summary = __('candidate_portal.xai.pending_summary');
            }

            $score = null;
            if (is_numeric($scoring?->global_match_score)) {
                $score = round((float) $scoring->global_match_score, 1);
            }

            $sjtResponses = $application->relationLoaded('sjtResponses')
                ? $application->sjtResponses
                : $application->sjtResponses()
                    ->get(['id', 'application_id', 'ai_score', 'ai_feedback_json', 'updated_at']);
            $scoredSjtResponses = $sjtResponses->filter(
                static fn (SjtResponse $response): bool => is_numeric($response->ai_score)
            );
            $averageSjtScore = $scoredSjtResponses->isNotEmpty()
                ? round((float) $scoredSjtResponses->avg(
                    static fn (SjtResponse $response): float => (float) $response->ai_score
                ), 1)
                : null;

            if (! is_numeric($score) && is_numeric($averageSjtScore)) {
                $score = (float) $averageSjtScore;
            }

            $analysisStatus = (string) ($scoring?->analysis_status ?? CandidateAnalysisService::ANALYSIS_PENDING);
            $sourceStatuses = is_array($scoring?->source_status_json ?? null)
                ? $scoring->source_status_json
                : [];
            $cvSourceStatus = (string) data_get($sourceStatuses, 'cv', 'pending_input');

            $vrin = (array) ($scoring?->vrin_json ?? []);
            $acquired = collect((array) data_get($vrin, 'acquired_skills', []))
                ->map(static fn ($value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values();
            $missing = collect((array) data_get($vrin, 'missing_skills', []))
                ->map(static fn ($value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values();

            $reasons = collect();
            $latestSjtResponse = $scoredSjtResponses
                ->sortByDesc(static fn (SjtResponse $response): int => $response->updated_at?->timestamp ?? 0)
                ->first();

            if ($analysisStatus === CandidateAnalysisService::ANALYSIS_INVALID_CV) {
                $reasons->push(__('candidate_portal.xai.reasons.invalid_cv'));
            } else {
                if ($missing->isNotEmpty()) {
                    $reasons->push(__('candidate_portal.xai.reasons.missing_skills', [
                        'skills' => $missing->take(2)->implode(', '),
                    ]));
                }
                if ($acquired->isNotEmpty()) {
                    $reasons->push(__('candidate_portal.xai.reasons.acquired_skills', [
                        'skills' => $acquired->take(2)->implode(', '),
                    ]));
                }

                if ($latestSjtResponse instanceof SjtResponse) {
                    $normalizeSignal = static function (mixed $value): string {
                        $normalized = Str::lower(trim((string) $value));
                        return in_array($normalized, ['high', 'medium', 'low'], true) ? $normalized : 'medium';
                    };

                    $signalLabel = static fn (string $signal): string => match ($signal) {
                        'high' => 'strong',
                        'low' => 'needs reinforcement',
                        default => 'balanced',
                    };

                    $reasons->push(__('candidate_portal.xai.reasons.sjt_signal', [
                        'accountability' => $signalLabel($normalizeSignal(data_get($latestSjtResponse->ai_feedback_json, 'signals.accountability'))),
                        'solution' => $signalLabel($normalizeSignal(data_get($latestSjtResponse->ai_feedback_json, 'signals.solution_orientation'))),
                        'tone' => $signalLabel($normalizeSignal(data_get($latestSjtResponse->ai_feedback_json, 'signals.tone'))),
                    ]));
                }

                if (is_numeric($score)) {
                    if ((float) $score >= 75) {
                        $reasons->push(__('candidate_portal.xai.reasons.alignment_high'));
                    } elseif ((float) $score >= 55) {
                        $reasons->push(__('candidate_portal.xai.reasons.alignment_medium'));
                    } else {
                        $reasons->push(__('candidate_portal.xai.reasons.alignment_low'));
                    }
                }

                $communicationSignals = collect([
                    is_numeric($report?->ocean_extraversion) ? (float) $report->ocean_extraversion : null,
                    is_numeric($report?->ocean_agreeableness) ? (float) $report->ocean_agreeableness : null,
                ])->filter(static fn ($value): bool => $value !== null);

                if ($communicationSignals->count() >= 2) {
                    $communicationAverage = (float) $communicationSignals->avg();
                    if ($communicationAverage >= 65) {
                        $reasons->push(__('candidate_portal.xai.reasons.communication_strong'));
                    } elseif ($communicationAverage >= 45) {
                        $reasons->push(__('candidate_portal.xai.reasons.communication_balanced'));
                    }
                }

                if ($reasons->isEmpty()) {
                    $reasons->push(__('candidate_portal.xai.reasons.pending'));
                }
            }

            $updatedAt = collect([
                $scoring?->updated_at,
                $report?->updated_at,
                $latestSjtResponse?->updated_at,
                $application->updated_at,
            ])->filter()->sortByDesc(
                static fn ($value): int => $value?->timestamp ?? 0
            )->first() ?? now();

            return [
                $applicationId => [
                    'score' => $score,
                    'summary' => $summary,
                    'reasons' => $reasons->take(3)->values()->all(),
                    'analysis_status' => $analysisStatus,
                    'analysis_status_label' => CandidateAnalysisService::analysisStatusLabel($analysisStatus),
                    'cv_source_status' => $cvSourceStatus,
                    'cv_source_status_label' => CandidateAnalysisService::sourceStatusLabel($cvSourceStatus, 'cv'),
                    'updated_at' => $updatedAt->toIso8601String(),
                    'updated_human' => $updatedAt->diffForHumans(),
                ],
            ];
        });
    }

    /**
     * @return Collection<int, Application>
     */
    private function loadGuideApplications(Company $company, Candidate $candidate): Collection
    {
        return Application::withoutGlobalScopes()
            ->with([
                'job:id,title',
                'currentStage:id,stage_label,stage_key,is_terminal',
                'interviews:id,application_id,status,scheduled_start_at,scheduled_end_at,timezone',
                'strategyLabBrief' => fn ($query) => $query->with('submission'),
                'contract',
                'onboardingTasks',
                'reverseFeedback',
                'offer:id,application_id,offer_status,salary_amount,currency,start_date',
            ])
            ->where('company_id', $company->id)
            ->where('candidate_id', $candidate->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param Collection<int, Application> $applications
     * @return Collection<string, string>
     */
    private function buildGuideNextSteps(Company $company, Collection $applications): Collection
    {
        if ($applications->isEmpty()) {
            return collect();
        }

        $videoAssessments = $this->buildVideoAssessments((string) $company->id, $applications);
        $sjtAssessments = $this->buildSjtAssessments((string) $company->id, $applications);
        $videoByApplication = collect($videoAssessments)->keyBy(
            static fn (array $item): string => (string) data_get($item, 'application.id')
        );
        $sjtByApplication = collect($sjtAssessments)->keyBy(
            static fn (array $item): string => (string) data_get($item, 'application.id')
        );

        return $applications->mapWithKeys(
            fn (Application $application): array => [
                (string) $application->id => $this->resolveNextStep(
                    $application,
                    $videoByApplication->get((string) $application->id),
                    $sjtByApplication->get((string) $application->id)
                ),
            ]
        );
    }

    private function isStatusGuidePrompt(string $message): bool
    {
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/next\s+step|where\s+am\s+i|status|progress|update|timeline|my\s+application|application\s+status|interview\s+status|shortlist|shortlisted|rejected/',
            $normalized
        );
    }

    private function isValuesGuidePrompt(string $message): bool
    {
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/company\s+values?|core\s+values?|culture|mission|vision|principles|what\s+matters/',
            $normalized
        );
    }

    private function isSalaryGuidePrompt(string $message): bool
    {
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/salary|compensation|pay(?:\s+range)?|package|wage|ctc|offer\s+amount/',
            $normalized
        );
    }

    private function isGuidanceGuidePrompt(string $message): bool
    {
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/recommend|recommendation|advice|guidance|tips|improve|prepare|what\s+should\s+i\s+do|how\s+can\s+i/',
            $normalized
        );
    }

    private function isProcessGuidePrompt(string $message): bool
    {
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match(
            '/strategy\s*lab|interview\s+process|hiring\s+process|recruitment\s+process|portal|how\s+does|what\s+happens\s+next|overall\s+process/',
            $normalized
        );
    }

    /**
     * @param Collection<int, Application> $applications
     * @param Collection<string, string> $nextSteps
     */
    private function buildGuideStatusAnswer(Collection $applications, Collection $nextSteps): string
    {
        $parts = [__('candidate_portal.guider.status_intro')];

        foreach ($applications->take(5) as $application) {
            $jobTitle = (string) ($application->job?->title ?? __('sjt.messages.unknown_job'));
            $stageLabel = (string) ($application->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage'));
            $nextStep = (string) $nextSteps->get((string) $application->id, __('candidate_portal.applications.next_step_default'));

            $line = __('candidate_portal.guider.status_line', [
                'job' => $jobTitle,
                'stage' => $stageLabel,
                'next_step' => $nextStep,
            ]);

            $upcomingInterview = $application->interviews?->first(function (Interview $interview): bool {
                return (string) $interview->status === Interview::STATUS_SCHEDULED
                    && $interview->scheduled_start_at !== null
                    && $interview->scheduled_start_at->isFuture();
            });

            if ($upcomingInterview instanceof Interview) {
                $scheduledFor = $upcomingInterview->scheduled_start_at
                    ? $upcomingInterview->scheduled_start_at
                        ->timezone((string) ($upcomingInterview->timezone ?: 'UTC'))
                        ->format('Y-m-d H:i')
                    : __('candidate_portal.applications.not_scheduled');
                $line .= ' '.__('candidate_portal.guider.status_interview_line', ['date' => $scheduledFor]);
            }

            $parts[] = $line;
        }

        $remaining = $applications->count() - min(5, $applications->count());
        if ($remaining > 0) {
            $parts[] = __('candidate_portal.guider.status_more', ['count' => $remaining]);
        }

        $parts[] = __('candidate_portal.guider.status_footer');

        return implode(' ', $parts);
    }

    /**
     * @param Collection<int, CompanyValue> $values
     */
    private function buildGuideValuesAnswer(Collection $values): string
    {
        if ($values->isEmpty()) {
            return __('candidate_portal.guider.values_not_available');
        }

        $parts = [__('candidate_portal.guider.values_intro')];

        foreach ($values->take(4) as $value) {
            $title = trim((string) $value->title);
            if ($title === '') {
                continue;
            }

            $description = trim((string) ($value->description ?? ''));
            if ($description !== '') {
                $parts[] = __('candidate_portal.guider.values_line_with_description', [
                    'title' => $title,
                    'description' => Str::limit($description, 140),
                ]);
                continue;
            }

            $parts[] = __('candidate_portal.guider.values_line', ['title' => $title]);
        }

        $remaining = $values->count() - min(4, $values->count());
        if ($remaining > 0) {
            $parts[] = __('candidate_portal.guider.values_more', ['count' => $remaining]);
        }

        return implode(' ', $parts);
    }

    /**
     * @param Collection<int, Application> $applications
     */
    private function buildGuideSalaryAnswer(Collection $applications): string
    {
        if ($applications->isEmpty()) {
            return __('candidate_portal.guider.salary_not_available');
        }

        $eligibleOffers = $applications
            ->filter(function (Application $application): bool {
                $offer = $application->offer;
                if (! $offer instanceof Offer) {
                    return false;
                }

                if (! in_array((string) $offer->offer_status, [Offer::STATUS_SENT, Offer::STATUS_ACCEPTED], true)) {
                    return false;
                }

                if ($offer->salary_amount === null) {
                    return false;
                }

                return trim((string) $offer->currency) !== '';
            })
            ->values();

        if ($eligibleOffers->isEmpty()) {
            return __('candidate_portal.guider.salary_not_available');
        }

        $parts = [__('candidate_portal.guider.salary_intro')];

        foreach ($eligibleOffers->take(3) as $application) {
            $offer = $application->offer;
            if (! $offer instanceof Offer) {
                continue;
            }

            $statusKey = 'candidate_portal.guider.salary_statuses.'.(string) $offer->offer_status;
            $statusLabel = __($statusKey);
            if ($statusLabel === $statusKey) {
                $statusLabel = (string) $offer->offer_status;
            }

            $parts[] = __('candidate_portal.guider.salary_line', [
                'job' => (string) ($application->job?->title ?? __('sjt.messages.unknown_job')),
                'amount' => number_format((float) $offer->salary_amount, 2, '.', ','),
                'currency' => trim((string) $offer->currency),
                'status' => $statusLabel,
            ]);
        }

        $remaining = $eligibleOffers->count() - min(3, $eligibleOffers->count());
        if ($remaining > 0) {
            $parts[] = __('candidate_portal.guider.salary_more', ['count' => $remaining]);
        }

        $parts[] = __('candidate_portal.guider.salary_footer');

        return implode(' ', $parts);
    }

    /**
     * @param Collection<int, Application> $applications
     * @param Collection<string, string> $nextSteps
     */
    private function buildGuideGuidanceAnswer(Collection $applications, Collection $nextSteps): string
    {
        if ($applications->isEmpty()) {
            return __('candidate_portal.guider.guidance_no_applications');
        }

        /** @var Application $primary */
        $primary = $applications->first();
        $primaryId = (string) $primary->id;
        $jobTitle = (string) ($primary->job?->title ?? __('sjt.messages.unknown_job'));
        $nextStep = (string) $nextSteps->get($primaryId, __('candidate_portal.applications.next_step_default'));

        $parts = [
            __('candidate_portal.guider.guidance_intro', [
                'job' => $jobTitle,
                'next_step' => $nextStep,
            ]),
        ];

        $upcomingInterview = $primary->interviews?->first(function (Interview $interview): bool {
            return (string) $interview->status === Interview::STATUS_SCHEDULED
                && $interview->scheduled_start_at !== null
                && $interview->scheduled_start_at->isFuture();
        });
        if ($upcomingInterview instanceof Interview) {
            $parts[] = __('candidate_portal.guider.guidance_tip_interview');
        } elseif (
            $primary->strategyLabBrief instanceof StrategyLabBrief
            && $primary->strategyLabBrief->submission === null
            && StrategyLabController::canAccessStrategyLab($primary)
        ) {
            $parts[] = __('candidate_portal.guider.guidance_tip_strategy_lab');
        } elseif ($this->isInHiredFlow($primary)) {
            $parts[] = __('candidate_portal.guider.guidance_tip_onboarding');
        } else {
            $parts[] = __('candidate_portal.guider.guidance_tip_general');
        }

        $parts[] = __('candidate_portal.guider.guidance_footer');

        return implode(' ', $parts);
    }

    /**
     * @param Collection<int, Application> $applications
     * @param Collection<string, string> $nextSteps
     */
    private function buildGuideProcessAnswer(Collection $applications, Collection $nextSteps): string
    {
        if ($applications->isEmpty()) {
            return __('candidate_portal.guider.process_no_applications');
        }

        /** @var Application $primary */
        $primary = $applications->first();
        $jobTitle = (string) ($primary->job?->title ?? __('sjt.messages.unknown_job'));
        $stageLabel = (string) ($primary->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage'));
        $nextStep = (string) $nextSteps->get((string) $primary->id, __('candidate_portal.applications.next_step_default'));

        $parts = [
            __('candidate_portal.guider.process_intro'),
            __('candidate_portal.guider.process_line', [
                'job' => $jobTitle,
                'stage' => $stageLabel,
                'next_step' => $nextStep,
            ]),
        ];

        $strategyBrief = $primary->strategyLabBrief;
        if ($strategyBrief instanceof StrategyLabBrief) {
            if ($strategyBrief->submission !== null) {
                $parts[] = __('candidate_portal.guider.process_strategy_submitted');
            } elseif ($strategyBrief->deadline_at !== null) {
                $parts[] = __('candidate_portal.guider.process_strategy_deadline', [
                    'deadline' => $strategyBrief->deadline_at->format('Y-m-d H:i'),
                ]);
            } else {
                $parts[] = __('candidate_portal.guider.process_strategy_assigned');
            }
        } elseif (StrategyLabController::canAccessStrategyLab($primary)) {
            $parts[] = __('candidate_portal.guider.process_strategy_available');
        } else {
            $parts[] = __('candidate_portal.guider.process_strategy_locked', [
                'reason' => StrategyLabController::strategyLabEligibilityError($primary),
            ]);
        }

        $parts[] = __('candidate_portal.guider.process_footer');

        return implode(' ', $parts);
    }

    /**
     * @param Collection<int, Application> $applications
     * @param Collection<int, array{application: Application,total: int,answered: int,percent: int,next_question_id: string,latest_unified_request: ?AiRequest}> $videoAssessments
     * @param Collection<int, array{application: Application,total: int,answered: int,scored: int,percent: int,status: string}> $sjtAssessments
     * @return Collection<int, array{id: string, type: string, title: string, message: string, created_at: \Illuminate\Support\Carbon}>
     */
    private function buildPortalNotifications(
        string $companyId,
        Collection $applications,
        Collection $videoAssessments,
        Collection $sjtAssessments,
        bool $canAccessSocialHub
    ): Collection {
        if ($applications->isEmpty()) {
            return collect();
        }

        $applicationById = $applications
            ->keyBy(static fn (Application $application): string => (string) $application->id);
        $applicationIds = $applicationById->keys()->all();

        $activityItems = ApplicationActivityEvent::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('application_id', $applicationIds)
            ->orderByDesc('created_at')
            ->limit(18)
            ->get(['id', 'application_id', 'event_type', 'payload', 'created_at'])
            ->map(function (ApplicationActivityEvent $event) use ($applicationById): ?array {
                $application = $applicationById->get((string) $event->application_id);
                if (! $application instanceof Application) {
                    return null;
                }

                $eventType = (string) $event->event_type;
                $payload = (array) ($event->payload ?? []);
                $jobTitle = (string) ($application->job?->title ?? __('sjt.messages.unknown_job'));
                $isInterviewEmailEvent = $this->isInterviewEmailEvent($payload);

                $type = match ($eventType) {
                    'interview.scheduled' => 'interview',
                    'email.sent' => $isInterviewEmailEvent ? 'interview' : 'application',
                    'video.response_submitted', 'video.unified_report_queued' => 'assessment',
                    default => 'application',
                };

                $title = match ($eventType) {
                    'stage.changed' => __('candidate_portal.notifications.event_labels.stage_changed'),
                    'interview.scheduled' => __('candidate_portal.notifications.event_labels.interview_scheduled'),
                    'email.sent' => $isInterviewEmailEvent
                        ? __('candidate_portal.notifications.event_labels.email_sent')
                        : __('candidate_portal.notifications.event_labels.portal_updated'),
                    'contract.signed' => __('candidate_portal.notifications.event_labels.contract_signed'),
                    'onboarding.document_uploaded' => __('candidate_portal.notifications.event_labels.document_uploaded'),
                    'onboarding.task_toggled' => __('candidate_portal.notifications.event_labels.task_updated'),
                    'candidate.reverse_feedback_submitted' => __('candidate_portal.notifications.event_labels.feedback_submitted'),
                    default => __('candidate_portal.notifications.event_labels.portal_updated'),
                };

                $message = match ($eventType) {
                    'stage.changed' => __('candidate_portal.notifications.event_messages.stage_changed', [
                        'job' => $jobTitle,
                        'stage' => (string) ($payload['to_stage_label'] ?? $application->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage')),
                    ]),
                    'interview.scheduled' => __('candidate_portal.notifications.event_messages.interview_scheduled', [
                        'job' => $jobTitle,
                        'date' => (string) ($payload['scheduled_for'] ?? __('candidate_portal.applications.not_scheduled')),
                    ]),
                    'email.sent' => $isInterviewEmailEvent
                        ? __('candidate_portal.notifications.event_messages.email_sent')
                        : __('candidate_portal.notifications.event_messages.portal_updated', ['job' => $jobTitle]),
                    'contract.signed' => __('candidate_portal.notifications.event_messages.contract_signed'),
                    'onboarding.document_uploaded' => __('candidate_portal.notifications.event_messages.document_uploaded'),
                    'onboarding.task_toggled' => __('candidate_portal.notifications.event_messages.task_updated'),
                    'candidate.reverse_feedback_submitted' => __('candidate_portal.notifications.event_messages.feedback_submitted'),
                    default => __('candidate_portal.notifications.event_messages.portal_updated', ['job' => $jobTitle]),
                };

                return [
                    'id' => (string) $event->id,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'created_at' => $event->created_at ?? now(),
                ];
            })
            ->filter()
            ->values();

        $assessmentItems = collect();

        foreach ($videoAssessments as $assessment) {
            $total = (int) ($assessment['total'] ?? 0);
            if ($total <= 0) {
                continue;
            }

            $answered = (int) ($assessment['answered'] ?? 0);
            if ($answered >= $total) {
                continue;
            }

            /** @var Application|null $application */
            $application = $assessment['application'] ?? null;
            $assessmentItems->push([
                'id' => 'video-'.(string) ($application?->id ?? Str::uuid()),
                'type' => 'assessment',
                'title' => __('candidate_portal.notifications.event_labels.video_assessment'),
                'message' => __('candidate_portal.notifications.event_messages.video_assessment', [
                    'answered' => $answered,
                    'total' => $total,
                    'job' => (string) ($application?->job?->title ?? __('sjt.messages.unknown_job')),
                ]),
                'created_at' => $application?->updated_at ?? now(),
            ]);
        }

        foreach ($sjtAssessments as $assessment) {
            $total = (int) ($assessment['total'] ?? 0);
            if ($total <= 0) {
                continue;
            }

            $scored = (int) ($assessment['scored'] ?? 0);
            if ($scored >= $total) {
                continue;
            }

            $answered = (int) ($assessment['answered'] ?? 0);
            /** @var Application|null $application */
            $application = $assessment['application'] ?? null;

            $assessmentItems->push([
                'id' => 'sjt-'.(string) ($application?->id ?? Str::uuid()),
                'type' => 'assessment',
                'title' => __('candidate_portal.notifications.event_labels.sjt_assessment'),
                'message' => __('candidate_portal.notifications.event_messages.sjt_assessment', [
                    'answered' => $answered,
                    'total' => $total,
                    'job' => (string) ($application?->job?->title ?? __('sjt.messages.unknown_job')),
                ]),
                'created_at' => $application?->updated_at ?? now(),
            ]);
        }

        $strategyUnlockedItems = $applications
            ->filter(
                static fn (Application $application): bool => StrategyLabController::canAccessStrategyLab($application)
            )
            ->map(static function (Application $application): array {
                return [
                    'id' => 'strategy-lab-unlocked-'.(string) $application->id,
                    'type' => 'application',
                    'title' => __('candidate_portal.notifications.event_labels.strategy_lab_unlocked'),
                    'message' => __('candidate_portal.notifications.event_messages.strategy_lab_unlocked'),
                    'created_at' => $application->updated_at ?? now(),
                ];
            })
            ->values();

        if ($canAccessSocialHub) {
            $activityItems->prepend([
                'id' => 'social-hub-enabled',
                'type' => 'social',
                'title' => __('candidate_portal.notifications.event_labels.social_hub_enabled'),
                'message' => __('candidate_portal.notifications.event_messages.social_hub_enabled'),
                'created_at' => now(),
            ]);
        }

        return $activityItems
            ->concat($strategyUnlockedItems)
            ->concat($assessmentItems)
            ->sortByDesc(static fn (array $item) => $item['created_at'])
            ->take(12)
            ->values();
    }

    /**
     * Only interview-related emails should surface as interview notifications.
     *
     * @param array<string, mixed> $payload
     */
    private function isInterviewEmailEvent(array $payload): bool
    {
        $template = Str::lower(trim((string) data_get($payload, 'template', '')));
        if ($template === 'interview_confirmation') {
            return true;
        }

        $interviewId = trim((string) data_get($payload, 'interview_id', ''));

        return $interviewId !== '';
    }

    /**
     * @param Collection<int, Application> $applications
     * @return Collection<int, array{
     *   application: Application,
     *   config: VideoConfig,
     *   total: int,
     *   answered: int,
     *   percent: int,
     *   next_question_id: string,
     *   latest_unified_request: ?AiRequest
     * }>
     */
    private function buildVideoAssessments(string $companyId, Collection $applications): Collection
    {
        if ($applications->isEmpty()) {
            return collect();
        }

        $jobIds = $applications->pluck('job_id')->filter()->unique()->values();
        $configByJobId = collect();

        foreach ($jobIds as $jobId) {
            $config = VideoConfig::withoutGlobalScopes()
                ->with('questions:id,config_id,display_order,question_text')
                ->where('company_id', $companyId)
                ->where('job_id', (string) $jobId)
                ->latest('created_at')
                ->first();

            if ($config instanceof VideoConfig && $config->questions->isNotEmpty()) {
                $configByJobId->put((string) $jobId, $config);
            }
        }

        if ($configByJobId->isEmpty()) {
            return collect();
        }

        return $applications
            ->map(function (Application $application) use ($companyId, $configByJobId): ?array {
                $config = $configByJobId->get((string) $application->job_id);
                if (! $config instanceof VideoConfig) {
                    return null;
                }

                $questions = $config->questions;
                $questionIds = $questions->pluck('id')->map(static fn ($id): string => (string) $id);
                if ($questionIds->isEmpty()) {
                    return null;
                }

                $responses = VideoResponse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('application_id', (string) $application->id)
                    ->whereIn('question_id', $questionIds->all())
                    ->orderByDesc('attempt_number')
                    ->get(['id', 'question_id', 'attempt_number']);

                $latestByQuestion = $responses
                    ->groupBy(static fn (VideoResponse $response): string => (string) $response->question_id)
                    ->map(static fn (Collection $items): ?VideoResponse => $items->first());

                $answered = $latestByQuestion->count();
                $total = $questions->count();
                $percent = $total > 0 ? (int) round(($answered / $total) * 100) : 0;

                $nextQuestion = $questions->first(
                    fn ($question): bool => ! $latestByQuestion->has((string) $question->id)
                );

                $latestUnifiedRequest = AiRequest::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('request_type', 'async_video_unified_report')
                    ->where('request_payload->application_id', (string) $application->id)
                    ->latest('created_at')
                    ->first();

                return [
                    'application' => $application,
                    'config' => $config,
                    'total' => $total,
                    'answered' => $answered,
                    'percent' => $percent,
                    'next_question_id' => (string) ($nextQuestion?->id ?? $questions->first()?->id),
                    'latest_unified_request' => $latestUnifiedRequest,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param Collection<int, Application> $applications
     * @return Collection<int, array{
     *   application: Application,
     *   total: int,
     *   answered: int,
     *   scored: int,
     *   percent: int,
     *   status: string
     * }>
     */
    private function buildSjtAssessments(string $companyId, Collection $applications): Collection
    {
        if ($applications->isEmpty()) {
            return collect();
        }

        return $applications
            ->map(function (Application $application) use ($companyId): ?array {
                $responseScenarioIds = SjtResponse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('application_id', (string) $application->id)
                    ->pluck('scenario_id')
                    ->filter()
                    ->values();

                $scenarios = SjtScenario::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where(function ($query) use ($application): void {
                        $query->whereNull('job_id')
                            ->orWhere('job_id', (string) $application->job_id);
                    })
                    ->where(function ($query) use ($responseScenarioIds): void {
                        $query->where('is_active', true);
                        if ($responseScenarioIds->isNotEmpty()) {
                            $query->orWhereIn('id', $responseScenarioIds->all());
                        }
                    })
                    ->get(['id']);

                $total = $scenarios->count();
                if ($total === 0) {
                    return null;
                }

                $responses = SjtResponse::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('application_id', (string) $application->id)
                    ->whereIn('scenario_id', $scenarios->pluck('id')->all())
                    ->get(['scenario_id', 'response_text', 'ai_score']);

                $answered = $responses
                    ->filter(fn (SjtResponse $response): bool => trim((string) $response->response_text) !== '')
                    ->count();
                $scored = $responses->filter(fn (SjtResponse $response): bool => $response->ai_score !== null)->count();
                $percent = $total > 0 ? (int) round(($answered / $total) * 100) : 0;

                $status = 'not_started';
                if ($scored > 0 && $scored >= $total) {
                    $status = 'scored';
                } elseif ($answered > 0) {
                    $status = 'in_progress';
                }

                return [
                    'application' => $application,
                    'total' => $total,
                    'answered' => $answered,
                    'scored' => $scored,
                    'percent' => $percent,
                    'status' => $status,
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveNextStep(Application $application, ?array $videoAssessment, ?array $sjtAssessment): string
    {
        if ($this->isInHiredFlow($application)) {
            $contract = $application->contract;
            if (! $contract instanceof Contract) {
                return __('candidate_portal.applications.next_step_onboarding_waiting_contract');
            }

            if ($contract->contract_status !== Contract::STATUS_SIGNED || $contract->signed_at === null) {
                return __('candidate_portal.applications.next_step_contract_sign');
            }

            $pendingTasks = $application->onboardingTasks
                ->filter(fn ($task): bool => ! (bool) $task->is_completed)
                ->count();

            if ($pendingTasks > 0) {
                return __('candidate_portal.applications.next_step_onboarding_tasks', ['count' => $pendingTasks]);
            }

            if ($application->reverseFeedback instanceof ReverseFeedback) {
                return __('candidate_portal.applications.next_step_feedback_submitted');
            }

            return __('candidate_portal.applications.next_step_feedback_required');
        }

        if ($this->isTerminalApplication($application)) {
            if (! $this->isReverseFeedbackEligible($application)) {
                return __('candidate_portal.applications.next_step_terminal_closed');
            }

            if ($application->reverseFeedback instanceof ReverseFeedback) {
                return __('candidate_portal.applications.next_step_feedback_submitted');
            }

            return __('candidate_portal.applications.next_step_feedback_required');
        }

        $strategyLabBrief = $application->strategyLabBrief;
        if (
            $strategyLabBrief instanceof StrategyLabBrief
            && $strategyLabBrief->submission === null
            && StrategyLabController::canAccessStrategyLab($application)
        ) {
            if ($strategyLabBrief->deadline_at !== null && $strategyLabBrief->deadline_at->isPast()) {
                return __('candidate_portal.applications.next_step_strategy_deadline_passed');
            }

            return __('candidate_portal.applications.next_step_strategy_lab');
        }

        if (is_array($videoAssessment) && (int) ($videoAssessment['answered'] ?? 0) < (int) ($videoAssessment['total'] ?? 0)) {
            return __('candidate_portal.applications.next_step_video');
        }

        if (is_array($sjtAssessment) && (int) ($sjtAssessment['scored'] ?? 0) < (int) ($sjtAssessment['total'] ?? 0)) {
            return __('candidate_portal.applications.next_step_sjt');
        }

        $upcomingInterview = $application->interviews
            ->first(function (Interview $interview): bool {
                return $interview->status === Interview::STATUS_SCHEDULED
                    && $interview->scheduled_start_at !== null
                    && $interview->scheduled_start_at->isFuture();
            });

        if ($upcomingInterview instanceof Interview) {
            $formatted = $upcomingInterview->scheduled_start_at
                ? $upcomingInterview->scheduled_start_at->timezone((string) ($upcomingInterview->timezone ?: 'UTC'))->format('Y-m-d H:i')
                : __('candidate_portal.applications.not_scheduled');

            return __('candidate_portal.applications.next_step_interview', ['date' => $formatted]);
        }

        return __('candidate_portal.applications.next_step_waiting', [
            'stage' => (string) ($application->currentStage?->stage_label ?? __('candidate_portal.applications.unknown_stage')),
        ]);
    }

    private function isTerminalApplication(Application $application): bool
    {
        if ($application->currentStage !== null && (bool) $application->currentStage->is_terminal) {
            return true;
        }

        $status = Str::lower(trim((string) $application->status));

        return in_array($status, [
            Application::STATUS_REJECTED,
            Application::STATUS_HIRED,
            Application::STATUS_WITHDRAWN,
        ], true);
    }

    private function isReverseFeedbackEligible(Application $application): bool
    {
        if ($this->isInHiredFlow($application)) {
            return $this->hasCompletedOnboardingForFeedback($application);
        }

        return $this->hasCompletedZoomInterview($application);
    }

    private function hasCompletedOnboardingForFeedback(Application $application): bool
    {
        $contract = $application->contract;
        if (
            ! $contract instanceof Contract
            || (string) $contract->contract_status !== Contract::STATUS_SIGNED
            || $contract->signed_at === null
        ) {
            return false;
        }

        return ! $application->onboardingTasks->contains(
            fn (OnboardingTask $task): bool => ! (bool) $task->is_completed
        );
    }

    private function hasCompletedZoomInterview(Application $application): bool
    {
        return $application->interviews->contains(function (Interview $interview): bool {
            if ((string) $interview->status !== Interview::STATUS_COMPLETED) {
                return false;
            }

            if ((string) $interview->location_type === Interview::LOCATION_ZOOM) {
                return true;
            }

            return Str::contains(Str::lower((string) $interview->meeting_link), 'zoom');
        });
    }

    private function isPreselectedForSocialHub(Application $application): bool
    {
        if ($this->isInHiredFlow($application)) {
            return true;
        }

        if ((string) $application->status !== Application::STATUS_ACTIVE) {
            return false;
        }

        if ($application->currentStage === null || (bool) $application->currentStage->is_terminal) {
            return false;
        }

        $stageText = $this->applicationStageText($application);
        if ($this->isRejectedStageText($stageText)) {
            return false;
        }

        if ($stageText === '') {
            return false;
        }

        return preg_match('/\b(pre[\s-]?select(?:ion|ed)?|selected|shortlist(?:ed)?|offer|hire|hired|onboard|onboarding|final)\b/', $stageText) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function candidateVisibleSocialPostTypes(): array
    {
        return [
            SocialPost::TYPE_KUDOS,
            SocialPost::TYPE_WELCOME,
            SocialPost::TYPE_ANNOUNCEMENT,
        ];
    }

    private function isInHiredFlow(Application $application): bool
    {
        $stageText = $this->applicationStageText($application);

        if ($this->isRejectedStageText($stageText)) {
            return false;
        }

        if ((string) $application->status === Application::STATUS_HIRED) {
            return true;
        }

        if ($stageText === '') {
            return false;
        }

        return preg_match('/\b(hire|hired|onboard|onboarding)\b/', $stageText) === 1;
    }

    private function applicationStageText(Application $application): string
    {
        return Str::lower(trim(implode(' ', [
            (string) ($application->currentStage?->stage_key ?? ''),
            (string) ($application->currentStage?->stage_label ?? ''),
        ])));
    }

    private function isRejectedStageText(string $stageText): bool
    {
        if ($stageText === '') {
            return false;
        }

        return preg_match('/\b(reject|rejected|declin|disqualif|not selected)\b/', $stageText) === 1;
    }

    private function isBlockedGuidePrompt(string $message): bool
    {
        $normalized = Str::lower($message);
        $asksForAnswer = (bool) preg_match(
            '/\b(answer|answers|solve|solution|write|complete|do|generate|give me|draft)\b/',
            $normalized
        );
        $targetsProtectedContent = (bool) preg_match(
            '/\b(sjt|assessment|test|exam|quiz|video interview|strategy lab|assignment|job task|take[- ]home|project)\b/',
            $normalized
        );

        return $asksForAnswer && $targetsProtectedContent;
    }

    /**
     * @param Collection<int, FaqItem> $faqs
     */
    private function matchFaq(string $message, Collection $faqs): ?FaqItem
    {
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return null;
        }

        $tokens = collect(preg_split('/[^a-z0-9]+/', $normalized) ?: [])
            ->map(static fn ($token): string => trim((string) $token))
            ->filter(static fn ($token): bool => mb_strlen($token) >= 3)
            ->unique()
            ->values();

        $bestMatch = null;
        $bestScore = 0;

        foreach ($faqs as $faq) {
            $category = Str::lower((string) $faq->category);
            $question = Str::lower((string) $faq->question);
            $answer = Str::lower((string) $faq->answer);

            $score = 0;
            if ($question !== '' && Str::contains($question, $normalized)) {
                $score += 12;
            }

            if ($answer !== '' && Str::contains($answer, $normalized)) {
                $score += 8;
            }

            foreach ($tokens as $token) {
                if (Str::contains($question, $token)) {
                    $score += 3;
                }
                if (Str::contains($answer, $token)) {
                    $score += 2;
                }
                if ($category !== '' && Str::contains($category, $token)) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $faq;
            }
        }

        if ($bestScore < 4 || ! $bestMatch instanceof FaqItem) {
            return null;
        }

        return $bestMatch;
    }
}
