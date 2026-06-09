<?php

namespace Database\Seeders;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\CandidateSurvey;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\ReverseFeedback;
use App\Models\SentimentResult;
use App\Services\Ai\AiRequestService;
use App\Services\EmployerBrand\EmployerBrandAlertService;
use App\Services\EmployerBrand\EmployerBrandSentimentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class EmployerBrandModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('sentiment_results') || ! Schema::hasTable('brand_alerts')) {
            $this->command?->warn(
                'Skipping EmployerBrandModuleSeeder: run migrations for sentiment_results and brand_alerts first.'
            );

            return;
        }

        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

        if (! $company instanceof Company) {
            return;
        }

        $applications = Application::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        if ($applications->count() < 10) {
            $this->call(RecruitmentOverviewModuleSeeder::class);

            $applications = Application::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->orderBy('created_at')
                ->limit(10)
                ->get();
        }

        if ($applications->count() < 10) {
            return;
        }

        $recruiterId = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->whereIn('company_role', [
                CompanyMembership::ROLE_COMPANY_ADMIN,
                CompanyMembership::ROLE_RECRUITER,
                CompanyMembership::ROLE_MANAGER,
            ])
            ->value('user_id');

        if (! is_string($recruiterId) || $recruiterId === '') {
            return;
        }

        $feedbackPlan = [
            [
                'source' => EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
                'text' => 'The process was smooth, professional, and very transparent.',
                'rating' => 5,
                'days_ago' => 9,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_INTERVIEW_FEEDBACK,
                'text' => 'Strong technical discussion and respectful panel behavior.',
                'rating' => 4,
                'days_ago' => 8,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_CANDIDATE_SURVEY,
                'text' => 'Helpful updates and clear expectations across the process.',
                'rating' => 5,
                'days_ago' => 7,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
                'text' => 'Good experience overall, but response time was a little slow.',
                'rating' => 3,
                'days_ago' => 6,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_INTERVIEW_FEEDBACK,
                'text' => 'Communication was confusing and follow-up felt disorganized.',
                'rating' => 2,
                'days_ago' => 6,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_CANDIDATE_SURVEY,
                'text' => 'I felt ignored for days and the process became frustrating.',
                'rating' => 2,
                'days_ago' => 5,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
                'text' => 'The recruiter was rude and unprofessional in multiple interactions.',
                'rating' => 1,
                'days_ago' => 4,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_INTERVIEW_FEEDBACK,
                'text' => 'Candidate reported hostile and humiliating behavior in interview.',
                'rating' => 1,
                'days_ago' => 3,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_CANDIDATE_SURVEY,
                'text' => 'Great candidate care and respectful communication throughout.',
                'rating' => 5,
                'days_ago' => 2,
            ],
            [
                'source' => EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK,
                'text' => 'Transparent process and supportive interactions from the team.',
                'rating' => 4,
                'days_ago' => 1,
            ],
        ];

        /** @var EmployerBrandSentimentService $sentimentService */
        $sentimentService = app(EmployerBrandSentimentService::class);
        /** @var AiRequestService $aiRequestService */
        $aiRequestService = app(AiRequestService::class);
        /** @var EmployerBrandAlertService $alertService */
        $alertService = app(EmployerBrandAlertService::class);

        $originalQueueDriver = config('queue.default');
        $originalGeminiStubEnabled = (bool) config('services.gemini.local_stub_enabled');

        config([
            'queue.default' => 'sync',
            'services.gemini.local_stub_enabled' => true,
        ]);

        $queuedRequestIds = [];

        try {
            foreach ($applications->values() as $index => $application) {
                $plan = $feedbackPlan[$index] ?? null;
                if (! is_array($plan)) {
                    continue;
                }

                $rating = max(1, min(5, (int) ($plan['rating'] ?? 3)));
                $daysAgo = max(0, (int) ($plan['days_ago'] ?? 0));
                $comment = trim((string) ($plan['text'] ?? ''));
                $createdAt = now()->subDays($daysAgo);

                $request = match ((string) ($plan['source'] ?? '')) {
                    EmployerBrandSentimentService::SOURCE_INTERVIEW_FEEDBACK => $this->seedInterviewFeedback(
                        companyId: (string) $company->id,
                        recruiterId: $recruiterId,
                        application: $application,
                        rating: $rating,
                        comment: $comment,
                        createdAt: $createdAt,
                        sentimentService: $sentimentService,
                    ),
                    EmployerBrandSentimentService::SOURCE_CANDIDATE_SURVEY => $this->seedCandidateSurvey(
                        companyId: (string) $company->id,
                        application: $application,
                        rating: $rating,
                        comment: $comment,
                        createdAt: $createdAt,
                        sentimentService: $sentimentService,
                    ),
                    default => $this->seedReverseFeedback(
                        companyId: (string) $company->id,
                        recruiterId: $recruiterId,
                        application: $application,
                        rating: $rating,
                        comment: $comment,
                        createdAt: $createdAt,
                        sentimentService: $sentimentService,
                    ),
                };

                if ($request instanceof AiRequest) {
                    $queuedRequestIds[] = (string) $request->id;
                }
            }

            if ($queuedRequestIds !== []) {
                AiRequest::withoutGlobalScopes()
                    ->whereIn('id', array_values(array_unique($queuedRequestIds)))
                    ->get()
                    ->each(function (AiRequest $request) use ($aiRequestService): void {
                        if ($request->status !== AiRequest::STATUS_SUCCEEDED) {
                            $aiRequestService->process($request);
                        }
                    });
            }
        } finally {
            config([
                'queue.default' => $originalQueueDriver,
                'services.gemini.local_stub_enabled' => $originalGeminiStubEnabled,
            ]);
        }

        $hasHighRisk = SentimentResult::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('risk_level', [SentimentResult::RISK_HIGH, SentimentResult::RISK_CRITICAL])
            ->exists();

        if (! $hasHighRisk) {
            $fallback = SentimentResult::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->latest('created_at')
                ->first();

            if ($fallback instanceof SentimentResult) {
                $fallback->forceFill([
                    'sentiment_score' => -0.92,
                    'top_themes_json' => ['candidate safety', 'professionalism'],
                    'risk_level' => SentimentResult::RISK_CRITICAL,
                    'created_at' => now(),
                ])->save();

                $alertService->evaluateFromSentimentResult($fallback);
            }
        }

        $this->command?->info('Employer Brand module sample data seeded (10 mixed comments with sentiment analysis).');
    }

    private function seedReverseFeedback(
        string $companyId,
        string $recruiterId,
        Application $application,
        int $rating,
        string $comment,
        mixed $createdAt,
        EmployerBrandSentimentService $sentimentService
    ): ?AiRequest {
        $feedback = ReverseFeedback::withoutGlobalScopes()->updateOrCreate(
            ['application_id' => (string) $application->id],
            [
                'company_id' => $companyId,
                'recruiter_user_id' => $recruiterId,
                'rating_clarity' => $rating,
                'rating_speed' => $rating,
                'rating_kindness' => $rating,
                'comment' => $comment !== '' ? $comment : null,
                'is_anonymous' => false,
                'created_at' => $createdAt,
            ]
        );

        if ($feedback->wasRecentlyCreated && $comment !== '') {
            return $sentimentService->queueForReverseFeedback($feedback);
        }

        return AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'sentiment_analysis')
            ->where('request_payload->source_type', EmployerBrandSentimentService::SOURCE_REVERSE_FEEDBACK)
            ->where('request_payload->source_id', (string) $feedback->id)
            ->latest('created_at')
            ->first();
    }

    private function seedInterviewFeedback(
        string $companyId,
        string $recruiterId,
        Application $application,
        int $rating,
        string $comment,
        mixed $createdAt,
        EmployerBrandSentimentService $sentimentService
    ): ?AiRequest {
        $interview = Interview::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'application_id' => (string) $application->id,
                'created_by_user_id' => $recruiterId,
            ],
            [
                'interview_type' => 'structured',
                'scheduled_start_at' => now()->subDays(1),
                'scheduled_end_at' => now()->subDays(1)->addMinutes(45),
                'timezone' => 'UTC',
                'location_type' => Interview::LOCATION_ZOOM,
                'meeting_link' => 'https://example.test/interview',
                'status' => Interview::STATUS_COMPLETED,
            ]
        );

        $recommendation = $rating >= 4
            ? InterviewFeedback::RECOMMENDATION_HIRE
            : ($rating <= 2 ? InterviewFeedback::RECOMMENDATION_NO : InterviewFeedback::RECOMMENDATION_HOLD);

        $feedback = InterviewFeedback::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'interview_id' => (string) $interview->id,
                'author_user_id' => $recruiterId,
            ],
            [
                'ratings_json' => [
                    'technical' => $rating,
                    'communication' => $rating,
                    'problem_solving' => $rating,
                ],
                'recommendation' => $recommendation,
                'notes' => $comment !== '' ? $comment : null,
                'created_at' => $createdAt,
            ]
        );

        if ($feedback->wasRecentlyCreated && $comment !== '') {
            return $sentimentService->queueForInterviewFeedback($feedback);
        }

        return AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'sentiment_analysis')
            ->where('request_payload->source_type', EmployerBrandSentimentService::SOURCE_INTERVIEW_FEEDBACK)
            ->where('request_payload->source_id', (string) $feedback->id)
            ->latest('created_at')
            ->first();
    }

    private function seedCandidateSurvey(
        string $companyId,
        Application $application,
        int $rating,
        string $comment,
        mixed $createdAt,
        EmployerBrandSentimentService $sentimentService
    ): ?AiRequest {
        $survey = CandidateSurvey::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'application_id' => (string) $application->id,
            ],
            [
                'overall_experience_rating' => $rating,
                'comment' => $comment !== '' ? $comment : null,
                'created_at' => $createdAt,
            ]
        );

        if ($survey->wasRecentlyCreated && $comment !== '') {
            return $sentimentService->queueForCandidateSurvey($survey);
        }

        return AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'sentiment_analysis')
            ->where('request_payload->source_type', EmployerBrandSentimentService::SOURCE_CANDIDATE_SURVEY)
            ->where('request_payload->source_id', (string) $survey->id)
            ->latest('created_at')
            ->first();
    }
}

