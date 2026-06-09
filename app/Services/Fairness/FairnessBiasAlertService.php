<?php

namespace App\Services\Fairness;

use App\Models\BiasAlert;
use App\Models\BiasAuditStat;
use App\Models\JobPipelineStage;

class FairnessBiasAlertService
{
    public function evaluateFromStat(BiasAuditStat $stat): void
    {
        $impactRatio = (float) $stat->impact_ratio;
        if ($impactRatio >= FairnessAuditService::IMPACT_RATIO_ALERT_THRESHOLD) {
            return;
        }

        $existing = BiasAlert::withoutGlobalScopes()
            ->where('company_id', $stat->company_id)
            ->where('job_id', $stat->job_id)
            ->where('dimension_key', $stat->dimension_key)
            ->whereNull('resolved_at')
            ->where('created_at', '>=', $stat->time_bucket_start)
            ->where('created_at', '<', $stat->time_bucket_end)
            ->exists();

        if ($existing) {
            return;
        }

        $stageLabel = JobPipelineStage::withoutGlobalScopes()
            ->where('id', $stat->stage_id)
            ->value('stage_label');

        BiasAlert::withoutGlobalScopes()->create([
            'company_id' => (string) $stat->company_id,
            'job_id' => (string) $stat->job_id,
            'dimension_key' => (string) $stat->dimension_key,
            'severity' => $this->severityForRatio($impactRatio),
            'message' => $this->buildMessage(
                dimensionKey: (string) $stat->dimension_key,
                stageLabel: is_string($stageLabel) && $stageLabel !== '' ? $stageLabel : 'Unknown Stage',
                impactRatio: $impactRatio
            ),
            'created_at' => now(),
            'resolved_at' => null,
        ]);
    }

    private function severityForRatio(float $impactRatio): string
    {
        return match (true) {
            $impactRatio < 0.50 => BiasAlert::SEVERITY_CRITICAL,
            $impactRatio < 0.65 => BiasAlert::SEVERITY_HIGH,
            default => BiasAlert::SEVERITY_MEDIUM,
        };
    }

    private function buildMessage(string $dimensionKey, string $stageLabel, float $impactRatio): string
    {
        return sprintf(
            'Bias risk: impact ratio %.2f in %s (%s). Threshold is 0.80.',
            $impactRatio,
            $stageLabel,
            $dimensionKey
        );
    }
}

