<?php

namespace Tests\Feature;

use App\Http\Controllers\CandidateWorkspaceController;
use App\Http\Controllers\ReportingExportController;
use App\Jobs\GenerateExportJob;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Export;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use Database\Seeders\ReportingExportsModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportingExportsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_export_request_queues_background_job(): void
    {
        $context = $this->createContext();
        Queue::fake();

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('home.export'), [
                'job_id' => (string) $context['jobBlind']->id,
                'date_range' => 'all',
                'format' => Export::FORMAT_CSV,
            ]);

        $response->assertRedirect(route('home', [
            'job_id' => (string) $context['jobBlind']->id,
            'date_range' => 'all',
        ]));
        $response->assertSessionHas('status', __('ui.exports.flash.queued'));

        $this->assertDatabaseHas('exports', [
            'company_id' => (string) $context['company']->id,
            'export_type' => Export::TYPE_DASHBOARD_OVERVIEW,
            'requested_by_user_id' => (string) $context['recruiter']->id,
            'format' => Export::FORMAT_CSV,
            'status' => Export::STATUS_QUEUED,
        ]);

        Queue::assertPushed(GenerateExportJob::class, 1);
    }

    public function test_overview_export_matches_filters_and_generates_file(): void
    {
        $context = $this->createContext();

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('home.export'), [
                'job_id' => (string) $context['jobBlind']->id,
                'date_range' => 'all',
                'format' => Export::FORMAT_CSV,
            ]);

        $response->assertRedirect(route('home', [
            'job_id' => (string) $context['jobBlind']->id,
            'date_range' => 'all',
        ]));

        $export = Export::withoutGlobalScopes()->latest('created_at')->first();
        $this->assertInstanceOf(Export::class, $export);
        $this->assertSame(Export::STATUS_COMPLETED, (string) $export->status);
        $this->assertNotNull($export->file_url);
        $this->assertTrue(Storage::disk('local')->exists((string) $export->file_url));

        $content = Storage::disk('local')->get((string) $export->file_url);
        $lines = array_values(array_filter(array_map('trim', explode("\n", trim((string) $content))), static fn (string $line): bool => $line !== ''));

        $expectedCount = Application::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('job_id', $context['jobBlind']->id)
            ->count();

        $this->assertSame($expectedCount + 1, count($lines));
    }

    public function test_blind_mode_screening_candidate_export_excludes_identity_fields(): void
    {
        $context = $this->createContext();

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.export'), [
                'job_id' => (string) $context['jobBlind']->id,
                'stage_id' => (string) $context['blindScreeningStage']->id,
                'status' => Application::STATUS_ACTIVE,
                'format' => Export::FORMAT_CSV,
            ]);

        $response->assertRedirect(route('candidates.index', [
            'job_id' => (string) $context['jobBlind']->id,
            'stage_id' => (string) $context['blindScreeningStage']->id,
            'status' => Application::STATUS_ACTIVE,
        ]));

        $export = Export::withoutGlobalScopes()->latest('created_at')->first();
        $this->assertInstanceOf(Export::class, $export);
        $this->assertSame(Export::TYPE_CANDIDATE_LIST, (string) $export->export_type);
        $this->assertSame(Export::STATUS_COMPLETED, (string) $export->status);

        $content = Storage::disk('local')->get((string) $export->file_url);
        $this->assertStringContainsString('Masked Identifier', $content);
        $this->assertStringNotContainsString('Candidate', $content);
        $this->assertStringNotContainsString('blind.screening@example.test', $content);
        $this->assertStringContainsString(
            CandidateWorkspaceController::maskedCandidateIdentifier((string) $context['blindScreeningApplication']->id),
            $content
        );
    }

    public function test_non_screening_candidate_export_contains_identity_fields(): void
    {
        $context = $this->createContext();

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.export'), [
                'job_id' => (string) $context['jobBlind']->id,
                'stage_id' => (string) $context['blindInterviewStage']->id,
                'status' => Application::STATUS_ACTIVE,
                'format' => Export::FORMAT_CSV,
            ]);

        $response->assertRedirect(route('candidates.index', [
            'job_id' => (string) $context['jobBlind']->id,
            'stage_id' => (string) $context['blindInterviewStage']->id,
            'status' => Application::STATUS_ACTIVE,
        ]));

        $export = Export::withoutGlobalScopes()->latest('created_at')->first();
        $this->assertInstanceOf(Export::class, $export);

        $content = Storage::disk('local')->get((string) $export->file_url);
        $this->assertStringContainsString('Candidate', $content);
        $this->assertStringContainsString('Blind Interview Candidate', $content);
        $this->assertStringContainsString('blind.interview@example.test', $content);
    }

    public function test_company_admin_is_redirected_to_export_history_after_requesting_export(): void
    {
        $context = $this->createContext();

        $response = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.export'), [
                'job_id' => (string) $context['jobBlind']->id,
                'stage_id' => (string) $context['blindScreeningStage']->id,
                'status' => Application::STATUS_ACTIVE,
                'format' => Export::FORMAT_CSV,
            ]);

        $response->assertRedirect(route('admin.exports.index', [
            'export_type' => Export::TYPE_CANDIDATE_LIST,
        ]));
        $response->assertSessionHas('status', __('ui.exports.flash.queued'));
    }

    public function test_export_download_is_signed_and_permissioned(): void
    {
        $context = $this->createContext();

        $path = 'private/exports/'.(string) $context['company']->id.'/manual-export.csv';
        Storage::disk('local')->put($path, "col1,col2\nx,y\n");

        $export = Export::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'export_type' => Export::TYPE_CANDIDATE_LIST,
            'requested_by_user_id' => (string) $context['recruiter']->id,
            'filters_json' => ['stage_id' => (string) $context['blindScreeningStage']->id],
            'format' => Export::FORMAT_CSV,
            'status' => Export::STATUS_COMPLETED,
            'file_url' => $path,
        ]);

        $signedUrl = ReportingExportController::signedDownloadUrl($export);
        $relativeSigned = $this->toRelativeUrl($signedUrl);

        $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get($relativeSigned)
            ->assertOk();

        $this->actingAs($context['otherRecruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get($relativeSigned)
            ->assertRedirect(route('home'));

        $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get($relativeSigned)
            ->assertOk();

        $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('exports.download', ['export' => $export->id]))
            ->assertRedirect(route('home'));
    }

    public function test_admin_export_history_page_shows_download_links(): void
    {
        $context = $this->createContext();

        $path = 'private/exports/'.(string) $context['company']->id.'/history-export.csv';
        Storage::disk('local')->put($path, "h1,h2\n1,2\n");

        Export::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'export_type' => Export::TYPE_DASHBOARD_OVERVIEW,
            'requested_by_user_id' => (string) $context['recruiter']->id,
            'filters_json' => ['date_range' => 'all'],
            'format' => Export::FORMAT_CSV,
            'status' => Export::STATUS_COMPLETED,
            'file_url' => $path,
        ]);

        $response = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('admin.exports.index'));

        $response->assertOk();
        $response->assertSee(__('ui.exports.history.heading'));
        $response->assertSee(__('ui.exports.types.dashboard_overview'));
        $response->assertSee(__('ui.exports.history.download'));
    }

    public function test_reporting_exports_seeder_creates_dashboard_and_candidate_exports(): void
    {
        $company = Company::query()->create([
            'name' => 'numa Demo',
            'slug' => 'numa-demo',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => (string) $company->id,
            'user_id' => (string) $admin->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $this->seed(ReportingExportsModuleSeeder::class);

        $this->assertTrue(
            Export::withoutGlobalScopes()
                ->where('export_type', Export::TYPE_DASHBOARD_OVERVIEW)
                ->where('status', Export::STATUS_COMPLETED)
                ->exists()
        );
        $this->assertTrue(
            Export::withoutGlobalScopes()
                ->where('export_type', Export::TYPE_CANDIDATE_LIST)
                ->where('status', Export::STATUS_COMPLETED)
                ->exists()
        );
    }

    /**
     * @return array{
     *   company: Company,
     *   admin: User,
     *   recruiter: User,
     *   otherRecruiter: User,
     *   jobBlind: Job,
     *   jobRegular: Job,
     *   blindScreeningStage: JobPipelineStage,
     *   blindInterviewStage: JobPipelineStage,
     *   regularScreeningStage: JobPipelineStage,
     *   blindScreeningApplication: Application
     * }
     */
    private function createContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Exports Test Co',
            'slug' => 'exports-test-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $recruiter = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);
        $otherRecruiter = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);

        $jobBlind = Job::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'title' => 'Blind Mode Job',
            'status' => Job::STATUS_PUBLISHED,
            'blind_mode_active' => true,
        ]);
        $jobRegular = Job::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'title' => 'Regular Job',
            'status' => Job::STATUS_PUBLISHED,
            'blind_mode_active' => false,
        ]);

        $blindScreeningStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => (string) $jobBlind->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);
        $blindInterviewStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => (string) $jobBlind->id,
            'stage_key' => 'interview',
            'stage_label' => 'Interview',
            'display_order' => 2,
            'is_terminal' => false,
        ]);
        $regularScreeningStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => (string) $jobRegular->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $blindScreeningApplication = $this->createCandidateApplication(
            company: $company,
            job: $jobBlind,
            stage: $blindScreeningStage,
            fullName: 'Blind Screening Candidate',
            email: 'blind.screening@example.test'
        );
        $this->createCandidateApplication(
            company: $company,
            job: $jobBlind,
            stage: $blindInterviewStage,
            fullName: 'Blind Interview Candidate',
            email: 'blind.interview@example.test'
        );
        $this->createCandidateApplication(
            company: $company,
            job: $jobRegular,
            stage: $regularScreeningStage,
            fullName: 'Regular Candidate',
            email: 'regular@example.test'
        );

        return compact(
            'company',
            'admin',
            'recruiter',
            'otherRecruiter',
            'jobBlind',
            'jobRegular',
            'blindScreeningStage',
            'blindInterviewStage',
            'regularScreeningStage',
            'blindScreeningApplication'
        );
    }

    private function createMember(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => (string) $company->id,
            'user_id' => (string) $user->id,
            'company_role' => $role,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }

    private function createCandidateApplication(
        Company $company,
        Job $job,
        JobPipelineStage $stage,
        string $fullName,
        string $email
    ): Application {
        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'full_name' => $fullName,
            'email' => $email,
            'location' => 'Remote',
        ]);

        return Application::withoutGlobalScopes()->create([
            'company_id' => (string) $company->id,
            'candidate_id' => (string) $candidate->id,
            'job_id' => (string) $job->id,
            'current_stage_id' => (string) $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);
    }

    private function toRelativeUrl(string $absoluteUrl): string
    {
        $parts = parse_url($absoluteUrl);
        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }
}

