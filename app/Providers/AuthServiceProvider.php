<?php

namespace App\Providers;

use App\Models\User;
use App\Models\CompanyMembership;
use App\Models\Company;
use App\Models\Export;
use App\Policies\ExportPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Export::class => ExportPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('access-platform-console', static fn (User $user): bool => $user->isSuperadmin());

        Gate::define('access-admin-pages', static function (User $user): bool {
            if ($user->isSuperadmin()) {
                return true;
            }

            $activeCompanyId = session('active_company_id');

            if ($activeCompanyId === null) {
                return false;
            }

            return $user->memberships()
                ->where('company_id', $activeCompanyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->where('company_role', User::ROLE_COMPANY_ADMIN)
                ->exists();
        });

        Gate::define('access-company-data', static function (User $user, int|string $companyId): bool {
            if ($user->isSuperadmin()) {
                return true;
            }

            return $user->memberships()
                ->where('company_id', $companyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
                ->exists();
        });

        Gate::define('access-candidate-assessments', static function (User $user): bool {
            if ($user->isSuperadmin()) {
                return true;
            }

            $activeCompanyId = session('active_company_id');
            if (! is_string($activeCompanyId) || $activeCompanyId === '') {
                return false;
            }

            return $user->memberships()
                ->where('company_id', $activeCompanyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->where('company_role', CompanyMembership::ROLE_CANDIDATE)
                ->exists();
        });

        Gate::define('access-recruitment-workspace', static function (User $user): bool {
            if ($user->isSuperadmin()) {
                return true;
            }

            $activeCompanyId = session('active_company_id');
            if (! is_string($activeCompanyId) || $activeCompanyId === '') {
                return false;
            }

            return $user->memberships()
                ->where('company_id', $activeCompanyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->whereIn('company_role', [
                    CompanyMembership::ROLE_COMPANY_ADMIN,
                    CompanyMembership::ROLE_RECRUITER,
                    CompanyMembership::ROLE_MANAGER,
                    CompanyMembership::ROLE_EMPLOYEE,
                ])
                ->exists();
        });
    }
}
