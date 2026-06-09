<?php

namespace App\Observers;

use App\Models\InterviewFeedback;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\EmployerBrand\EmployerBrandSentimentService;
use Illuminate\Support\Facades\Log;
use Throwable;

class InterviewFeedbackObserver
{
    public function created(InterviewFeedback $interviewFeedback): void
    {
        try {
            app(EmployerBrandSentimentService::class)->queueForInterviewFeedback($interviewFeedback);
        } catch (Throwable $exception) {
            Log::warning('Unable to queue interview feedback sentiment analysis.', [
                'company_id' => (string) $interviewFeedback->company_id,
                'interview_id' => (string) $interviewFeedback->interview_id,
                'feedback_id' => (string) $interviewFeedback->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $applicationId = (string) $interviewFeedback->interview?->application_id;
            if ($applicationId !== '') {
                app(CandidateAnalysisService::class)->recomputeForApplicationId(
                    companyId: (string) $interviewFeedback->company_id,
                    applicationId: $applicationId
                );
            }
        } catch (Throwable) {
            // Do not block feedback persistence if deterministic analysis refresh fails.
        }
    }
}
