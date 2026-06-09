<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Candidate;
use App\Models\InterviewParticipant;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_role_cannot_access_internal_candidate_workspace_pages(): void
    {
        $company = Company::query()->create([
            'name' => 'Workspace Access Co',
            'slug' => 'workspace-access-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email_verified_at' => now(),
            'platform_role' => User::PLATFORM_NONE,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $indexResponse = $this->actingAs($candidateUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('candidates.index', ['company_id' => (string) $company->id]));

        $indexResponse->assertForbidden();

        $kanbanResponse = $this->actingAs($candidateUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('candidates.kanban', ['company_id' => (string) $company->id]));

        $kanbanResponse->assertForbidden();
    }

    public function test_recruiter_role_can_access_internal_candidate_workspace_pages(): void
    {
        $company = Company::query()->create([
            'name' => 'Workspace Recruiter Co',
            'slug' => 'workspace-recruiter-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiterUser = User::factory()->create([
            'email_verified_at' => now(),
            'platform_role' => User::PLATFORM_NONE,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiterUser->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $indexResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('candidates.index', ['company_id' => (string) $company->id]));

        $indexResponse->assertOk();
    }

    public function test_candidate_workspace_search_matches_email_case_insensitively(): void
    {
        $company = Company::query()->create([
            'name' => 'Workspace Search Co',
            'slug' => 'workspace-search-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiterUser = User::factory()->create([
            'email_verified_at' => now(),
            'platform_role' => User::PLATFORM_NONE,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiterUser->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Account Executive',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screen',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $targetCandidate = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Target Search Candidate',
            'email' => 'target.search@example.com',
        ]);

        $otherCandidate = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Other Candidate',
            'email' => 'other.candidate@example.com',
        ]);

        Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $targetCandidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_site',
        ]);

        Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $otherCandidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_site',
        ]);

        $indexResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('candidates.index', [
                'company_id' => (string) $company->id,
                'q' => 'TARGET.SEARCH@EXAMPLE.COM',
            ]));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Target Search Candidate');
    }

    public function test_recruiter_assistant_returns_status_and_interview_context(): void
    {
        $company = Company::query()->create([
            'name' => 'Assistant Workspace Co',
            'slug' => 'assistant-workspace-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiterUser = User::factory()->create([
            'email_verified_at' => now(),
            'platform_role' => User::PLATFORM_NONE,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiterUser->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Assistant Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Assistant Candidate',
            'email' => 'assistant.candidate@example.com',
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_site',
        ]);

        Interview::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'interview_type' => 'screening',
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHour(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://zoom.example.com/test',
            'status' => Interview::STATUS_SCHEDULED,
            'created_by_user_id' => $recruiterUser->id,
        ]);

        $statusResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'application_id' => (string) $application->id,
                'message' => 'What is the candidate status?',
            ]);

        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('ok', true);
        $statusResponse->assertJsonPath('intent', 'status');
        $statusResponse->assertJsonPath('summary.application_id', (string) $application->id);

        $interviewResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'application_id' => (string) $application->id,
                'message' => 'Show interview progress and zoom updates',
            ]);

        $interviewResponse->assertOk();
        $interviewResponse->assertJsonPath('ok', true);
        $interviewResponse->assertJsonPath('intent', 'interview');
    }

    public function test_recruiter_assistant_can_answer_global_candidature_pipeline_questions(): void
    {
        $company = Company::query()->create([
            'name' => 'Global Assistant Co',
            'slug' => 'global-assistant-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiterUser = User::factory()->create([
            'email_verified_at' => now(),
            'platform_role' => User::PLATFORM_NONE,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiterUser->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Global Assistant Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $screeningStage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $offerStage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'offer',
            'stage_label' => 'Offer',
            'display_order' => 2,
            'is_terminal' => false,
        ]);

        $candidateA = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Global Candidate A',
            'email' => 'global-a@example.com',
        ]);

        $candidateB = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Global Candidate B',
            'email' => 'global-b@example.com',
        ]);

        $applicationA = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidateA->id,
            'job_id' => $job->id,
            'current_stage_id' => $screeningStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_site',
            'updated_at' => now()->subDays(7),
        ]);

        $applicationB = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidateB->id,
            'job_id' => $job->id,
            'current_stage_id' => $offerStage->id,
            'status' => Application::STATUS_HIRED,
            'source_type' => 'career_site',
        ]);

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'application_id' => (string) $applicationA->id,
            'event_type' => 'application.created',
            'payload' => [],
            'actor_user_id' => null,
            'created_at' => now()->subDays(7),
        ]);

        Interview::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $applicationA->id,
            'interview_type' => 'screening',
            'scheduled_start_at' => now()->addHours(4),
            'scheduled_end_at' => now()->addHours(5),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://zoom.example.com/global-test',
            'status' => Interview::STATUS_SCHEDULED,
            'created_by_user_id' => $recruiterUser->id,
        ]);

        $completedInterview = Interview::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $applicationA->id,
            'interview_type' => 'technical',
            'scheduled_start_at' => now()->subDay(),
            'scheduled_end_at' => now()->subDay()->addHour(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://zoom.example.com/completed-test',
            'status' => Interview::STATUS_COMPLETED,
            'created_by_user_id' => $recruiterUser->id,
        ]);

        InterviewParticipant::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'interview_id' => (string) $completedInterview->id,
            'user_id' => (string) $recruiterUser->id,
            'participant_role' => 'interviewer',
            'created_at' => now()->subDay(),
        ]);

        AiRequest::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'request_type' => 'candidate_analysis',
            'input_hash' => hash('sha256', 'global-assistant-analysis'),
            'status' => AiRequest::STATUS_RUNNING,
            'model_name' => 'test-model',
            'request_payload' => [
                'application_id' => (string) $applicationA->id,
            ],
            'prompt_version' => 'test',
            'created_at' => now(),
        ]);

        $overviewResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'message' => 'Give me a pipeline overview of candidatures',
            ]);

        $overviewResponse->assertOk();
        $overviewResponse->assertJsonPath('ok', true);
        $overviewResponse->assertJsonPath('intent', 'global_overview');
        $overviewResponse->assertJsonPath('summary.scope', 'global');
        $overviewResponse->assertJsonPath('summary.total_applications', 2);

        $analysisResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'message' => 'How many candidatures are under analysis?',
            ]);

        $analysisResponse->assertOk();
        $analysisResponse->assertJsonPath('intent', 'global_analysis');

        $feedbackResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'message' => 'Which candidates have pending feedback?',
            ]);

        $feedbackResponse->assertOk();
        $feedbackResponse->assertJsonPath('intent', 'global_feedback');
        $feedbackResponse->assertJsonPath('summary.pending_feedback_applications', 1);
        $feedbackResponse->assertJsonPath('summary.pending_feedback_items', 1);
        $this->assertStringContainsString('Global Candidate A', (string) $feedbackResponse->json('answer'));

        $blockersResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'message' => 'Show offer onboarding blockers',
            ]);

        $blockersResponse->assertOk();
        $blockersResponse->assertJsonPath('intent', 'global_blockers');
        $blockersResponse->assertJsonPath('summary.blocked_applications', 1);
        $this->assertStringContainsString('Global Candidate B', (string) $blockersResponse->json('answer'));
        $this->assertStringContainsString('offer missing', (string) $blockersResponse->json('answer'));

        $stalledResponse = $this->actingAs($recruiterUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'message' => 'Which candidatures are stalled?',
            ]);

        $stalledResponse->assertOk();
        $stalledResponse->assertJsonPath('intent', 'global_stalled');
        $this->assertStringContainsString('Global Candidate A', (string) $stalledResponse->json('answer'));
    }

    public function test_candidate_role_cannot_call_recruiter_assistant_endpoint(): void
    {
        $company = Company::query()->create([
            'name' => 'Assistant Restriction Co',
            'slug' => 'assistant-restriction-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email_verified_at' => now(),
            'platform_role' => User::PLATFORM_NONE,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $this->actingAs($candidateUser)
            ->withSession(['active_company_id' => (string) $company->id])
            ->postJson(route('candidates.assistant.ask', ['company_id' => (string) $company->id]), [
                'message' => 'status',
            ])
            ->assertForbidden();
    }
}
