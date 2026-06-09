<?php

namespace Tests\Feature;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\JobPosting;
use App\Models\JobPostingPublishAttempt;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use App\Support\Audit\AuditActionType;
use Database\Seeders\MultipostingModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class MultipostingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiposting_flow_supports_toggle_generate_edit_publish_tracking_and_utm_attribution(): void
    {
        $platform = 'indeed';
        $company = $this->createCompany('multiposting-company-a');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'Platform Engineer');

        $this->bindSuccessfulAiAdapter('AI adapted Indeed content');

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.toggle', ['job' => $job->id, 'platform' => $platform]))
            ->assertRedirect();

        $this->assertDatabaseHas('job_postings', [
            'job_id' => $job->id,
            'platform' => $platform,
            'status' => JobPosting::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.generate', ['job' => $job->id, 'platform' => $platform]))
            ->assertRedirect();

        $this->assertDatabaseHas('job_postings', [
            'job_id' => $job->id,
            'platform' => $platform,
            'status' => JobPosting::STATUS_READY,
            'ai_generated_content' => 'AI adapted Indeed content',
        ]);

        $editedContent = 'Edited Indeed copy before publishing.';
        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.save-content', ['job' => $job->id, 'platform' => $platform]), [
                'ai_generated_content' => $editedContent,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('job_postings', [
            'job_id' => $job->id,
            'platform' => $platform,
            'status' => JobPosting::STATUS_READY,
            'ai_generated_content' => $editedContent,
        ]);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.publish', ['job' => $job->id, 'platform' => $platform]))
            ->assertRedirect();

        $posting = JobPosting::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('platform', $platform)
            ->firstOrFail();

        $this->assertSame(JobPosting::STATUS_PUBLISHED, (string) $posting->status);
        $this->assertNotNull($posting->tracking_url);
        $this->assertNotNull($posting->posted_at);
        $this->assertSame(JobPostingPublishAttempt::STATUS_SUCCEEDED, (string) $posting->last_publish_status);
        $this->assertSame('sync', (string) $posting->last_execution_mode);
        $this->assertNotNull($posting->last_publish_attempted_at);
        $this->assertNotNull($posting->last_publish_succeeded_at);
        $this->assertNull($posting->last_publish_error);
        $this->assertDatabaseHas('job_posting_publish_attempts', [
            'job_posting_id' => $posting->id,
            'status' => JobPostingPublishAttempt::STATUS_SUCCEEDED,
            'execution_mode' => 'sync',
        ]);

        $trackingUrl = $this->toRelativeUrl((string) $posting->tracking_url);
        $trackingResponse = $this->get($trackingUrl);
        $trackingResponse->assertRedirect();

        $redirectTarget = (string) $trackingResponse->headers->get('Location');
        $this->assertStringContainsString('utm_source=indeed', $redirectTarget);
        $this->assertStringContainsString('utm_medium=job_board', $redirectTarget);

        $posting->refresh();
        $this->assertSame(1, (int) $posting->clicks_count);
        $this->assertDatabaseCount('click_events', 1);

        $applyResponse = $this->post(route('career.apply', ['company' => $company->slug, 'job' => $job->id]), [
            'full_name' => 'Sadia Amanullah',
            'email' => 'sadia.amanullah.multipost@example.com',
            'password' => 'CandidatePass123!',
            'password_confirmation' => 'CandidatePass123!',
            'phone' => '+1-555-1111',
            'location' => 'Remote',
            'resume' => UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf'),
            'consent' => '1',
            'utm_source' => 'indeed',
            'utm_medium' => 'job_board',
            'utm_campaign' => 'job-'.$job->id,
        ]);
        $applyResponse->assertRedirect();

        $this->assertDatabaseHas('applications', [
            'job_id' => $job->id,
            'utm_source' => 'indeed',
            'source_type' => 'job_board',
        ]);

        $auditRows = AuditLog::withoutGlobalScopes()
            ->where('action_type', AuditActionType::JOB_POSTING_STATUS_CHANGED)
            ->where('entity_id', $posting->id)
            ->get();

        $this->assertTrue($auditRows->isNotEmpty());
        $this->assertTrue(
            $auditRows->contains(fn (AuditLog $row): bool => (string) data_get($row->metadata, 'to_status') === JobPosting::STATUS_PUBLISHED)
        );
    }

    public function test_failed_generation_sets_failed_status_and_retry_is_visible(): void
    {
        $company = $this->createCompany('multiposting-company-b');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'Data Analyst');

        $this->bindFailingAiAdapter('Simulated AI failure for testing retry.');

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.toggle', ['job' => $job->id, 'platform' => 'indeed']))
            ->assertRedirect();

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('jobs.show', ['job' => $job->id]))
            ->post(route('jobs.multiposting.generate', ['job' => $job->id, 'platform' => 'indeed']));

        $response->assertRedirect(route('jobs.show', ['job' => $job->id]));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('job_postings', [
            'job_id' => $job->id,
            'platform' => 'indeed',
            'status' => JobPosting::STATUS_FAILED,
        ]);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('jobs.show', ['job' => $job->id]))
            ->assertOk()
            ->assertSee(__('jobs.multiposting.retry'));
    }

    public function test_disabling_mirror_clears_tracking_link(): void
    {
        $company = $this->createCompany('multiposting-company-c');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'QA Engineer');

        $posting = JobPosting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'platform' => 'glassdoor',
            'status' => JobPosting::STATUS_PUBLISHED,
            'ai_generated_content' => 'Content',
            'tracking_url' => 'https://example.test/track',
            'clicks_count' => 2,
            'posted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.multiposting.toggle', ['job' => $job->id, 'platform' => 'glassdoor']))
            ->assertRedirect();

        $posting->refresh();
        $this->assertSame(JobPosting::STATUS_DISABLED, (string) $posting->status);
        $this->assertNull($posting->tracking_url);
    }

    public function test_failed_publish_attempt_is_recorded_for_observability(): void
    {
        $company = $this->createCompany('multiposting-company-d');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithPipeline($company, 'Growth Marketer');

        $posting = JobPosting::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'platform' => 'indeed',
            'status' => JobPosting::STATUS_READY,
            'ai_generated_content' => null,
            'clicks_count' => 0,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('jobs.show', ['job' => $job->id]))
            ->post(route('jobs.multiposting.publish', ['job' => $job->id, 'platform' => 'indeed']));

        $response->assertRedirect(route('jobs.show', ['job' => $job->id]));
        $response->assertSessionHas('error');

        $posting->refresh();
        $this->assertSame(JobPostingPublishAttempt::STATUS_FAILED, (string) $posting->last_publish_status);
        $this->assertSame('sync', (string) $posting->last_execution_mode);
        $this->assertNotNull($posting->last_publish_attempted_at);
        $this->assertStringContainsString('Generate or add adapted content', (string) $posting->last_publish_error);

        $this->assertDatabaseHas('job_posting_publish_attempts', [
            'job_posting_id' => $posting->id,
            'status' => JobPostingPublishAttempt::STATUS_FAILED,
            'execution_mode' => 'sync',
        ]);
    }

    public function test_multiposting_seeder_creates_required_sample_data(): void
    {
        $this->seed(MultipostingModuleSeeder::class);

        $company = Company::query()->where('slug', 'numa-demo')->first();
        $this->assertNotNull($company);

        $job = Job::withoutGlobalScopes()
            ->where('company_id', $company?->id)
            ->where('title', 'Multiposting Demo Role')
            ->first();
        $this->assertNotNull($job);

        $postings = JobPosting::withoutGlobalScopes()
            ->where('job_id', $job?->id)
            ->get()
            ->keyBy('platform');

        $this->assertTrue($postings->has('linkedin'));
        $this->assertTrue($postings->has('indeed'));
        $this->assertSame(JobPosting::STATUS_PUBLISHED, (string) $postings->get('linkedin')?->status);
        $this->assertSame(JobPosting::STATUS_READY, (string) $postings->get('indeed')?->status);
        $this->assertSame(5, (int) $postings->get('linkedin')?->clicks_count);
        $this->assertDatabaseCount('click_events', 5);
    }

    private function bindSuccessfulAiAdapter(string $adaptedContent): void
    {
        $mock = Mockery::mock(AiRequestService::class);

        $mock->shouldReceive('queueRequest')
            ->andReturnUsing(function (string $companyId, string $requestType, array $payload, ?string $modelName = null, ?string $promptVersion = null): AiRequest {
                return AiRequest::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'request_type' => $requestType,
                    'input_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE)),
                    'status' => AiRequest::STATUS_QUEUED,
                    'model_name' => $modelName ?? 'test-model',
                    'prompt_version' => $promptVersion ?? 'test-v1',
                    'request_payload' => $payload,
                    'created_at' => now(),
                ]);
            });

        $mock->shouldReceive('process')
            ->andReturnUsing(function (AiRequest $request) use ($adaptedContent): void {
                $request->forceFill([
                    'status' => AiRequest::STATUS_SUCCEEDED,
                    'response_payload' => [
                        'mode' => 'json',
                        'output' => [
                            'adapted_content' => $adaptedContent,
                        ],
                    ],
                    'finished_at' => now(),
                ])->save();
            });

        $this->app->instance(AiRequestService::class, $mock);
    }

    private function bindFailingAiAdapter(string $errorMessage): void
    {
        $mock = Mockery::mock(AiRequestService::class);

        $mock->shouldReceive('queueRequest')
            ->andReturnUsing(function (string $companyId, string $requestType, array $payload, ?string $modelName = null, ?string $promptVersion = null): AiRequest {
                return AiRequest::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'request_type' => $requestType,
                    'input_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE)),
                    'status' => AiRequest::STATUS_QUEUED,
                    'model_name' => $modelName ?? 'test-model',
                    'prompt_version' => $promptVersion ?? 'test-v1',
                    'request_payload' => $payload,
                    'created_at' => now(),
                ]);
            });

        $mock->shouldReceive('process')
            ->andReturnUsing(function (AiRequest $request) use ($errorMessage): void {
                $request->forceFill([
                    'status' => AiRequest::STATUS_FAILED,
                    'error_message' => $errorMessage,
                    'response_payload' => ['attempts' => []],
                    'finished_at' => now(),
                ])->save();
            });

        $this->app->instance(AiRequestService::class, $mock);
    }

    private function toRelativeUrl(string $absoluteOrRelative): string
    {
        $path = (string) parse_url($absoluteOrRelative, PHP_URL_PATH);
        $query = (string) parse_url($absoluteOrRelative, PHP_URL_QUERY);

        return $query !== '' ? "{$path}?{$query}" : $path;
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

