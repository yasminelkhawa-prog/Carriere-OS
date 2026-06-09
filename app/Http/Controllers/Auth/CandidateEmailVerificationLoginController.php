<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CandidateEmailVerificationLoginController extends Controller
{
    public function __invoke(
        Request $request,
        User $user,
        Company $company,
        Application $application
    ): RedirectResponse {
        abort_unless((bool) $user->active, 403);
        abort_unless((string) $application->company_id === (string) $company->id, 404);

        $application->loadMissing('candidate');
        abort_unless((string) ($application->candidate?->user_id ?? '') === (string) $user->id, 403);

        $hash = (string) $request->query('hash', '');
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        $hasCandidateMembership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->where('company_role', CompanyMembership::ROLE_CANDIDATE)
            ->exists();
        abort_unless($hasCandidateMembership, 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->put('active_company_id', (string) $company->id);

        return redirect()
            ->route('candidate.portal', ['company' => $company->slug])
            ->with('status', __('auth.email_verified'));
    }
}

