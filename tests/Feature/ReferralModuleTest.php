<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReferralModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_referrals_and_duplicate_is_prevented_with_friendly_message(): void
    {
        Storage::fake('local');
        $context = $this->createReferralContext();

        $response = $this->actingAs($context['employee'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('referrals.store'), [
                'candidate_email' => 'ReFerRal.One@Example.Test',
                'candidate_name' => 'Referral One',
                'candidate_linkedin_url' => 'https://www.linkedin.com/in/referral-one',
                'resume' => UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf'),
            ]);

        $response->assertRedirect(route('referrals.index'));
        $this->assertDatabaseHas('referrals', [
            'company_id' => (string) $context['company']->id,
            'referrer_user_id' => (string) $context['employee']->id,
            'candidate_email' => 'referral.one@example.test',
            'status' => Referral::STATUS_SUBMITTED,
        ]);

        $createdReferral = Referral::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('candidate_email', 'referral.one@example.test')
            ->first();
        $this->assertInstanceOf(Referral::class, $createdReferral);
        $this->assertNotNull($createdReferral->resume_file_url);
        Storage::disk('local')->assertExists((string) $createdReferral->resume_file_url);

        $duplicate = $this->actingAs($context['employee'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('referrals.store'), [
                'candidate_email' => 'referral.one@example.test',
                'candidate_name' => 'Duplicate Candidate',
            ]);

        $duplicate->assertSessionHasErrors([
            'candidate_email' => __('referrals.validation.duplicate_referral'),
        ]);
    }

    public function test_referral_list_has_select2_status_filter_and_updates_with_filters(): void
    {
        $context = $this->createReferralContext();

        Referral::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'referrer_user_id' => $context['employee']->id,
            'candidate_email' => 'submitted@example.test',
            'candidate_name' => 'Submitted Referral',
            'candidate_linkedin_url' => null,
            'resume_file_url' => null,
            'status' => Referral::STATUS_SUBMITTED,
        ]);

        Referral::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'referrer_user_id' => $context['employee']->id,
            'candidate_email' => 'hired@example.test',
            'candidate_name' => 'Hired Referral',
            'candidate_linkedin_url' => null,
            'resume_file_url' => null,
            'status' => Referral::STATUS_HIRED,
        ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('referrals.index', ['status' => Referral::STATUS_SUBMITTED]));

        $response->assertOk();
        $response->assertSee('name="status"', false);
        $response->assertSee('data-placeholder=', false);
        $response->assertSee('submitted@example.test');
        $response->assertDontSee('hired@example.test');
    }

    public function test_recruiter_can_convert_referral_and_application_source_is_referral_channel(): void
    {
        $context = $this->createReferralContext();

        $referral = Referral::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'referrer_user_id' => $context['employee']->id,
            'candidate_email' => 'convert.me@example.test',
            'candidate_name' => 'Convert Me',
            'candidate_linkedin_url' => null,
            'resume_file_url' => null,
            'status' => Referral::STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('referrals.convert', ['referral' => $referral->id]), [
                'job_id' => (string) $context['job']->id,
            ]);

        $response->assertRedirect();

        $application = Application::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('source_type', 'referral')
            ->where('source_detail', (string) $referral->id)
            ->first();

        $this->assertInstanceOf(Application::class, $application);
        $this->assertSame('referral', (string) $application->source_type);
        $this->assertSame((string) $referral->id, (string) $application->source_detail);
        $this->assertSame(Referral::STATUS_CONVERTED, (string) $referral->fresh()->status);

        $candidate = Candidate::withoutGlobalScopes()->find($application->candidate_id);
        $this->assertInstanceOf(Candidate::class, $candidate);
        $this->assertSame('convert.me@example.test', (string) $candidate->email);

        $overview = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home', ['date_range' => 'all']));
        $overview->assertOk();
        $sourcePerformance = $overview->viewData('sourcePerformance');
        $this->assertInstanceOf(Collection::class, $sourcePerformance);
        $this->assertTrue(
            $sourcePerformance->contains(static fn (array $row): bool => (string) ($row['source_type'] ?? '') === 'referral')
        );
    }

    public function test_conversion_failure_returns_user_friendly_error_message(): void
    {
        $context = $this->createReferralContext();

        $referral = Referral::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'referrer_user_id' => $context['employee']->id,
            'candidate_email' => 'duplicate.application@example.test',
            'candidate_name' => 'Duplicate Application',
            'candidate_linkedin_url' => null,
            'resume_file_url' => null,
            'status' => Referral::STATUS_SUBMITTED,
        ]);

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'user_id' => null,
            'full_name' => 'Duplicate Application',
            'email' => 'duplicate.application@example.test',
            'phone' => null,
            'location' => null,
        ]);

        Application::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'candidate_id' => $candidate->id,
            'job_id' => $context['job']->id,
            'current_stage_id' => $context['initialStage']->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'manual',
            'source_detail' => null,
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
        ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->from(route('referrals.index'))
            ->post(route('referrals.convert', ['referral' => $referral->id]), [
                'referral_id' => (string) $referral->id,
                'job_id' => (string) $context['job']->id,
            ]);

        $response->assertRedirect(route('referrals.index'));
        $response->assertSessionHas('error', __('referrals.validation.duplicate_active_application'));
        $response->assertSessionHasErrors([
            'referral' => __('referrals.validation.duplicate_active_application'),
        ]);

        $this->assertDatabaseMissing('applications', [
            'source_type' => 'referral',
            'source_detail' => (string) $referral->id,
        ]);
    }

    public function test_referral_status_updates_when_linked_application_reaches_terminal_outcomes(): void
    {
        $context = $this->createReferralContext();

        $hiredReferral = Referral::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'referrer_user_id' => $context['employee']->id,
            'candidate_email' => 'hire.me@example.test',
            'candidate_name' => 'Hire Me',
            'candidate_linkedin_url' => null,
            'resume_file_url' => null,
            'status' => Referral::STATUS_SUBMITTED,
        ]);
        $rejectedReferral = Referral::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'referrer_user_id' => $context['employee']->id,
            'candidate_email' => 'reject.me@example.test',
            'candidate_name' => 'Reject Me',
            'candidate_linkedin_url' => null,
            'resume_file_url' => null,
            'status' => Referral::STATUS_SUBMITTED,
        ]);

        $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('referrals.convert', ['referral' => $hiredReferral->id]), [
                'job_id' => (string) $context['job']->id,
            ])->assertRedirect();

        $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('referrals.convert', ['referral' => $rejectedReferral->id]), [
                'job_id' => (string) $context['job']->id,
            ])->assertRedirect();

        $hiredApplication = Application::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('source_detail', (string) $hiredReferral->id)
            ->first();
        $rejectedApplication = Application::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('source_detail', (string) $rejectedReferral->id)
            ->first();

        $this->assertInstanceOf(Application::class, $hiredApplication);
        $this->assertInstanceOf(Application::class, $rejectedApplication);

        $hiredApplication->forceFill([
            'current_stage_id' => (string) $context['hiredStage']->id,
            'status' => Application::STATUS_ACTIVE,
        ])->save();

        $rejectedApplication->forceFill([
            'current_stage_id' => (string) $context['rejectedStage']->id,
            'status' => Application::STATUS_REJECTED,
        ])->save();

        $this->assertSame(Referral::STATUS_HIRED, (string) $hiredReferral->fresh()->status);
        $this->assertSame(Referral::STATUS_REJECTED, (string) $rejectedReferral->fresh()->status);
    }

    public function test_permissions_for_referral_module_are_enforced(): void
    {
        $context = $this->createReferralContext();
        $candidate = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);
        CompanyMembership::query()->create([
            'company_id' => $context['company']->id,
            'user_id' => $candidate->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $referral = Referral::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'referrer_user_id' => $context['employee']->id,
            'candidate_email' => 'secure@example.test',
            'candidate_name' => 'Secure Candidate',
            'candidate_linkedin_url' => null,
            'resume_file_url' => null,
            'status' => Referral::STATUS_SUBMITTED,
        ]);

        $employeeConvert = $this->actingAs($context['employee'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('referrals.convert', ['referral' => $referral->id]), [
                'job_id' => (string) $context['job']->id,
            ]);
        $employeeConvert->assertForbidden();

        $candidateIndex = $this->actingAs($candidate)
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('referrals.index'));
        $candidateIndex->assertForbidden();
    }

    /**
     * @return array{
     *   company: Company,
     *   employee: User,
     *   recruiter: User,
     *   job: Job,
     *   initialStage: JobPipelineStage,
     *   hiredStage: JobPipelineStage,
     *   rejectedStage: JobPipelineStage
     * }
     */
    private function createReferralContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Referral Test Co',
            'slug' => 'referral-test-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $department = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Engineering',
        ]);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'title' => 'Backend Engineer',
            'location' => 'Remote',
            'status' => Job::STATUS_PUBLISHED,
            'blind_mode_active' => false,
        ]);

        $initialStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);
        $hiredStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'hired',
            'stage_label' => 'Hired',
            'display_order' => 2,
            'is_terminal' => true,
        ]);
        $rejectedStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 3,
            'is_terminal' => true,
        ]);

        $employee = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);
        $recruiter = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'company_role' => CompanyMembership::ROLE_EMPLOYEE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return [
            'company' => $company,
            'employee' => $employee,
            'recruiter' => $recruiter,
            'job' => $job,
            'initialStage' => $initialStage,
            'hiredStage' => $hiredStage,
            'rejectedStage' => $rejectedStage,
        ];
    }
}
