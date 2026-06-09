<?php

namespace App\Observers;

use App\Models\CandidateSurvey;
use App\Services\EmployerBrand\EmployerBrandSentimentService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CandidateSurveyObserver
{
    public function created(CandidateSurvey $candidateSurvey): void
    {
        try {
            app(EmployerBrandSentimentService::class)->queueForCandidateSurvey($candidateSurvey);
        } catch (Throwable $exception) {
            Log::warning('Unable to queue candidate survey sentiment analysis.', [
                'company_id' => (string) $candidateSurvey->company_id,
                'application_id' => (string) $candidateSurvey->application_id,
                'candidate_survey_id' => (string) $candidateSurvey->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
