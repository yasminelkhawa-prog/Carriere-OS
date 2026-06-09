<?php

namespace App\Support\Guardrails;

class AiOutputMode
{
    public static function for(string $purpose): ?string
    {
        return config("guardrails.ai_output_modes.$purpose");
    }

    public static function requiresStrictJson(string $purpose): bool
    {
        return self::for($purpose) === 'json_schema';
    }
}
