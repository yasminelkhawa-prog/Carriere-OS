@props([
    'variant' => 'default',
])

@php
    $styles = [
        'default' => 'border-primary-200/60 bg-primary-100/80 text-primary-900',
        'pending' => 'border-primary-300/60 bg-primary-100/80 text-primary-900',
        'neutral' => 'border-slate-300 bg-slate-100 text-slate-800',
        'success' => 'border-success-200/50 bg-success-100/70 text-success-900',
        'warning' => 'border-danger-200/60 bg-danger-100/80 text-danger-900',
        'danger' => 'border-danger-200/60 bg-danger-100/80 text-danger-900',
        'error' => 'border-danger-200/60 bg-danger-100/80 text-danger-900',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full border px-4 py-1.5 text-sm font-medium leading-none', $styles[$variant] ?? $styles['default']]) }}>
    {{ $slot }}
</span>
