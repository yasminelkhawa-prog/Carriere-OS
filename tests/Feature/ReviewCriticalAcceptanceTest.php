<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyIntegration;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCriticalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recruiter_job_multiposting_acceptance_flow_enforces_channel_scope_and_oauth_gating(): void
    {
        $company = $this->createCompany('review-acceptance-company');
        $admin = $this->createCompanyAdmin($company);
        $job = $this->createPublishedJobWithDescriptionAndPipeline($company);

        $initialPage = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('jobs.show', ['job' => $job->id]));

        $initialPage->assertOk();
        $initialPage->assertSee('Review Critical Platform Engineer');
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'description_html' => '<p>Build resilient multiposting workflows for recruiter publishing.</p>',
        ]);
        $initialPage->assertSee(__('jobs.multiposting.title'));
        $initialPage->assertSee('Indeed');
        $initialPage->assertSee('Glassdoor');
        $initialPage->assertSee('LinkedIn');
        $initialPage->assertSee(__('jobs.multiposting.publish_methods.feed'));
        $initialPage->assertSee(__('jobs.multiposting.readiness.not_ready'));
        $initialPage->assertSee(__('jobs.multiposting.readiness.oauth_required'));
        $initialPage->assertSee(__('jobs.multiposting.readiness.open_configuration'));

        $publicPage = $this->get(route('career.show', ['company' => $company->slug, 'job' => $job->id]));
        $publicPage->assertOk();
        $publicPage->assertSee('Build resilient multiposting workflows for recruiter publishing.', false);
        $publicPage->assertSee('JobPosting');

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('jobs.show', ['job' => $job->id]))
            ->post(route('jobs.multiposting.bulk', ['job' => $job->id]), [
                'action' => 'enable',
                'platforms' => ['indeed', 'glassdoor'],
            ])
            ->assertRedirect(route('jobs.show', ['job' => $job->id]))
            ->assertSessionHas('status', __('jobs.multiposting.flash.bulk_completed', [
                'action' => __('jobs.multiposting.bulk.actions.enable'),
                'platforms' => 'Indeed, Glassdoor',
            ]));

        $this->assertDatabaseHas('job_postings', [
            'job_id' => $job->id,
            'platform' => 'indeed',
            'status' => JobPosting::STATUS_DRAFT,
        ]);
        $this->assertDatabaseHas('job_postings', [
            'job_id' => $job->id,
            'platform' => 'glassdoor',
            'status' => JobPosting::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->from(route('jobs.show', ['job' => $job->id]))
            ->post(route('jobs.multiposting.publish', ['job' => $job->id, 'platform' => 'linkedin']))
            ->assertRedirect(route('jobs.show', ['job' => $job->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('job_postings', [
            'job_id' => $job->id,
            'platform' => 'linkedin',
            'status' => JobPosting::STATUS_DRAFT,
        ]);

        CompanyIntegration::query()->create([
            'company_id' => $company->id,
            'provider' => CompanyIntegration::PROVIDER_LINKEDIN,
            'status' => CompanyIntegration::STATUS_CONNECTED,
            'access_token' => 'review-acceptance-token',
            'last_connected_at' => now(),
        ]);

        $connectedPage = $this->actingAs($admin)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('jobs.show', ['job' => $job->id]));

        $connectedPage->assertOk();
        $connectedPage->assertSee('LinkedIn');
        $connectedPage->assertDontSee(__('jobs.multiposting.readiness.not_ready'));
        $connectedPage->assertDontSee(__('jobs.multiposting.readiness.oauth_required'));
        $connectedPage->assertDontSee(__('jobs.multiposting.readiness.open_configuration'));
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

    private function createPublishedJobWithDescriptionAndPipeline(Company $company): Job
    {
        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Review Critical Platform Engineer',
            'description_html' => '<p>Build resilient multiposting workflows for recruiter publishing.</p>',
            'status' => Job::STATUS_PUBLISHED,
            'location' => 'Remote',
            'employment_type' => Job::EMPLOYMENT_FULL_TIME,
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
