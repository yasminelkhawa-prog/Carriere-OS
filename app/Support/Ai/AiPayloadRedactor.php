<?php

namespace App\Support\Ai;

use Illuminate\Support\Str;

class AiPayloadRedactor
{
    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    public function redact(?array $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return $this->redactValue($payload);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function redactValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redactValue($childValue, (string) $childKey);
            }

            return $redacted;
        }

        if ($this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (is_string($value)) {
            return Str::limit($value, 220);
        }

        return $value;
    }

    private function isSensitiveKey(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        $needle = Str::lower($key);

        return Str::contains($needle, [
            'secret',
            'token',
            'password',
            'api_key',
            'apikey',
            'raw_document',
            'raw_cv',
            'raw_resume',
            'document_content',
        ]);
    }
}
