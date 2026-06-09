@props([
    'href' => '#',
    'label' => '',
    'icon' => 'overview',
    'active' => false,
    'dot' => false,
    'compact' => false,
    'collapsible' => false,
])

@php
$baseClasses = $compact
    ? 'group portal-menu-item flex items-center gap-2 rounded-xl px-3 py-2 font-medium transition-weightless'
    : 'group portal-menu-item flex items-center gap-3 rounded-2xl px-4 py-3 font-medium transition-weightless';

$activeClasses = $compact
    ? 'bg-aura-200 text-aura-900'
    : 'bg-aura-700 text-white shadow-lg shadow-aura-300';

    $inactiveClasses = $compact
        ? 'text-slate-800 hover:bg-aura-100/70'
        : 'text-slate-700 hover:bg-white/85 hover:text-slate-900 hover:shadow-sm';
@endphp

<a
    href="{{ $href }}"
    data-portal-menu-item
    x-bind:title="@js($collapsible && ! $compact) && leftSidebarCollapsed ? @js($label) : ''"
    {{ $attributes->class([$baseClasses, $active ? $activeClasses : $inactiveClasses])->merge([
        'x-bind:class' => $collapsible && ! $compact ? "leftSidebarCollapsed ? 'justify-center gap-0 px-2 py-3' : ''" : '',
    ]) }}
>
    <x-ui.nav-icon :name="$icon" :class="$compact ? 'size-4' : 'size-5'" />
    @if($collapsible && ! $compact)
        <span class="truncate" x-cloak x-show="!leftSidebarCollapsed">{{ $label }}</span>
    @else
        <span class="truncate">{{ $label }}</span>
    @endif
    @if($dot)
        <span
            class="ms-auto inline-flex size-2.5 rounded-full bg-danger-500 ring-2 ring-white"
            aria-hidden="true"
            @if($collapsible && ! $compact)
                x-bind:class="leftSidebarCollapsed ? 'ms-0 mt-1' : 'ms-auto'"
            @endif
        ></span>
    @endif
</a>
