<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CareerFeedFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_indeed_feed_returns_standardized_xml_with_required_tags(): void
    {
        $company = Company::query()->create([
            'name' => 'Feed Foundation Company',
            'slug' => 'feed-foundation-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $published = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Senior Laravel Engineer',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Build and scale APIs.</p>',
            'location_street' => '123 Main St',
            'location_city' => 'Karachi',
            'location_country' => 'Pakistan',
            'location_postal_code' => '75500',
            'employment_type' => Job::EMPLOYMENT_FULL_TIME,
            'salary_min' => 90000,
            'salary_max' => 130000,
            'salary_currency' => 'USD',
        ]);

        Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Draft Role',
            'status' => Job::STATUS_DRAFT,
        ]);

        $response = $this->get(route('career.feed.indeed', [
            'company' => $company->slug,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('indeed', (string) $xml->target);
        $this->assertSame('direct_feed', (string) $xml->delivery);
        $this->assertSame($company->name, (string) $xml->company);
        $this->assertCount(1, $xml->job);

        $row = $xml->job[0];
        $this->assertSame((string) $published->id, (string) $row->id);
        $this->assertSame((string) $published->id, (string) $row->referencenumber);
        $this->assertSame('Senior Laravel Engineer', (string) $row->title);
        $this->assertSame('Karachi', (string) $row->city);
        $this->assertSame('USD', (string) $row->salary->currency);
        $this->assertSame('fulltime', (string) $row->jobtype);

        $applyUrl = (string) $row->apply_url;
        $this->assertStringContainsString('/careers/feed-foundation-company/apply/'.$published->id, $applyUrl);
        $this->assertStringContainsString('utm_source=indeed', $applyUrl);
        $this->assertStringContainsString('utm_medium=xml_feed', $applyUrl);
        $this->assertStringContainsString('utm_campaign=job-'.$published->id, $applyUrl);
    }

    public function test_company_syndication_feed_documents_glassdoor_coverage(): void
    {
        $company = Company::query()->create([
            'name' => 'Syndication Company',
            'slug' => 'syndication-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $published = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Distributed Product Designer',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Own end-to-end product flows.</p>',
            'location_city' => 'Lahore',
            'location_country' => 'Pakistan',
            'employment_type' => Job::EMPLOYMENT_PART_TIME,
        ]);

        $response = $this->get(route('career.feed.syndication', [
            'company' => $company->slug,
        ]));

        $response->assertOk();

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('syndication', (string) $xml->target);
        $this->assertSame('glassdoor', (string) $xml->syndication_partner);

        $row = $xml->job[0];
        $this->assertSame((string) $published->id, (string) $row->id);
        $this->assertSame('parttime', (string) $row->jobtype);
        $this->assertStringContainsString('utm_source=glassdoor', (string) $row->apply_url);
    }
}
