@props([
    'variant' => 'info',
])

@php
    $styles = [
        'info' => 'border-primary-200/60 bg-primary-100/70 text-primary-900',
        'success' => 'border-success-200/60 bg-success-100/65 text-success-900',
        'warning' => 'border-danger-200/60 bg-danger-100/75 text-danger-900',
        'danger' => 'border-danger-200/60 bg-danger-100/65 text-danger-900',
    ];

    $labels = [
        'info' => 'INFO',
        'success' => 'OK',
        'warning' => 'WARN',
        'danger' => 'ERROR',
    ];
@endphp

<div role="alert" {{ $attributes->class(['rounded-xl border px-4 py-3 text-sm', $styles[$variant] ?? $styles['info']]) }}>
    <div class="flex items-start gap-2">
        <span class="rounded-md border border-current/25 bg-white/35 px-1.5 py-0.5 text-[10px] font-bold tracking-wide">
            {{ $labels[$variant] ?? $labels['info'] }}
        </span>
        <div class="min-w-0 flex-1">
            {{ $slot }}
        </div>
    </div>
</div>
