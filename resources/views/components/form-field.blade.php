@props([
    'label',
    'name' => null,
    'required' => false,
    'help' => null,
    'error' => null,
])

@php
    $hasError = (bool) ($error || ($name && $errors->has($name)));
@endphp

<div {{ $attributes->class(['space-y-2', 'form-field-invalid' => $hasError]) }}>
    <label class="block text-sm font-medium text-slate-800">
        {{ $label }}
        @if ($required)
            <span class="text-danger-700">*</span>
        @endif
    </label>

    {{ $slot }}

    @if ($help)
        <p class="text-xs text-slate-600">{{ $help }}</p>
    @endif

    @if ($hasError)
        <p class="inline-flex items-center gap-1 text-xs font-medium text-danger-700">
            <span aria-hidden="true">!</span>
            <span>{{ $error ?: $errors->first($name) }}</span>
        </p>
    @endif
</div>
