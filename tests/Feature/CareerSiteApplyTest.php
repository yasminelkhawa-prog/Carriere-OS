<?php

namespace Tests\Feature;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\EmailOutboxLog;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CareerSiteApplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_creates_candidate_application_documents_and_async_cv_parsing_request(): void
    {
        Mail::fake();
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'Career Site Company',
            'slug' => 'career-site-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Senior Laravel Engineer',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $terminal = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 1,
            'is_terminal' => true,
        ]);

        $firstNonTerminal = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 2,
            'is_terminal' => false,
        ]);

        $response = $this->post(route('career.apply', [
            'company' => $company->slug,
            'job' => $job->id,
        ]), [
            'full_name' => 'Candidate One',
            'email' => 'candidate.one@gmail.com',
            'password' => 'CandidatePass123!',
            'password_confirmation' => 'CandidatePass123!',
            'phone' => '+1-555-0001',
            'location' => 'Montreal',
            'assistant_answers_json' => json_encode([
                ['question' => 'What is your full name?', 'answer' => 'Candidate One'],
                ['question' => 'What makes you a strong fit for the Senior Laravel Engineer role?', 'answer' => 'I have led Laravel platform work for several years.'],
            ], JSON_THROW_ON_ERROR),
            'resume' => UploadedFile::fake()->create('resume.pdf', 128, 'application/pdf'),
            'portfolio' => UploadedFile::fake()->create('portfolio.zip', 256, 'application/zip'),
            'consent' => '1',
            'utm_source' => 'linkedin',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'spring-hiring',
        ]);

        $application = Application::withoutGlobalScopes()->first();
        $candidate = Candidate::withoutGlobalScopes()->first();
        $user = User::query()->where('email', 'candidate.one@gmail.com')->first();

        $response->assertRedirect(route('career.apply.confirmation', [
            'company' => $company->slug,
            'job' => $job->id,
            'application' => $application?->id,
        ]));

        $confirmationResponse = $this->get(route('career.apply.confirmation', [
            'company' => $company->slug,
            'job' => $job->id,
            'application' => $application?->id,
        ]));

        $confirmationResponse->assertOk();
        $confirmationResponse->assertSee(__('career.confirmation.portal_title'));
        $confirmationResponse->assertSee(__('career.confirmation.open_portal'));
        $confirmationResponse->assertSee(route('candidate.portal', ['company' => $company->slug]), false);

        $this->assertNotNull($application);
        $this->assertNotNull($candidate);
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('CandidatePass123!', (string) $user->password));
        $this->assertSame((string) $firstNonTerminal->id, (string) $application->current_stage_id);
        $this->assertNotSame((string) $terminal->id, (string) $application->current_stage_id);
        $this->assertSame('job_board', (string) $application->source_type);

        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership);
        $this->assertSame(CompanyMembership::ROLE_CANDIDATE, (string) $membership->company_role);
        $this->assertSame(CompanyMembership::STATUS_ACTIVE, (string) $membership->membership_status);

        $resumeDocument = CandidateDocument::withoutGlobalScopes()
            ->where('candidate_id', $candidate->id)
            ->where('document_type', CandidateDocument::TYPE_RESUME)
            ->first();

        $this->assertNotNull($resumeDocument);
        $this->assertTrue(Storage::disk('local')->exists((string) $resumeDocument->file_url));

        $this->assertDatabaseHas('ai_requests', [
            'company_id' => $company->id,
            'request_type' => 'cv_parsing',
        ]);
        $this->assertTrue(
            AiRequest::withoutGlobalScopes()
                ->where('request_type', 'cv_parsing')
                ->where('company_id', $company->id)
                ->exists()
        );

        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'application.created')
                ->exists()
        );
        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'document.uploaded')
                ->exists()
        );
        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'application.assistant_completed')
                ->whereRaw("payload::text like '%Candidate One%'")
                ->exists()
        );
        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('event_type', 'email.sent')
                ->whereRaw("payload->>'template' = 'application_portal_verification'")
                ->exists()
        );

        $this->assertTrue(
            EmailOutboxLog::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('to_email', 'candidate.one@gmail.com')
                ->where('template_key', 'application_portal_verification')
                ->where('related_entity_type', 'application')
                ->where('related_entity_id', (string) $application->id)
                ->whereIn('status', [EmailOutboxLog::STATUS_QUEUED, EmailOutboxLog::STATUS_SENT])
                ->exists()
        );
    }

    public function test_apply_rejects_invalid_resume_with_clear_error_message(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'Career Site Company 2',
            'slug' => 'career-site-company-2',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Product Manager',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $response = $this->from(route('career.show', [
            'company' => $company->slug,
            'job' => $job->id,
        ]))->post(route('career.apply', [
            'company' => $company->slug,
            'job' => $job->id,
        ]), [
            'full_name' => 'Candidate Two',
            'email' => 'candidate.two@gmail.com',
            'password' => 'CandidatePass123!',
            'password_confirmation' => 'CandidatePass123!',
            'resume' => UploadedFile::fake()->create('resume.exe', 128, 'application/octet-stream'),
            'consent' => '1',
        ]);

        $response->assertRedirect(route('career.show', [
            'company' => $company->slug,
            'job' => $job->id,
        ]));
        $response->assertSessionHasErrors([
            'resume' => __('career.apply.errors.resume_mimes'),
        ]);
    }

    public function test_candidate_verification_link_logs_candidate_in_and_redirects_to_portal(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'Career Verify Company',
            'slug' => 'career-verify-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $this->post(route('career.apply', [
            'company' => $company->slug,
            'job' => $job->id,
        ]), [
            'full_name' => 'Candidate Verify',
            'email' => 'candidate.verify@gmail.com',
            'password' => 'CandidatePass123!',
            'password_confirmation' => 'CandidatePass123!',
            'resume' => UploadedFile::fake()->create('resume.pdf', 128, 'application/pdf'),
            'consent' => '1',
        ])->assertRedirect();

        $application = Application::withoutGlobalScopes()->first();
        $this->assertNotNull($application);

        $user = User::query()->where('email', 'candidate.verify@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user?->hasVerifiedEmail());

        $signedUrl = URL::temporarySignedRoute(
            'candidate.email.verify-login',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'user' => (string) $user?->id,
                'company' => (string) $company->id,
                'application' => (string) $application?->id,
                'hash' => sha1((string) $user?->getEmailForVerification()),
            ]
        );

        $response = $this->get($signedUrl);

        $response->assertRedirect(route('candidate.portal', ['company' => $company->slug]));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) $user?->fresh()?->hasVerifiedEmail());
        $this->assertSame((string) $company->id, (string) session('active_company_id'));
    }

    public function test_apply_to_unpublished_or_archived_job_is_blocked(): void
    {
        $company = Company::query()->create([
            'name' => 'Career Site Company 3',
            'slug' => 'career-site-company-3',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $archivedJob = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Archived Role',
            'status' => Job::STATUS_ARCHIVED,
        ]);

        $response = $this->post(route('career.apply', [
            'company' => $company->slug,
            'job' => $archivedJob->id,
        ]), [
            'full_name' => 'Candidate Three',
            'email' => 'candidate.three@gmail.com',
            'password' => 'CandidatePass123!',
            'password_confirmation' => 'CandidatePass123!',
            'resume' => UploadedFile::fake()->create('resume.pdf', 64, 'application/pdf'),
            'consent' => '1',
        ]);

        $response->assertNotFound();
    }

    public function test_candidate_with_existing_application_sees_disabled_actions_and_cannot_reapply(): void
    {
        Storage::fake('local');

        $company = Company::query()->create([
            'name' => 'Career Site Company 4',
            'slug' => 'career-site-company-4',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'QA Engineer',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $user = User::factory()->create([
            'email' => 'candidate.existing@gmail.com',
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'full_name' => 'Existing Candidate',
            'email' => 'candidate.existing@gmail.com',
            'phone' => '+1-555-0004',
            'location' => 'Lahore',
        ]);

        Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $indexResponse = $this->actingAs($user)->get(route('career.index', [
            'company' => $company->slug,
        ]));
        $indexResponse->assertOk();
        $indexResponse->assertSee(__('career.list.already_applied'));

        $showResponse = $this->actingAs($user)->get(route('career.show', [
            'company' => $company->slug,
            'job' => $job->id,
        ]));
        $showResponse->assertOk();
        $showResponse->assertSee(__('career.apply.already_applied_notice'));
        $showResponse->assertDontSee(__('career.apply.submit'));

        $applyResponse = $this->actingAs($user)
            ->from(route('career.show', [
                'company' => $company->slug,
                'job' => $job->id,
            ]))
            ->post(route('career.apply', [
                'company' => $company->slug,
                'job' => $job->id,
            ]), [
                'full_name' => 'Existing Candidate',
                'email' => 'candidate.existing@gmail.com',
                'password' => 'CandidatePass123!',
                'password_confirmation' => 'CandidatePass123!',
                'resume' => UploadedFile::fake()->create('resume.pdf', 128, 'application/pdf'),
                'consent' => '1',
            ]);

        $applyResponse->assertRedirect(route('career.show', [
            'company' => $company->slug,
            'job' => $job->id,
        ]));
        $applyResponse->assertSessionHasErrors([
            'email' => __('career.apply.errors.already_applied'),
        ]);
    }
}
