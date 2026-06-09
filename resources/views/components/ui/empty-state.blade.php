@props([
    'title',
    'message',
])

<section {{ $attributes->class(['rounded-2xl border border-dashed border-aura-300/50 bg-white/55 p-8 text-center backdrop-blur-xl']) }}>
    <h3 class="text-xl font-semibold text-slate-900">{{ $title }}</h3>
    <p class="mt-2 text-sm text-slate-700">{{ $message }}</p>
    @if(trim($slot) !== '')
        <div class="mt-5">
            {{ $slot }}
        </div>
    @endif
</section>
