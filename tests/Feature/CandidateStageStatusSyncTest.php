<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateStageStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_stage_keeps_application_status_in_sync_with_target_stage(): void
    {
        $company = Company::query()->create([
            'name' => 'Stage Sync Company',
            'slug' => 'stage-sync-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiter = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
            'platform_role' => User::PLATFORM_NONE,
        ]);

        CompanyMembership::query()->create([
            'company_id' => (string) $company->id,
            'user_id' => (string) $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => (string) $company->id,
            'title' => 'Stage Sync Engineer',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $appliedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $job->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $hiredStage = JobPipelineStage::query()->create([
            'job_id' => (string) $job->id,
            'stage_key' => 'hired',
            'stage_label' => 'Hired',
            'display_order' => 2,
            'is_terminal' => true,
        ]);

        $rejectedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $job->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 3,
            'is_terminal' => true,
        ]);

        $candidate = Candidate::query()->create([
            'company_id' => (string) $company->id,
            'full_name' => 'Stage Sync Candidate',
            'email' => 'stage-sync-candidate@example.com',
        ]);

        $application = Application::query()->create([
            'company_id' => (string) $company->id,
            'candidate_id' => (string) $candidate->id,
            'job_id' => (string) $job->id,
            'current_stage_id' => (string) $appliedStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('candidates.move-stage', ['application' => (string) $application->id]), [
                'stage_id' => (string) $hiredStage->id,
                'company_id' => (string) $company->id,
            ])
            ->assertStatus(302);

        $application->refresh();
        $this->assertSame((string) $hiredStage->id, (string) $application->current_stage_id);
        $this->assertSame(Application::STATUS_HIRED, (string) $application->status);

        $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->post(route('candidates.move-stage', ['application' => (string) $application->id]), [
                'stage_id' => (string) $rejectedStage->id,
                'company_id' => (string) $company->id,
            ])
            ->assertStatus(302);

        $application->refresh();
        $this->assertSame((string) $rejectedStage->id, (string) $application->current_stage_id);
        $this->assertSame(Application::STATUS_REJECTED, (string) $application->status);
    }
}

