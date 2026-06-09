<?php

namespace Tests\Feature;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobDescriptionBlock;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobsManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighting_total_must_equal_100(): void
    {
        $company = $this->createCompany('jobs-company-a');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createJob($company, 'Backend Engineer');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('jobs.show', ['job' => $job->id]))
            ->post(route('jobs.weighting.save', ['job' => $job->id]), [
                'weight_skills_match' => 20,
                'weight_experience_match' => 15,
                'weight_education_match' => 10,
                'weight_certifications' => 8,
                'weight_language_match' => 8,
                'weight_assessment_performance' => 10,
                'weight_interview_performance' => 10,
                'weight_strategy_lab' => 10,
                'weight_culture_fit' => 8,
            ]);

        $response->assertRedirect(route('jobs.show', ['job' => $job->id]));
        $response->assertSessionHasErrors([
            'weight_total' => __('jobs.weighting_sum_invalid'),
        ]);
    }

    public function test_creating_job_redirects_to_multiposting_workflow_with_oauth_channels_disabled_until_configuration_is_complete(): void
    {
        $company = $this->createCompany('jobs-company-create-flow');
        $admin = $this->createCompanyAdmin($company);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.store', ['company_id' => $company->id]), [
                'title' => 'Platform Partnerships Manager',
                'department_id' => null,
                'location' => 'Remote',
                'status' => Job::STATUS_DRAFT,
                'salary_budget_max' => 150000,
                'blind_mode_active' => '0',
                'employment_type' => Job::EMPLOYMENT_FULL_TIME,
            ]);

        $job = Job::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('title', 'Platform Partnerships Manager')
            ->firstOrFail();

        $response->assertRedirect(route('jobs.show', [
            'job' => $job,
            'company_id' => $company->id,
            'tab' => 'multiposting',
            'open_multiposting_workflow' => 1,
        ]));

        $page = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('jobs.show', [
                'job' => $job,
                'company_id' => $company->id,
                'tab' => 'multiposting',
                'open_multiposting_workflow' => 1,
            ]));

        $page->assertOk();
        $page->assertSee(__('jobs.multiposting.bulk.modal_title'));
        $page->assertSee('open: true', false);
        $page->assertSee('name="platforms[]"', false);
        $page->assertSee('value="linkedin"', false);
        $page->assertSee('disabled:cursor-not-allowed', false);
        $page->assertSee(__('jobs.multiposting.readiness.oauth_required'));
        $page->assertSee(route('configuration.index', ['company_id' => (string) $company->id]), false);
    }

    public function test_pipeline_requires_terminal_and_non_terminal_stages(): void
    {
        $company = $this->createCompany('jobs-company-b');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createJob($company, 'Data Engineer');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('jobs.show', ['job' => $job->id]))
            ->post(route('jobs.pipeline.save', ['job' => $job->id]), [
                'stage_key' => ['screen', 'interview'],
                'stage_label' => ['Screen', 'Interview'],
                'display_order' => [1, 2],
                'is_terminal' => [],
            ]);

        $response->assertRedirect(route('jobs.show', ['job' => $job->id]));
        $response->assertSessionHasErrors([
            'pipeline' => __('jobs.pipeline_terminal_required'),
        ]);
    }

    public function test_persona_generation_is_queued_as_ai_request(): void
    {
        $company = $this->createCompany('jobs-company-c');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createJob($company, 'Product Designer');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.persona.generate', ['job' => $job->id]));

        $response->assertRedirect();

        $request = AiRequest::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('request_type', 'job_persona_generation')
            ->first();

        $this->assertNotNull($request);
        $this->assertSame((string) $job->id, (string) data_get($request?->request_payload, 'job_id'));
    }

    public function test_published_job_is_exposed_on_career_site(): void
    {
        $company = $this->createCompany('jobs-company-d');

        $published = $this->createJob($company, 'Published Role', Job::STATUS_PUBLISHED);
        $draft = $this->createJob($company, 'Draft Role', Job::STATUS_DRAFT);

        $response = $this->get(route('career.index', ['company' => $company->slug]));

        $response->assertOk();
        $response->assertSee($published->title);
        $response->assertDontSee($draft->title);
    }

    public function test_description_block_reorder_persists(): void
    {
        $company = $this->createCompany('jobs-company-e');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createJob($company, 'Fullstack Engineer');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.blocks.save', ['job' => $job->id]), [
                'block_type' => ['overview', 'requirements'],
                'block_content' => ['{"text":"Overview"}', '{"text":"Requirements"}'],
                'display_order' => [2, 1],
            ]);

        $response->assertRedirect();

        $blocks = JobDescriptionBlock::query()
            ->where('job_id', $job->id)
            ->orderBy('display_order')
            ->get();

        $this->assertCount(2, $blocks);
        $this->assertSame('requirements', (string) $blocks[0]->block_type);
        $this->assertSame(1, (int) $blocks[0]->display_order);
        $this->assertSame('overview', (string) $blocks[1]->block_type);
        $this->assertSame(2, (int) $blocks[1]->display_order);
    }

    public function test_candidate_cannot_access_jobs_management_routes(): void
    {
        $company = $this->createCompany('jobs-company-f');
        $job = $this->createJob($company, 'Restricted Job');
        $candidate = $this->createCompanyMember($company, CompanyMembership::ROLE_CANDIDATE);

        $this->actingAs($candidate)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('jobs.index'))
            ->assertForbidden();

        $this->actingAs($candidate)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('jobs.show', ['job' => $job->id]))
            ->assertForbidden();

        $this->actingAs($candidate)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.persona.generate', ['job' => $job->id]))
            ->assertForbidden();
    }

    public function test_pipeline_cannot_remove_stage_that_is_in_use(): void
    {
        $company = $this->createCompany('jobs-company-g');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createJob($company, 'Operations Manager');

        $screenStage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screen',
            'stage_label' => 'Screen',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $offerStage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'offer',
            'stage_label' => 'Offer',
            'display_order' => 2,
            'is_terminal' => true,
        ]);

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'full_name' => 'Pipeline Candidate',
            'email' => 'pipeline-candidate@example.com',
            'phone' => null,
            'location' => null,
        ]);

        Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $screenStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
            'source_detail' => null,
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('jobs.show', ['job' => $job->id]))
            ->post(route('jobs.pipeline.save', ['job' => $job->id]), [
                'stage_id' => [(string) $offerStage->id, ''],
                'stage_key' => ['offer', 'interview'],
                'stage_label' => ['Offer', 'Interview'],
                'display_order' => [1, 2],
                'is_terminal' => [0],
            ]);

        $response->assertRedirect(route('jobs.show', ['job' => $job->id]));
        $response->assertSessionHasErrors(['pipeline']);
    }

    public function test_pipeline_save_updates_referenced_stage_without_deleting_it(): void
    {
        $company = $this->createCompany('jobs-company-h');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createJob($company, 'Growth Manager');

        $screenStage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screen',
            'stage_label' => 'Screen',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $offerStage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'offer',
            'stage_label' => 'Offer',
            'display_order' => 2,
            'is_terminal' => true,
        ]);

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => null,
            'full_name' => 'Pipeline Candidate 2',
            'email' => 'pipeline-candidate-2@example.com',
            'phone' => null,
            'location' => null,
        ]);

        $application = Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $screenStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
            'source_detail' => null,
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('jobs.pipeline.save', ['job' => $job->id]), [
                'stage_id' => [(string) $screenStage->id, (string) $offerStage->id],
                'stage_key' => ['screen', 'offer'],
                'stage_label' => ['Application Review', 'Offer'],
                'display_order' => [1, 2],
                'is_terminal' => [1],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame((string) $screenStage->id, (string) $application->current_stage_id);

        $updatedScreenStage = JobPipelineStage::query()->findOrFail($screenStage->id);
        $this->assertSame('Application Review', (string) $updatedScreenStage->stage_label);
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
        return $this->createCompanyMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
    }

    private function createCompanyMember(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => $role,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }

    private function createJob(Company $company, string $title, string $status = Job::STATUS_DRAFT): Job
    {
        return Job::query()->create([
            'company_id' => $company->id,
            'title' => $title,
            'status' => $status,
        ]);
    }
}
