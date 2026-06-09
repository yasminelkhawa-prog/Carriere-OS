<x-shell-layout :title="__('candidate_portal.security.title').' | '.config('app.name')">
    <div class="space-y-6">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif
        @if($errors->any())
            <x-toast-alert type="warning">{{ $errors->first() }}</x-toast-alert>
        @endif

        <x-glass-card :title="__('candidate_portal.security.title')" :subtitle="__('candidate_portal.security.subtitle')">
            <form method="POST" action="{{ route('candidate.password.update', ['company' => $company->slug]) }}" class="grid gap-4 lg:grid-cols-3">
                @csrf
                <x-form-field :label="__('candidate_portal.security.current_password')" name="current_password">
                    <input type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>
                <x-form-field :label="__('candidate_portal.security.new_password')" name="password">
                    <input type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>
                <x-form-field :label="__('candidate_portal.security.confirm_password')" name="password_confirmation">
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>
                <div class="lg:col-span-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-600">{{ __('candidate_portal.security.helper') }}</p>
                    <button type="submit" class="rounded-xl bg-aura-700 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-aura-800">{{ __('candidate_portal.security.submit') }}</button>
                </div>
            </form>
        </x-glass-card>

        <x-glass-card :title="__('ui.nav.profile')" :subtitle="__('candidate_portal.workflow.items.security.description')">
            <div class="grid gap-3 md:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">{{ __('profile.full_name') }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ auth()->user()?->profile?->full_name ?? $candidate->full_name ?? auth()->user()?->email }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white/75 p-4">
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-500">Email</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ auth()->user()?->email }}</p>
                </article>
            </div>
        </x-glass-card>
    </div>
</x-shell-layout>
