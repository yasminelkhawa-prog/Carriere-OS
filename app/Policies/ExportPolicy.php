<?php

namespace App\Policies;

use App\Models\CompanyMembership;
use App\Models\Export;
use App\Models\User;

class ExportPolicy
{
    public function view(User $user, Export $export): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        $membership = $user->memberships()
            ->where('company_id', (string) $export->company_id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->first(['company_role']);

        if (! $membership instanceof CompanyMembership) {
            return false;
        }

        if ((string) $membership->company_role === CompanyMembership::ROLE_COMPANY_ADMIN) {
            return true;
        }

        return (string) $user->id === (string) $export->requested_by_user_id;
    }
}
