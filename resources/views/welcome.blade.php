<x-shell-layout :title="__('ui.welcome.title').' | '.config('app.name')">
    <x-glass-card>
        <p class="text-xs uppercase tracking-[0.3em] text-aura-700/80">{{ __('ui.welcome.eyebrow') }}</p>
        <h2 class="mt-4 text-4xl font-semibold text-slate-900 sm:text-5xl">{{ __('ui.welcome.headline') }}</h2>
        <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-700">
            {{ __('ui.welcome.description') }}
        </p>

        <div class="mt-8 flex flex-wrap gap-3 text-sm">
            <x-badge>{{ __('ui.welcome.badge_laravel') }}</x-badge>
            <x-badge>{{ __('ui.welcome.badge_postgres') }}</x-badge>
            <x-badge variant="success">{{ __('ui.welcome.badge_queue') }}</x-badge>
        </div>
    </x-glass-card>
</x-shell-layout>
