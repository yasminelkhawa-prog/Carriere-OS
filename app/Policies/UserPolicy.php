<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CompanyMembership;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->hasRole(User::ROLE_COMPANY_ADMIN);
    }

    public function view(User $user, User $target): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        $activeCompanyId = session('active_company_id');

        if ($activeCompanyId === null || ! $user->hasRole(User::ROLE_COMPANY_ADMIN)) {
            return false;
        }

        return $target->memberships()
            ->where('company_id', $activeCompanyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->exists();
    }

    public function updateRole(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }

    public function create(User $user): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->hasRole(User::ROLE_COMPANY_ADMIN);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }
}
