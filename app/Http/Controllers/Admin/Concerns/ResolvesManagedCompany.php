<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesManagedCompany
{
    protected function managedCompanyId(Request $request, bool $requiredForSuperadmin = true): ?string
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->isSuperadmin()) {
            $companyId = (string) ($request->input('company_id', $request->query('company_id', '')));

            if ($companyId === '') {
                return $requiredForSuperadmin ? null : null;
            }

            return Company::query()->whereKey($companyId)->exists() ? $companyId : null;
        }

        $activeCompanyId = (string) session('active_company_id', '');

        return $activeCompanyId !== '' ? $activeCompanyId : null;
    }
}
