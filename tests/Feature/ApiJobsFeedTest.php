<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Job;
use App\Services\Feeds\AggregatorJobsXmlFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiJobsFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_api_jobs_feed_returns_standardized_indeed_xml_with_required_tags(): void
    {
        $company = Company::query()->create([
            'name' => 'Aggregator Feed Company',
            'slug' => 'aggregator-feed-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $publishedJob = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Syndicated Backend Engineer',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p><strong>API role</strong> with scaling ownership.</p>',
            'location_city' => 'Karachi',
            'location_country' => 'Pakistan',
            'employment_type' => Job::EMPLOYMENT_FULL_TIME,
        ]);

        Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Draft Hidden Role',
            'status' => Job::STATUS_DRAFT,
            'description_html' => '<p>This must not appear in feed.</p>',
        ]);

        $response = $this->get('/api/feeds/indeed.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<publisher>', false);
        $response->assertSee('<target>indeed</target>', false);
        $response->assertSee('<delivery>direct_feed</delivery>', false);
        $response->assertSee('<job>', false);
        $response->assertSee('<title>Syndicated Backend Engineer</title>', false);
        $response->assertSee('<referencenumber>'.$publishedJob->id.'</referencenumber>', false);
        $response->assertSee('<apply_url>', false);
        $response->assertSee('<![CDATA[<p><strong>API role</strong> with scaling ownership.</p>]]>', false);
        $response->assertDontSee('Draft Hidden Role');
    }

    public function test_api_syndication_feed_exposes_glassdoor_coverage_note(): void
    {
        $company = Company::query()->create([
            'name' => 'Glassdoor Feed Company',
            'slug' => 'glassdoor-feed-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Marketplace Analyst',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Shape marketplace reporting.</p>',
        ]);

        $response = $this->get('/api/feeds/syndication.xml');

        $response->assertOk();
        $response->assertSee('<target>syndication</target>', false);
        $response->assertSee('<syndication_partner>glassdoor</syndication_partner>', false);
        $response->assertSee('<tracking_source>glassdoor</tracking_source>', false);
    }

    public function test_api_jobs_feed_cache_refreshes_only_on_status_changes_and_deletes(): void
    {
        $company = Company::query()->create([
            'name' => 'Cache Feed Company',
            'slug' => 'cache-feed-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Original Feed Title',
            'status' => Job::STATUS_PUBLISHED,
            'description_html' => '<p>Original description</p>',
        ]);

        $firstResponse = $this->get('/api/feeds/indeed.xml');
        $firstResponse->assertOk();
        $firstResponse->assertSee('Original Feed Title');
        $this->assertTrue(Cache::has(AggregatorJobsXmlFeedService::CACHE_KEY));

        $job->update([
            'title' => 'Updated Without Status Change',
        ]);

        $secondResponse = $this->get('/api/feeds/indeed.xml');
        $secondResponse->assertOk();
        $secondResponse->assertSee('Original Feed Title');
        $secondResponse->assertDontSee('Updated Without Status Change');

        $job->update([
            'status' => Job::STATUS_DRAFT,
        ]);

        $thirdResponse = $this->get('/api/feeds/indeed.xml');
        $thirdResponse->assertOk();
        $thirdResponse->assertDontSee('Original Feed Title');

        $job->update([
            'status' => Job::STATUS_PUBLISHED,
            'title' => 'Updated After Status Change',
        ]);

        $fourthResponse = $this->get('/api/feeds/indeed.xml');
        $fourthResponse->assertOk();
        $fourthResponse->assertSee('Updated After Status Change');

        $job->delete();

        $fifthResponse = $this->get('/api/feeds/indeed.xml');
        $fifthResponse->assertOk();
        $fifthResponse->assertDontSee('Updated After Status Change');
    }
}
