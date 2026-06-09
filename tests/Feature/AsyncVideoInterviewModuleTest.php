<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiRequestJob;
use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationScoring;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\UnifiedInterviewReport;
use App\Models\User;
use App\Models\VideoConfig;
use App\Models\VideoQuestion;
use App\Models\VideoResponse;
use App\Services\Ai\AiRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AsyncVideoInterviewModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_submit_video_stories_and_final_question_queues_unified_report(): void
    {
        Storage::fake('local');
        Queue::fake();

        $context = $this->createVideoContext(questionCount: 2, retriesAllowed: 1);

        $submitFirst = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.video-stories.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'videoQuestion' => $context['questions'][0]->id,
            ]), [
                'read_time_completed' => '1',
                'duration_seconds' => 65,
                'video_file' => UploadedFile::fake()->create('answer-1.webm', 1200, 'video/webm'),
                'pauses_count' => 2,
                'speech_rate_estimate' => 126.4,
                'filler_ratio_estimate' => 0.0412,
                'transcript_text' => 'First response transcript.',
                'action' => 'next',
            ]);

        $submitFirst->assertRedirect();
        $submitFirst->assertSessionHas('status', __('video_assessment.stories.messages.saved'));

        $this->assertTrue(
            VideoResponse::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->where('question_id', $context['questions'][0]->id)
                ->where('attempt_number', 1)
                ->exists()
        );

        $this->assertTrue(
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $context['company']->id)
                ->where('request_type', 'video_response_metrics')
                ->whereRaw("request_payload->>'question_id' = ?", [(string) $context['questions'][0]->id])
                ->exists()
        );

        $this->assertFalse(
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $context['company']->id)
                ->where('request_type', 'async_video_unified_report')
                ->whereRaw("request_payload->>'application_id' = ?", [(string) $context['application']->id])
                ->exists()
        );

        $submitSecond = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.video-stories.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'videoQuestion' => $context['questions'][1]->id,
            ]), [
                'read_time_completed' => '1',
                'duration_seconds' => 72,
                'video_file' => UploadedFile::fake()->create('answer-2.webm', 1300, 'video/webm'),
                'pauses_count' => 3,
                'speech_rate_estimate' => 132.2,
                'filler_ratio_estimate' => 0.038,
                'transcript_text' => 'Second response transcript.',
                'action' => 'next',
            ]);

        $submitSecond->assertRedirect();
        $submitSecond->assertSessionHas('status', __('video_assessment.stories.messages.completed_message'));

        $this->assertTrue(
            AiRequest::withoutGlobalScopes()
                ->where('company_id', $context['company']->id)
                ->where('request_type', 'async_video_unified_report')
                ->whereRaw("request_payload->>'application_id' = ?", [(string) $context['application']->id])
                ->exists()
        );

        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->where('event_type', 'video.response_submitted')
                ->exists()
        );
        $this->assertTrue(
            ApplicationActivityEvent::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->where('event_type', 'video.unified_report_queued')
                ->exists()
        );

        Queue::assertPushed(ProcessAiRequestJob::class);
    }

    public function test_video_story_submit_enforces_read_timer_and_retry_limit(): void
    {
        Storage::fake('local');
        $context = $this->createVideoContext(questionCount: 1, retriesAllowed: 0);
        $question = $context['questions'][0];

        $readTimerFailure = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.video-stories', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'question_id' => $question->id,
            ]))
            ->post(route('candidate.video-stories.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'videoQuestion' => $question->id,
            ]), [
                'read_time_completed' => '0',
                'duration_seconds' => 60,
                'video_file' => UploadedFile::fake()->create('blocked.webm', 900, 'video/webm'),
                'action' => 'next',
            ]);

        $readTimerFailure->assertRedirect(route('candidate.video-stories', [
            'company' => $context['company']->slug,
            'application' => $context['application']->id,
            'question_id' => $question->id,
        ]));
        $readTimerFailure->assertSessionHasErrors(['read_time_completed']);

        $success = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.video-stories.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'videoQuestion' => $question->id,
            ]), [
                'read_time_completed' => '1',
                'duration_seconds' => 60,
                'video_file' => UploadedFile::fake()->create('allowed.webm', 1000, 'video/webm'),
                'action' => 'next',
            ]);

        $success->assertRedirect();

        $exceeded = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.video-stories.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'videoQuestion' => $question->id,
            ]), [
                'read_time_completed' => '1',
                'duration_seconds' => 55,
                'video_file' => UploadedFile::fake()->create('exceeded.webm', 1000, 'video/webm'),
                'action' => 'retry',
            ]);

        $exceeded->assertRedirect(route('candidate.video-stories', [
            'company' => $context['company']->slug,
            'application' => $context['application']->id,
            'question_id' => $question->id,
        ]));
        $exceeded->assertSessionHas('error', __('video_assessment.stories.messages.retry_limit'));

        $this->assertSame(1, VideoResponse::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->where('question_id', $question->id)
            ->count());
    }

    public function test_ai_request_service_persists_video_metrics_and_unified_report_data(): void
    {
        $context = $this->createVideoContext(questionCount: 1, retriesAllowed: 1);
        $question = $context['questions'][0];

        $response = VideoResponse::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'question_id' => $question->id,
            'attempt_number' => 1,
            'video_file_url' => 'private/video-interview/sample.webm',
            'duration_seconds' => 80,
            'pauses_count' => null,
            'speech_rate_estimate' => null,
            'filler_ratio_estimate' => null,
            'transcript_text' => null,
            'created_at' => now(),
        ]);

        $metricsRequest = AiRequest::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'request_type' => 'video_response_metrics',
            'input_hash' => hash('sha256', 'video-metrics'),
            'status' => AiRequest::STATUS_QUEUED,
            'model_name' => 'gemini-2.5-flash',
            'prompt_version' => 'video_response_metrics_v1',
            'request_payload' => [
                'video_response_id' => (string) $response->id,
                'output_mode' => 'json_schema',
                'prompt' => 'Extract transcript and speech metrics.',
                'json_schema' => [
                    'required' => ['transcript_text', 'pauses_count', 'speech_rate_estimate', 'filler_ratio_estimate'],
                    'properties' => [
                        'transcript_text' => ['type' => 'string'],
                        'pauses_count' => ['type' => 'integer'],
                        'speech_rate_estimate' => ['type' => 'number'],
                        'filler_ratio_estimate' => ['type' => 'number'],
                    ],
                ],
            ],
            'created_at' => now(),
        ]);

        app(AiRequestService::class)->process($metricsRequest);

        $metricsRequest->refresh();
        $response->refresh();

        $this->assertSame(AiRequest::STATUS_SUCCEEDED, (string) $metricsRequest->status);
        $this->assertNotNull($response->transcript_text);
        $this->assertNotNull($response->pauses_count);
        $this->assertNotNull($response->speech_rate_estimate);
        $this->assertNotNull($response->filler_ratio_estimate);

        $unifiedRequest = AiRequest::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'request_type' => 'async_video_unified_report',
            'input_hash' => hash('sha256', 'video-unified'),
            'status' => AiRequest::STATUS_QUEUED,
            'model_name' => 'gemini-2.5-flash',
            'prompt_version' => 'async_video_unified_report_v1',
            'request_payload' => [
                'application_id' => (string) $context['application']->id,
                'output_mode' => 'json_schema',
                'prompt' => 'Generate unified strict JSON report.',
                'json_schema' => [
                    'required' => [
                        'xai_summary',
                        'ocean',
                        'match_percentage',
                        'salary',
                        'generic_motivation',
                        'vrin',
                        'global_match_score',
                    ],
                    'properties' => [
                        'xai_summary' => ['type' => 'string'],
                        'ocean' => ['type' => 'object'],
                        'match_percentage' => ['type' => 'number'],
                        'salary' => ['type' => 'object'],
                        'generic_motivation' => ['type' => 'boolean'],
                        'vrin' => ['type' => 'object'],
                        'global_match_score' => ['type' => 'number'],
                    ],
                ],
            ],
            'created_at' => now(),
        ]);

        app(AiRequestService::class)->process($unifiedRequest);
        $unifiedRequest->refresh();

        $this->assertSame(AiRequest::STATUS_SUCCEEDED, (string) $unifiedRequest->status);

        $scoring = ApplicationScoring::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->first();
        $report = UnifiedInterviewReport::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->first();

        $this->assertNotNull($scoring);
        $this->assertNotNull($report);
        $this->assertNotNull($scoring?->xai_summary);
        $this->assertNotNull($report?->xai_summary);
        $this->assertNotNull($report?->match_percentage);
    }

    public function test_candidate_portal_shows_video_assessment_entry_and_stories_link(): void
    {
        $context = $this->createVideoContext(questionCount: 2, retriesAllowed: 1);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertSee(__('video_assessment.portal.title'));
        $response->assertSee((string) $context['config']->name);
        $response->assertSee(route('candidate.video-stories', [
            'company' => $context['company']->slug,
            'application' => $context['application']->id,
            'question_id' => $context['questions'][0]->id,
        ]), false);
    }

    public function test_recruiter_can_retry_failed_unified_video_report_request(): void
    {
        Queue::fake();
        $context = $this->createVideoContext(questionCount: 1, retriesAllowed: 1);

        $failed = AiRequest::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'request_type' => 'async_video_unified_report',
            'input_hash' => hash('sha256', 'failed-video-unified'),
            'status' => AiRequest::STATUS_FAILED,
            'model_name' => 'gemini-2.5-flash',
            'prompt_version' => 'async_video_unified_report_v1',
            'request_payload' => [
                'application_id' => (string) $context['application']->id,
                'output_mode' => 'json_schema',
                'prompt' => 'retry me',
                'json_schema' => ['required' => ['xai_summary'], 'properties' => ['xai_summary' => ['type' => 'string']]],
            ],
            'error_message' => 'Simulated failure',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.video-report.retry', ['application' => $context['application']->id]));

        $response->assertRedirect(route('candidates.index', ['application_id' => (string) $context['application']->id]));
        $response->assertSessionHas('status', __('candidates.flash.analysis_requested'));

        $failed->refresh();
        $this->assertSame(AiRequest::STATUS_QUEUED, (string) $failed->status);
        Queue::assertPushed(ProcessAiRequestJob::class);
    }

    public function test_ocean_overlay_appears_only_when_employee_assessments_exist(): void
    {
        $context = $this->createVideoContext(questionCount: 1, retriesAllowed: 1);

        UnifiedInterviewReport::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'ai_full_payload' => ['source' => 'candidate'],
            'xai_summary' => 'Candidate report',
            'ocean_openness' => 70,
            'ocean_conscientiousness' => 75,
            'ocean_extraversion' => 62,
            'ocean_agreeableness' => 68,
            'ocean_neuroticism' => 30,
            'generic_motivation' => false,
            'match_percentage' => 74.5,
            'salary_expected_min' => 90000,
            'salary_expected_max' => 110000,
            'salary_currency' => 'USD',
            'salary_fit_score' => 80.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $noEmployeeBaseline = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidates.index', ['application_id' => (string) $context['application']->id]));

        $noEmployeeBaseline->assertOk();
        $noEmployeeBaseline->assertDontSee(__('candidates.detail.ocean_overlay'));

        $employeeUser = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $context['company']->id,
            'user_id' => $employeeUser->id,
            'company_role' => CompanyMembership::ROLE_EMPLOYEE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $employeeCandidate = Candidate::query()->create([
            'company_id' => $context['company']->id,
            'user_id' => $employeeUser->id,
            'full_name' => 'Employee Baseline Candidate',
            'email' => 'employee-baseline@example.com',
        ]);

        $employeeApplication = Application::query()->create([
            'company_id' => $context['company']->id,
            'candidate_id' => $employeeCandidate->id,
            'job_id' => $context['job']->id,
            'current_stage_id' => $context['stage']->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'internal',
        ]);

        UnifiedInterviewReport::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $employeeApplication->id,
            'ai_full_payload' => ['source' => 'employee'],
            'xai_summary' => 'Employee report',
            'ocean_openness' => 66,
            'ocean_conscientiousness' => 79,
            'ocean_extraversion' => 64,
            'ocean_agreeableness' => 71,
            'ocean_neuroticism' => 29,
            'generic_motivation' => false,
            'match_percentage' => 77.0,
            'salary_expected_min' => 88000,
            'salary_expected_max' => 108000,
            'salary_currency' => 'USD',
            'salary_fit_score' => 82.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $withEmployeeBaseline = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidates.index', ['application_id' => (string) $context['application']->id]));

        $withEmployeeBaseline->assertOk();
        $withEmployeeBaseline->assertSee(__('candidates.detail.ocean_overlay'));
    }

    /**
     * @return array{
     *   company: Company,
     *   recruiter: User,
     *   candidateUser: User,
     *   candidate: Candidate,
     *   job: Job,
     *   stage: JobPipelineStage,
     *   application: Application,
     *   config: VideoConfig,
     *   questions: array<int, VideoQuestion>
     * }
     */
    private function createVideoContext(int $questionCount, int $retriesAllowed): array
    {
        $suffix = Str::lower(Str::random(8));
        $company = Company::query()->create([
            'name' => 'Video Company '.$suffix,
            'slug' => 'video-company-'.$suffix,
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiter = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Async Video Role',
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
            'user_id' => $candidateUser->id,
            'full_name' => 'Video Candidate',
            'email' => 'video-candidate-'.$suffix.'@example.com',
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $config = VideoConfig::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'job_id' => $job->id,
            'name' => 'Stories Config',
            'read_time_seconds' => 20,
            'answer_time_seconds' => 90,
            'retries_allowed' => $retriesAllowed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questions = [];
        for ($i = 1; $i <= $questionCount; $i++) {
            $questions[] = VideoQuestion::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'config_id' => $config->id,
                'display_order' => $i,
                'question_text' => 'Question '.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return compact('company', 'recruiter', 'candidateUser', 'candidate', 'job', 'stage', 'application', 'config', 'questions');
    }
}
