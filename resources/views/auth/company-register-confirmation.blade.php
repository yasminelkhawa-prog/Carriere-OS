<x-auth-layout :title="__('platform.registration_confirmation_title').' | '.config('app.name')">
    <x-glass-card :title="__('platform.registration_confirmation_title')" :subtitle="__('platform.registration_confirmation_subtitle')">
        <p class="text-sm text-slate-700">
            {{ __('platform.registration_confirmation_body', ['company' => $registeredCompanyName]) }}
        </p>
        <div class="mt-4">
            <a href="{{ route('login') }}" class="inline-flex rounded-xl border border-aura-300/50 bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                {{ __('auth.login_title') }}
            </a>
        </div>
    </x-glass-card>
</x-auth-layout>
