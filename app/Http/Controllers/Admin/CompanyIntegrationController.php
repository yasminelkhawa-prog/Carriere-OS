<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Models\User;
use App\Services\Multiposting\LinkedInApiPublisher;
use App\Services\Integrations\LinkedInCompanyIntegrationService;
use App\Services\Multiposting\LinkedInOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CompanyIntegrationController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly LinkedInOAuthService $linkedInOAuth,
        private readonly LinkedInCompanyIntegrationService $linkedInIntegrationService,
        private readonly LinkedInApiPublisher $linkedInApiPublisher
    ) {
    }

    public function redirectToLinkedIn(Request $request): RedirectResponse
    {
        $company = $this->resolveManagedCompany($request);
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $integration = CompanyIntegration::query()->firstOrCreate(
            [
                'company_id' => (string) $company->id,
                'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            ],
            [
                'status' => CompanyIntegration::STATUS_DISCONNECTED,
            ]
        );

        if (! $this->linkedInOAuth->isConfigured()) {
            $integration->forceFill([
                'status' => CompanyIntegration::STATUS_ERROR,
                'last_error' => 'LinkedIn OAuth client credentials are not configured.',
            ])->save();

            return redirect()
                ->route('configuration.index', ['company_id' => (string) $company->id])
                ->with('error', __('ui.integrations.linkedin.not_configured'));
        }

        $statePayload = $this->linkedInOAuth->buildStatePayload($company, (string) $actor->id);
        $state = (string) ($statePayload['nonce'] ?? '');

        Cache::put(
            $this->stateCacheKey($state),
            $statePayload,
            now()->addMinutes(10)
        );

        $integration->forceFill([
            'status' => CompanyIntegration::STATUS_PENDING,
            'last_error' => null,
        ])->save();

        return redirect()->away($this->linkedInOAuth->authorizationUrl($state));
    }

    public function handleLinkedInCallback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'error_description' => ['nullable', 'string'],
        ]);

        $state = (string) $validated['state'];
        $statePayload = Cache::pull($this->stateCacheKey($state));

        if (! is_array($statePayload)) {
            return redirect()->route('configuration.index')
                ->with('error', __('ui.integrations.linkedin.invalid_state'));
        }

        $company = Company::query()->find((string) ($statePayload['company_id'] ?? ''));
        if (! $company instanceof Company) {
            return redirect()->route('configuration.index')
                ->with('error', __('ui.integrations.linkedin.company_missing'));
        }

        $integration = CompanyIntegration::query()->firstOrCreate(
            [
                'company_id' => (string) $company->id,
                'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            ],
            [
                'status' => CompanyIntegration::STATUS_DISCONNECTED,
            ]
        );

        $redirectParams = ['company_id' => (string) $company->id];

        if (trim((string) ($validated['error'] ?? '')) !== '') {
            $message = trim((string) ($validated['error_description'] ?? $validated['error']));

            $integration->forceFill([
                'status' => CompanyIntegration::STATUS_ERROR,
                'last_error' => $message !== '' ? $message : 'LinkedIn authorization was cancelled or denied.',
            ])->save();

            return redirect()->route('configuration.index', $redirectParams)
                ->with('error', __('ui.integrations.linkedin.connect_failed', ['message' => $integration->last_error]));
        }

        try {
            $tokenPayload = $this->linkedInOAuth->exchangeAuthorizationCode((string) ($validated['code'] ?? ''));
            $accessToken = trim((string) ($tokenPayload['access_token'] ?? ''));
            $refreshToken = trim((string) ($tokenPayload['refresh_token'] ?? ''));
            $expiresIn = is_numeric($tokenPayload['expires_in'] ?? null) ? (int) $tokenPayload['expires_in'] : null;
            $profile = $this->linkedInOAuth->fetchBasicProfile($accessToken);

            $integration->forceFill([
                'status' => CompanyIntegration::STATUS_CONNECTED,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken !== '' ? $refreshToken : null,
                'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                'granted_scopes_json' => $this->linkedInOAuth->scopes(),
                'external_account_id' => trim((string) ($profile['sub'] ?? $profile['id'] ?? '')) ?: null,
                'external_account_name' => trim((string) ($profile['name'] ?? '')) ?: null,
                'last_connected_at' => now(),
                'last_error' => null,
                'meta_json' => [
                    'profile' => $profile,
                    'token_type' => (string) ($tokenPayload['token_type'] ?? ''),
                ],
            ])->save();
        } catch (RuntimeException $exception) {
            $integration->forceFill([
                'status' => CompanyIntegration::STATUS_ERROR,
                'last_error' => $exception->getMessage(),
            ])->save();

            return redirect()->route('configuration.index', $redirectParams)
                ->with('error', __('ui.integrations.linkedin.connect_failed', ['message' => $exception->getMessage()]));
        }

        return redirect()->route('configuration.index', $redirectParams)
            ->with('status', __('ui.integrations.linkedin.connected'));
    }

    public function disconnectLinkedIn(Request $request): RedirectResponse
    {
        $company = $this->resolveManagedCompany($request);

        $integration = CompanyIntegration::query()
            ->where('company_id', (string) $company->id)
            ->where('provider', CompanyIntegration::PROVIDER_LINKEDIN)
            ->first();

        if ($integration instanceof CompanyIntegration) {
            $integration->forceFill([
                'status' => CompanyIntegration::STATUS_DISCONNECTED,
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
                'granted_scopes_json' => null,
                'external_account_id' => null,
                'external_account_name' => null,
                'last_error' => null,
                'meta_json' => null,
            ])->save();
        }

        return redirect()
            ->route('configuration.index', ['company_id' => (string) $company->id])
            ->with('status', __('ui.integrations.linkedin.disconnected'));
    }

    public function testLinkedInConnection(Request $request): RedirectResponse
    {
        $company = $this->resolveManagedCompany($request);

        $integration = CompanyIntegration::query()
            ->where('company_id', (string) $company->id)
            ->where('provider', CompanyIntegration::PROVIDER_LINKEDIN)
            ->first();

        if (! $integration instanceof CompanyIntegration) {
            return redirect()
                ->route('configuration.index', ['company_id' => (string) $company->id])
                ->with('error', __('ui.integrations.linkedin.not_connected'));
        }

        try {
            $profile = $this->linkedInIntegrationService->testConnection($integration);

            return redirect()
                ->route('configuration.index', ['company_id' => (string) $company->id])
                ->with('status', __('ui.integrations.linkedin.test_success', [
                    'account' => (string) ($profile['name'] ?? $integration->external_account_name ?? 'LinkedIn'),
                ]));
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('configuration.index', ['company_id' => (string) $company->id])
                ->with('error', __('ui.integrations.linkedin.test_failed', ['message' => $exception->getMessage()]));
        }
    }

    public function saveLinkedInPartnerSettings(Request $request): RedirectResponse
    {
        $company = $this->resolveManagedCompany($request);

        $validated = $request->validate([
            'partner_client_id' => ['nullable', 'string', 'max:255'],
            'partner_client_secret' => ['nullable', 'string', 'max:255'],
            'developer_application_id' => ['nullable', 'string', 'max:255'],
            'company_urn' => ['nullable', 'string', 'max:255'],
            'integration_context' => ['nullable', 'string', 'max:255'],
            'contract_urn' => ['nullable', 'string', 'max:255'],
            'company_name_fallback' => ['nullable', 'string', 'max:255'],
            'company_apply_url_override' => ['nullable', 'url', 'max:2048'],
            'listing_type' => ['nullable', 'string', 'in:BASIC,PREMIUM'],
            'availability' => ['nullable', 'string', 'max:64'],
            'poster_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $integration = CompanyIntegration::query()->firstOrCreate(
            [
                'company_id' => (string) $company->id,
                'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            ],
            [
                'status' => CompanyIntegration::STATUS_DISCONNECTED,
            ]
        );

        $meta = (array) ($integration->meta_json ?? []);
        $meta['partner_job_posting'] = array_filter([
            'partner_client_id' => trim((string) ($validated['partner_client_id'] ?? '')),
            'partner_client_secret' => trim((string) ($validated['partner_client_secret'] ?? '')),
            'developer_application_id' => trim((string) ($validated['developer_application_id'] ?? '')),
            'company_urn' => trim((string) ($validated['company_urn'] ?? '')),
            'integration_context' => trim((string) ($validated['integration_context'] ?? '')),
            'contract_urn' => trim((string) ($validated['contract_urn'] ?? '')),
            'company_name_fallback' => trim((string) ($validated['company_name_fallback'] ?? '')),
            'company_apply_url_override' => trim((string) ($validated['company_apply_url_override'] ?? '')),
            'listing_type' => trim((string) ($validated['listing_type'] ?? 'BASIC')) ?: 'BASIC',
            'availability' => strtoupper(trim((string) ($validated['availability'] ?? ''))),
            'poster_email' => trim((string) ($validated['poster_email'] ?? '')),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $integration->forceFill([
            'meta_json' => $meta,
            'last_error' => null,
        ])->save();

        $readiness = $this->linkedInApiPublisher->partnerReadiness($integration);
        $messageKey = $readiness['ready']
            ? 'ui.integrations.linkedin.partner_settings_saved_ready'
            : 'ui.integrations.linkedin.partner_settings_saved_incomplete';

        return redirect()
            ->route('configuration.index', ['company_id' => (string) $company->id])
            ->with('status', __($messageKey, [
                'missing' => implode(', ', $readiness['missing']),
            ]));
    }

    private function resolveManagedCompany(Request $request): Company
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 403);

        $company = Company::query()->find($companyId);
        abort_unless($company instanceof Company, 404);

        return $company;
    }

    private function stateCacheKey(string $state): string
    {
        return 'linkedin.oauth.state.'.$state;
    }
}
