@props([
    'type' => 'warning',
])

@php
    $styles = [
        'info' => 'border-primary-200/70 bg-primary-100/90 text-primary-900',
        'success' => 'border-success-200/60 bg-success-100/90 text-success-900',
        'warning' => 'border-danger-200/70 bg-danger-100/90 text-danger-900',
        'error' => 'border-danger-200/70 bg-danger-100/90 text-danger-900',
    ];

    $labels = [
        'info' => 'INFO',
        'success' => 'OK',
        'warning' => 'WARN',
        'error' => 'ERROR',
    ];
@endphp

<div x-transition role="alert" {{ $attributes->class(['rounded-xl border px-4 py-3 text-sm shadow-lg backdrop-blur', $styles[$type] ?? $styles['warning']]) }}>
    <div class="flex items-start gap-2">
        <span class="rounded-md border border-current/25 bg-white/40 px-1.5 py-0.5 text-[10px] font-bold tracking-wide">
            {{ $labels[$type] ?? $labels['warning'] }}
        </span>
        <div class="min-w-0 flex-1">
            {{ $slot }}
        </div>
    </div>
</div>
