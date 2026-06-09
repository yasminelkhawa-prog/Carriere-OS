<?php

namespace App\Observers;

use App\Models\ReverseFeedback;
use App\Services\EmployerBrand\EmployerBrandSentimentService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReverseFeedbackObserver
{
    public function created(ReverseFeedback $reverseFeedback): void
    {
        try {
            app(EmployerBrandSentimentService::class)->queueForReverseFeedback($reverseFeedback);
        } catch (Throwable $exception) {
            Log::warning('Unable to queue reverse feedback sentiment analysis.', [
                'company_id' => (string) $reverseFeedback->company_id,
                'application_id' => (string) $reverseFeedback->application_id,
                'reverse_feedback_id' => (string) $reverseFeedback->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
