<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyContextController extends Controller
{
    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function dispatch(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->isSuperadmin()) {
            session()->forget('active_company_id');

            return redirect()->route('platform.console');
        }

        $activeMemberships = $user->memberships()
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
            ->with('company')
            ->get();

        if ($activeMemberships->count() === 1) {
            $selectedCompanyId = (string) $activeMemberships->first()->company_id;
            session(['active_company_id' => $selectedCompanyId]);
            $this->sensitiveEvents->companySelected(
                companyId: $selectedCompanyId,
                metadata: ['mode' => 'auto_single_membership'],
                actor: $user
            );

            return $this->redirectAfterCompanySelection($user, $selectedCompanyId);
        }

        if ($activeMemberships->count() > 1) {
            return redirect()->route('company.select');
        }

        return redirect()->route('company.access-status');
    }

    public function select(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isSuperadmin()) {
            return redirect()->route('auth.company.dispatch');
        }

        $memberships = $user->memberships()
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
            ->with('company:id,name')
            ->get();

        if ($memberships->count() <= 1) {
            return redirect()->route('auth.company.dispatch');
        }

        return view('auth.company-selector', ['memberships' => $memberships]);
    }

    public function storeSelection(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isSuperadmin()) {
            return redirect()->route('auth.company.dispatch');
        }

        $membershipCompanyIds = $user->memberships()
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
            ->pluck('company_id')
            ->all();

        $validated = $request->validate([
            'company_id' => ['required', Rule::in($membershipCompanyIds)],
        ]);

        session(['active_company_id' => $validated['company_id']]);
        $this->sensitiveEvents->companySelected(
            companyId: (string) $validated['company_id'],
            metadata: ['mode' => 'manual_selector'],
            actor: $user
        );

        return $this->redirectAfterCompanySelection($user, (string) $validated['company_id']);
    }

    public function switch(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isSuperadmin()) {
            return redirect()->route('auth.company.dispatch');
        }

        $membershipCompanyIds = $user->memberships()
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
            ->pluck('company_id')
            ->all();

        $validated = $request->validate([
            'company_id' => ['required', Rule::in($membershipCompanyIds)],
        ]);

        session(['active_company_id' => $validated['company_id']]);
        $this->sensitiveEvents->companySwitched(
            companyId: (string) $validated['company_id'],
            metadata: ['mode' => 'topbar_switcher'],
            actor: $user
        );

        return $this->redirectAfterCompanySelection($user, (string) $validated['company_id']);
    }

    public function accessStatus(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->isSuperadmin()) {
            return redirect()->route('platform.console');
        }

        $eligibleMembershipCompanyIds = $user->memberships()
            ->whereIn('membership_status', [
                CompanyMembership::STATUS_ACTIVE,
                CompanyMembership::STATUS_PENDING,
            ])
            ->pluck('company_id');

        $companies = Company::query()
            ->whereIn('id', $eligibleMembershipCompanyIds)
            ->whereIn('status', [
                Company::STATUS_PENDING,
                Company::STATUS_REJECTED,
                Company::STATUS_SUSPENDED,
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $isAwaitingApproval = $companies->isNotEmpty()
            && $companies->every(fn (Company $company) => $company->status === Company::STATUS_PENDING);

        return view('auth.company-access-status', [
            'companies' => $companies,
            'hasActiveMemberships' => $eligibleMembershipCompanyIds->isNotEmpty(),
            'isAwaitingApproval' => $isAwaitingApproval,
        ]);
    }

    private function redirectAfterCompanySelection(User $user, string $companyId): RedirectResponse
    {
        $activeRole = $user->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->value('company_role');

        if ($activeRole === CompanyMembership::ROLE_CANDIDATE) {
            $companySlug = Company::query()->whereKey($companyId)->value('slug');
            if (is_string($companySlug) && $companySlug !== '') {
                return redirect()->route('candidate.portal', ['company' => $companySlug]);
            }
        }

        return redirect()->route('home');
    }
}
