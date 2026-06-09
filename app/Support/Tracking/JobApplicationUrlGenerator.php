<?php

namespace App\Support\Tracking;

use App\Models\Job;
use App\Models\JobPosting;
use RuntimeException;

class JobApplicationUrlGenerator
{
    public function forFeed(Job $job, string $board, ?string $campaign = null): string
    {
        return $this->forJob($job, $board, 'xml_feed', $campaign);
    }

    public function forJob(Job $job, string $source, string $medium = 'xml_feed', ?string $campaign = null): string
    {
        $job->loadMissing('company');

        if (! $job->company) {
            throw new RuntimeException('Company context not found while generating apply URL.');
        }

        return route('career.apply.entry', array_merge([
            'company' => $job->company,
            'job' => $job,
        ], $this->buildUtmQuery(
            source: $source,
            medium: $medium,
            defaultCampaign: 'job-'.$job->id,
            campaignOverride: $campaign
        )));
    }

    public function forJobPostingTracking(
        JobPosting $posting,
        string $medium = 'job_board',
        ?string $campaign = null
    ): string {
        $posting->loadMissing('job.company');

        if (! $posting->job || ! $posting->job->company) {
            throw new RuntimeException('Job context not found while generating tracking URL.');
        }

        return route('career.multiposting.track', array_merge([
            'company' => $posting->job->company,
            'job' => $posting->job,
            'jobPosting' => $posting,
        ], $this->buildUtmQuery(
            source: (string) $posting->platform,
            medium: $medium,
            defaultCampaign: 'job-'.$posting->job_id,
            campaignOverride: $campaign
        )));
    }

    /**
     * @return array{utm_source: string, utm_medium: string, utm_campaign: string}
     */
    public function buildUtmQuery(
        string $source,
        string $medium = 'xml_feed',
        string $defaultCampaign = 'job',
        ?string $campaignOverride = null
    ): array {
        $utmSource = $this->sanitize($source) ?? 'unknown';
        $utmMedium = $this->sanitize($medium) ?? 'xml_feed';
        $utmCampaign = $this->sanitize($campaignOverride) ?? $this->sanitize($defaultCampaign) ?? 'job';

        return [
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
        ];
    }

    private function sanitize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $trimmed);
    }
}
