<?php

namespace Tests\Feature;

use App\Jobs\RunApiChannelPublishJob;
use App\Jobs\SyncLinkedInJobPostingStatusJob;
use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\JobPosting;
use App\Models\JobPostingPublishAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LinkedInApiPushPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_queues_native_api_push_worker_for_linkedin_when_partner_configuration_is_complete(): void
    {
        config()->set('services.linkedin.job_posting_endpoint', 'https://api.linkedin.com/rest/simpleJobPostings');
        config()->set('services.linkedin.oauth_access_token_url', 'https://www.linkedin.com/oauth/v2/accessToken');
        config()->set('services.linkedin.job_posting_version', '202603');
        config()->set('multiposting.automation.enabled', false);

        $company = $this->createCompany('linkedin-api-push-company-a');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'API Push Engineer');

        CompanyIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'access_token' => 'member-oauth-token',
            'last_connected_at' => now(),
            'meta_json' => [
                'partner_job_posting' => [
                    'partner_client_id' => 'child-client-id',
                    'partner_client_secret' => 'child-client-secret',
                    'company_urn' => 'urn:li:company:2414183',
                ],
            ],
        ]);

        $posting = JobPosting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'platform' => 'linkedin',
            'status' => JobPosting::STATUS_READY,
            'ai_generated_content' => 'Prepared LinkedIn API content.',
            'clicks_count' => 0,
        ]);

        Queue::fake();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.publish', ['job' => $job->id, 'platform' => 'linkedin']));

        $response->assertRedirect();
        $response->assertSessionHas('status', __('jobs.multiposting.flash.publish_queued', [
            'platform' => __('jobs.multiposting.platforms.linkedin'),
        ]));

        Queue::assertPushed(RunApiChannelPublishJob::class, function (RunApiChannelPublishJob $queuedJob) use ($posting, $admin): bool {
            return (string) $queuedJob->jobPostingId === (string) $posting->id
                && $queuedJob->publishAttemptId !== ''
                && (string) $queuedJob->actorUserId === (string) $admin->id;
        });

        $posting->refresh();
        $this->assertSame(JobPosting::STATUS_PUBLISHING, (string) $posting->status);
        $this->assertSame(JobPostingPublishAttempt::STATUS_QUEUED, (string) $posting->last_publish_status);
        $this->assertSame('async', (string) $posting->last_execution_mode);
    }

    public function test_native_api_push_worker_uses_client_credentials_and_queues_task_sync(): void
    {
        config()->set('services.linkedin.job_posting_endpoint', 'https://api.linkedin.com/rest/simpleJobPostings');
        config()->set('services.linkedin.oauth_access_token_url', 'https://www.linkedin.com/oauth/v2/accessToken');
        config()->set('services.linkedin.job_posting_version', '202603');
        config()->set('services.linkedin.job_posting_task_initial_delay_seconds', 60);

        $company = $this->createCompany('linkedin-api-push-company-b');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'Native API Publisher');

        $integration = CompanyIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'access_token' => 'member-oauth-token',
            'last_connected_at' => now(),
            'meta_json' => [
                'partner_job_posting' => [
                    'partner_client_id' => 'child-client-id',
                    'partner_client_secret' => 'child-client-secret',
                    'company_urn' => 'urn:li:company:2414183',
                ],
            ],
        ]);

        $posting = JobPosting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'platform' => 'linkedin',
            'status' => JobPosting::STATUS_PUBLISHING,
            'ai_generated_content' => '<p>Native LinkedIn payload body.</p>',
            'clicks_count' => 0,
        ]);

        $attempt = JobPostingPublishAttempt::query()->create([
            'company_id' => $company->id,
            'job_posting_id' => $posting->id,
            'initiated_by_user_id' => $admin->id,
            'platform' => 'linkedin',
            'attempt_number' => 1,
            'status' => JobPostingPublishAttempt::STATUS_QUEUED,
            'execution_mode' => 'async',
            'queued_at' => now(),
        ]);

        $capturedTokenRequest = null;
        $capturedPublishRequest = null;

        Http::fake([
            'https://www.linkedin.com/oauth/v2/accessToken' => function ($request) use (&$capturedTokenRequest) {
                $capturedTokenRequest = $request;

                return Http::response([
                    'access_token' => 'partner-access-token',
                    'expires_in' => 1800,
                ], 200);
            },
            'https://api.linkedin.com/rest/simpleJobPostings' => function ($request) use (&$capturedPublishRequest) {
                $capturedPublishRequest = $request;

                return Http::response([
                    'elements' => [[
                        'id' => 'urn:li:simpleJobPostingTask:33349175-1da5-48b4-a3a6-abfd9248bdc6',
                        'location' => '/simpleJobPostings/urn%3Ali%3AsimpleJobPostingTask%3A33349175-1da5-48b4-a3a6-abfd9248bdc6',
                        'entity' => [
                            'externalJobPostingId' => (string) $request['elements'][0]['externalJobPostingId'],
                        ],
                        'status' => 202,
                    ]],
                ], 200);
            },
        ]);

        Queue::fake();

        $jobRunner = new RunApiChannelPublishJob((string) $posting->id, (string) $attempt->id, (string) $admin->id);
        $jobRunner->handle(
            app(\App\Services\Multiposting\LinkedInApiPublisher::class),
            app(\App\Services\Multiposting\MultipostingService::class),
            app(\App\Support\Audit\SensitiveEventRecorder::class)
        );

        $this->assertNotNull($capturedTokenRequest);
        $this->assertNotNull($capturedPublishRequest);
        $this->assertSame('child-client-id', $capturedTokenRequest['client_id']);
        $this->assertSame('client_credentials', $capturedTokenRequest['grant_type']);
        $this->assertTrue($capturedPublishRequest->hasHeader('Authorization', 'Bearer partner-access-token'));
        $this->assertTrue($capturedPublishRequest->hasHeader('x-restli-method', 'batch_create'));
        $this->assertTrue($capturedPublishRequest->hasHeader('Linkedin-Version', '202603'));
        $this->assertSame('Native API Publisher', data_get($capturedPublishRequest->data(), 'elements.0.title'));
        $this->assertSame('<p>Native LinkedIn payload body.</p>', data_get($capturedPublishRequest->data(), 'elements.0.description'));
        $this->assertSame('urn:li:company:2414183', data_get($capturedPublishRequest->data(), 'elements.0.company'));
        $this->assertSame('CREATE', data_get($capturedPublishRequest->data(), 'elements.0.jobPostingOperationType'));

        Queue::assertPushed(SyncLinkedInJobPostingStatusJob::class, function (SyncLinkedInJobPostingStatusJob $queuedJob) use ($posting, $attempt, $admin): bool {
            return (string) $queuedJob->jobPostingId === (string) $posting->id
                && (string) $queuedJob->publishAttemptId === (string) $attempt->id
                && (string) $queuedJob->taskUrn === 'urn:li:simpleJobPostingTask:33349175-1da5-48b4-a3a6-abfd9248bdc6'
                && (string) $queuedJob->actorUserId === (string) $admin->id;
        });

        $attempt->refresh();
        $integration->refresh();

        $this->assertSame(JobPostingPublishAttempt::STATUS_RUNNING, (string) $attempt->status);
        $this->assertSame('urn:li:simpleJobPostingTask:33349175-1da5-48b4-a3a6-abfd9248bdc6', data_get($attempt->diagnostics_json, 'task_urn'));
        $this->assertNotNull($integration->last_used_at);
    }

    public function test_linkedin_task_sync_marks_publish_attempt_succeeded_when_task_is_confirmed(): void
    {
        config()->set('services.linkedin.job_posting_task_status_endpoint', 'https://api.linkedin.com/rest/simpleJobPostings');
        config()->set('services.linkedin.oauth_access_token_url', 'https://www.linkedin.com/oauth/v2/accessToken');
        config()->set('services.linkedin.job_posting_version', '202603');

        $company = $this->createCompany('linkedin-api-push-company-c');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'Task Sync Engineer');

        CompanyIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'access_token' => 'member-oauth-token',
            'last_connected_at' => now(),
            'meta_json' => [
                'partner_job_posting' => [
                    'partner_client_id' => 'child-client-id',
                    'partner_client_secret' => 'child-client-secret',
                    'company_urn' => 'urn:li:company:2414183',
                ],
            ],
        ]);

        $posting = JobPosting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'platform' => 'linkedin',
            'status' => JobPosting::STATUS_PUBLISHING,
            'ai_generated_content' => '<p>Task Sync LinkedIn payload body.</p>',
            'clicks_count' => 0,
        ]);

        $attempt = JobPostingPublishAttempt::query()->create([
            'company_id' => $company->id,
            'job_posting_id' => $posting->id,
            'initiated_by_user_id' => $admin->id,
            'platform' => 'linkedin',
            'attempt_number' => 1,
            'status' => JobPostingPublishAttempt::STATUS_RUNNING,
            'execution_mode' => 'async',
            'queued_at' => now(),
            'started_at' => now(),
            'diagnostics_json' => [
                'task_urn' => 'urn:li:simpleJobPostingTask:sync-123',
            ],
        ]);

        Http::fake([
            'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
                'access_token' => 'partner-access-token',
                'expires_in' => 1800,
            ], 200),
            'https://api.linkedin.com/rest/simpleJobPostings*' => Http::response([
                'results' => [
                    'urn:li:simpleJobPostingTask:sync-123' => [
                        'id' => 'urn:li:simpleJobPostingTask:sync-123',
                        'status' => 'SUCCEEDED',
                        'jobPosting' => 'urn:li:jobPosting:456789',
                    ],
                ],
                'statuses' => new \stdClass(),
                'errors' => new \stdClass(),
            ], 200),
        ]);

        $jobRunner = new SyncLinkedInJobPostingStatusJob(
            (string) $posting->id,
            (string) $attempt->id,
            'urn:li:simpleJobPostingTask:sync-123',
            1,
            (string) $admin->id
        );
        $jobRunner->handle(
            app(\App\Services\Multiposting\LinkedInApiPublisher::class),
            app(\App\Services\Multiposting\MultipostingService::class),
            app(\App\Support\Audit\SensitiveEventRecorder::class)
        );

        $posting->refresh();
        $attempt->refresh();

        $this->assertSame(JobPosting::STATUS_PUBLISHED, (string) $posting->status);
        $this->assertNotNull($posting->tracking_url);
        $this->assertSame(JobPostingPublishAttempt::STATUS_SUCCEEDED, (string) $attempt->status);
        $this->assertSame('urn:li:jobPosting:456789', data_get($attempt->diagnostics_json, 'external_id'));
        $this->assertSame('SUCCEEDED', data_get($attempt->diagnostics_json, 'task_status'));
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
            'location_country' => 'United States',
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
