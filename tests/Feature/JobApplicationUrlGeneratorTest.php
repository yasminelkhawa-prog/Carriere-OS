<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobPosting;
use App\Support\Tracking\JobApplicationUrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApplicationUrlGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_builds_board_specific_apply_url_with_xml_feed_medium(): void
    {
        $company = Company::query()->create([
            'name' => 'UTM Utility Company',
            'slug' => 'utm-utility-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Platform Engineer',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $generator = app(JobApplicationUrlGenerator::class);
        $url = $generator->forFeed($job, 'indeed');

        $this->assertStringContainsString('/careers/utm-utility-company/apply/'.$job->id, $url);
        $this->assertStringContainsString('utm_source=indeed', $url);
        $this->assertStringContainsString('utm_medium=xml_feed', $url);
    }

    public function test_generator_builds_job_posting_tracking_url_with_job_board_medium(): void
    {
        $company = Company::query()->create([
            'name' => 'Tracking Utility Company',
            'slug' => 'tracking-utility-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Growth Manager',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $posting = JobPosting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'platform' => 'linkedin',
            'status' => JobPosting::STATUS_PUBLISHED,
        ]);

        $generator = app(JobApplicationUrlGenerator::class);
        $url = $generator->forJobPostingTracking($posting, 'job_board');

        $this->assertStringContainsString('/careers/tracking-utility-company/jobs/'.$job->id.'/track/'.$posting->id, $url);
        $this->assertStringContainsString('utm_source=linkedin', $url);
        $this->assertStringContainsString('utm_medium=job_board', $url);
    }
}
