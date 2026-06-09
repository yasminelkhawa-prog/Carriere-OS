<?php

namespace App\Observers;

use App\Models\ApplicationScoring;
use App\Services\Fairness\FairnessAuditService;
use Throwable;

class ApplicationScoringObserver
{
    public function created(ApplicationScoring $scoring): void
    {
        $this->queueFairnessAggregation($scoring);
    }

    public function updated(ApplicationScoring $scoring): void
    {
        if (! $scoring->wasChanged(['global_match_score', 'vrin_json'])) {
            return;
        }

        $this->queueFairnessAggregation($scoring);
    }

    private function queueFairnessAggregation(ApplicationScoring $scoring): void
    {
        try {
            app(FairnessAuditService::class)->queueForScoring($scoring);
        } catch (Throwable) {
            // Do not block scoring updates if fairness aggregation dispatch fails.
        }
    }
}

