<?php

namespace App\Services\Feeds;

use App\Models\Company;
use App\Models\Job;
use App\Support\Jobs\JobDescriptionContentRenderer;
use App\Support\Tracking\JobApplicationUrlGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use XMLWriter;

class AggregatorJobsXmlFeedService
{
    public const CACHE_KEY = 'feeds.aggregator.jobs.xml.v1';
    public const SYNDICATION_CACHE_KEY = 'feeds.aggregator.jobs.syndication.xml.v1';

    public function __construct(
        private readonly JobApplicationUrlGenerator $urlGenerator,
        private readonly JobDescriptionContentRenderer $descriptionRenderer
    ) {
    }

    public function getCachedFeedXml(string $board = 'indeed'): string
    {
        $normalizedBoard = $this->normalizeBoard($board);

        return (string) Cache::rememberForever($this->cacheKey($normalizedBoard), function () use ($normalizedBoard): string {
            return $this->buildFeedXml(board: $normalizedBoard);
        });
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::SYNDICATION_CACHE_KEY);
    }

    public function buildFeedXml(
        ?Company $company = null,
        string $board = 'indeed',
        ?string $trackingSource = null,
        ?string $campaign = null
    ): string {
        $normalizedBoard = $this->normalizeBoard($board);
        $applySource = $this->resolveApplySource($normalizedBoard, $trackingSource);

        $jobs = Job::withoutGlobalScopes()
            ->with(['company:id,name,slug,status', 'descriptionBlocks'])
            ->where('status', Job::STATUS_PUBLISHED)
            ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE))
            ->when($company !== null, fn ($query) => $query->where('company_id', $company->id))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);

        $writer->startElement('source');
        $writer->writeElement('publisher', (string) config('app.name', 'CarriereOS'));
        $writer->writeElement('publisherurl', (string) rtrim((string) config('app.url', ''), '/'));
        $writer->writeElement('target', $normalizedBoard);
        $writer->writeElement('delivery', $this->deliveryMode($normalizedBoard));
        $writer->writeElement('tracking_source', $applySource);
        if ($company !== null) {
            $writer->writeElement('company', (string) $company->name);
            $writer->writeElement('companyslug', (string) $company->slug);
        }
        if ($normalizedBoard === 'syndication') {
            $writer->writeElement('syndication_partner', 'glassdoor');
            $writer->writeElement('coverage_note', 'Glassdoor coverage is currently delivered through the syndication feed contract.');
        }
        $writer->writeElement('lastBuildDate', now()->toRfc7231String());

        foreach ($jobs as $job) {
            $writer->startElement('job');
            $writer->writeElement('id', (string) $job->id);
            $writer->writeElement('title', (string) $job->title);
            $writer->writeElement('date', ($job->created_at ?? now())->toDateString());
            $writer->writeElement('referencenumber', (string) $job->id);

            $applyUrl = $this->urlGenerator->forFeed($job, $applySource, $campaign);
            $writer->writeElement('url', $applyUrl);
            $writer->writeElement('apply_url', $applyUrl);
            $writer->writeElement('status', (string) $job->status);
            $writer->writeElement('company', (string) ($job->company?->name ?? ''));
            $writer->writeElement('city', (string) ($job->location_city ?? $job->location ?? ''));
            $writer->writeElement('state', '');
            $writer->writeElement('country', (string) ($job->location_country ?? ''));
            $writer->writeElement('postalcode', (string) ($job->location_postal_code ?? ''));
            $writer->writeElement('streetaddress', (string) ($job->location_street ?? ''));

            $employmentType = (string) ($job->employment_type ?? Job::EMPLOYMENT_FULL_TIME);
            $writer->writeElement('jobtype', $this->mapJobType($employmentType));
            $writer->writeElement('employment_type', $employmentType);

            $salaryMin = $this->toNullableInt($job->salary_min);
            $salaryMax = $this->toNullableInt($job->salary_max) ?? $this->toNullableInt($job->salary_budget_max);
            $currency = strtoupper((string) ($job->salary_currency ?? ''));
            if ($salaryMin !== null || $salaryMax !== null || $currency !== '') {
                $writer->startElement('salary');
                if ($salaryMin !== null) {
                    $writer->writeElement('min', (string) $salaryMin);
                }
                if ($salaryMax !== null) {
                    $writer->writeElement('max', (string) $salaryMax);
                }
                if ($currency !== '') {
                    $writer->writeElement('currency', $currency);
                }
                $writer->endElement();
            }

            $writer->startElement('description');
            $writer->writeCData($this->descriptionRenderer->renderHtml($job));
            $writer->endElement();
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return (string) $writer->outputMemory();
    }

    private function cacheKey(string $board): string
    {
        return $board === 'syndication'
            ? self::SYNDICATION_CACHE_KEY
            : self::CACHE_KEY;
    }

    private function normalizeBoard(string $board): string
    {
        $normalized = Str::of($board)->trim()->lower()->value();

        return match ($normalized) {
            'glassdoor', 'syndication' => 'syndication',
            default => 'indeed',
        };
    }

    private function resolveApplySource(string $board, ?string $trackingSource): string
    {
        $normalizedSource = Str::of((string) $trackingSource)->trim()->lower()->value();

        if ($normalizedSource !== '') {
            return $normalizedSource;
        }

        return $board === 'syndication' ? 'glassdoor' : 'indeed';
    }

    private function deliveryMode(string $board): string
    {
        return $board === 'syndication'
            ? 'syndication_feed'
            : 'direct_feed';
    }

    private function mapJobType(string $employmentType): string
    {
        return match ($employmentType) {
            Job::EMPLOYMENT_PART_TIME => 'parttime',
            Job::EMPLOYMENT_CONTRACT => 'contract',
            default => 'fulltime',
        };
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
