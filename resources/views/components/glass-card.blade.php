@props([
    'title' => null,
    'subtitle' => null,
    'bodyClass' => null,
])

<section {{ $attributes->class(['glass-card p-5']) }}>
    @if ($title)
        <h2 class="text-2xl font-semibold text-slate-900">{{ $title }}</h2>
    @endif

    @if ($subtitle)
        <p class="mt-2 text-sm text-slate-700">{{ $subtitle }}</p>
    @endif

    <div @class([
        'mt-6' => $title || $subtitle,
        $bodyClass,
    ])>
        {{ $slot }}
    </div>
</section>
