<?php

namespace Tests\Feature;

use App\Jobs\SendEmailOutboxJob;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\EmailOutboxLog;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\RejectionDraft;
use App\Models\User;
use App\Services\Communication\CommunicationEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunicationEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_template_email_fails_safely_when_required_variables_are_missing(): void
    {
        $company = Company::query()->create([
            'name' => 'Communication Co',
            'slug' => 'communication-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $service = app(CommunicationEngineService::class);

        $outcome = $service->queueTemplateEmail(
            companyId: (string) $company->id,
            templateKey: 'application_acknowledgement',
            toEmail: 'alex@example.com',
            toName: 'Alex',
            language: 'en',
            variables: [
                'candidate_name' => 'Alex',
                'job_title' => 'Engineer',
                'company_name' => 'Communication Co',
            ],
            relatedEntityType: 'application',
            relatedEntityId: (string) fake()->uuid()
        );

        $this->assertFalse($outcome['ok']);
        $this->assertStringContainsString('application_reference', (string) $outcome['error']);

        $this->assertTrue(
            EmailOutboxLog::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('template_key', 'application_acknowledgement')
                ->where('status', EmailOutboxLog::STATUS_FAILED)
                ->exists()
        );
    }

    public function test_queue_template_email_renders_french_template_when_requested(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Communication Locale Co',
            'slug' => 'communication-locale-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $service = app(CommunicationEngineService::class);

        $outcome = $service->queueTemplateEmail(
            companyId: (string) $company->id,
            templateKey: 'application_acknowledgement',
            toEmail: 'candidate.fr@example.com',
            toName: 'Candidate FR',
            language: 'fr',
            variables: [
                'candidate_name' => 'Candidate FR',
                'job_title' => 'Responsable Produit',
                'company_name' => 'Communication Locale Co',
                'application_reference' => 'APP-FR-001',
            ],
            relatedEntityType: 'application',
            relatedEntityId: (string) fake()->uuid()
        );

        $this->assertTrue($outcome['ok']);
        $this->assertNotNull($outcome['log']);

        $log = EmailOutboxLog::withoutGlobalScopes()->find((string) $outcome['log']->id);
        $this->assertNotNull($log);
        $this->assertSame('application_acknowledgement', (string) $log->template_key);
        $this->assertStringContainsString('Bonjour', (string) $log->body);
    }

    public function test_queue_template_email_fails_safely_when_recipient_email_is_missing(): void
    {
        $company = Company::query()->create([
            'name' => 'Communication Recipient Co',
            'slug' => 'communication-recipient-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $service = app(CommunicationEngineService::class);

        $outcome = $service->queueTemplateEmail(
            companyId: (string) $company->id,
            templateKey: 'interview_confirmation',
            toEmail: '   ',
            toName: 'Candidate',
            language: 'en',
            variables: [
                'candidate_name' => 'Candidate',
                'job_title' => 'Engineer',
                'scheduled_for' => '2026-03-01 10:00 UTC',
                'channel' => 'Zoom',
                'meeting_link' => 'https://zoom.us/j/1234567890',
            ],
            relatedEntityType: 'interview',
            relatedEntityId: (string) fake()->uuid()
        );

        $this->assertFalse($outcome['ok']);
        $this->assertSame(__('communications.errors.missing_candidate_email'), (string) $outcome['error']);

        $this->assertTrue(
            EmailOutboxLog::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('template_key', 'interview_confirmation')
                ->where('status', EmailOutboxLog::STATUS_FAILED)
                ->where('to_email', '')
                ->exists()
        );
    }

    public function test_send_outbox_job_marks_related_rejection_draft_as_sent(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Communication Rejection Co',
            'slug' => 'communication-rejection-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Backend Engineer',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'full_name' => 'Candidate Reject',
            'email' => 'candidate.reject@example.com',
            'phone' => '+1-555-0110',
            'location' => 'Remote',
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_REJECTED,
            'source_type' => 'career_page',
        ]);

        $rejectionDraft = RejectionDraft::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'draft_subject' => 'Application Update',
            'draft_body' => 'Thank you for your interest.',
            'xai_reason_text' => 'Skill alignment gap.',
            'status' => RejectionDraft::STATUS_APPROVED,
        ]);

        $outboxLog = EmailOutboxLog::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'to_email' => $candidate->email,
            'to_name' => $candidate->full_name,
            'subject' => 'Application Update',
            'body' => 'Thank you for your interest.',
            'status' => EmailOutboxLog::STATUS_QUEUED,
            'template_key' => 'rejection_decision',
            'related_entity_type' => 'rejection_draft',
            'related_entity_id' => (string) $rejectionDraft->id,
            'created_at' => now(),
            'sent_at' => null,
            'error_message' => null,
        ]);

        (new SendEmailOutboxJob((string) $outboxLog->id))->handle();

        $outboxLog->refresh();
        $rejectionDraft->refresh();

        $this->assertSame(EmailOutboxLog::STATUS_SENT, (string) $outboxLog->status);
        $this->assertSame(RejectionDraft::STATUS_SENT, (string) $rejectionDraft->status);
    }

    public function test_admin_can_retry_failed_outbox_log_from_template_manager(): void
    {
        Mail::fake();

        $company = Company::query()->create([
            'name' => 'Communication Retry Co',
            'slug' => 'communication-retry-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $failedLog = EmailOutboxLog::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'to_email' => 'candidate.retry@example.com',
            'to_name' => 'Candidate Retry',
            'subject' => 'Retry Subject',
            'body' => 'Retry Body',
            'status' => EmailOutboxLog::STATUS_FAILED,
            'template_key' => 'application_acknowledgement',
            'related_entity_type' => 'application',
            'related_entity_id' => (string) fake()->uuid(),
            'created_at' => now(),
            'sent_at' => null,
            'error_message' => 'Original transport error',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('admin.email-templates.retry-outbox', $failedLog), [
                'template_key' => 'application_acknowledgement',
                'language' => 'en',
                'status' => EmailOutboxLog::STATUS_FAILED,
            ]);

        $response->assertRedirect(route('admin.email-templates.index', [
            'template_key' => 'application_acknowledgement',
            'language' => 'en',
            'status' => EmailOutboxLog::STATUS_FAILED,
        ]));
        $response->assertSessionHas('status', __('communications.flash.retry_queued'));

        $failedLog->refresh();
        $this->assertContains((string) $failedLog->status, [
            EmailOutboxLog::STATUS_QUEUED,
            EmailOutboxLog::STATUS_SENT,
        ]);
        $this->assertNull($failedLog->error_message);
    }
}
