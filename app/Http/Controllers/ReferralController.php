<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(private readonly ReferralService $referralService)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        if ($companyId === null) {
            return view('referrals.index', [
                'requiresCompanySelection' => true,
                'companies' => $companies,
                'referrals' => collect(),
                'jobs' => collect(),
                'statusOptions' => Referral::statuses(),
                'referrerOptions' => collect(),
                'canConvert' => false,
                'canViewAll' => false,
                'filters' => [
                    'status' => null,
                    'referrer_user_id' => null,
                ],
            ]);
        }

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canAccessIndex($actor, $role), 403);

        $canViewAll = $this->canViewAllReferrals($actor, $role);
        $canConvert = $this->canConvertReferrals($actor, $role);
        $filters = $this->validatedFilters($request, $companyId);

        $query = Referral::withoutGlobalScopes()
            ->with(['referrer.profile', 'linkedApplication.job'])
            ->where('company_id', $companyId);

        if (! $canViewAll) {
            $query->where('referrer_user_id', (string) $actor->id);
        }

        if (is_string($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (
            $canViewAll
            && is_string($filters['referrer_user_id'])
            && $filters['referrer_user_id'] !== ''
        ) {
            $query->where('referrer_user_id', $filters['referrer_user_id']);
        }

        $referrals = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $jobs = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', Job::STATUS_PUBLISHED)
            ->orderBy('title')
            ->get(['id', 'title']);

        $referrerOptions = CompanyMembership::query()
            ->with('user.profile')
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_MANAGER,
                CompanyMembership::ROLE_EMPLOYEE,
            ])
            ->get()
            ->map(static fn (CompanyMembership $membership): array => [
                'id' => (string) $membership->user_id,
                'name' => (string) ($membership->user?->profile?->full_name ?? $membership->user?->email ?? $membership->user_id),
            ])
            ->sortBy('name')
            ->values();

        return view('referrals.index', [
            'requiresCompanySelection' => false,
            'companies' => $companies,
            'referrals' => $referrals,
            'jobs' => $jobs,
            'statusOptions' => Referral::statuses(),
            'referrerOptions' => $referrerOptions,
            'canConvert' => $canConvert,
            'canViewAll' => $canViewAll,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        [$companyId, $companies] = $this->resolveCompanyContext($request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        if ($companyId === null) {
            return view('referrals.create', [
                'requiresCompanySelection' => true,
                'companies' => $companies,
            ]);
        }

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canSubmitReferrals($actor, $role), 403);

        return view('referrals.create', [
            'requiresCompanySelection' => false,
            'companies' => $companies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 403);

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canSubmitReferrals($actor, $role), 403);

        $validated = $request->validate([
            'candidate_email' => ['required', 'email:rfc', 'max:255'],
            'candidate_name' => ['nullable', 'string', 'max:255'],
            'candidate_linkedin_url' => ['nullable', 'url', 'max:2048'],
            'resume' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'],
        ]);

        $candidateEmail = Str::lower(trim((string) $validated['candidate_email']));

        $duplicate = Referral::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('referrer_user_id', (string) $actor->id)
            ->where('candidate_email', $candidateEmail)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'candidate_email' => __('referrals.validation.duplicate_referral'),
            ]);
        }

        $resumePath = null;
        if ($request->file('resume') !== null) {
            $resumePath = $request->file('resume')
                ->store("private/referrals/{$companyId}/{$actor->id}", 'local');
        }

        Referral::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'referrer_user_id' => (string) $actor->id,
            'candidate_email' => $candidateEmail,
            'candidate_name' => trim((string) ($validated['candidate_name'] ?? '')) ?: null,
            'candidate_linkedin_url' => trim((string) ($validated['candidate_linkedin_url'] ?? '')) ?: null,
            'resume_file_url' => $resumePath,
            'status' => Referral::STATUS_SUBMITTED,
        ]);

        return redirect()
            ->route('referrals.index', $this->companyQuery($request))
            ->with('status', __('referrals.flash.created'));
    }

    public function convert(Request $request, Referral $referral): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 403);
        abort_unless((string) $referral->company_id === $companyId, 403);

        $role = $this->activeMembershipRole($actor, $companyId);
        abort_unless($this->canConvertReferrals($actor, $role), 403);

        $validated = $request->validate([
            'job_id' => [
                'required',
                'uuid',
                Rule::exists('jobs', 'id')->where(static fn ($query) => $query->where('company_id', $companyId)),
            ],
        ]);

        $job = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail((string) $validated['job_id']);

        try {
            $application = $this->referralService->convertToApplication($referral, $job, $actor);
        } catch (ValidationException $exception) {
            $firstErrorMessage = collect($exception->errors())
                ->flatten()
                ->first();

            return redirect()
                ->back()
                ->withInput($request->only(['job_id', 'referral_id', 'company_id']))
                ->withErrors($exception->errors())
                ->with(
                    'error',
                    is_string($firstErrorMessage) && $firstErrorMessage !== ''
                        ? $firstErrorMessage
                        : __('referrals.flash.convert_failed')
                );
        }

        return redirect()
            ->route('candidates.index', array_merge($this->companyQuery($request), [
                'application_id' => (string) $application->id,
            ]))
            ->with('status', __('referrals.flash.converted'));
    }

    /**
     * @return array{0: ?string, 1: \Illuminate\Support\Collection<int, Company>}
     */
    private function resolveCompanyContext(Request $request): array
    {
        $user = $request->user();
        $companies = collect();

        if ($user instanceof User && $user->isSuperadmin()) {
            $companies = Company::query()
                ->where('status', Company::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $companyId = $this->managedCompanyId($request, false);
        if ($companyId !== null) {
            $exists = Company::query()
                ->where('id', $companyId)
                ->where('status', Company::STATUS_ACTIVE)
                ->exists();
            if (! $exists) {
                $companyId = null;
            }
        }

        return [$companyId, $companies];
    }

    /**
     * @return array{status: ?string, referrer_user_id: ?string}
     */
    private function validatedFilters(Request $request, string $companyId): array
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(Referral::statuses())],
            'referrer_user_id' => [
                'nullable',
                'uuid',
                Rule::exists('company_memberships', 'user_id')
                    ->where(static function ($query) use ($companyId): void {
                        $query->where('company_id', $companyId)
                            ->where('membership_status', CompanyMembership::STATUS_ACTIVE);
                    }),
            ],
        ]);

        return [
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'referrer_user_id' => isset($validated['referrer_user_id']) ? (string) $validated['referrer_user_id'] : null,
        ];
    }

    private function activeMembershipRole(User $user, string $companyId): ?string
    {
        if ($user->isSuperadmin()) {
            return null;
        }

        return $user->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->value('company_role');
    }

    private function canAccessIndex(User $user, ?string $role): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_RECRUITER,
            CompanyMembership::ROLE_MANAGER,
            CompanyMembership::ROLE_EMPLOYEE,
        ], true);
    }

    private function canViewAllReferrals(User $user, ?string $role): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_RECRUITER,
            CompanyMembership::ROLE_MANAGER,
        ], true);
    }

    private function canSubmitReferrals(User $user, ?string $role): bool
    {
        if ($user->isSuperadmin()) {
            return false;
        }

        return in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_RECRUITER,
            CompanyMembership::ROLE_MANAGER,
            CompanyMembership::ROLE_EMPLOYEE,
        ], true);
    }

    private function canConvertReferrals(User $user, ?string $role): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return in_array((string) $role, [
            CompanyMembership::ROLE_COMPANY_ADMIN,
            CompanyMembership::ROLE_RECRUITER,
            CompanyMembership::ROLE_MANAGER,
        ], true);
    }

    /**
     * @return array<string, string>
     */
    private function companyQuery(Request $request): array
    {
        $companyId = (string) $request->input('company_id', $request->query('company_id', ''));

        return $companyId !== '' ? ['company_id' => $companyId] : [];
    }
}
