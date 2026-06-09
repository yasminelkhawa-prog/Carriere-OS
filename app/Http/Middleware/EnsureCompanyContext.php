<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\CompanyMembership;
use App\Models\Company;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isSuperadmin()) {
            return $next($request);
        }

        $activeCompanyId = (string) session('active_company_id');

        if (
            $activeCompanyId !== '' &&
            $user->activeMemberships()
                ->where('company_id', $activeCompanyId)
                ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
                ->exists()
        ) {
            return $next($request);
        }

        return redirect()->route('auth.company.dispatch');
    }
}
