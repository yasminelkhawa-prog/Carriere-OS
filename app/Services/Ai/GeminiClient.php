<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeminiClient
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function generate(string $prompt, string $modelName): string
    {
        return $this->generateParts([
            ['text' => $prompt],
        ], $modelName);
    }

    /**
     * @param array<int, array<string, mixed>> $parts
     */
    public function generateParts(array $parts, string $modelName): string
    {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $models = $this->resolveModels($modelName);
        $attemptedModels = [];
        $lastException = null;

        foreach ($models as $candidateModel) {
            $attemptedModels[] = $candidateModel;

            try {
                $response = $this->http->timeout((int) config('services.gemini.timeout_seconds', 30))
                    ->post($this->buildGenerateContentUrl($candidateModel, $apiKey), [
                        'contents' => [[
                            'parts' => $parts,
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                        ],
                        'safetySettings' => [],
                    ]);
            } catch (Throwable $exception) {
                $lastException = $exception;
                continue;
            }

            if ($response->successful()) {
                $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
                if ($text !== '') {
                    return $text;
                }

                throw new RuntimeException("Gemini returned an empty response for model [{$candidateModel}].");
            }

            if ($this->shouldFallbackToNextModel($response)) {
                continue;
            }

            try {
                $response->throw();
            } catch (RequestException $exception) {
                $lastException = $exception;
                break;
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException(
            sprintf(
                'Gemini model is unavailable. Tried models: %s.',
                implode(', ', $attemptedModels)
            )
        );
    }

    /**
     * @return array<int, string>
     */
    private function resolveModels(string $primaryModel): array
    {
        $configuredFallbacks = config('services.gemini.fallback_models', []);
        if (is_string($configuredFallbacks)) {
            $configuredFallbacks = explode(',', $configuredFallbacks);
        }

        $configuredFallbacks = array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            is_array($configuredFallbacks) ? $configuredFallbacks : []
        )));

        $models = array_merge([$primaryModel], $configuredFallbacks);

        $normalized = [];
        foreach ($models as $model) {
            $model = $this->normalizeModelName((string) $model);
            if ($model === '' || in_array($model, $normalized, true)) {
                continue;
            }
            $normalized[] = $model;
        }

        return $normalized;
    }

    private function normalizeModelName(string $model): string
    {
        $model = trim($model);
        if (Str::startsWith($model, 'models/')) {
            return (string) Str::after($model, 'models/');
        }

        return $model;
    }

    private function buildGenerateContentUrl(string $modelName, string $apiKey): string
    {
        return sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $modelName,
            urlencode($apiKey)
        );
    }

    private function shouldFallbackToNextModel(Response $response): bool
    {
        if ($response->status() !== 404) {
            return false;
        }

        $message = (string) data_get($response->json(), 'error.message', '');
        $details = (string) json_encode(Arr::get($response->json(), 'error.details', []), JSON_UNESCAPED_UNICODE);

        return str_contains($message, 'is not found for API version')
            || str_contains($message, 'is not supported for generateContent')
            || str_contains($details, 'MODEL_NOT_FOUND');
    }
}
