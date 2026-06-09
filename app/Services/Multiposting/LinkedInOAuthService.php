<?php

namespace App\Services\Multiposting;

use App\Models\Company;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LinkedInOAuthService
{
    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $scope): string => trim((string) $scope),
            (array) config('services.linkedin.scopes', [])
        )));
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    public function authorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('LinkedIn OAuth is not configured.');
        }

        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => implode(' ', $this->scopes()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('LinkedIn OAuth is not configured.');
        }

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri(),
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                ])
                ->throw();
        } catch (RequestException $exception) {
            $message = trim((string) $exception->response?->body());

            throw new RuntimeException($message !== '' ? $message : $exception->getMessage(), previous: $exception);
        }

        $payload = $response->json();

        if (! is_array($payload) || trim((string) ($payload['access_token'] ?? '')) === '') {
            throw new RuntimeException('LinkedIn token exchange did not return an access token.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchBasicProfile(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->acceptJson()
                ->get('https://api.linkedin.com/v2/userinfo')
                ->throw();
        } catch (RequestException $exception) {
            return [];
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchBasicProfileOrFail(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->acceptJson()
                ->get('https://api.linkedin.com/v2/userinfo')
                ->throw();
        } catch (RequestException $exception) {
            $message = trim((string) $exception->response?->body());

            throw new RuntimeException($message !== '' ? $message : $exception->getMessage(), previous: $exception);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('LinkedIn user profile response was invalid.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatePayload(Company $company, string $actorUserId): array
    {
        return [
            'nonce' => (string) Str::uuid(),
            'company_id' => (string) $company->id,
            'actor_user_id' => $actorUserId,
            'provider' => 'linkedin',
        ];
    }

    private function clientId(): string
    {
        return trim((string) config('services.linkedin.client_id', ''));
    }

    private function clientSecret(): string
    {
        return trim((string) config('services.linkedin.client_secret', ''));
    }

    private function redirectUri(): string
    {
        return trim((string) config('services.linkedin.redirect_uri', ''));
    }
}
