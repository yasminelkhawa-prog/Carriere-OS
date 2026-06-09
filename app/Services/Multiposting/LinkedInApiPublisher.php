<?php

namespace App\Services\Multiposting;

use App\Models\CompanyIntegration;
use App\Models\Job;
use App\Models\JobPosting;
use App\Support\Jobs\JobDescriptionContentRenderer;
use App\Support\Tracking\JobApplicationUrlGenerator;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LinkedInApiPublisher
{
    private const META_KEY = 'partner_job_posting';

    public function __construct(
        private readonly JobDescriptionContentRenderer $descriptionRenderer,
        private readonly JobApplicationUrlGenerator $applicationUrlGenerator
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->jobPostingEndpoint() !== ''
            && $this->tokenEndpoint() !== ''
            && $this->apiVersion() !== '';
    }

    public function isReadyForPosting(JobPosting $posting): bool
    {
        $integration = $this->resolveIntegration($posting);

        return $this->partnerReadiness($integration)['ready'];
    }

    /**
     * @return array{ready: bool, missing: array<int, string>, settings: array<string, mixed>}
     */
    public function partnerReadiness(?CompanyIntegration $integration): array
    {
        $settings = $this->partnerSettings($integration);
        $missing = [];

        if (($settings['partner_client_id'] ?? '') === '') {
            $missing[] = 'partner_client_id';
        }

        if (($settings['partner_client_secret'] ?? '') === '') {
            $missing[] = 'partner_client_secret';
        }

        if (($settings['company_urn'] ?? '') === ''
            && ($settings['integration_context'] ?? '') === ''
            && ($settings['company_name_fallback'] ?? '') === '') {
            $missing[] = 'company_or_integration_context';
        }

        return [
            'ready' => $missing === [] && $this->isConfigured(),
            'missing' => $missing,
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function partnerSettings(?CompanyIntegration $integration): array
    {
        $meta = (array) ($integration?->meta_json ?? []);
        $stored = (array) ($meta[self::META_KEY] ?? []);

        $companyUrn = $this->normalizeUrn(
            (string) ($stored['company_urn'] ?? $stored['company_id'] ?? ''),
            'urn:li:company:'
        );
        $integrationContext = $this->normalizeUrn(
            (string) ($stored['integration_context'] ?? $stored['organization_urn'] ?? $stored['organization_id'] ?? ''),
            'urn:li:organization:'
        );
        $contractUrn = $this->normalizeUrn(
            (string) ($stored['contract_urn'] ?? $stored['contract_id'] ?? ''),
            'urn:li:contract:'
        );

        return [
            'partner_client_id' => trim((string) ($stored['partner_client_id'] ?? config('services.linkedin.partner_client_id', ''))),
            'partner_client_secret' => trim((string) ($stored['partner_client_secret'] ?? config('services.linkedin.partner_client_secret', ''))),
            'developer_application_id' => trim((string) ($stored['developer_application_id'] ?? '')),
            'company_urn' => $companyUrn,
            'integration_context' => $integrationContext,
            'contract_urn' => $contractUrn,
            'company_name_fallback' => trim((string) ($stored['company_name_fallback'] ?? '')),
            'company_apply_url_override' => trim((string) ($stored['company_apply_url_override'] ?? '')),
            'listing_type' => strtoupper(trim((string) ($stored['listing_type'] ?? 'BASIC'))),
            'availability' => strtoupper(trim((string) ($stored['availability'] ?? ''))),
            'poster_email' => trim((string) ($stored['poster_email'] ?? '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(JobPosting $posting): array
    {
        $integration = $this->resolveConnectedIntegration($posting);
        $readiness = $this->partnerReadiness($integration);

        if (! $readiness['ready']) {
            throw new RuntimeException(
                'LinkedIn partner configuration is incomplete for this company. Missing: '.implode(', ', $readiness['missing'])
            );
        }

        $payload = $this->buildPayload($posting, $readiness['settings']);
        $accessToken = $this->issuePartnerAccessToken($integration, $readiness['settings']);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->withHeaders($this->requestHeaders())
                ->timeout((int) config('services.linkedin.job_posting_timeout_seconds', 20))
                ->post($this->jobPostingEndpoint(), $payload)
                ->throw();
        } catch (RequestException $exception) {
            $message = trim((string) $exception->response?->body());

            throw new RuntimeException($message !== '' ? $message : $exception->getMessage(), previous: $exception);
        }

        $responsePayload = $this->arrayPayload($response->json());
        $firstElement = (array) ($responsePayload['elements'][0] ?? []);
        $error = (array) ($firstElement['error'] ?? []);
        if ($error !== []) {
            throw new RuntimeException((string) ($error['message'] ?? 'LinkedIn job submission failed.'));
        }

        $taskUrn = trim((string) ($firstElement['id'] ?? ''));
        if ($taskUrn === '') {
            throw new RuntimeException('LinkedIn did not return a task id for the submitted job.');
        }

        $existingMeta = (array) ($integration->meta_json ?? []);
        $existingPartnerMeta = (array) ($existingMeta[self::META_KEY] ?? []);

        $integration->forceFill([
            'last_used_at' => now(),
            'last_error' => null,
            'meta_json' => array_merge($existingMeta, [
                self::META_KEY => array_merge($existingPartnerMeta, [
                    'last_submission_at' => now()->toIso8601String(),
                    'last_submission_task_urn' => $taskUrn,
                    'last_submission_response' => $responsePayload,
                ]),
            ]),
        ])->save();

        return [
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'external_id' => $taskUrn,
            'external_url' => '',
            'payload' => $payload,
            'response' => $responsePayload,
            'endpoint' => $this->jobPostingEndpoint(),
            'task_urn' => $taskUrn,
            'task_status' => 'IN_PROGRESS',
            'pending_sync' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchTaskStatus(JobPosting $posting, string $taskUrn): array
    {
        $integration = $this->resolveConnectedIntegration($posting);
        $settings = $this->partnerSettings($integration);
        $accessToken = $this->issuePartnerAccessToken($integration, $settings);

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->withHeaders([
                    'Linkedin-Version' => $this->apiVersion(),
                    'X-RestLi-Protocol-Version' => '2.0.0',
                ])
                ->timeout((int) config('services.linkedin.job_posting_timeout_seconds', 20))
                ->get($this->taskStatusEndpoint(), [
                    'ids' => 'List('.$taskUrn.')',
                ])
                ->throw();
        } catch (RequestException $exception) {
            $message = trim((string) $exception->response?->body());

            throw new RuntimeException($message !== '' ? $message : $exception->getMessage(), previous: $exception);
        }

        $responsePayload = $this->arrayPayload($response->json());
        $taskPayload = (array) data_get($responsePayload, 'results.'.$taskUrn, []);
        $status = strtoupper(trim((string) ($taskPayload['status'] ?? '')));

        return [
            'external_id' => trim((string) ($taskPayload['jobPosting'] ?? $taskUrn)),
            'external_url' => '',
            'task_urn' => $taskUrn,
            'task_status' => $status,
            'error_message' => trim((string) ($taskPayload['errorMessage'] ?? '')),
            'response' => $responsePayload,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function buildPayload(JobPosting $posting, array $settings = []): array
    {
        $posting->loadMissing('job.company');

        $job = $posting->job;
        if (! $job instanceof Job || ! $job->company) {
            throw new RuntimeException('Job context not found for LinkedIn API push.');
        }

        $job->loadMissing('descriptionBlocks');

        $description = trim((string) $posting->ai_generated_content) !== ''
            ? (string) $posting->ai_generated_content
            : $this->descriptionRenderer->renderHtml($job);

        $textDescription = trim(strip_tags($description));
        if ($textDescription === '') {
            throw new RuntimeException('LinkedIn publishing requires a non-empty job description.');
        }

        $location = $this->resolveLocation($job);
        $employmentStatus = $this->mapEmploymentStatus((string) ($job->employment_type ?? Job::EMPLOYMENT_FULL_TIME));
        $applyUrl = $this->resolveApplyUrl($job, $settings);
        $workplaceTypes = $this->resolveWorkplaceTypes($job, $location);
        $listingType = strtoupper((string) ($settings['listing_type'] ?? 'BASIC'));

        $element = array_filter([
            'externalJobPostingId' => (string) $posting->id,
            'listingType' => in_array($listingType, ['BASIC', 'PREMIUM'], true) ? $listingType : 'BASIC',
            'title' => Str::limit((string) $job->title, 200, ''),
            'description' => $description,
            'companyApplyUrl' => $applyUrl,
            'employmentStatus' => $employmentStatus,
            'listedAt' => CarbonImmutable::now()->getTimestampMs(),
            'jobPostingOperationType' => $posting->status === JobPosting::STATUS_PUBLISHED ? 'UPDATE' : 'CREATE',
            'location' => $location,
            'workplaceTypes' => $workplaceTypes !== [] ? $workplaceTypes : null,
            'company' => (string) ($settings['company_urn'] ?? ''),
            'integrationContext' => (string) ($settings['integration_context'] ?? ''),
            'companyName' => (string) ($settings['company_urn'] ?? '') === '' && (string) ($settings['integration_context'] ?? '') === ''
                ? ((string) ($settings['company_name_fallback'] ?? '') !== '' ? (string) $settings['company_name_fallback'] : (string) $job->company->name)
                : null,
            'contract' => (string) ($settings['contract_urn'] ?? '') !== '' ? (string) $settings['contract_urn'] : null,
            'availability' => (string) ($settings['availability'] ?? '') !== '' ? (string) $settings['availability'] : null,
            'posterEmail' => (string) ($settings['poster_email'] ?? '') !== '' ? (string) $settings['poster_email'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return [
            'elements' => [$element],
        ];
    }

    private function resolveIntegration(JobPosting $posting): ?CompanyIntegration
    {
        return CompanyIntegration::query()
            ->where('company_id', (string) $posting->company_id)
            ->where('provider', CompanyIntegration::PROVIDER_LINKEDIN)
            ->first();
    }

    private function resolveConnectedIntegration(JobPosting $posting): CompanyIntegration
    {
        $integration = $this->resolveIntegration($posting);

        if (! $integration instanceof CompanyIntegration || ! $integration->isConnected()) {
            throw new RuntimeException('LinkedIn company integration is not connected.');
        }

        return $integration;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function issuePartnerAccessToken(CompanyIntegration $integration, array $settings): string
    {
        $clientId = trim((string) ($settings['partner_client_id'] ?? ''));
        $clientSecret = trim((string) ($settings['partner_client_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('LinkedIn partner client credentials are missing.');
        }

        $cacheKey = 'linkedin.partner-token.'.sha1($integration->id.'|'.$clientId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && trim((string) ($cached['access_token'] ?? '')) !== '') {
            return (string) $cached['access_token'];
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.linkedin.job_posting_timeout_seconds', 20))
                ->post($this->tokenEndpoint(), [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ])
                ->throw();
        } catch (RequestException $exception) {
            $message = trim((string) $exception->response?->body());

            throw new RuntimeException($message !== '' ? $message : 'LinkedIn partner token exchange failed.', previous: $exception);
        }

        $payload = $this->arrayPayload($response->json());
        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('LinkedIn client credentials flow did not return an access token.');
        }

        $ttl = max(60, ((int) ($payload['expires_in'] ?? 1800)) - 60);
        Cache::put($cacheKey, [
            'access_token' => $accessToken,
        ], now()->addSeconds($ttl));

        return $accessToken;
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        return [
            'x-restli-method' => 'batch_create',
            'Linkedin-Version' => $this->apiVersion(),
            'X-RestLi-Protocol-Version' => '2.0.0',
        ];
    }

    private function resolveLocation(Job $job): string
    {
        $country = trim((string) ($job->location_country ?? ''));
        $city = trim((string) ($job->location_city ?? ''));
        $fallback = trim((string) ($job->location ?? ''));

        $workplaceTypes = $this->resolveWorkplaceTypes($job, $fallback !== '' ? $fallback : $country);
        if ($workplaceTypes === ['remote'] && $country !== '') {
            return $country;
        }

        foreach ([$fallback, $city !== '' && $country !== '' ? $city.', '.$country : '', $city, $country] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        throw new RuntimeException('LinkedIn publishing requires a recognizable job location.');
    }

    /**
     * @return array<int, string>
     */
    private function resolveWorkplaceTypes(Job $job, string $locationSignal): array
    {
        $haystack = Str::lower(implode(' ', array_filter([
            (string) $locationSignal,
            (string) ($job->location ?? ''),
            (string) $job->description_html,
        ])));

        if (Str::contains($haystack, 'remote')) {
            return ['remote'];
        }

        if (Str::contains($haystack, 'hybrid')) {
            return ['hybrid'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolveApplyUrl(Job $job, array $settings): string
    {
        $override = trim((string) ($settings['company_apply_url_override'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        return $this->applicationUrlGenerator->forJob($job, 'linkedin', 'linkedin_partner');
    }

    private function mapEmploymentStatus(string $employmentType): string
    {
        return match ($employmentType) {
            Job::EMPLOYMENT_PART_TIME => 'PART_TIME',
            Job::EMPLOYMENT_CONTRACT => 'CONTRACT',
            default => 'FULL_TIME',
        };
    }

    private function normalizeUrn(string $value, string $prefix): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (Str::startsWith($trimmed, $prefix)) {
            return $trimmed;
        }

        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return $prefix.$trimmed;
        }

        return $trimmed;
    }

    /**
     * @param mixed $payload
     * @return array<string, mixed>
     */
    private function arrayPayload(mixed $payload): array
    {
        return is_array($payload) ? $payload : [];
    }

    private function jobPostingEndpoint(): string
    {
        return trim((string) config('services.linkedin.job_posting_endpoint', ''));
    }

    private function taskStatusEndpoint(): string
    {
        return trim((string) config('services.linkedin.job_posting_task_status_endpoint', ''));
    }

    private function tokenEndpoint(): string
    {
        return trim((string) config('services.linkedin.oauth_access_token_url', ''));
    }

    private function apiVersion(): string
    {
        return trim((string) config('services.linkedin.job_posting_version', ''));
    }
}
