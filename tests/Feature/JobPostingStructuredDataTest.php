<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPostingStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_career_job_page_renders_complete_server_side_json_ld_jobposting_schema(): void
    {
        $company = Company::query()->create([
            'name' => 'Schema Career Company',
            'slug' => 'schema-career-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Backend Platform Engineer',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Build and operate distributed hiring APIs.</p>',
            'location_street' => '221B Baker Street',
            'location_city' => 'London',
            'location_country' => 'UK',
            'location_postal_code' => 'NW1',
            'employment_type' => Job::EMPLOYMENT_FULL_TIME,
            'salary_min' => 95000,
            'salary_max' => 125000,
            'salary_currency' => 'USD',
        ]);

        $response = $this->get(route('career.show', [
            'company' => $company->slug,
            'job' => $job->id,
        ]));

        $response->assertOk();
        $response->assertSee('<script type="application/ld+json">', false);

        $schema = $this->extractJobPostingSchema((string) $response->getContent());

        $this->assertSame('https://schema.org', (string) ($schema['@context'] ?? ''));
        $this->assertSame('JobPosting', (string) ($schema['@type'] ?? ''));
        $this->assertSame('Backend Platform Engineer', (string) ($schema['title'] ?? ''));
        $this->assertSame('<p>Build and operate distributed hiring APIs.</p>', (string) ($schema['description'] ?? ''));
        $this->assertSame('FULL_TIME', (string) ($schema['employmentType'] ?? ''));
        $this->assertTrue((bool) ($schema['directApply'] ?? false));
        $this->assertSame('Schema Career Company', (string) data_get($schema, 'hiringOrganization.name'));
        $this->assertSame('London', (string) data_get($schema, 'jobLocation.address.addressLocality'));
        $this->assertSame('UK', (string) data_get($schema, 'jobLocation.address.addressCountry'));
        $this->assertSame('USD', (string) data_get($schema, 'baseSalary.currency'));
        $this->assertSame(95000, (int) data_get($schema, 'baseSalary.value.minValue'));
        $this->assertSame(125000, (int) data_get($schema, 'baseSalary.value.maxValue'));
        $this->assertSame(route('career.show', ['company' => $company->slug, 'job' => $job->id]), (string) ($schema['url'] ?? ''));
    }

    public function test_public_job_page_uses_description_blocks_for_schema_fallback_in_stable_order(): void
    {
        $company = Company::query()->create([
            'name' => 'Schema Public Company',
            'slug' => 'schema-public-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Product Analyst',
            'status' => Job::STATUS_PUBLISHED,
            'location' => 'Hybrid',
            'employment_type' => Job::EMPLOYMENT_PART_TIME,
        ]);

        JobDescriptionBlock::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'block_type' => 'overview',
            'block_content_json' => ['text' => 'Analyze product funnels and present measurable insights.'],
            'display_order' => 1,
        ]);

        JobDescriptionBlock::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'block_type' => 'requirements',
            'block_content_json' => ['text' => 'Comfort with SQL and experimentation workflows.'],
            'display_order' => 2,
        ]);

        $response = $this->get(route('public.jobs.show', ['job' => $job->id]));

        $response->assertOk();
        $response->assertSee('<script type="application/ld+json">', false);

        $schema = $this->extractJobPostingSchema((string) $response->getContent());
        $description = (string) ($schema['description'] ?? '');

        $this->assertSame('PART_TIME', (string) ($schema['employmentType'] ?? ''));
        $this->assertStringContainsString('Overview', $description);
        $this->assertStringContainsString('Analyze product funnels', $description);
        $this->assertStringContainsString('Requirements', $description);
        $this->assertStringContainsString('experimentation workflows', $description);
        $overviewPosition = strpos($description, 'Overview');
        $requirementsPosition = strpos($description, 'Requirements');
        $this->assertIsInt($overviewPosition);
        $this->assertIsInt($requirementsPosition);
        $this->assertTrue($overviewPosition < $requirementsPosition);
        $this->assertSame(route('public.jobs.show', ['job' => $job->id]), (string) ($schema['url'] ?? ''));
    }

    public function test_remote_job_schema_sets_telecommute_flags_and_country_requirement(): void
    {
        $company = Company::query()->create([
            'name' => 'Remote Schema Company',
            'slug' => 'remote-schema-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Remote Staff Engineer',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Lead remote-first platform initiatives.</p>',
            'location' => 'Remote',
            'location_country' => 'Pakistan',
            'employment_type' => Job::EMPLOYMENT_CONTRACT,
        ]);

        $response = $this->get(route('career.show', [
            'company' => $company->slug,
            'job' => $job->id,
        ]));

        $response->assertOk();

        $schema = $this->extractJobPostingSchema((string) $response->getContent());

        $this->assertSame('TELECOMMUTE', (string) ($schema['jobLocationType'] ?? ''));
        $this->assertSame('Country', (string) data_get($schema, 'applicantLocationRequirements.@type'));
        $this->assertSame('Pakistan', (string) data_get($schema, 'applicantLocationRequirements.name'));
        $this->assertSame('CONTRACTOR', (string) ($schema['employmentType'] ?? ''));
    }

    public function test_schema_omits_base_salary_when_salary_metadata_is_incomplete(): void
    {
        $company = Company::query()->create([
            'name' => 'Salary Optional Company',
            'slug' => 'salary-optional-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Community Manager',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Run community and lifecycle campaigns.</p>',
            'location_city' => 'Karachi',
            'employment_type' => Job::EMPLOYMENT_FULL_TIME,
            'salary_min' => 50000,
        ]);

        $response = $this->get(route('public.jobs.show', ['job' => $job->id]));
        $response->assertOk();

        $schema = $this->extractJobPostingSchema((string) $response->getContent());

        $this->assertArrayNotHasKey('baseSalary', $schema);
        $this->assertSame(route('public.jobs.show', ['job' => $job->id]), (string) ($schema['url'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJobPostingSchema(string $html): array
    {
        $matched = preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $html, $matches);
        $this->assertSame(1, $matched);

        $decoded = json_decode((string) ($matches[1] ?? ''), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
