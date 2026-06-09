<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationScoring;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Department;
use App\Models\Export;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentOverviewDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_kpis_are_db_backed_and_change_with_filters(): void
    {
        $context = $this->createAnalyticsContext();

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home', ['date_range' => 'all']));

        $response->assertOk();
        $kpis = $response->viewData('kpis');
        $this->assertIsArray($kpis);
        $this->assertSame(20, (int) ($kpis['total_applications'] ?? 0));
        $this->assertSame(18, (int) ($kpis['active_pipeline'] ?? 0));

        $filtered = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home', [
                'date_range' => 'all',
                'job_id' => (string) $context['jobs']->first()->id,
            ]));

        $filtered->assertOk();
        $filteredKpis = $filtered->viewData('kpis');
        $this->assertIsArray($filteredKpis);
        $this->assertSame(10, (int) ($filteredKpis['total_applications'] ?? 0));
    }

    public function test_funnel_drilldown_opens_filtered_candidate_view(): void
    {
        $context = $this->createAnalyticsContext();

        $overview = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home', ['date_range' => 'all']));

        $overview->assertOk();
        $funnel = $overview->viewData('funnelStages');
        $this->assertInstanceOf(Collection::class, $funnel);
        $this->assertNotEmpty($funnel);

        $stage = $funnel->first();
        $this->assertIsArray($stage);
        $this->assertArrayHasKey('drilldown_url', $stage);

        $drilldown = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get((string) $stage['drilldown_url']);

        $drilldown->assertOk();
        $filters = $drilldown->viewData('filters');
        $this->assertSame((string) $stage['stage_id'], (string) ($filters['stage_id'] ?? ''));

        $applications = $drilldown->viewData('applications');
        $this->assertInstanceOf(LengthAwarePaginator::class, $applications);
        foreach ($applications->items() as $application) {
            $this->assertInstanceOf(Application::class, $application);
            $this->assertSame((string) $stage['stage_id'], (string) $application->current_stage_id);
        }
    }

    public function test_priorities_drilldown_opens_filtered_candidate_view(): void
    {
        $context = $this->createAnalyticsContext();

        $overview = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home', ['date_range' => 'all']));

        $overview->assertOk();
        $priorities = $overview->viewData('priorities');
        $this->assertInstanceOf(Collection::class, $priorities);
        $this->assertNotEmpty($priorities);

        $priority = $priorities->first();
        $this->assertIsArray($priority);
        $this->assertArrayHasKey('drilldown_url', $priority);
        $this->assertArrayHasKey('application_id', $priority);

        $drilldown = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get((string) $priority['drilldown_url']);

        $drilldown->assertOk();
        $filters = $drilldown->viewData('filters');
        $this->assertSame((string) $priority['application_id'], (string) ($filters['application_id'] ?? ''));

        $applications = $drilldown->viewData('applications');
        $this->assertInstanceOf(LengthAwarePaginator::class, $applications);
        $this->assertGreaterThan(0, $applications->total());
        foreach ($applications->items() as $application) {
            $this->assertInstanceOf(Application::class, $application);
            $this->assertSame((string) $priority['application_id'], (string) $application->id);
        }
    }

    public function test_export_matches_applied_filters(): void
    {
        $context = $this->createAnalyticsContext();
        $jobId = (string) $context['jobs']->first()->id;

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('home.export'), [
                'date_range' => 'all',
                'job_id' => $jobId,
                'format' => 'csv',
            ]);

        $response->assertRedirect(route('home', [
            'job_id' => $jobId,
            'date_range' => 'all',
        ]));
        $response->assertSessionHas('status', __('ui.exports.flash.queued'));

        $export = Export::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->latest('created_at')
            ->first();

        $this->assertInstanceOf(Export::class, $export);
        $this->assertSame(Export::TYPE_DASHBOARD_OVERVIEW, (string) $export->export_type);
        $this->assertSame(Export::FORMAT_CSV, (string) $export->format);
        $this->assertSame(Export::STATUS_COMPLETED, (string) $export->status);
        $this->assertNotNull($export->file_url);
        $this->assertTrue(Storage::disk('local')->exists((string) $export->file_url));

        $content = Storage::disk('local')->get((string) $export->file_url);
        $this->assertIsString($content);

        $lines = array_values(array_filter(array_map('trim', explode("\n", trim($content))), static fn (string $line): bool => $line !== ''));
        $this->assertGreaterThan(1, count($lines));

        $expectedCount = Application::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('job_id', $jobId)
            ->count();

        // Header + rows
        $this->assertSame($expectedCount + 1, count($lines));
    }

    public function test_dashboard_shows_empty_states_when_data_is_missing(): void
    {
        $company = Company::query()->create([
            'name' => 'Empty Analytics Co',
            'slug' => 'empty-analytics',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiter = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $company->id])
            ->get(route('home', ['date_range' => 'all']));

        $response->assertOk();
        $response->assertSee(__('ui.dashboard.empty_title'));
    }

    public function test_filters_are_validated_and_sql_injection_attempts_are_rejected(): void
    {
        $context = $this->createAnalyticsContext();

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home', [
                'department_id' => "' OR 1=1 --",
                'date_range' => 'all',
            ]));

        $response->assertSessionHasErrors(['department_id']);
    }

    public function test_dashboard_and_export_are_permissioned(): void
    {
        $context = $this->createAnalyticsContext();

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

        $dashboard = $this->actingAs($candidate)
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home'));

        $dashboard->assertForbidden();

        $export = $this->actingAs($candidate)
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('home.export'), ['format' => 'csv']);

        $export->assertForbidden();
    }

    public function test_overview_renders_select2_ready_filter_dropdowns(): void
    {
        $context = $this->createAnalyticsContext();

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('home', ['date_range' => 'all']));

        $response->assertOk();
        $response->assertSee('name="job_id"', false);
        $response->assertSee('name="department_id"', false);
        $response->assertSee('name="date_range"', false);
        $response->assertSee('data-placeholder=', false);
    }

    /**
     * @return array{
     *   company: Company,
     *   recruiter: User,
     *   jobs: Collection<int, Job>
     * }
     */
    private function createAnalyticsContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Analytics Company',
            'slug' => 'analytics-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiter = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $engineering = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Engineering',
        ]);
        $growth = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Growth',
        ]);

        $jobs = collect([
            Job::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'department_id' => $engineering->id,
                'title' => 'Backend Engineer',
                'status' => Job::STATUS_PUBLISHED,
            ]),
            Job::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'department_id' => $growth->id,
                'title' => 'Growth Specialist',
                'status' => Job::STATUS_PUBLISHED,
            ]),
        ]);

        $stagesByJob = [];
        $pipeline = [
            ['key' => 'applied', 'label' => 'Applied', 'display_order' => 1, 'is_terminal' => false],
            ['key' => 'screening', 'label' => 'Screening', 'display_order' => 2, 'is_terminal' => false],
            ['key' => 'interview', 'label' => 'Interview', 'display_order' => 3, 'is_terminal' => false],
            ['key' => 'offer', 'label' => 'Offer', 'display_order' => 4, 'is_terminal' => false],
            ['key' => 'hired', 'label' => 'Hired', 'display_order' => 5, 'is_terminal' => true],
            ['key' => 'rejected', 'label' => 'Rejected', 'display_order' => 6, 'is_terminal' => true],
        ];

        foreach ($jobs as $job) {
            foreach ($pipeline as $stageDefinition) {
                $stage = JobPipelineStage::withoutGlobalScopes()->create([
                    'job_id' => $job->id,
                    'stage_key' => $stageDefinition['key'],
                    'stage_label' => $stageDefinition['label'],
                    'display_order' => $stageDefinition['display_order'],
                    'is_terminal' => $stageDefinition['is_terminal'],
                ]);

                $stagesByJob[(string) $job->id][$stageDefinition['key']] = $stage;
            }
        }

        $stagePlan = [
            'applied', 'applied', 'applied', 'applied', 'applied', 'applied',
            'screening', 'screening', 'screening', 'screening', 'screening',
            'interview', 'interview', 'interview', 'interview',
            'offer', 'offer', 'offer',
            'hired',
            'rejected',
        ];
        $sourcePlan = [
            'career_page', 'job_board', 'linkedin', 'referral', 'career_page',
            'linkedin', 'job_board', 'career_page', 'referral', 'linkedin',
            'career_page', 'job_board', 'linkedin', 'career_page', 'referral',
            'linkedin', 'career_page', 'job_board', 'referral', 'career_page',
        ];

        foreach (range(1, 20) as $index) {
            $job = $jobs->get(($index - 1) % $jobs->count());
            if (! $job instanceof Job) {
                continue;
            }

            $stageKey = $stagePlan[$index - 1] ?? 'applied';
            $status = match ($stageKey) {
                'hired' => Application::STATUS_HIRED,
                'rejected' => Application::STATUS_REJECTED,
                default => Application::STATUS_ACTIVE,
            };

            $candidate = Candidate::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'user_id' => null,
                'full_name' => 'Overview Candidate '.$index,
                'email' => 'overview.candidate'.$index.'@example.test',
            ]);

            $createdAt = now()->subDays(60 - ($index * 2));
            $lastActivityAt = $createdAt->copy()->addHours(4 + ($index * 2));
            $stage = $stagesByJob[(string) $job->id][$stageKey] ?? null;
            if (! $stage instanceof JobPipelineStage) {
                continue;
            }

            $application = Application::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'current_stage_id' => $stage->id,
                'status' => $status,
                'source_type' => $sourcePlan[$index - 1] ?? 'career_page',
                'created_at' => $createdAt,
                'updated_at' => $lastActivityAt,
            ]);

            ApplicationScoring::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'global_match_score' => (float) (55 + ($index % 40)),
                'vrin_json' => ['acquired_skills' => [], 'missing_skills' => []],
                'xai_summary' => 'Test score',
                'updated_at' => $lastActivityAt,
            ]);

            ApplicationActivityEvent::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'event_type' => 'application.created',
                'payload' => ['test' => 'module_15'],
                'actor_user_id' => null,
                'created_at' => $lastActivityAt,
            ]);
        }

        return [
            'company' => $company,
            'recruiter' => $recruiter,
            'jobs' => $jobs,
        ];
    }
}
