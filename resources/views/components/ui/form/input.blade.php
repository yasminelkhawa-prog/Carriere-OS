@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
])

@php
    $hasError = $errors->has($name);
@endphp

<label class="block space-y-2">
    <span class="text-sm font-medium text-slate-800">{{ $label }}</span>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @required($required)
        @if($hasError) aria-invalid="true" @endif
        {{ $attributes->class([
            'w-full rounded-xl border bg-white/85 px-3 py-2.5 text-slate-900 shadow-sm transition-weightless',
            $hasError
                ? 'border-danger-400 focus:border-danger-500 focus:ring-danger-300'
                : 'border-primary-200/50 focus:border-primary-500 focus:ring-primary-300',
        ]) }}
    >
</label>
