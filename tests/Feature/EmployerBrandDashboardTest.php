<?php

namespace Tests\Feature;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\BrandAlert;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Department;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\ReverseFeedback;
use App\Models\SentimentResult;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use App\Services\EmployerBrand\EmployerBrandSentimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EmployerBrandDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'services.gemini.local_stub_enabled' => true,
            'services.gemini.max_attempts' => 1,
        ]);
    }

    public function test_dashboard_shows_sentiment_results_themes_and_investigation_links(): void
    {
        $context = $this->createBrandContext(applicationCount: 2);
        $application = $context['applications']->first();
        $this->assertInstanceOf(Application::class, $application);

        $feedback = ReverseFeedback::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $application->id,
            'recruiter_user_id' => $context['recruiter']->id,
            'rating_clarity' => 5,
            'rating_speed' => 5,
            'rating_kindness' => 5,
            'comment' => 'Great communication, respectful process, and clear expectations.',
            'is_anonymous' => false,
            'created_at' => now(),
        ]);

        $this->processSentimentAnalysis(
            companyId: (string) $context['company']->id,
            sourceType: EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
            sourceId: (string) $feedback->id
        );

        $response = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('analytics.index', ['period' => 'all']));

        $response->assertOk();
        $response->assertSee('name="recruiter_id"', false);
        $response->assertSee('name="job_id"', false);
        $response->assertSee('name="period"', false);
        $response->assertSee('data-placeholder=', false);

        $sentimentEntries = $response->viewData('sentimentEntries');
        $this->assertInstanceOf(Collection::class, $sentimentEntries);
        $this->assertNotEmpty($sentimentEntries);

        $entry = $sentimentEntries->first();
        $this->assertIsArray($entry);
        $this->assertNotEmpty($entry['themes'] ?? []);
        $this->assertNotNull($entry['drilldown_url'] ?? null);

        $drilldown = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get((string) $entry['drilldown_url']);

        $drilldown->assertOk();
        $filters = $drilldown->viewData('filters');
        $this->assertSame((string) $application->id, (string) ($filters['application_id'] ?? ''));
        $this->assertInstanceOf(LengthAwarePaginator::class, $drilldown->viewData('applications'));
    }

    public function test_alerts_are_created_on_threshold_breach_and_critical_resolve_is_permissioned_and_logged(): void
    {
        $context = $this->createBrandContext(applicationCount: 6);

        $negativeTexts = [
            'Rude and unprofessional communication throughout the process.',
            'Hostile behavior was observed in interview interactions.',
            'Frustrating process with ignored follow-ups and confusing updates.',
            'Disorganized scheduling and disrespectful handling.',
            'Humiliating and hostile interview experience for candidate.',
        ];

        foreach ($context['applications']->take(5)->values() as $index => $application) {
            $feedback = ReverseFeedback::withoutGlobalScopes()->create([
                'company_id' => $context['company']->id,
                'application_id' => $application->id,
                'recruiter_user_id' => $context['recruiter']->id,
                'rating_clarity' => 1,
                'rating_speed' => 1,
                'rating_kindness' => 1,
                'comment' => $negativeTexts[$index] ?? 'Negative feedback.',
                'is_anonymous' => false,
                'created_at' => now()->subDays(5 - $index),
            ]);

            $this->processSentimentAnalysis(
                companyId: (string) $context['company']->id,
                sourceType: EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
                sourceId: (string) $feedback->id
            );
        }

        $response = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('analytics.index', ['period' => 'all']));

        $response->assertOk();
        $alerts = $response->viewData('activeAlerts');
        $this->assertInstanceOf(Collection::class, $alerts);
        $this->assertNotEmpty($alerts);

        $criticalAlert = BrandAlert::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('severity', BrandAlert::SEVERITY_CRITICAL)
            ->whereNull('resolved_at')
            ->first();
        $this->assertInstanceOf(BrandAlert::class, $criticalAlert);

        $forbidden = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('analytics.alerts.resolve', ['brandAlert' => $criticalAlert->id]));
        $forbidden->assertForbidden();

        $resolved = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('analytics.alerts.resolve', ['brandAlert' => $criticalAlert->id]));
        $resolved->assertRedirect();

        $this->assertDatabaseHas('brand_alerts', [
            'id' => (string) $criticalAlert->id,
        ]);
        $this->assertNotNull(
            BrandAlert::withoutGlobalScopes()->where('id', $criticalAlert->id)->value('resolved_at')
        );

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => (string) $context['company']->id,
            'actor_user_id' => (string) $context['admin']->id,
            'action_type' => 'employer_brand.alert_resolved',
            'entity_type' => 'brand_alert',
            'entity_id' => (string) $criticalAlert->id,
        ]);
    }

    public function test_feedback_without_text_is_not_analyzed_but_counts_in_rating_averages(): void
    {
        $context = $this->createBrandContext(applicationCount: 1);
        $application = $context['applications']->first();
        $this->assertInstanceOf(Application::class, $application);

        $feedback = ReverseFeedback::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $application->id,
            'recruiter_user_id' => $context['recruiter']->id,
            'rating_clarity' => 4,
            'rating_speed' => 4,
            'rating_kindness' => 4,
            'comment' => null,
            'is_anonymous' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('analytics.index', ['period' => 'all']));

        $response->assertOk();
        $summary = $response->viewData('ratingSummary');
        $this->assertSame(1, (int) ($summary['responses'] ?? 0));
        $this->assertSame(4.0, (float) ($summary['avg_overall'] ?? 0.0));

        $this->assertDatabaseMissing('ai_requests', [
            'company_id' => (string) $context['company']->id,
            'request_type' => 'sentiment_analysis',
        ]);
        $this->assertDatabaseMissing('sentiment_results', [
            'company_id' => (string) $context['company']->id,
            'source_type' => EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
            'source_id' => (string) $feedback->id,
        ]);
    }

    public function test_failed_sentiment_analysis_keeps_pending_state_and_dashboard_does_not_break(): void
    {
        $context = $this->createBrandContext(applicationCount: 1);
        $application = $context['applications']->first();
        $this->assertInstanceOf(Application::class, $application);

        $feedback = ReverseFeedback::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $application->id,
            'recruiter_user_id' => $context['recruiter']->id,
            'rating_clarity' => 2,
            'rating_speed' => 2,
            'rating_kindness' => 2,
            'comment' => 'This process was frustrating and very disorganized.',
            'is_anonymous' => false,
            'created_at' => now(),
        ]);

        $request = AiRequest::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('request_type', 'sentiment_analysis')
            ->where('request_payload->source_type', EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK)
            ->where('request_payload->source_id', (string) $feedback->id)
            ->latest('created_at')
            ->first();
        $this->assertInstanceOf(AiRequest::class, $request);

        $payload = $request->request_payload ?? [];
        $payload['dev_force_invalid_json'] = true;
        $request->forceFill(['request_payload' => $payload])->save();

        app(AiRequestService::class)->process($request->fresh());

        $this->assertDatabaseHas('ai_requests', [
            'id' => (string) $request->id,
            'status' => AiRequest::STATUS_FAILED,
        ]);
        $this->assertDatabaseHas('sentiment_results', [
            'company_id' => (string) $context['company']->id,
            'source_type' => EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
            'source_id' => (string) $feedback->id,
            'risk_level' => SentimentResult::RISK_PENDING,
        ]);

        $response = $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('analytics.index', ['period' => 'all']));

        $response->assertOk();
        $response->assertSee(__('ui.employer_brand.pending'));
        $this->assertSame(1, (int) $response->viewData('pendingSentimentCount'));
    }

    public function test_filters_are_validated_and_sql_injection_like_input_is_rejected(): void
    {
        $context = $this->createBrandContext(applicationCount: 1);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('analytics.index', [
                'recruiter_id' => "' OR 1=1 --",
                'period' => 'all',
            ]));

        $response->assertSessionHasErrors(['recruiter_id']);
    }

    /**
     * @return array{
     *   company: Company,
     *   admin: User,
     *   recruiter: User,
     *   job: Job,
     *   stage: JobPipelineStage,
     *   applications: Collection<int, Application>
     * }
     */
    private function createBrandContext(int $applicationCount): array
    {
        $company = Company::query()->create([
            'name' => 'Employer Brand Co',
            'slug' => 'employer-brand-co',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);
        $recruiter = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $department = Department::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'People Ops',
        ]);
        $job = Job::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'title' => 'Employer Brand Specialist',
            'status' => Job::STATUS_PUBLISHED,
            'location' => 'Remote',
            'blind_mode_active' => false,
        ]);
        $stage = JobPipelineStage::withoutGlobalScopes()->create([
            'job_id' => $job->id,
            'stage_key' => 'screening',
            'stage_label' => 'Screening',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $applications = collect();
        foreach (range(1, $applicationCount) as $index) {
            $candidate = Candidate::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'full_name' => 'Brand Candidate '.$index,
                'email' => 'brand.candidate'.$index.'@example.test',
                'phone' => null,
                'location' => 'Remote',
            ]);

            $applications->push(Application::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'current_stage_id' => $stage->id,
                'status' => Application::STATUS_ACTIVE,
                'source_type' => 'career_page',
                'source_detail' => null,
                'utm_source' => null,
                'utm_campaign' => null,
                'utm_medium' => null,
            ]));
        }

        return [
            'company' => $company,
            'admin' => $admin,
            'recruiter' => $recruiter,
            'job' => $job,
            'stage' => $stage,
            'applications' => $applications,
        ];
    }

    private function processSentimentAnalysis(string $companyId, string $sourceType, string $sourceId): void
    {
        $request = AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'sentiment_analysis')
            ->where('request_payload->source_type', $sourceType)
            ->where('request_payload->source_id', $sourceId)
            ->latest('created_at')
            ->first();

        $this->assertInstanceOf(AiRequest::class, $request);

        app(AiRequestService::class)->process($request);
    }
}
