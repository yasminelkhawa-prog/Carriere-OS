<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiRequestJob;
use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationScoring;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\SjtResponse;
use App\Models\SjtScenario;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SjtAssessmentModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_save_sjt_draft_with_copy_paste_blocked_flag(): void
    {
        $context = $this->createAssessmentContext();

        $response = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidate.assessments.sjt.draft', [
                'application' => $context['application']->id,
                'scenario' => $context['scenario']->id,
            ]), [
                'response_text' => 'Draft response for scenario one.',
            ]);

        $response->assertRedirect(route('candidate.assessments.sjt', [
            'application_id' => (string) $context['application']->id,
            'scenario_id' => (string) $context['scenario']->id,
        ]));
        $response->assertSessionHas('status', __('sjt.messages.draft_saved'));

        $savedResponse = SjtResponse::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->where('scenario_id', $context['scenario']->id)
            ->first();

        $this->assertNotNull($savedResponse);
        $this->assertSame('Draft response for scenario one.', (string) $savedResponse?->response_text);
        $this->assertTrue((bool) $savedResponse?->copy_paste_blocked_flag);
        $this->assertNull($savedResponse?->ai_score);
    }

    public function test_submit_enforces_trimmed_min_length_validation(): void
    {
        $context = $this->createAssessmentContext();

        $response = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->from(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
            ]))
            ->post(route('candidate.assessments.sjt.submit', [
                'application' => $context['application']->id,
                'scenario' => $context['scenario']->id,
            ]), [
                'response_text' => '  '.str_repeat('a', 119).'  ',
            ]);

        $response->assertRedirect(route('candidate.assessments.sjt', [
            'application_id' => (string) $context['application']->id,
            'scenario_id' => (string) $context['scenario']->id,
        ]));
        $response->assertSessionHasErrors(['response_text']);

        $this->assertFalse(
            SjtResponse::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->where('scenario_id', $context['scenario']->id)
                ->exists()
        );
    }

    public function test_submit_creates_ai_scoring_request_and_processing_state(): void
    {
        Queue::fake();
        $context = $this->createAssessmentContext();

        $submitResponse = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidate.assessments.sjt.submit', [
                'application' => $context['application']->id,
                'scenario' => $context['scenario']->id,
            ]), [
                'response_text' => str_repeat('a', 220),
            ]);

        $submitResponse->assertRedirect(route('candidate.assessments.sjt', [
            'application_id' => (string) $context['application']->id,
            'scenario_id' => (string) $context['scenario']->id,
        ]));
        $submitResponse->assertSessionHas('status', __('sjt.messages.submitted_for_scoring'));

        $savedResponse = SjtResponse::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->where('scenario_id', $context['scenario']->id)
            ->first();

        $this->assertNotNull($savedResponse);
        $this->assertNull($savedResponse?->ai_score);

        $this->assertTrue(
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $context['company']->id)
                ->where('request_type', 'sjt_scoring')
                ->whereRaw("request_payload->>'sjt_response_id' = ?", [(string) $savedResponse?->id])
                ->exists()
        );
        Queue::assertPushed(ProcessAiRequestJob::class);

        $indexResponse = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
            ]));

        $indexResponse->assertOk();
        $indexResponse->assertSee(__('sjt.states.processing'));
        $indexResponse->assertSee(__('sjt.readonly_notice'));
        $indexResponse->assertDontSee(__('sjt.actions.save_draft'));
        $indexResponse->assertDontSee(__('sjt.actions.submit_final'));
        $indexResponse->assertSee('data-guide-bot-disabled="true"', false);
    }

    public function test_candidate_cannot_submit_same_scenario_more_than_once(): void
    {
        Queue::fake();
        $context = $this->createAssessmentContext();

        $firstSubmissionText = str_repeat('a', 220);

        $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidate.assessments.sjt.submit', [
                'application' => $context['application']->id,
                'scenario' => $context['scenario']->id,
            ]), [
                'response_text' => $firstSubmissionText,
            ])
            ->assertRedirect();

        $secondResponse = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->from(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
            ]))
            ->post(route('candidate.assessments.sjt.submit', [
                'application' => $context['application']->id,
                'scenario' => $context['scenario']->id,
            ]), [
                'response_text' => str_repeat('b', 240),
            ]);

        $secondResponse->assertRedirect(route('candidate.assessments.sjt', [
            'application_id' => (string) $context['application']->id,
            'scenario_id' => (string) $context['scenario']->id,
        ]));
        $secondResponse->assertSessionHasErrors([
            'assessment' => __('sjt.messages.already_submitted'),
        ]);

        $savedResponse = SjtResponse::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->where('scenario_id', $context['scenario']->id)
            ->first();

        $this->assertNotNull($savedResponse);
        $this->assertSame($firstSubmissionText, (string) $savedResponse?->response_text);
        $this->assertSame(1, AiRequest::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('request_type', 'sjt_scoring')
            ->whereRaw("request_payload->>'sjt_response_id' = ?", [(string) $savedResponse?->id])
            ->count());
    }

    public function test_candidate_cannot_edit_draft_after_final_submission(): void
    {
        Queue::fake();
        $context = $this->createAssessmentContext();

        $firstSubmissionText = str_repeat('c', 220);

        $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidate.assessments.sjt.submit', [
                'application' => $context['application']->id,
                'scenario' => $context['scenario']->id,
            ]), [
                'response_text' => $firstSubmissionText,
            ])
            ->assertRedirect();

        $draftResponse = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->from(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
            ]))
            ->post(route('candidate.assessments.sjt.draft', [
                'application' => $context['application']->id,
                'scenario' => $context['scenario']->id,
            ]), [
                'response_text' => 'Trying to overwrite',
            ]);

        $draftResponse->assertRedirect(route('candidate.assessments.sjt', [
            'application_id' => (string) $context['application']->id,
            'scenario_id' => (string) $context['scenario']->id,
        ]));
        $draftResponse->assertSessionHasErrors([
            'assessment' => __('sjt.messages.already_submitted'),
        ]);

        $savedResponse = SjtResponse::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->where('scenario_id', $context['scenario']->id)
            ->first();

        $this->assertNotNull($savedResponse);
        $this->assertSame($firstSubmissionText, (string) $savedResponse?->response_text);
    }

    public function test_retry_requeues_failed_scoring_without_losing_response_text(): void
    {
        Queue::fake();
        $context = $this->createAssessmentContext();

        $sjtResponse = SjtResponse::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'scenario_id' => $context['scenario']->id,
            'response_text' => str_repeat('b', 150),
            'copy_paste_blocked_flag' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $failedRequest = AiRequest::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'request_type' => 'sjt_scoring',
            'input_hash' => hash('sha256', 'failed-request'),
            'status' => AiRequest::STATUS_FAILED,
            'model_name' => 'gemini-1.5-flash',
            'prompt_version' => 'sjt_scoring_v1',
            'request_payload' => [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
                'sjt_response_id' => (string) $sjtResponse->id,
                'output_mode' => 'json_schema',
                'prompt' => 'retry me',
            ],
            'response_payload' => null,
            'error_message' => 'Simulated failure',
            'created_at' => now(),
        ]);

        $retryResponse = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidate.assessments.sjt.retry', ['sjtResponse' => $sjtResponse->id]));

        $retryResponse->assertRedirect();
        $retryResponse->assertSessionHas('status', __('sjt.messages.retry_queued'));

        $failedRequest->refresh();
        $sjtResponse->refresh();

        $this->assertSame(AiRequest::STATUS_QUEUED, (string) $failedRequest->status);
        $this->assertSame(str_repeat('b', 150), (string) $sjtResponse->response_text);
        Queue::assertPushed(ProcessAiRequestJob::class);
    }

    public function test_index_only_lists_active_scenarios_matching_application_job_or_global(): void
    {
        $context = $this->createAssessmentContext();

        $otherJob = Job::query()->create([
            'company_id' => $context['company']->id,
            'title' => 'Other Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'job_id' => null,
            'title' => 'Global Scenario',
            'scenario_media_url' => null,
            'scenario_text' => 'Global scenario text.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'job_id' => $otherJob->id,
            'title' => 'Other Job Scenario',
            'scenario_media_url' => null,
            'scenario_text' => 'Should not be visible.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'job_id' => $context['job']->id,
            'title' => 'Inactive Scenario',
            'scenario_media_url' => null,
            'scenario_text' => 'Should not be visible.',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
            ]));

        $response->assertOk();
        $response->assertSee('Scenario One');
        $response->assertSee('Global Scenario');
        $response->assertDontSee('Other Job Scenario');
        $response->assertDontSee('Inactive Scenario');
    }

    public function test_sjt_screen_shows_the_action_instruction_copy(): void
    {
        $context = $this->createAssessmentContext();

        $response = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
            ]));

        $response->assertOk();
        $response->assertSee(__('sjt.response_title'));
        $response->assertSee(__('sjt.response_instruction'));
        $response->assertSee(__('sjt.copy_paste_notice'));
    }

    public function test_candidate_can_still_view_scored_response_when_scenario_is_inactive(): void
    {
        $context = $this->createAssessmentContext();

        $responseRecord = SjtResponse::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'scenario_id' => $context['scenario']->id,
            'response_text' => str_repeat('z', 180),
            'copy_paste_blocked_flag' => true,
            'ai_score' => 88.5,
            'ai_feedback_json' => [
                'summary' => 'Strong prioritization and communication.',
                'strengths' => ['Prioritization'],
                'concerns' => [],
                'recommendation' => 'Proceed',
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AiRequest::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'request_type' => 'sjt_scoring',
            'input_hash' => hash('sha256', 'inactive-scenario-score'),
            'status' => AiRequest::STATUS_SUCCEEDED,
            'model_name' => 'gemini-1.5-flash',
            'prompt_version' => 'sjt_scoring_v1',
            'request_payload' => [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
                'sjt_response_id' => (string) $responseRecord->id,
            ],
            'response_payload' => ['mode' => 'json', 'output' => ['score' => 88.5]],
            'created_at' => now(),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $context['scenario']->forceFill([
            'is_active' => false,
            'updated_at' => now(),
        ])->save();

        $response = $this->actingAs($context['user'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
            ]));

        $response->assertOk();
        $response->assertSee('Scenario One');
        $response->assertSee('88.50');
        $response->assertSee(__('sjt.states.scored'));
    }

    public function test_processing_sjt_scoring_persists_hidden_signals_and_updates_overall_score(): void
    {
        config()->set('services.gemini.local_stub_enabled', true);
        $context = $this->createAssessmentContext();

        ApplicationScoring::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['application']->id,
            'global_match_score' => 90.0,
            'vrin_json' => [
                'acquired_skills' => ['Communication'],
                'missing_skills' => ['Python framework experience'],
            ],
            'xai_summary' => 'Initial baseline score.',
            'updated_at' => now(),
        ]);

        $responseRecord = SjtResponse::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['application']->id,
            'scenario_id' => (string) $context['scenario']->id,
            'response_text' => str_repeat('x', 220),
            'copy_paste_blocked_flag' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $aiRequest = AiRequest::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'request_type' => 'sjt_scoring',
            'input_hash' => hash('sha256', 'sjt-process-signals'),
            'status' => AiRequest::STATUS_QUEUED,
            'model_name' => 'gemini-1.5-flash',
            'prompt_version' => 'sjt_scoring_v1',
            'request_payload' => [
                'application_id' => (string) $context['application']->id,
                'scenario_id' => (string) $context['scenario']->id,
                'sjt_response_id' => (string) $responseRecord->id,
                'output_mode' => 'json_schema',
                'prompt' => 'Evaluate SJT response and return JSON only.',
                'json_schema' => [
                    'required' => ['score', 'signals', 'feedback'],
                    'properties' => [
                        'score' => ['type' => 'number'],
                        'signals' => ['type' => 'object'],
                        'feedback' => ['type' => 'object'],
                    ],
                ],
            ],
            'created_at' => now(),
        ]);

        app(AiRequestService::class)->process($aiRequest);

        $responseRecord->refresh();
        $this->assertNotNull($responseRecord->ai_score);
        $this->assertIsArray($responseRecord->ai_feedback_json);
        $this->assertSame(
            'high',
            (string) data_get($responseRecord->ai_feedback_json, 'signals.accountability')
        );
        $this->assertSame(
            'medium',
            (string) data_get($responseRecord->ai_feedback_json, 'signals.solution_orientation')
        );
        $this->assertSame(
            'high',
            (string) data_get($responseRecord->ai_feedback_json, 'signals.tone')
        );

        $scoring = ApplicationScoring::withoutGlobalScopes()
            ->where('application_id', (string) $context['application']->id)
            ->first();

        $this->assertNotNull($scoring);
        $this->assertTrue(is_numeric($scoring?->global_match_score));
        $this->assertEquals(87.6, round((float) $scoring?->global_match_score, 1));
        $this->assertSame(90.0, (float) data_get($scoring?->vrin_json, 'base_global_match_score'));
    }

    /**
     * @return array{
     *   company: Company,
     *   user: User,
     *   candidate: Candidate,
     *   job: Job,
     *   stage: JobPipelineStage,
     *   application: Application,
     *   scenario: SjtScenario
     * }
     */
    private function createAssessmentContext(): array
    {
        $company = Company::query()->create([
            'name' => 'SJT Test Company',
            'slug' => 'sjt-test-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'SJT Role',
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
            'user_id' => $user->id,
            'full_name' => 'SJT Candidate',
            'email' => (string) $user->email,
            'phone' => '+1-555-0110',
            'location' => 'Remote',
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $scenario = SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'title' => 'Scenario One',
            'scenario_media_url' => 'https://example.com/scenario.mp4',
            'scenario_text' => 'A teammate is blocked before a major deadline. What do you do?',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('company', 'user', 'candidate', 'job', 'stage', 'application', 'scenario');
    }
}
