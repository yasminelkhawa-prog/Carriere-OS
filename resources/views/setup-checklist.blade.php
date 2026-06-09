<x-shell-layout :title="__('ui.setup.title').' | '.config('app.name')">
    <x-glass-card>
        <p class="text-xs uppercase tracking-[0.3em] text-primary-700/80">{{ __('ui.setup.eyebrow') }}</p>
        <h2 class="mt-4 text-3xl font-semibold text-slate-900">{{ __('ui.setup.headline') }}</h2>
        <p class="mt-3 text-sm leading-relaxed text-slate-700">
            {{ __('ui.setup.description') }}
        </p>

        @if ($missingVars !== [])
            <ul class="mt-7 space-y-2">
                @foreach ($missingVars as $key)
                    <li class="rounded-lg border border-primary-200/60 bg-primary-50/80 px-4 py-3 text-sm text-primary-900">
                        {{ $key }}
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-7">
                <x-empty-state :title="__('ui.setup.empty_title')" :message="__('ui.setup.empty_message')" />
            </div>
        @endif
    </x-glass-card>
</x-shell-layout>
