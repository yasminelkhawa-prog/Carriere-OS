<?php

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Profile;
use App\Models\AiRequest;
use App\Models\ApplicationActivityEvent;
use App\Models\StrategyLabAiSummary;
use App\Models\StrategyLabBrief;
use App\Models\StrategyLabSubmission;
use App\Models\User;
use App\Models\VideoConfig;
use App\Models\VideoQuestion;
use App\Models\VideoResponse;
use App\Services\Ai\AiRequestService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('module11:seed-strategy-lab-sample {--process-now : Run queued AI requests immediately}', function () {
    /** @var AiRequestService $aiRequestService */
    $aiRequestService = app(AiRequestService::class);

    $company = Company::query()->firstOrCreate(
        ['slug' => 'module11-sample-co'],
        [
            'name' => 'Module 11 Sample Co',
            'status' => Company::STATUS_ACTIVE,
        ]
    );

    $recruiter = User::query()->firstOrCreate(
        ['email' => 'module11.recruiter@example.com'],
        [
            'password' => Hash::make('password'),
            'platform_role' => User::PLATFORM_NONE,
            'active' => true,
            'email_verified_at' => now(),
        ]
    );

    $candidateUser = User::query()->firstOrCreate(
        ['email' => 'module11.candidate@example.com'],
        [
            'password' => Hash::make('password'),
            'platform_role' => User::PLATFORM_NONE,
            'active' => true,
            'email_verified_at' => now(),
        ]
    );

    Profile::query()->updateOrCreate(
        ['user_id' => $recruiter->id],
        ['full_name' => 'Module 11 Recruiter', 'locale' => 'en']
    );
    Profile::query()->updateOrCreate(
        ['user_id' => $candidateUser->id],
        ['full_name' => 'Module 11 Candidate', 'locale' => 'en']
    );

    CompanyMembership::query()->updateOrCreate(
        ['company_id' => $company->id, 'user_id' => $recruiter->id],
        ['company_role' => CompanyMembership::ROLE_RECRUITER, 'membership_status' => CompanyMembership::STATUS_ACTIVE]
    );
    CompanyMembership::query()->updateOrCreate(
        ['company_id' => $company->id, 'user_id' => $candidateUser->id],
        ['company_role' => CompanyMembership::ROLE_CANDIDATE, 'membership_status' => CompanyMembership::STATUS_ACTIVE]
    );

    $job = Job::query()->firstOrCreate(
        ['company_id' => $company->id, 'title' => 'Module 11 Strategy Role'],
        ['status' => Job::STATUS_PUBLISHED]
    );

    $shortlistedStage = JobPipelineStage::query()->firstOrCreate(
        ['job_id' => $job->id, 'stage_key' => 'shortlisted'],
        ['stage_label' => 'Shortlisted', 'display_order' => 2, 'is_terminal' => false]
    );

    $candidate = Candidate::withoutGlobalScopes()->firstOrCreate(
        ['company_id' => $company->id, 'email' => 'module11.candidate@example.com'],
        [
            'user_id' => $candidateUser->id,
            'full_name' => 'Module 11 Candidate',
            'phone' => '+1-555-0111',
            'location' => 'Remote',
        ]
    );

    $candidate->forceFill([
        'user_id' => $candidateUser->id,
        'full_name' => 'Module 11 Candidate',
    ])->save();

    $application = Application::withoutGlobalScopes()->firstOrCreate(
        ['company_id' => $company->id, 'candidate_id' => $candidate->id, 'job_id' => $job->id],
        [
            'current_stage_id' => $shortlistedStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]
    );

    $application->forceFill([
        'current_stage_id' => $shortlistedStage->id,
        'status' => Application::STATUS_ACTIVE,
    ])->save();

    $briefTitle = 'Module 11 Strategy Challenge';
    $brief = StrategyLabBrief::withoutGlobalScopes()->updateOrCreate(
        ['company_id' => $company->id, 'application_id' => $application->id],
        [
            'brief_title' => $briefTitle,
            'brief_pdf_url' => null,
            'deadline_at' => now()->addDays(3),
            'status' => StrategyLabBrief::STATUS_ASSIGNED,
            'generated_ai_request_id' => null,
        ]
    );

    StrategyLabAiSummary::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('application_id', $application->id)
        ->delete();

    $briefAiRequest = $aiRequestService->queueRequest(
        companyId: (string) $company->id,
        requestType: 'strategy_lab_brief_generation',
        requestPayload: [
            'application_id' => (string) $application->id,
            'strategy_lab_brief_id' => (string) $brief->id,
            'brief_title' => $briefTitle,
            'prompt' => implode("\n", [
                'Generate a concise mini-project brief for a shortlisted candidate.',
                'Return plain text only. No markdown.',
                'Title: '.$briefTitle,
                'Job title: '.(string) $job->title,
                'Candidate name: '.(string) $candidate->full_name,
                'Include objective, constraints, deliverables, and evaluation criteria.',
            ]),
            'output_mode' => 'text',
        ],
        promptVersion: 'strategy_lab_brief_v1'
    );

    $brief->forceFill([
        'generated_ai_request_id' => $briefAiRequest->id,
        'updated_at' => now(),
    ])->save();

    $submissionPath = "private/strategy-lab/submissions/{$company->id}/{$application->id}/module11-solution.docx";
    Storage::disk('local')->put($submissionPath, 'Module 11 sample submission content.');

    $submission = StrategyLabSubmission::withoutGlobalScopes()->updateOrCreate(
        ['company_id' => $company->id, 'application_id' => $application->id],
        [
            'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
            'file_url' => $submissionPath,
            'original_filename' => 'module11-solution.docx',
            'submitted_at' => now(),
        ]
    );

    $brief->forceFill([
        'status' => StrategyLabBrief::STATUS_SUBMITTED,
        'updated_at' => now(),
    ])->save();

    $summaryAiRequest = $aiRequestService->queueRequest(
        companyId: (string) $company->id,
        requestType: 'strategy_lab_executive_summary',
        requestPayload: [
            'application_id' => (string) $application->id,
            'strategy_lab_submission_id' => (string) $submission->id,
            'output_mode' => 'json_schema',
            'prompt' => implode("\n", [
                'Generate an executive summary for a Strategy Lab submission.',
                'Use only the metadata provided and infer cautiously.',
                'Submission type: '.$submission->submission_type,
                'Original filename: '.$submission->original_filename,
                'Return strict JSON only.',
            ]),
            'json_schema' => [
                'required' => ['executive_summary_text', 'strengths_json', 'weaknesses_json', 'creativity_score'],
                'properties' => [
                    'executive_summary_text' => ['type' => 'string'],
                    'strengths_json' => ['type' => 'array'],
                    'weaknesses_json' => ['type' => 'array'],
                    'creativity_score' => ['type' => 'number'],
                ],
            ],
        ],
        promptVersion: 'strategy_lab_summary_v1'
    );

    if ((bool) $this->option('process-now')) {
        try {
            $aiRequestService->process($briefAiRequest->fresh());
        } catch (\Throwable $exception) {
            $this->warn('Brief generation request failed: '.$exception->getMessage());
        }

        try {
            $aiRequestService->process($summaryAiRequest->fresh());
        } catch (\Throwable $exception) {
            $this->warn('Executive summary request failed: '.$exception->getMessage());
        }
    }

    $brief->refresh();
    $hasSummary = StrategyLabAiSummary::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('application_id', $application->id)
        ->exists();

    $this->newLine();
    $this->info('Module 11 sample data prepared with real AI request queueing.');
    $this->line('Company: '.$company->name.' ('.$company->slug.')');
    $this->line('Recruiter login: '.$recruiter->email.' / password');
    $this->line('Candidate login: '.$candidateUser->email.' / password');
    $this->line('Candidate portal: '.route('candidate.portal', ['company' => $company->slug]));
    $this->line('Application ID: '.(string) $application->id);
    $this->line('Strategy brief ID: '.(string) $brief->id);
    $this->line('Submission ID: '.(string) $submission->id);
    $this->line('Brief PDF generated: '.($brief->brief_pdf_url ? 'yes' : 'no (queued/failed)'));
    $this->line('AI summary generated: '.($hasSummary ? 'yes' : 'no (queued/failed)'));
})->purpose('Create Module 11 sample data and queue real AI requests (no fake local-stub outputs).');

Artisan::command('module12:seed-video-sample {--process-now : Run queued AI requests immediately}', function () {
    /** @var AiRequestService $aiRequestService */
    $aiRequestService = app(AiRequestService::class);

    $company = Company::query()->firstOrCreate(
        ['slug' => 'module12-sample-co'],
        [
            'name' => 'Module 12 Sample Co',
            'status' => Company::STATUS_ACTIVE,
        ]
    );

    $recruiter = User::query()->firstOrCreate(
        ['email' => 'module12.recruiter@example.com'],
        [
            'password' => Hash::make('password'),
            'platform_role' => User::PLATFORM_NONE,
            'active' => true,
            'email_verified_at' => now(),
        ]
    );

    $candidateUser = User::query()->firstOrCreate(
        ['email' => 'module12.candidate@example.com'],
        [
            'password' => Hash::make('password'),
            'platform_role' => User::PLATFORM_NONE,
            'active' => true,
            'email_verified_at' => now(),
        ]
    );

    Profile::query()->updateOrCreate(
        ['user_id' => $recruiter->id],
        ['full_name' => 'Module 12 Recruiter', 'locale' => 'en']
    );
    Profile::query()->updateOrCreate(
        ['user_id' => $candidateUser->id],
        ['full_name' => 'Module 12 Candidate', 'locale' => 'en']
    );

    CompanyMembership::query()->updateOrCreate(
        ['company_id' => $company->id, 'user_id' => $recruiter->id],
        ['company_role' => CompanyMembership::ROLE_RECRUITER, 'membership_status' => CompanyMembership::STATUS_ACTIVE]
    );
    CompanyMembership::query()->updateOrCreate(
        ['company_id' => $company->id, 'user_id' => $candidateUser->id],
        ['company_role' => CompanyMembership::ROLE_CANDIDATE, 'membership_status' => CompanyMembership::STATUS_ACTIVE]
    );

    $job = Job::query()->firstOrCreate(
        ['company_id' => $company->id, 'title' => 'Module 12 Async Video Role'],
        ['status' => Job::STATUS_PUBLISHED]
    );

    $appliedStage = JobPipelineStage::query()->firstOrCreate(
        ['job_id' => $job->id, 'stage_key' => 'applied'],
        ['stage_label' => 'Applied', 'display_order' => 1, 'is_terminal' => false]
    );

    $candidate = Candidate::withoutGlobalScopes()->firstOrCreate(
        ['company_id' => $company->id, 'email' => 'module12.candidate@example.com'],
        [
            'user_id' => $candidateUser->id,
            'full_name' => 'Module 12 Candidate',
            'phone' => '+1-555-0122',
            'location' => 'Remote',
        ]
    );

    $candidate->forceFill([
        'user_id' => $candidateUser->id,
        'full_name' => 'Module 12 Candidate',
    ])->save();

    $application = Application::withoutGlobalScopes()->firstOrCreate(
        ['company_id' => $company->id, 'candidate_id' => $candidate->id, 'job_id' => $job->id],
        [
            'current_stage_id' => $appliedStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]
    );

    $application->forceFill([
        'current_stage_id' => $appliedStage->id,
        'status' => Application::STATUS_ACTIVE,
    ])->save();

    $config = VideoConfig::withoutGlobalScopes()->updateOrCreate(
        [
            'company_id' => $company->id,
            'job_id' => $job->id,
            'name' => 'Module 12 Stories Config',
        ],
        [
            'read_time_seconds' => 25,
            'answer_time_seconds' => 120,
            'retries_allowed' => 1,
        ]
    );

    VideoQuestion::withoutGlobalScopes()->where('config_id', $config->id)->delete();

    $questionTexts = [
        'Tell us about a time you resolved a conflict under pressure.',
        'How do you prioritize tasks when deadlines conflict?',
        'Describe a difficult stakeholder communication and your approach.',
        'What would you improve in your last project and why?',
    ];

    $questions = collect();
    foreach ($questionTexts as $index => $questionText) {
        $questions->push(
            VideoQuestion::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'config_id' => $config->id,
                'display_order' => $index + 1,
                'question_text' => $questionText,
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );
    }

    VideoResponse::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('application_id', $application->id)
        ->whereIn('question_id', $questions->pluck('id'))
        ->delete();

    AiRequest::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->whereIn('request_type', ['video_response_metrics', 'async_video_unified_report'])
        ->where('request_payload->application_id', (string) $application->id)
        ->delete();

    $metricsRequests = [];
    $segments = [];

    foreach ($questions as $index => $question) {
        $seconds = 72 + ($index * 4);
        $pauses = 2 + $index;
        $speech = 124.5 + ($index * 3.3);
        $filler = 0.036 + ($index * 0.004);
        $transcript = 'Candidate response '.($index + 1).': practical example with clear structure and outcome.';

        $filePath = "private/video-interview/{$company->id}/{$application->id}/{$question->id}/seeded-answer-".($index + 1).".webm";
        Storage::disk('local')->put($filePath, 'module12 seeded sample video placeholder');

        $response = VideoResponse::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'question_id' => $question->id,
            'attempt_number' => 1,
            'video_file_url' => $filePath,
            'duration_seconds' => $seconds,
            'pauses_count' => $pauses,
            'speech_rate_estimate' => round($speech, 2),
            'filler_ratio_estimate' => round($filler, 4),
            'transcript_text' => $transcript,
            'created_at' => now(),
        ]);

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'event_type' => 'video.response_submitted',
            'payload' => [
                'question_id' => (string) $question->id,
                'attempt_number' => 1,
                'video_response_id' => (string) $response->id,
            ],
            'actor_user_id' => $candidateUser->id,
            'created_at' => now(),
        ]);

        $metricsRequests[] = $aiRequestService->queueRequest(
            companyId: (string) $company->id,
            requestType: 'video_response_metrics',
            requestPayload: [
                'application_id' => (string) $application->id,
                'question_id' => (string) $question->id,
                'video_response_id' => (string) $response->id,
                'output_mode' => 'json_schema',
                'prompt' => implode("\n", [
                    'Extract transcript and speech metrics for this async interview response.',
                    'Return strict JSON only.',
                    'Question: '.$question->question_text,
                    'Transcript hint: '.$transcript,
                ]),
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
            promptVersion: 'video_response_metrics_v1'
        );

        $segments[] = [
            'question_id' => (string) $question->id,
            'question' => (string) $question->question_text,
            'attempt_number' => 1,
            'duration_seconds' => $seconds,
            'pauses_count' => $pauses,
            'speech_rate_estimate' => round($speech, 2),
            'filler_ratio_estimate' => round($filler, 4),
            'transcript_text' => $transcript,
        ];
    }

    $unifiedRequest = $aiRequestService->queueRequest(
        companyId: (string) $company->id,
        requestType: 'async_video_unified_report',
        requestPayload: [
            'application_id' => (string) $application->id,
            'job_id' => (string) $job->id,
            'output_mode' => 'json_schema',
            'segments' => $segments,
            'prompt' => implode("\n", [
                'Generate a unified strict JSON candidate report from async video interview segments.',
                'Include technical and psychometric signals with brief XAI summary.',
                'Return strict JSON only and ensure numeric fields are bounded 0-100 where relevant.',
                'Fields required: xai_summary, ocean, match_percentage, salary, generic_motivation, vrin, global_match_score.',
            ]),
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
        promptVersion: 'async_video_unified_report_v1'
    );

    ApplicationActivityEvent::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'application_id' => $application->id,
        'event_type' => 'video.unified_report_queued',
        'payload' => [
            'config_id' => (string) $config->id,
            'questions_count' => $questions->count(),
            'ai_request_id' => (string) $unifiedRequest->id,
        ],
        'actor_user_id' => $recruiter->id,
        'created_at' => now(),
    ]);

    if ((bool) $this->option('process-now')) {
        foreach ($metricsRequests as $metricsRequest) {
            try {
                $aiRequestService->process($metricsRequest->fresh());
            } catch (\Throwable $exception) {
                $this->warn('Video metrics request failed: '.$exception->getMessage());
            }
        }

        try {
            $aiRequestService->process($unifiedRequest->fresh());
        } catch (\Throwable $exception) {
            $this->warn('Unified report request failed: '.$exception->getMessage());
        }
    }

    $latestUnified = AiRequest::withoutGlobalScopes()->find($unifiedRequest->id);

    $this->newLine();
    $this->info('Module 12 sample data prepared (4-question completed Stories submission).');
    $this->line('Company: '.$company->name.' ('.$company->slug.')');
    $this->line('Recruiter login: '.$recruiter->email.' / password');
    $this->line('Candidate login: '.$candidateUser->email.' / password');
    $this->line('Video config ID: '.(string) $config->id);
    $this->line('Application ID: '.(string) $application->id);
    $this->line('Questions created: '.$questions->count());
    $this->line('Candidate portal: '.route('candidate.portal', ['company' => $company->slug]));
    $this->line('Stories page: '.route('candidate.video-stories', ['company' => $company->slug, 'application' => $application->id]));
    $this->line('Unified report request status: '.(string) ($latestUnified?->status ?? 'queued'));
})->purpose('Create Module 12 sample data (video config with 4 questions and completed candidate submission with transcripts).');

Artisan::command('module20:seed-social-hub-sample', function () {
    $this->call('db:seed', [
        '--class' => \Database\Seeders\SocialHubModuleSeeder::class,
        '--force' => true,
    ]);

    $company = Company::query()->where('slug', 'numa-demo')->first()
        ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

    if (! $company instanceof Company) {
        $this->warn('No active company found after seeding.');
        return;
    }

    $postCount = \App\Models\SocialPost::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->count();
    $reactionCount = \App\Models\SocialReaction::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->count();

    $this->newLine();
    $this->info('Module 20 sample data ready.');
    $this->line('Company: '.$company->name.' ('.$company->slug.')');
    $this->line('Social Hub URL: '.route('social-hub.index'));
    $this->line('Posts seeded: '.$postCount);
    $this->line('Reactions seeded: '.$reactionCount);
})->purpose('Seed Module 20 Social Hub sample data (6 posts across all types plus reactions).');

