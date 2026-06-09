<?php

namespace Tests\Feature;

use App\Jobs\RunJobBoardAutomationJob;
use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\JobPosting;
use App\Models\JobPostingPublishAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultipostingAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_queues_rpa_worker_for_automation_platform_when_enabled(): void
    {
        config()->set('multiposting.automation.enabled', true);
        config()->set('multiposting.automation.platforms', ['linkedin']);
        config()->set('multiposting.automation.queue', 'default');

        $company = $this->createCompany('automation-company');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'Automation Engineer');

        CompanyIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'access_token' => 'test-token',
            'last_connected_at' => now(),
        ]);

        $posting = JobPosting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'platform' => 'linkedin',
            'status' => JobPosting::STATUS_READY,
            'ai_generated_content' => 'Prepared content for posting.',
            'tracking_url' => null,
            'clicks_count' => 0,
            'posted_at' => null,
        ]);

        Queue::fake();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.publish', ['job' => $job->id, 'platform' => 'linkedin']));

        $response->assertRedirect();
        $response->assertSessionHas('status', __('jobs.multiposting.flash.publish_queued', [
            'platform' => __('jobs.multiposting.platforms.linkedin'),
        ]));

        Queue::assertPushed(RunJobBoardAutomationJob::class, function (RunJobBoardAutomationJob $queuedJob) use ($posting, $admin): bool {
            return (string) $queuedJob->jobPostingId === (string) $posting->id
                && $queuedJob->publishAttemptId !== ''
                && (string) $queuedJob->actorUserId === (string) $admin->id;
        });

        $posting->refresh();
        $this->assertSame(JobPosting::STATUS_PUBLISHING, (string) $posting->status);
        $this->assertNull($posting->tracking_url);
        $this->assertNull($posting->posted_at);
        $this->assertSame(JobPostingPublishAttempt::STATUS_QUEUED, (string) $posting->last_publish_status);
        $this->assertSame('async_automation', (string) $posting->last_execution_mode);
        $this->assertDatabaseHas('job_posting_publish_attempts', [
            'job_posting_id' => $posting->id,
            'status' => JobPostingPublishAttempt::STATUS_QUEUED,
            'execution_mode' => 'async_automation',
        ]);
    }

    private function createCompany(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.strtoupper($slug),
            'slug' => $slug,
            'status' => Company::STATUS_ACTIVE,
        ]);
    }

    private function createCompanyAdmin(Company $company): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }

    private function createPublishedJobWithPipeline(Company $company, string $title): Job
    {
        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => $title,
            'status' => Job::STATUS_PUBLISHED,
            'location' => 'Remote',
        ]);

        JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        return $job;
    }
}
