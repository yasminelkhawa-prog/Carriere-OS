<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyRegistrationRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.company-register');
    }

    public function confirmation(Request $request): View|RedirectResponse
    {
        $registeredCompanyName = session('registered_company_name');

        if (! is_string($registeredCompanyName) || $registeredCompanyName === '') {
            return redirect()->route('company.register');
        }

        return view('auth.company-register-confirmation', [
            'registeredCompanyName' => $registeredCompanyName,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255', 'unique:companies,name'],
            'company_slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:companies,slug'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'locale' => ['required', Rule::in(['en', 'fr'])],
            'agreement' => ['accepted'],
        ], [
            'email.unique' => __('platform.email_exists_guidance'),
            'agreement.accepted' => __('platform.agreement_required'),
        ]);

        $company = Company::query()->create([
            'name' => $validated['company_name'],
            'slug' => $validated['company_slug'],
            'status' => Company::STATUS_PENDING,
        ]);

        $user = User::query()->create([
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'platform_role' => User::PLATFORM_NONE,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        Profile::query()->create([
            'user_id' => $user->id,
            'full_name' => $validated['full_name'],
            'locale' => $validated['locale'],
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => User::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_PENDING,
        ]);

        CompanyRegistrationRequest::query()->create([
            'company_id' => $company->id,
            'requested_by_user_id' => $user->id,
            'request_payload' => [
                'company_name' => $validated['company_name'],
                'company_slug' => $validated['company_slug'],
                'admin_full_name' => $validated['full_name'],
                'admin_email' => $validated['email'],
                'locale' => $validated['locale'],
                'agreement' => true,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ],
            'status' => CompanyRegistrationRequest::STATUS_PENDING,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('company.register.confirmation')
            ->with('registered_company_name', $company->name);
    }
}
