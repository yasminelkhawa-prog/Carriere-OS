<?php

namespace App\Support\Guardrails;

class DecisionGuard
{
    public static function canAutoSend(string $event): bool
    {
        return in_array($event, config('guardrails.decision_accountability.auto_send_allowed', []), true);
    }

    public static function canAutoReject(): bool
    {
        return (bool) config('guardrails.decision_accountability.reject_auto_send', false);
    }

    public static function canAutoOffer(): bool
    {
        return (bool) config('guardrails.decision_accountability.offer_auto_send', false);
    }

    public static function canAutoHire(): bool
    {
        return (bool) config('guardrails.decision_accountability.hire_auto_send', false);
    }
}
