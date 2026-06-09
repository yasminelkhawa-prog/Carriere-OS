<?php

namespace App\Observers;

use App\Models\Application;
use App\Services\Analysis\CandidateAnalysisService;
use App\Services\Cv\CandidateCvParsingPipeline;
use App\Services\Fairness\FairnessAuditService;
use App\Services\Referral\ReferralService;
use Throwable;

class ApplicationObserver
{
    public function created(Application $application): void
    {
        $this->syncReferralStatus($application);
        $this->queueCvParsing($application, 'candidate_import');
        $this->refreshCandidateAnalysis($application);
        $this->queueFairnessAggregation($application);
    }

    public function updated(Application $application): void
    {
        if (! $application->wasChanged(['status', 'current_stage_id', 'source_type', 'source_detail'])) {
            if ($application->wasChanged(['candidate_id', 'job_id'])) {
                $this->queueCvParsing($application, 'candidate_update');
            }
            return;
        }

        $this->syncReferralStatus($application);
        $this->refreshCandidateAnalysis($application);
        $this->queueFairnessAggregation(
            application: $application,
            originalStageId: $application->wasChanged('current_stage_id')
                ? (string) $application->getOriginal('current_stage_id')
                : null
        );
    }

    private function syncReferralStatus(Application $application): void
    {
        try {
            app(ReferralService::class)->syncFromApplication($application);
        } catch (Throwable) {
            // Do not block application mutations if referral synchronization fails.
        }
    }

    private function queueFairnessAggregation(Application $application, ?string $originalStageId = null): void
    {
        try {
            app(FairnessAuditService::class)->queueForApplication($application, $originalStageId);
        } catch (Throwable) {
            // Do not block application mutations if fairness aggregation dispatch fails.
        }
    }

    private function queueCvParsing(Application $application, string $trigger): void
    {
        try {
            app(CandidateCvParsingPipeline::class)->queueForApplication(
                application: $application,
                trigger: $trigger
            );
        } catch (Throwable) {
            // Do not block application mutations if CV parsing dispatch fails.
        }
    }

    private function refreshCandidateAnalysis(Application $application): void
    {
        try {
            app(CandidateAnalysisService::class)->recomputeForApplication($application);
        } catch (Throwable) {
            // Do not block application mutations if deterministic analysis refresh fails.
        }
    }
}
