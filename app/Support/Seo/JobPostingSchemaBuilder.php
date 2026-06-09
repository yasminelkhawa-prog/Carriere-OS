<?php

namespace App\Support\Seo;

use App\Models\Company;
use App\Models\Job;
use App\Support\Jobs\JobDescriptionContentRenderer;
use Illuminate\Support\Str;

class JobPostingSchemaBuilder
{
    public function __construct(
        private readonly JobDescriptionContentRenderer $descriptionRenderer
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Job $job, Company $company, ?string $publicUrl = null): array
    {
        $employmentType = $this->mapEmploymentType(
            (string) ($job->employment_type ?? Job::EMPLOYMENT_FULL_TIME)
        );

        $isRemote = $this->isRemote($job);
        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => (string) $job->title,
            'description' => $this->descriptionRenderer->renderHtml($job),
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => (string) $company->name,
                'value' => (string) $job->id,
            ],
            'datePosted' => ($job->created_at ?? now())->toAtomString(),
            'employmentType' => $employmentType,
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => (string) $company->name,
                'sameAs' => route('career.index', ['company' => $company]),
            ],
            'jobLocation' => $this->resolveJobLocation($job),
            'jobLocationType' => $isRemote ? 'TELECOMMUTE' : null,
            'applicantLocationRequirements' => $this->resolveApplicantLocationRequirements($job, $isRemote),
            'baseSalary' => $this->resolveBaseSalary($job),
            'directApply' => true,
            'url' => $publicUrl ?? route('career.show', ['company' => $company, 'job' => $job]),
        ];

        return $this->removeNulls($payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveJobLocation(Job $job): ?array
    {
        $street = $this->trimmedOrNull($job->location_street);
        $city = $this->trimmedOrNull($job->location_city);
        $country = $this->trimmedOrNull($job->location_country);
        $postalCode = $this->trimmedOrNull($job->location_postal_code);
        $fallbackLocation = $this->trimmedOrNull($job->location);

        if ($city === null && $fallbackLocation !== null && ! $this->isRemote($job)) {
            $city = $fallbackLocation;
        }

        $address = $this->removeNulls([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'addressLocality' => $city,
            'postalCode' => $postalCode,
            'addressCountry' => $country,
        ]);

        if (! isset($address['addressLocality']) && ! isset($address['addressCountry']) && ! isset($address['streetAddress'])) {
            return null;
        }

        return [
            '@type' => 'Place',
            'address' => $address,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveApplicantLocationRequirements(Job $job, bool $isRemote): ?array
    {
        if (! $isRemote) {
            return null;
        }

        $country = $this->trimmedOrNull($job->location_country);
        if ($country === null) {
            return null;
        }

        return [
            '@type' => 'Country',
            'name' => $country,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveBaseSalary(Job $job): ?array
    {
        $currency = strtoupper((string) ($this->trimmedOrNull($job->salary_currency) ?? ''));
        $salaryMin = $this->toNullableInt($job->salary_min);
        $salaryMax = $this->toNullableInt($job->salary_max) ?? $this->toNullableInt($job->salary_budget_max);

        if ($currency === '' || ($salaryMin === null && $salaryMax === null)) {
            return null;
        }

        $quantitativeValue = $this->removeNulls([
            '@type' => 'QuantitativeValue',
            'minValue' => $salaryMin,
            'maxValue' => $salaryMax,
            'unitText' => 'YEAR',
        ]);

        return [
            '@type' => 'MonetaryAmount',
            'currency' => $currency,
            'value' => $quantitativeValue,
        ];
    }

    private function isRemote(Job $job): bool
    {
        $signals = [
            (string) ($job->location ?? ''),
            (string) ($job->location_city ?? ''),
            (string) ($job->location_street ?? ''),
        ];

        return collect($signals)
            ->contains(fn (string $signal): bool => str_contains(Str::lower($signal), 'remote'));
    }

    private function mapEmploymentType(string $employmentType): string
    {
        return match ($employmentType) {
            Job::EMPLOYMENT_PART_TIME => 'PART_TIME',
            Job::EMPLOYMENT_CONTRACT => 'CONTRACTOR',
            default => 'FULL_TIME',
        };
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function removeNulls(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->removeNulls($value);
            }

            if ($value === null) {
                continue;
            }

            if (is_array($value) && $value === []) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
