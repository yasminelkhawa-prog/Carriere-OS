<?php

namespace App\Services\EmployerBrand;

use App\Models\BrandAlert;
use App\Models\SentimentResult;

class EmployerBrandAlertService
{
    public function evaluateFromSentimentResult(SentimentResult $result): void
    {
        if (in_array($result->risk_level, [SentimentResult::RISK_HIGH, SentimentResult::RISK_CRITICAL], true)) {
            $this->createRiskThresholdAlert($result);
        }

        $this->createSustainedNegativeTrendAlert((string) $result->company_id);
    }

    private function createRiskThresholdAlert(SentimentResult $result): void
    {
        $existing = BrandAlert::withoutGlobalScopes()
            ->where('company_id', $result->company_id)
            ->where('alert_type', BrandAlert::ALERT_RISK_THRESHOLD)
            ->where('related_entity_type', $result->source_type)
            ->where('related_entity_id', $result->source_id)
            ->whereNull('resolved_at')
            ->exists();

        if ($existing) {
            return;
        }

        $severity = $result->risk_level === SentimentResult::RISK_CRITICAL
            ? BrandAlert::SEVERITY_CRITICAL
            : BrandAlert::SEVERITY_HIGH;

        $sourceLabel = str_replace('_', ' ', strtolower((string) $result->source_type));
        $score = is_numeric($result->sentiment_score)
            ? number_format((float) $result->sentiment_score, 2)
            : 'N/A';

        BrandAlert::withoutGlobalScopes()->create([
            'company_id' => (string) $result->company_id,
            'alert_type' => BrandAlert::ALERT_RISK_THRESHOLD,
            'severity' => $severity,
            'message' => "High-risk sentiment detected in {$sourceLabel} feedback (score {$score}).",
            'related_entity_type' => (string) $result->source_type,
            'related_entity_id' => (string) $result->source_id,
            'created_at' => now(),
            'resolved_at' => null,
        ]);
    }

    private function createSustainedNegativeTrendAlert(string $companyId): void
    {
        $scores = SentimentResult::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('sentiment_score')
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('created_at')
            ->limit(12)
            ->pluck('sentiment_score');

        $count = $scores->count();
        if ($count < 5) {
            return;
        }

        $avg = (float) $scores->avg();
        $negativeCount = $scores->filter(static fn ($score): bool => is_numeric($score) && (float) $score <= -0.25)->count();

        if ($avg > -0.35 || $negativeCount < 4) {
            return;
        }

        $existing = BrandAlert::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('alert_type', BrandAlert::ALERT_NEGATIVE_TREND)
            ->whereNull('resolved_at')
            ->exists();

        if ($existing) {
            return;
        }

        BrandAlert::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'alert_type' => BrandAlert::ALERT_NEGATIVE_TREND,
            'severity' => $avg <= -0.6 ? BrandAlert::SEVERITY_CRITICAL : BrandAlert::SEVERITY_HIGH,
            'message' => 'Negative sentiment trend has been sustained across recent feedback.',
            'related_entity_type' => 'company',
            'related_entity_id' => $companyId,
            'created_at' => now(),
            'resolved_at' => null,
        ]);
    }
}
