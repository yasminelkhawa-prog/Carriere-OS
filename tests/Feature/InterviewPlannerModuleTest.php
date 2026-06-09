<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\InterviewParticipant;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InterviewPlannerModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_interview_list_can_be_filtered_by_job_and_interviewer(): void
    {
        $company = $this->createCompany('interview-filter-company');
        $actor = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        $interviewerA = $this->createMember($company, CompanyMembership::ROLE_MANAGER);
        $interviewerB = $this->createMember($company, CompanyMembership::ROLE_EMPLOYEE);

        [$jobA, $stageA] = $this->createJobWithStage($company, 'Backend Role');
        [$jobB, $stageB] = $this->createJobWithStage($company, 'Frontend Role');

        $applicationA = $this->createCandidateApplication($company, $jobA, $stageA, 'Candidate Alpha', 'alpha@example.com');
        $applicationB = $this->createCandidateApplication($company, $jobB, $stageB, 'Candidate Beta', 'beta@example.com');

        $interviewA = $this->createInterview($company, $applicationA, $actor);
        $interviewB = $this->createInterview($company, $applicationB, $actor);

        InterviewParticipant::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'interview_id' => $interviewA->id,
            'user_id' => $interviewerA->id,
            'participant_role' => 'interviewer',
            'created_at' => now(),
        ]);

        InterviewParticipant::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'interview_id' => $interviewB->id,
            'user_id' => $interviewerB->id,
            'participant_role' => 'interviewer',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($actor)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('interviews.index', [
                'job_id' => (string) $jobA->id,
                'interviewer_user_id' => (string) $interviewerA->id,
            ]));

        $response->assertOk();
        $response->assertSee('Candidate Alpha');
        $response->assertDontSee('Candidate Beta');
    }

    public function test_candidate_detail_schedule_requires_timezone_and_interviewer(): void
    {
        $company = $this->createCompany('interview-validation-company');
        $actor = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        [$job, $stage] = $this->createJobWithStage($company, 'Validation Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Validation Candidate', 'validation@example.com');

        $response = $this->actingAs($actor)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('candidates.index'))
            ->post(route('candidates.schedule-interview', ['application' => $application->id]), [
                'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect(route('candidates.index'));
        $response->assertSessionHasErrors(['timezone', 'interviewer_user_ids']);
    }

    public function test_company_admin_can_override_past_schedule_in_candidate_detail(): void
    {
        Mail::fake();

        $company = $this->createCompany('interview-override-company');
        $admin = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $interviewer = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        [$job, $stage] = $this->createJobWithStage($company, 'Override Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Override Candidate', 'override@example.com');

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('candidates.schedule-interview', ['application' => $application->id]), [
                'scheduled_for' => now()->subDay()->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
                'duration_minutes' => 60,
                'interviewer_user_ids' => [(string) $interviewer->id],
                'interview_type' => 'screening',
                'location_type' => Interview::LOCATION_ZOOM,
                'meeting_link' => 'https://zoom.us/j/1234567890',
                'admin_override_past' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', __('candidates.flash.interview_scheduled'));

        $this->assertTrue(
            Interview::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('status', Interview::STATUS_SCHEDULED)
                ->where('location_type', Interview::LOCATION_ZOOM)
                ->exists()
        );

        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'interview.scheduled')
                ->exists()
        );
    }

    public function test_feedback_recommendation_must_use_hire_hold_or_no(): void
    {
        $company = $this->createCompany('interview-feedback-company');
        $author = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        [$job, $stage] = $this->createJobWithStage($company, 'Feedback Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Feedback Candidate', 'feedback@example.com');
        $interview = $this->createInterview($company, $application, $author);

        InterviewParticipant::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'interview_id' => $interview->id,
            'user_id' => $author->id,
            'participant_role' => 'interviewer',
            'created_at' => now(),
        ]);

        $invalidResponse = $this->actingAs($author)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('interviews.show', ['interview' => $interview->id]))
            ->post(route('interviews.feedback.store', ['interview' => $interview->id]), [
                'recommendation' => 'strong_yes',
            ]);

        $invalidResponse->assertRedirect(route('interviews.show', ['interview' => $interview->id]));
        $invalidResponse->assertSessionHasErrors(['recommendation']);

        $validResponse = $this->actingAs($author)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('interviews.feedback.store', ['interview' => $interview->id]), [
                'recommendation' => InterviewFeedback::RECOMMENDATION_HIRE,
                'notes' => 'Strong fit.',
                'rating_technical' => 5,
            ]);

        $validResponse->assertRedirect();
        $validResponse->assertSessionHas('status', __('interviews.feedback_saved'));

        $this->assertTrue(
            InterviewFeedback::withoutGlobalScopes()
                ->where('interview_id', $interview->id)
                ->where('author_user_id', $author->id)
                ->where('recommendation', InterviewFeedback::RECOMMENDATION_HIRE)
                ->exists()
        );
    }

    public function test_non_assigned_recruiter_cannot_submit_feedback(): void
    {
        $company = $this->createCompany('interview-feedback-recruiter-company');
        $creator = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $recruiter = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        $assignedInterviewer = $this->createMember($company, CompanyMembership::ROLE_MANAGER);
        [$job, $stage] = $this->createJobWithStage($company, 'Recruiter Feedback Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Recruiter Feedback Candidate', 'recruiter-feedback@example.com');
        $interview = $this->createInterview($company, $application, $creator);

        InterviewParticipant::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'interview_id' => $interview->id,
            'user_id' => $assignedInterviewer->id,
            'participant_role' => 'interviewer',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->withHeader('Accept', 'application/json')
            ->post(route('interviews.feedback.store', ['interview' => $interview->id]), [
                'recommendation' => InterviewFeedback::RECOMMENDATION_HOLD,
                'notes' => 'Recruiter follow-up feedback.',
                'rating_communication' => 4,
            ]);

        $response->assertForbidden();
        $response->assertJson([
            'message' => __('interviews.permissions.feedback_interviewer_only'),
        ]);

        $this->assertFalse(
            InterviewFeedback::withoutGlobalScopes()
                ->where('interview_id', $interview->id)
                ->where('author_user_id', $recruiter->id)
                ->exists()
        );
    }

    public function test_interview_detail_hides_feedback_form_for_non_interviewer(): void
    {
        $company = $this->createCompany('interview-feedback-visibility-company');
        $creator = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $recruiter = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        $assignedInterviewer = $this->createMember($company, CompanyMembership::ROLE_MANAGER);
        [$job, $stage] = $this->createJobWithStage($company, 'Feedback Visibility Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Feedback Visibility Candidate', 'feedback-visibility@example.com');
        $interview = $this->createInterview($company, $application, $creator);

        InterviewParticipant::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'interview_id' => $interview->id,
            'user_id' => $assignedInterviewer->id,
            'participant_role' => 'interviewer',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('interviews.show', ['interview' => $interview->id]));

        $response->assertOk();
        $response->assertDontSee(__('interviews.detail.add_feedback'));
        $response->assertSee(__('interviews.permissions.feedback_interviewer_only'));
    }

    public function test_generate_invite_allowed_for_recruiter_view_role(): void
    {
        $company = $this->createCompany('interview-invite-recruiter-company');
        $creator = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $recruiter = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        [$job, $stage] = $this->createJobWithStage($company, 'Invite Recruiter Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Invite Recruiter Candidate', 'invite-recruiter@example.com');
        $interview = $this->createInterview($company, $application, $creator);

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('interviews.invite', ['interview' => $interview->id]));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'https://calendar.google.com/calendar/render?action=TEMPLATE',
            (string) $response->headers->get('Location')
        );
    }

    public function test_generate_invite_allowed_for_company_admin(): void
    {
        $company = $this->createCompany('interview-invite-admin-company');
        $admin = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        [$job, $stage] = $this->createJobWithStage($company, 'Invite Admin Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Invite Admin Candidate', 'invite-admin@example.com');
        $interview = $this->createInterview($company, $application, $admin);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('interviews.invite', ['interview' => $interview->id]));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'https://calendar.google.com/calendar/render?action=TEMPLATE',
            (string) $response->headers->get('Location')
        );
    }

    public function test_generate_invite_forbidden_for_unrelated_employee(): void
    {
        $company = $this->createCompany('interview-invite-employee-company');
        $creator = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $employee = $this->createMember($company, CompanyMembership::ROLE_EMPLOYEE);
        [$job, $stage] = $this->createJobWithStage($company, 'Invite Employee Role');
        $application = $this->createCandidateApplication($company, $job, $stage, 'Invite Employee Candidate', 'invite-employee@example.com');
        $interview = $this->createInterview($company, $application, $creator);

        $showResponse = $this->actingAs($employee)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('interviews.show', ['interview' => $interview->id]));

        $showResponse->assertOk();
        $showResponse->assertDontSee(__('interviews.detail.invite'));

        $inviteResponse = $this->actingAs($employee)
            ->withSession(['active_company_id' => (string) $company->id])
            ->withHeader('Accept', 'application/json')
            ->get(route('interviews.invite', ['interview' => $interview->id]));

        $inviteResponse->assertForbidden();
        $inviteResponse->assertJson([
            'message' => __('interviews.permissions.invite_forbidden'),
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

    private function createMember(Company $company, string $role): User
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

    /**
     * @return array{0: Job, 1: JobPipelineStage}
     */
    private function createJobWithStage(Company $company, string $title): array
    {
        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => $title,
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        return [$job, $stage];
    }

    private function createCandidateApplication(
        Company $company,
        Job $job,
        JobPipelineStage $stage,
        string $candidateName,
        string $candidateEmail
    ): Application {
        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => $candidateName,
            'email' => $candidateEmail,
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

    private function createInterview(Company $company, Application $application, User $creator): Interview
    {
        return Interview::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'interview_type' => 'screening',
            'scheduled_start_at' => now()->addDay()->utc(),
            'scheduled_end_at' => now()->addDay()->addHour()->utc(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://zoom.us/j/1234567890',
            'status' => Interview::STATUS_SCHEDULED,
            'created_by_user_id' => $creator->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
