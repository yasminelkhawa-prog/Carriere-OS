<x-career-layout :title="__('career.confirmation.title').' | '.$company->name" :company="$company">
    <x-glass-card :title="__('career.confirmation.title')" :subtitle="__('career.confirmation.subtitle')">
        <p class="text-sm text-slate-700">{{ __('career.confirmation.message') }}</p>
        <div class="mt-4 rounded-2xl border border-success-200/70 bg-success-50/70 p-4">
            <p class="text-sm font-semibold text-success-900">{{ __('career.confirmation.portal_title') }}</p>
            <p class="mt-1 text-sm text-slate-700">{{ __('career.confirmation.portal_message') }}</p>
            <ul class="mt-3 grid gap-2 text-xs text-slate-700 sm:grid-cols-2">
                <li>{{ __('career.confirmation.portal_steps.status') }}</li>
                <li>{{ __('career.confirmation.portal_steps.assessments') }}</li>
                <li>{{ __('career.confirmation.portal_steps.social_hub') }}</li>
                <li>{{ __('career.confirmation.portal_steps.feedback') }}</li>
            </ul>
        </div>
        @if($application)
            <p class="mt-3 text-sm text-slate-700">
                {{ __('career.confirmation.reference') }}
                <span class="font-semibold text-slate-900">{{ $application->id }}</span>
            </p>
        @endif

        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('career.index', ['company' => $company]) }}" class="rounded-xl border border-aura-300/50 bg-white/80 px-4 py-2 text-sm font-medium text-slate-900 transition-weightless hover:bg-white">
                {{ __('career.confirmation.back_to_jobs') }}
            </a>
            <a href="{{ route('candidate.portal', ['company' => $company->slug]) }}" class="rounded-xl border border-success-300/60 bg-success-50 px-4 py-2 text-sm font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                {{ __('career.confirmation.open_portal') }}
            </a>
            <a href="{{ route('login') }}" class="rounded-xl bg-success-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                {{ __('career.confirmation.login') }}
            </a>
        </div>
    </x-glass-card>
</x-career-layout>
