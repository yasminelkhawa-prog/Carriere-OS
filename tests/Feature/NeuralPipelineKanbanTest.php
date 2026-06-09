<?php

namespace Tests\Feature;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationScoring;
use App\Models\ApplicationStageHistory;
use App\Models\ApplicationTask;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\EmailOutboxLog;
use App\Models\Interview;
use App\Models\InterviewParticipant;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\RejectionDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NeuralPipelineKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_interview_transition_keeps_candidate_in_previous_stage(): void
    {
        $company = $this->createCompany('kanban-company-a');
        $recruiter = $this->createRecruiter($company);
        [$job, $appliedStage, $interviewStage] = $this->createPipeline($company);
        $application = $this->createCandidateApplication($company, $job, $appliedStage, 'candidate-a@example.com');

        $outsider = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('candidates.kanban.transition', ['application' => $application->id]), [
                'to_stage_id' => (string) $interviewStage->id,
                'transition_type' => 'interview',
                'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
                'interviewer_user_ids' => [(string) $outsider->id],
                'company_id' => (string) $company->id,
                'job_id' => (string) $job->id,
            ]);

        $response->assertRedirect(route('candidates.kanban', [
            'company_id' => (string) $company->id,
            'job_id' => (string) $job->id,
        ]));
        $response->assertSessionHas('error', __('interviews.validation.interviewer_required'));

        $application->refresh();

        $this->assertSame((string) $appliedStage->id, (string) $application->current_stage_id);
        $this->assertFalse(
            ApplicationStageHistory::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('to_stage_id', $interviewStage->id)
                ->exists()
        );
    }

    public function test_successful_interview_transition_creates_history_events_task_and_audit_log(): void
    {
        Mail::fake();

        $company = $this->createCompany('kanban-company-b');
        $recruiter = $this->createRecruiter($company);
        [$job, $appliedStage, $interviewStage] = $this->createPipeline($company);
        $application = $this->createCandidateApplication($company, $job, $appliedStage, 'candidate-b@example.com');

        $scheduledFor = now()->addDays(2)->format('Y-m-d H:i:s');

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('candidates.kanban.transition', ['application' => $application->id]), [
                'to_stage_id' => (string) $interviewStage->id,
                'transition_type' => 'interview',
                'scheduled_for' => $scheduledFor,
                'timezone' => 'UTC',
                'duration_minutes' => 60,
                'interviewer_user_ids' => [(string) $recruiter->id],
                'channel' => 'Google Meet',
                'notes' => 'Bring portfolio.',
                'company_id' => (string) $company->id,
                'job_id' => (string) $job->id,
            ]);

        $response->assertRedirect(route('candidates.kanban', [
            'company_id' => (string) $company->id,
            'job_id' => (string) $job->id,
        ]));

        $application->refresh();
        $this->assertSame((string) $interviewStage->id, (string) $application->current_stage_id);

        $this->assertTrue(
            ApplicationStageHistory::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('from_stage_id', $appliedStage->id)
                ->where('to_stage_id', $interviewStage->id)
                ->where('actor_user_id', $recruiter->id)
                ->exists()
        );

        $interview = Interview::withoutGlobalScopes()
            ->where('application_id', $application->id)
            ->first();
        $this->assertNotNull($interview);

        $this->assertTrue(
            InterviewParticipant::withoutGlobalScopes()
                ->where('interview_id', $interview?->id)
                ->where('user_id', $recruiter->id)
                ->exists()
        );

        $this->assertTrue(
            ApplicationTask::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('status', ApplicationTask::STATUS_OPEN)
                ->where('description', 'Bring portfolio.')
                ->exists()
        );

        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'stage.changed')
                ->exists()
        );
        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'interview.scheduled')
                ->exists()
        );

        $this->assertTrue(
            AuditLog::withoutGlobalScopes()
                ->where('entity_type', 'application')
                ->where('entity_id', $application->id)
                ->where('action_type', 'stage.changed')
                ->exists()
        );

        $this->assertTrue(
            EmailOutboxLog::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('template_key', 'interview_confirmation')
                ->where('related_entity_type', 'interview')
                ->where('related_entity_id', (string) $interview->id)
                ->whereIn('status', [EmailOutboxLog::STATUS_QUEUED, EmailOutboxLog::STATUS_SENT])
                ->exists()
        );
    }

    public function test_rejected_transition_queues_ai_draft_without_auto_sending_email(): void
    {
        Mail::fake();

        $company = $this->createCompany('kanban-company-c');
        $recruiter = $this->createRecruiter($company);
        [$job, $appliedStage, , $rejectedStage] = $this->createPipeline($company);
        $application = $this->createCandidateApplication($company, $job, $appliedStage, 'candidate-c@example.com');

        ApplicationScoring::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'global_match_score' => 62.40,
            'vrin_json' => ['composite_score' => 62, 'value' => 70, 'rarity' => 60, 'inimitability' => 58, 'non_substitutability' => 61],
            'xai_summary' => 'Missing advanced cloud architecture depth.',
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('candidates.kanban.transition', ['application' => $application->id]), [
                'to_stage_id' => (string) $rejectedStage->id,
                'transition_type' => 'rejected',
                'reason' => 'Skills mismatch',
                'confirm_terminal' => 1,
                'company_id' => (string) $company->id,
                'job_id' => (string) $job->id,
            ]);

        $response->assertRedirect(route('candidates.kanban', [
            'company_id' => (string) $company->id,
            'job_id' => (string) $job->id,
        ]));

        $application->refresh();
        $this->assertSame((string) $rejectedStage->id, (string) $application->current_stage_id);
        $this->assertSame(Application::STATUS_REJECTED, (string) $application->status);

        $this->assertTrue(
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('request_type', 'rejection_draft')
                ->whereRaw("request_payload->>'application_id' = ?", [(string) $application->id])
                ->exists()
        );

        $this->assertTrue(
            RejectionDraft::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('application_id', $application->id)
                ->where('status', RejectionDraft::STATUS_DRAFT)
                ->exists()
        );

        $this->assertFalse(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'email.sent')
                ->whereRaw("payload->>'template' = 'rejection_decision'")
                ->exists()
        );

        $this->assertFalse(
            EmailOutboxLog::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('template_key', 'rejection_decision')
                ->where('related_entity_type', 'rejection_draft')
                ->exists()
        );
    }

    public function test_kanban_blocks_when_pipeline_is_misconfigured(): void
    {
        $company = $this->createCompany('kanban-company-d');
        $recruiter = $this->createRecruiter($company);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Misconfigured Pipeline Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 1,
            'is_terminal' => true,
        ]);

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('candidates.kanban', [
                'company_id' => (string) $company->id,
                'job_id' => (string) $job->id,
            ]));

        $response->assertOk();
        $response->assertSee(__('kanban.pipeline.misconfigured_title'));
        $response->assertSee(__('kanban.pipeline.errors.non_terminal_required'));
        $response->assertSee(__('kanban.pipeline.fix_instructions'));
    }

    private function createCompany(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Company '.strtoupper($slug),
            'slug' => $slug,
            'status' => Company::STATUS_ACTIVE,
        ]);
    }

    private function createRecruiter(Company $company): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }

    /**
     * @return array{0: Job, 1: JobPipelineStage, 2: JobPipelineStage, 3: JobPipelineStage}
     */
    private function createPipeline(Company $company): array
    {
        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Neural Pipeline Engineer',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $applied = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $interview = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'interview',
            'stage_label' => 'Interview',
            'display_order' => 2,
            'is_terminal' => false,
        ]);

        $rejected = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 3,
            'is_terminal' => true,
        ]);

        return [$job, $applied, $interview, $rejected];
    }

    private function createCandidateApplication(
        Company $company,
        Job $job,
        JobPipelineStage $stage,
        string $email
    ): Application {
        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Candidate '.strtoupper(str_replace(['@', '.'], ['_', '_'], $email)),
            'email' => $email,
            'phone' => '+1-555-0100',
            'location' => 'Remote',
        ]);

        return Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);
    }
}
