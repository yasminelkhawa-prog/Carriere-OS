<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\SjtResponse;
use App\Models\SjtScenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SjtScenarioManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_update_and_delete_sjt_scenario(): void
    {
        $company = Company::query()->create([
            'name' => 'SJT Admin Company',
            'slug' => 'sjt-admin-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'SJT Admin Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $storeResponse = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('admin.sjt-scenarios.store'), [
                'job_id' => (string) $job->id,
                'title' => 'Escalation Scenario',
                'scenario_media_url' => 'https://example.com/escalation.mp4',
                'scenario_text' => 'A customer escalation arrives before launch. What is your response?',
                'is_active' => 1,
            ]);

        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHas('status', __('sjt.admin.messages.created'));

        $scenario = SjtScenario::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('title', 'Escalation Scenario')
            ->first();

        $this->assertNotNull($scenario);
        $this->assertTrue((bool) $scenario?->is_active);

        $updateResponse = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->patch(route('admin.sjt-scenarios.update', ['sjtScenario' => $scenario?->id]), [
                'job_id' => null,
                'title' => 'Escalation Scenario Updated',
                'scenario_media_url' => 'https://example.com/escalation.gif',
                'scenario_text' => 'Updated scenario text',
            ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('status', __('sjt.admin.messages.updated'));

        $scenario?->refresh();
        $this->assertSame('Escalation Scenario Updated', (string) $scenario?->title);
        $this->assertSame('https://example.com/escalation.gif', (string) $scenario?->scenario_media_url);
        $this->assertFalse((bool) $scenario?->is_active);

        $deleteResponse = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->delete(route('admin.sjt-scenarios.destroy', ['sjtScenario' => $scenario?->id]));

        $deleteResponse->assertRedirect();
        $deleteResponse->assertSessionHas('status', __('sjt.admin.messages.deleted'));

        $this->assertFalse(
            SjtScenario::withoutGlobalScopes()
                ->where('id', $scenario?->id)
                ->exists()
        );
    }

    public function test_candidate_portal_shows_sjt_assessment_entry_when_scenarios_exist(): void
    {
        $company = Company::query()->create([
            'name' => 'SJT Candidate Company',
            'slug' => 'sjt-candidate-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'full_name' => 'Portal Candidate',
            'email' => (string) $candidateUser->email,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Portal SJT Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'title' => 'Portal Scenario',
            'scenario_media_url' => 'https://example.com/portal.mp4',
            'scenario_text' => 'Describe your response to a production incident.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($candidateUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('candidate.portal', ['company' => $company->slug]));

        $response->assertOk();
        $response->assertSee(__('sjt.portal.title'));
        $response->assertSee('Portal SJT Role');
        $response->assertSee(route('candidate.assessments.sjt', ['application_id' => (string) $application->id]));
    }

    public function test_candidate_portal_keeps_sjt_entry_visible_for_inactive_scored_scenario(): void
    {
        $company = Company::query()->create([
            'name' => 'SJT Candidate Company 2',
            'slug' => 'sjt-candidate-company-2',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'full_name' => 'Portal Candidate Two',
            'email' => (string) $candidateUser->email,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Portal SJT Role Two',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $scenario = SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'title' => 'Inactive scored scenario',
            'scenario_media_url' => null,
            'scenario_text' => 'Handled previously.',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SjtResponse::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'scenario_id' => $scenario->id,
            'response_text' => str_repeat('a', 150),
            'copy_paste_blocked_flag' => true,
            'ai_score' => 75.0,
            'ai_feedback_json' => ['summary' => 'Good enough'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($candidateUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('candidate.portal', ['company' => $company->slug]));

        $response->assertOk();
        $response->assertSee(__('sjt.portal.title'));
        $response->assertSee('Portal SJT Role Two');
        $response->assertSee(route('candidate.assessments.sjt', ['application_id' => (string) $application->id]));
    }
}
