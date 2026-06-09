<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\Job;
use App\Models\User;
use App\Models\UserSecureSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InterviewController extends Controller
{
    use ResolvesManagedCompany;

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, true);
        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Interview::statuses())],
            'job_id' => ['nullable', 'uuid'],
            'interviewer_user_id' => ['nullable', 'uuid'],
        ]);

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('interviews.index', [
                'requiresCompanySelection' => true,
                'interviews' => collect(),
                'selectedCompanyId' => null,
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'jobs' => collect(),
                'interviewers' => collect(),
                'selectedStatus' => null,
                'selectedJobId' => null,
                'selectedInterviewerUserId' => null,
            ]);
        }

        $jobs = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('title')
            ->get(['id', 'title']);

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

        $selectedStatus = $filters['status'] ?? null;
        $selectedJobId = $filters['job_id'] ?? null;
        $selectedInterviewerUserId = $filters['interviewer_user_id'] ?? null;

        $interviews = Interview::withoutGlobalScopes()
            ->with(['application.candidate', 'application.job', 'participants.user.profile'])
            ->where('company_id', $companyId)
            ->when($selectedStatus !== null, fn ($query) => $query->where('status', $selectedStatus))
            ->when($selectedJobId !== null, function ($query) use ($selectedJobId): void {
                $query->whereHas('application', fn ($applicationQuery) => $applicationQuery->where('job_id', $selectedJobId));
            })
            ->when($selectedInterviewerUserId !== null, function ($query) use ($selectedInterviewerUserId): void {
                $query->whereHas('participants', fn ($participantQuery) => $participantQuery->where('user_id', $selectedInterviewerUserId));
            })
            ->orderByDesc('scheduled_start_at')
            ->paginate(20)
            ->withQueryString();

        return view('interviews.index', [
            'requiresCompanySelection' => false,
            'interviews' => $interviews,
            'selectedCompanyId' => $companyId,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'jobs' => $jobs,
            'interviewers' => $interviewers,
            'selectedStatus' => $selectedStatus,
            'selectedJobId' => $selectedJobId,
            'selectedInterviewerUserId' => $selectedInterviewerUserId,
        ]);
    }

    public function show(Request $request, Interview $interview): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $interview->company_id === (string) $companyId, 403);
        abort_unless($this->hasCompanyContextAccess($actor, $companyId), 403);

        $interview->load([
            'application.candidate',
            'application.job',
            'participants.user.profile',
            'feedback.author.profile',
        ]);

        $canSubmitFeedback = $this->isAssignedInterviewer($interview, $actor);
        $canGenerateInvite = $this->canGenerateInvite($interview, $actor, $companyId);

        return view('interviews.show', [
            'interview' => $interview,
            'inviteUrl' => $canGenerateInvite ? $this->buildExternalInviteUrl($interview) : null,
            'canSubmitFeedback' => $canSubmitFeedback,
            'canGenerateInvite' => $canGenerateInvite,
        ]);
    }

    public function storeFeedback(Request $request, Interview $interview): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $interview->company_id === (string) $companyId, 403);
        abort_unless($this->hasCompanyContextAccess($actor, $companyId), 403);
        if (! $this->isAssignedInterviewer($interview, $actor)) {
            abort(403, __('interviews.permissions.feedback_interviewer_only'));
        }

        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(InterviewFeedback::recommendations())],
            'notes' => ['nullable', 'string', 'max:3000'],
            'rating_technical' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_communication' => ['nullable', 'integer', 'min:1', 'max:5'],
            'rating_problem_solving' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $feedback = $interview->feedback()->create([
            'company_id' => $companyId,
            'author_user_id' => $actor->id,
            'ratings_json' => [
                'technical' => $validated['rating_technical'] ?? null,
                'communication' => $validated['rating_communication'] ?? null,
                'problem_solving' => $validated['rating_problem_solving'] ?? null,
            ],
            'recommendation' => $validated['recommendation'],
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
        ]);

        if ($request->expectsJson()) {
            $interview->loadMissing('participants');
            $requiredFeedbackCount = $interview->participants
                ->where('participant_role', 'interviewer')
                ->count();
            $submittedFeedbackCount = $interview->feedback()
                ->select('author_user_id')
                ->distinct()
                ->count('author_user_id');

            return response()->json([
                'status' => 'ok',
                'feedback' => $feedback->toArray(),
                'missing_feedback_count' => max(0, $requiredFeedbackCount - $submittedFeedbackCount),
            ]);
        }

        return back()->with('status', __('interviews.feedback_saved'));
    }

    public function invite(Request $request, Interview $interview): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $interview->company_id === (string) $companyId, 403);
        abort_unless($this->hasCompanyContextAccess($actor, $companyId), 403);

        if (! $this->canGenerateInvite($interview, $actor, $companyId)) {
            abort(403, __('interviews.permissions.invite_forbidden'));
        }

        $inviteUrl = $this->buildExternalInviteUrl($interview);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'invite_url' => $inviteUrl,
            ]);
        }

        return redirect()->away($inviteUrl);
    }

    public function updateZoomLink(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, false) ?? (string) session('active_company_id', '');
        $companyId = $companyId !== '' ? $companyId : null;

        $validated = $request->validate([
            'zoom_personal_meeting_room_link' => ['nullable', 'url', 'max:1024'],
        ]);

        $value = trim((string) ($validated['zoom_personal_meeting_room_link'] ?? ''));

        if ($value === '') {
            UserSecureSetting::withoutGlobalScopes()
                ->where('user_id', $actor->id)
                ->where('company_id', $companyId)
                ->where('setting_key', UserSecureSetting::KEY_ZOOM_PMR_LINK)
                ->delete();
        } else {
            UserSecureSetting::withoutGlobalScopes()->updateOrCreate(
                [
                    'user_id' => $actor->id,
                    'company_id' => $companyId,
                    'setting_key' => UserSecureSetting::KEY_ZOOM_PMR_LINK,
                ],
                [
                    'setting_value' => $value,
                ]
            );
        }

        return back()->with('status', __('interviews.zoom_link_saved'));
    }

    private function buildExternalInviteUrl(Interview $interview): string
    {
        $interview->loadMissing(['application.candidate', 'application.job']);

        $title = __('interviews.invite_title', ['job' => (string) $interview->application?->job?->title]);
        $localStart = Carbon::parse($interview->scheduled_start_at)
            ->timezone($interview->timezone)
            ->format('Y-m-d H:i');
        $meetingLink = (string) ($interview->meeting_link ?? '');
        $locationAddress = trim((string) ($interview->location_address ?? ''));
        $interviewTypeLabel = __('interviews.types.'.$interview->interview_type);
        if ($interviewTypeLabel === 'interviews.types.'.$interview->interview_type) {
            $interviewTypeLabel = (string) $interview->interview_type;
        }
        $locationTypeLabel = __('interviews.location_types.'.$interview->location_type);
        if ($locationTypeLabel === 'interviews.location_types.'.$interview->location_type) {
            $locationTypeLabel = (string) $interview->location_type;
        }

        $locationValue = $interview->location_type === Interview::LOCATION_IN_PERSON && $locationAddress !== ''
            ? $locationAddress
            : ($meetingLink !== '' ? $meetingLink : $locationTypeLabel);

        $details = __('interviews.invite_details', [
            'candidate' => (string) $interview->application?->candidate?->full_name,
            'type' => $interviewTypeLabel,
            'local_time' => $localStart,
            'timezone' => (string) $interview->timezone,
            'location' => $locationValue,
        ]);

        $start = Carbon::parse($interview->scheduled_start_at)->utc()->format('Ymd\THis\Z');
        $end = Carbon::parse($interview->scheduled_end_at)->utc()->format('Ymd\THis\Z');
        $location = $locationValue;

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            .'&text='.urlencode($title)
            .'&dates='.urlencode($start.'/'.$end)
            .'&details='.urlencode($details)
            .'&location='.urlencode($location);
    }

    private function hasCompanyContextAccess(User $actor, string $companyId): bool
    {
        if ($actor->isSuperadmin()) {
            return true;
        }

        return CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->exists();
    }

    private function isAssignedInterviewer(Interview $interview, User $actor): bool
    {
        return $interview->participants()
            ->where('user_id', $actor->id)
            ->where('participant_role', 'interviewer')
            ->exists();
    }

    private function canGenerateInvite(Interview $interview, User $actor, string $companyId): bool
    {
        if ($actor->isSuperadmin()) {
            return true;
        }

        $hasViewRole = CompanyMembership::query()
            ->where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_MANAGER,
            ])
            ->exists();

        return $hasViewRole || $this->isAssignedInterviewer($interview, $actor);
    }
}
