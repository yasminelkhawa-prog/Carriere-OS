<?php

namespace App\Services\Integrations;

use App\Models\CompanyIntegration;
use App\Services\Multiposting\LinkedInOAuthService;
use Illuminate\Support\Carbon;
use RuntimeException;

class LinkedInCompanyIntegrationService
{
    public function __construct(
        private readonly LinkedInOAuthService $linkedInOAuth
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function testConnection(CompanyIntegration $integration): array
    {
        if ($integration->provider !== CompanyIntegration::PROVIDER_LINKEDIN) {
            throw new RuntimeException('Unsupported integration provider.');
        }

        if (! $integration->isConnected()) {
            throw new RuntimeException('LinkedIn is not connected for this company yet.');
        }

        if ($this->isExpired($integration)) {
            $integration->forceFill([
                'status' => CompanyIntegration::STATUS_EXPIRED,
                'last_error' => 'LinkedIn access token has expired. Please reconnect the account.',
            ])->save();

            throw new RuntimeException('LinkedIn access token has expired. Please reconnect the account.');
        }

        $profile = $this->linkedInOAuth->fetchBasicProfileOrFail((string) $integration->access_token);

        $integration->forceFill([
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'external_account_id' => trim((string) ($profile['sub'] ?? $profile['id'] ?? '')) ?: $integration->external_account_id,
            'external_account_name' => trim((string) ($profile['name'] ?? '')) ?: $integration->external_account_name,
            'last_used_at' => now(),
            'last_error' => null,
            'meta_json' => array_merge((array) ($integration->meta_json ?? []), [
                'profile' => $profile,
                'last_verified_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $profile;
    }

    private function isExpired(CompanyIntegration $integration): bool
    {
        return $integration->token_expires_at instanceof Carbon
            && $integration->token_expires_at->isPast();
    }
}
