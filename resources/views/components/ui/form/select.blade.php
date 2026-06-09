@props([
    'label',
    'name',
    'options' => [],
    'selected' => null,
    'placeholder' => '',
    'multiple' => false,
    'required' => false,
])

@php
    $current = old($name, $selected);
    $values = is_array($current) ? $current : [$current];
    $hasError = $errors->has($name);
@endphp

<label class="block space-y-2">
    <span class="text-sm font-medium text-slate-800">{{ $label }}</span>
    <select
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        data-placeholder="{{ $placeholder }}"
        @required($required)
        @if($multiple) multiple @endif
        @if($hasError) aria-invalid="true" @endif
        {{ $attributes->class([
            'js-select2 w-full rounded-xl border bg-white/85 px-3 py-2.5 text-slate-900 shadow-sm transition-weightless',
            $hasError
                ? 'border-danger-400 focus:border-danger-500 focus:ring-danger-300'
                : 'border-primary-200/50 focus:border-primary-500 focus:ring-primary-300',
        ]) }}
    >
        @if (! $multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, array_map('strval', $values), true))>{{ $optionLabel }}</option>
        @endforeach
    </select>
</label>
