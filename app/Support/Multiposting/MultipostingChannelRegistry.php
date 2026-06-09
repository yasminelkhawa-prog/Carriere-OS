<?php

namespace App\Support\Multiposting;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MultipostingChannelRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        /** @var array<string, array<string, mixed>> $channels */
        $channels = config('multiposting.channels', []);

        return collect($channels)
            ->mapWithKeys(function (mixed $channel, string $key): array {
                $normalizedKey = Str::lower(trim($key));

                return [$normalizedKey => is_array($channel) ? $channel : []];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function for(string $channel): ?array
    {
        $key = Str::lower(trim($channel));

        if ($key === '') {
            return null;
        }

        return $this->all()[$key] ?? null;
    }

    public function has(string $channel): bool
    {
        return $this->for($channel) !== null;
    }

    public function isActive(string $channel): bool
    {
        return (bool) data_get($this->for($channel), 'active', false);
    }

    public function supports(string $channel, string $capability): bool
    {
        return (bool) data_get(
            $this->for($channel),
            'capabilities.'.Str::lower(trim($capability)),
            false
        );
    }

    public function label(string $channel): string
    {
        return (string) data_get($this->for($channel), 'label', Str::headline(str_replace('_', ' ', $channel)));
    }

    public function deliveryType(string $channel): string
    {
        return (string) data_get($this->for($channel), 'delivery_type', 'unknown');
    }

    public function publishMethod(string $channel): string
    {
        return (string) data_get($this->for($channel), 'publish_method', 'unknown');
    }

    public function authMethod(string $channel): string
    {
        return (string) data_get($this->for($channel), 'auth_method', 'unknown');
    }

    public function executionMode(string $channel): string
    {
        return (string) data_get($this->for($channel), 'execution_mode', 'unknown');
    }

    public function phase(string $channel): string
    {
        return (string) data_get($this->for($channel), 'phase', 'unknown');
    }

    /**
     * @return array<int, string>
     */
    public function jobBoardPlatforms(bool $activeOnly = true): array
    {
        $platforms = array_values(array_filter(array_map(
            static fn (mixed $value): string => Str::lower(trim((string) $value)),
            (array) config('multiposting.job_board_platforms', [])
        )));

        if (! $activeOnly) {
            return $platforms;
        }

        return array_values(array_filter(
            $platforms,
            fn (string $platform): bool => $this->isActive($platform)
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeJobBoardChannelDetails(): array
    {
        return array_map(
            fn (string $platform): array => $this->detail($platform),
            $this->jobBoardPlatforms(true)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $channel): array
    {
        $definition = $this->for($channel) ?? [];

        return [
            'key' => Str::lower(trim($channel)),
            'label' => $this->label($channel),
            'active' => (bool) data_get($definition, 'active', false),
            'delivery_type' => $this->deliveryType($channel),
            'publish_method' => $this->publishMethod($channel),
            'auth_method' => $this->authMethod($channel),
            'execution_mode' => $this->executionMode($channel),
            'phase' => $this->phase($channel),
            'capabilities' => Arr::wrap(data_get($definition, 'capabilities', [])),
        ];
    }

    /**
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public function groupedActiveJobBoardChannelDetails(): array
    {
        return collect($this->activeJobBoardChannelDetails())
            ->groupBy(fn (array $channel): string => (string) ($channel['delivery_type'] ?? 'unknown'))
            ->all();
    }
}
