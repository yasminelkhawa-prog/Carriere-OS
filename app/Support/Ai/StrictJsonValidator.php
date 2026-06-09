<?php

namespace App\Support\Ai;

class StrictJsonValidator
{
    /**
     * @param array<string, mixed> $schema
     * @return array{valid: bool, error: string|null}
     */
    public function validate(mixed $decodedJson, array $schema): array
    {
        if (! is_array($decodedJson)) {
            return ['valid' => false, 'error' => 'JSON must decode to an object.'];
        }

        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $requiredKey) {
                if (! array_key_exists((string) $requiredKey, $decodedJson)) {
                    return ['valid' => false, 'error' => "Missing required key: {$requiredKey}."];
                }
            }
        }

        $properties = $schema['properties'] ?? [];
        if (! is_array($properties)) {
            return ['valid' => true, 'error' => null];
        }

        foreach ($properties as $key => $definition) {
            if (! array_key_exists((string) $key, $decodedJson)) {
                continue;
            }

            if (! is_array($definition) || ! isset($definition['type'])) {
                continue;
            }

            $type = (string) $definition['type'];
            $value = $decodedJson[(string) $key];

            if (! $this->matchesType($value, $type)) {
                return ['valid' => false, 'error' => "Key {$key} must be of type {$type}."];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            // json_decode(..., true) turns both {} and [] into [] when empty.
            // Treat empty array as valid for object schemas to avoid false negatives.
            'object' => is_array($value) && (! array_is_list($value) || $value === []),
            default => true,
        };
    }
}
