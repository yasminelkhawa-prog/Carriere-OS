<x-shell-layout :title="__('referrals.create.title').' | '.config('app.name')">
    <section class="space-y-4">
        @if($requiresCompanySelection)
            <x-glass-card>
                <x-empty-state :title="__('referrals.company_required.title')" :message="__('referrals.company_required.message')" />
            </x-glass-card>
        @else
            <x-glass-card class="p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="panel-title text-3xl font-semibold tracking-tight text-slate-900">{{ __('referrals.create.heading') }}</h1>
                        <p class="mt-1 text-sm text-slate-600">{{ __('referrals.create.subheading') }}</p>
                    </div>
                    <a href="{{ route('referrals.index', array_filter(['company_id' => request('company_id')])) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                        {{ __('referrals.actions.view_list') }}
                    </a>
                </div>
            </x-glass-card>

            <x-glass-card class="p-5">
                <form method="POST" action="{{ route('referrals.store', array_filter(['company_id' => request('company_id')])) }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                    @csrf

                    <x-form-field :label="__('referrals.fields.candidate_email')" name="candidate_email" required>
                        <input type="email" name="candidate_email" value="{{ old('candidate_email') }}" required class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        @error('candidate_email')
                            <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                        @enderror
                    </x-form-field>

                    <x-form-field :label="__('referrals.fields.candidate_name')" name="candidate_name">
                        <input type="text" name="candidate_name" value="{{ old('candidate_name') }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        @error('candidate_name')
                            <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                        @enderror
                    </x-form-field>

                    <x-form-field :label="__('referrals.fields.linkedin_url')" name="candidate_linkedin_url">
                        <input type="url" name="candidate_linkedin_url" value="{{ old('candidate_linkedin_url') }}" placeholder="https://www.linkedin.com/in/..." class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        @error('candidate_linkedin_url')
                            <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                        @enderror
                    </x-form-field>

                    <x-form-field :label="__('referrals.fields.resume')" name="resume">
                        <input type="file" name="resume" accept=".pdf,application/pdf" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm">
                        <p class="mt-1 text-xs text-slate-500">{{ __('referrals.fields.resume_hint') }}</p>
                        @error('resume')
                            <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                        @enderror
                    </x-form-field>

                    <div class="md:col-span-2 flex items-center gap-2">
                        <button type="submit" class="rounded-xl bg-success-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-success-700">
                            {{ __('referrals.actions.submit') }}
                        </button>
                        <a href="{{ route('referrals.index', array_filter(['company_id' => request('company_id')])) }}" class="rounded-xl border border-aura-300/50 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-weightless hover:bg-slate-50">
                            {{ __('referrals.actions.cancel') }}
                        </a>
                    </div>
                </form>
            </x-glass-card>
        @endif
    </section>
</x-shell-layout>
