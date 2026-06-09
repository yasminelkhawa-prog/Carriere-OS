<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\BiasAlert;
use App\Models\BiasAuditStat;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CvParsingResult;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\User;
use App\Services\Fairness\FairnessAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FairnessAuditModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bias_audit_table_enforces_uniqueness_by_job_stage_bucket_and_dimension(): void
    {
        $context = $this->createFairnessContext();
        $bucketStart = CarbonImmutable::now()->utc()->startOfDay();

        BiasAuditStat::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'job_id' => $context['job']->id,
            'stage_id' => $context['screeningStage']->id,
            'time_bucket_start' => $bucketStart,
            'time_bucket_end' => $bucketStart->addDay(),
            'dimension_key' => FairnessAuditService::DIMENSION_GENDER_MEN_WOMEN,
            'group_a_count' => 5,
            'group_b_count' => 2,
            'impact_ratio' => 0.4,
            'fairness_index' => 40.0,
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        BiasAuditStat::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'job_id' => $context['job']->id,
            'stage_id' => $context['screeningStage']->id,
            'time_bucket_start' => $bucketStart,
            'time_bucket_end' => $bucketStart->addDay(),
            'dimension_key' => FairnessAuditService::DIMENSION_GENDER_MEN_WOMEN,
            'group_a_count' => 6,
            'group_b_count' => 1,
            'impact_ratio' => 0.1667,
            'fairness_index' => 16.67,
            'created_at' => now(),
        ]);
    }

    public function test_aggregation_pipeline_stores_aggregate_only_stats_and_triggers_critical_alert(): void
    {
        $context = $this->createFairnessContext();
        $bucketStart = CarbonImmutable::now()->utc()->startOfDay();

        $this->seedApplicationsForFairness(
            company: $context['company'],
            job: $context['job'],
            stage: $context['screeningStage'],
            menCount: 10,
            womenCount: 2,
            topSchoolCount: 10,
            regularSchoolCount: 2
        );

        app(FairnessAuditService::class)->recompute(
            companyId: (string) $context['company']->id,
            jobId: (string) $context['job']->id,
            stageId: (string) $context['screeningStage']->id,
            timeBucketStart: $bucketStart
        );

        $this->assertDatabaseCount('bias_audit_stats', 2);
        $this->assertDatabaseHas('bias_alerts', [
            'company_id' => (string) $context['company']->id,
            'job_id' => (string) $context['job']->id,
            'dimension_key' => FairnessAuditService::DIMENSION_GENDER_MEN_WOMEN,
        ]);
        $this->assertTrue(
            BiasAuditStat::withoutGlobalScopes()
                ->where('company_id', $context['company']->id)
                ->where('dimension_key', FairnessAuditService::DIMENSION_GENDER_MEN_WOMEN)
                ->where('impact_ratio', '<', FairnessAuditService::IMPACT_RATIO_ALERT_THRESHOLD)
                ->exists()
        );

        $this->assertFalse(Schema::hasColumn('bias_audit_stats', 'candidate_id'));
        $this->assertFalse(Schema::hasColumn('bias_audit_stats', 'sensitive_label'));
    }

    public function test_fairness_dashboard_renders_aggregated_widgets_and_prominent_alerts_for_hr_admin(): void
    {
        $context = $this->createFairnessContext();
        $bucketStart = CarbonImmutable::now()->utc()->startOfDay();

        $this->seedApplicationsForFairness(
            company: $context['company'],
            job: $context['job'],
            stage: $context['screeningStage'],
            menCount: 8,
            womenCount: 2,
            topSchoolCount: 7,
            regularSchoolCount: 3
        );
        $this->seedApplicationsForFairness(
            company: $context['company'],
            job: $context['job'],
            stage: $context['interviewStage'],
            menCount: 3,
            womenCount: 3,
            topSchoolCount: 2,
            regularSchoolCount: 4
        );

        app(FairnessAuditService::class)->recompute(
            companyId: (string) $context['company']->id,
            jobId: (string) $context['job']->id,
            stageId: (string) $context['screeningStage']->id,
            timeBucketStart: $bucketStart
        );
        app(FairnessAuditService::class)->recompute(
            companyId: (string) $context['company']->id,
            jobId: (string) $context['job']->id,
            stageId: (string) $context['interviewStage']->id,
            timeBucketStart: $bucketStart
        );

        $response = $this->actingAs($context['manager'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('analytics.fairness', [
                'job_id' => (string) $context['job']->id,
                'dimension_key' => FairnessAuditService::DIMENSION_GENDER_MEN_WOMEN,
                'period' => 'all',
            ]));

        $response->assertOk();
        $response->assertSee(__('ui.fairness.equality_pulse.title'));
        $response->assertSee(__('ui.fairness.diversity_funnel.title'));
        $response->assertSee(__('ui.fairness.alerts.prominent_title'));
        $response->assertDontSee(__('ui.fairness.insufficient_data_title'));
    }

    public function test_fairness_dashboard_shows_insufficient_data_state(): void
    {
        $context = $this->createFairnessContext();
        $bucketStart = CarbonImmutable::now()->utc()->startOfDay();

        $this->seedApplicationsForFairness(
            company: $context['company'],
            job: $context['job'],
            stage: $context['screeningStage'],
            menCount: 1,
            womenCount: 0,
            topSchoolCount: 1,
            regularSchoolCount: 0
        );

        app(FairnessAuditService::class)->recompute(
            companyId: (string) $context['company']->id,
            jobId: (string) $context['job']->id,
            stageId: (string) $context['screeningStage']->id,
            timeBucketStart: $bucketStart
        );

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('analytics.fairness', [
                'job_id' => (string) $context['job']->id,
                'period' => 'all',
            ]));

        $response->assertOk();
        $response->assertSee(__('ui.fairness.insufficient_data_title'));
        $response->assertSee(__('ui.fairness.insufficient_data_message'));
    }

    public function test_blind_mode_masks_identity_only_in_screening_stage(): void
    {
        $context = $this->createFairnessContext();

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'full_name' => 'Blind Mode Candidate',
            'email' => 'blind-mode-candidate@example.test',
            'phone' => '+1-555-1000',
            'location' => 'Lahore',
        ]);

        $application = Application::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'candidate_id' => $candidate->id,
            'job_id' => $context['job']->id,
            'current_stage_id' => $context['screeningStage']->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $maskedResponse = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidates.index', ['application_id' => (string) $application->id]));

        $maskedResponse->assertOk();
        $maskedResponse->assertSee(__('candidates.detail.masked_identifier', [
            'identifier' => \App\Http\Controllers\CandidateWorkspaceController::maskedCandidateIdentifier((string) $application->id),
        ]));
        $maskedResponse->assertDontSee('Blind Mode Candidate');

        $application->forceFill(['current_stage_id' => (string) $context['interviewStage']->id])->save();

        $unmaskedResponse = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidates.index', ['application_id' => (string) $application->id]));

        $unmaskedResponse->assertOk();
        $unmaskedResponse->assertSee('Blind Mode Candidate');
    }

    public function test_blind_mode_does_not_break_interview_scheduling_or_stage_transition_pipeline(): void
    {
        $context = $this->createFairnessContext();
        $application = $this->createCandidateApplication($context['company'], $context['job'], $context['screeningStage'], 'schedule@example.test');

        $scheduleResponse = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.schedule-interview', ['application' => $application->id]), [
                'scheduled_for' => now()->addDay()->format('Y-m-d H:i:s'),
                'timezone' => 'UTC',
                'duration_minutes' => 60,
                'interviewer_user_ids' => [(string) $context['recruiter']->id],
                'interview_type' => 'screening',
                'location_type' => Interview::LOCATION_ZOOM,
                'meeting_link' => 'https://zoom.us/j/1234567890',
            ]);

        $scheduleResponse->assertRedirect();
        $scheduleResponse->assertSessionHas('status', __('candidates.flash.interview_scheduled'));
        $this->assertTrue(
            Interview::withoutGlobalScopes()
                ->where('application_id', $application->id)
                ->where('status', Interview::STATUS_SCHEDULED)
                ->exists()
        );
        $this->assertDatabaseHas('email_outbox_logs', [
            'company_id' => (string) $context['company']->id,
            'template_key' => 'interview_confirmation',
        ]);

        // Decision transitions should ignore fairness audit attributes.
        BiasAuditStat::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => $context['job']->id,
                'stage_id' => $context['screeningStage']->id,
                'time_bucket_start' => CarbonImmutable::now()->utc()->startOfDay(),
                'dimension_key' => FairnessAuditService::DIMENSION_GENDER_MEN_WOMEN,
            ],
            [
                'company_id' => $context['company']->id,
                'time_bucket_end' => CarbonImmutable::now()->utc()->startOfDay()->addDay(),
                'group_a_count' => 100,
                'group_b_count' => 1,
                'impact_ratio' => 0.0100,
                'fairness_index' => 1.00,
                'created_at' => now(),
            ]
        );

        $transitionResponse = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.kanban.transition', ['application' => $application->id]), [
                'to_stage_id' => (string) $context['interviewStage']->id,
                'transition_type' => 'standard',
                'confirm_terminal' => 0,
                'company_id' => (string) $context['company']->id,
                'job_id' => (string) $context['job']->id,
            ]);

        $transitionResponse->assertRedirect();
        $this->assertSame(
            (string) $context['interviewStage']->id,
            (string) $application->fresh()->current_stage_id
        );
    }

    public function test_only_hr_admin_can_resolve_bias_alerts(): void
    {
        $context = $this->createFairnessContext();

        $alert = BiasAlert::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'job_id' => $context['job']->id,
            'dimension_key' => FairnessAuditService::DIMENSION_SCHOOL_TOP_REGULAR,
            'severity' => BiasAlert::SEVERITY_CRITICAL,
            'message' => 'Critical fairness alert',
            'created_at' => now(),
            'resolved_at' => null,
        ]);

        $forbidden = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('analytics.fairness.alerts.resolve', ['biasAlert' => $alert->id]));
        $forbidden->assertForbidden();

        $allowed = $this->actingAs($context['manager'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('analytics.fairness.alerts.resolve', ['biasAlert' => $alert->id]));
        $allowed->assertRedirect();
        $allowed->assertSessionHas('status', __('ui.fairness.alerts.resolved'));

        $this->assertNotNull(
            BiasAlert::withoutGlobalScopes()
                ->where('id', $alert->id)
                ->value('resolved_at')
        );
    }

    /**
     * @return array{
     *   company: Company,
     *   admin: User,
     *   manager: User,
     *   recruiter: User,
     *   job: Job,
     *   screeningStage: JobPipelineStage,
     *   interviewStage: JobPipelineStage,
     *   terminalStage: JobPipelineStage
     * }
     */
    private function createFairnessContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Fairness Co',
            'slug' => 'fairness-'.strtolower((string) \Illuminate\Support\Str::random(8)),
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = $this->createMember($company, CompanyMembership::ROLE_COMPANY_ADMIN);
        $manager = $this->createMember($company, CompanyMembership::ROLE_MANAGER);
        $recruiter = $this->createMember($company, CompanyMembership::ROLE_RECRUITER);

        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Fairness Analyst',
            'status' => Job::STATUS_PUBLISHED,
            'location' => 'Remote',
            'blind_mode_active' => true,
        ]);

        $screeningStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);
        $interviewStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'interview',
            'stage_label' => 'Interview',
            'display_order' => 2,
            'is_terminal' => false,
        ]);
        $terminalStage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'hired',
            'stage_label' => 'Hired',
            'display_order' => 3,
            'is_terminal' => true,
        ]);

        return compact('company', 'admin', 'manager', 'recruiter', 'job', 'screeningStage', 'interviewStage', 'terminalStage');
    }

    private function createMember(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => $role,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        return $user;
    }

    private function seedApplicationsForFairness(
        Company $company,
        Job $job,
        JobPipelineStage $stage,
        int $menCount,
        int $womenCount,
        int $topSchoolCount,
        int $regularSchoolCount
    ): void {
        $total = max($menCount + $womenCount, $topSchoolCount + $regularSchoolCount);

        if ($total <= 0) {
            return;
        }

        foreach (range(1, $total) as $index) {
            $email = 'fairness-'.$stage->stage_key.'-'.$index.'-'.strtolower((string) \Illuminate\Support\Str::random(5)).'@example.test';
            $application = $this->createCandidateApplication($company, $job, $stage, $email);

            $gender = $index <= $menCount
                ? 'male'
                : ($index <= ($menCount + $womenCount) ? 'female' : 'unknown');
            $schoolTier = $index <= $topSchoolCount
                ? 'top_school'
                : ($index <= ($topSchoolCount + $regularSchoolCount) ? 'regular_university' : 'unknown');

            $application->forceFill([
                'source_type' => 'career_page',
                'created_at' => CarbonImmutable::now()->utc()->startOfDay()->addMinutes($index * 3),
                'updated_at' => CarbonImmutable::now()->utc()->startOfDay()->addMinutes($index * 3),
            ])->save();

            \App\Models\ApplicationScoring::withoutGlobalScopes()->updateOrCreate(
                ['application_id' => $application->id],
                [
                    'company_id' => $company->id,
                    'global_match_score' => 70.0,
                    'vrin_json' => [
                        'acquired_skills' => ['Communication'],
                        'missing_skills' => ['System design'],
                    ],
                    'xai_summary' => 'Fairness test score',
                    'updated_at' => now(),
                ]
            );

            CvParsingResult::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'candidate_id' => $application->candidate_id,
                'application_id' => $application->id,
                'parse_status' => 'succeeded',
                'parser_version' => 'fairness_test_v1',
                'gender_inference' => $gender,
                'school_background_tier' => $schoolTier,
                'created_at' => CarbonImmutable::now()->utc()->startOfDay()->addMinutes($index * 3),
                'updated_at' => CarbonImmutable::now()->utc()->startOfDay()->addMinutes($index * 3),
            ]);
        }
    }

    private function createCandidateApplication(Company $company, Job $job, JobPipelineStage $stage, string $email): Application
    {
        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'full_name' => 'Candidate '.strtoupper(str_replace(['@', '.'], ['_', '_'], $email)),
            'email' => $email,
            'phone' => '+1-555-0100',
            'location' => 'Remote',
        ]);

        return Application::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);
    }
}
