<x-career-layout :title="__('career.confirmation.title').' | '.$company->name" :company="$company">
    <x-glass-card :title="__('career.confirmation.title')" :subtitle="__('career.confirmation.subtitle')">
        <p class="text-sm text-slate-700">{{ __('career.confirmation.message') }}</p>

        @if($application)
            <p class="mt-3 text-sm text-slate-700">
                {{ __('career.confirmation.reference') }}
                <span class="font-semibold text-slate-900">{{ $application->id }}</span>
            </p>
        @endif

        <div class="mt-4 rounded-2xl border border-success-200/70 bg-success-50/70 p-4">
            <p class="text-sm font-semibold text-success-900">{{ __('career.confirmation.portal_title') }}</p>
            <p class="mt-1 text-sm text-slate-700">{{ __('career.confirmation.portal_message') }}</p>
            <ul class="mt-3 grid gap-2 text-xs text-slate-700 sm:grid-cols-2">
                <li class="flex items-start gap-1.5"><span class="mt-0.5 text-success-600">✓</span>{{ __('career.confirmation.portal_steps.status') }}</li>
                <li class="flex items-start gap-1.5"><span class="mt-0.5 text-success-600">✓</span>{{ __('career.confirmation.portal_steps.assessments') }}</li>
                <li class="flex items-start gap-1.5"><span class="mt-0.5 text-success-600">✓</span>{{ __('career.confirmation.portal_steps.social_hub') }}</li>
                <li class="flex items-start gap-1.5"><span class="mt-0.5 text-success-600">✓</span>{{ __('career.confirmation.portal_steps.feedback') }}</li>
            </ul>
        </div>

        {{-- Save data prompt --}}
        @if($showSavePrompt ?? false)
            <div class="mt-6 rounded-2xl border border-aura-200/60 bg-gradient-to-br from-aura-50 to-sky-50 p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-aura-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-aura-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ __('career.save_data.title') }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ __('career.save_data.description') }}</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <form method="POST" action="{{ route('career.apply.save-data', ['company' => $company, 'job' => $job]) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="save_data" value="1">
                        <button type="submit" class="w-full rounded-xl bg-aura-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-aura-700">
                            {{ __('career.save_data.yes') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('career.apply.save-data', ['company' => $company, 'job' => $job]) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="save_data" value="0">
                        <button type="submit" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-weightless hover:bg-slate-50">
                            {{ __('career.save_data.no') }}
                        </button>
                    </form>
                </div>
            </div>
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
