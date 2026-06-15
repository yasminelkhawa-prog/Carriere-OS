<x-shell-layout :title="__('candidate_portal.account.title').' | '.config('app.name')">
    <div class="space-y-6 pb-16">
        @if(session('status'))
            <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
        @endif
        @if(session('error'))
            <x-toast-alert type="warning">{{ session('error') }}</x-toast-alert>
        @endif

        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-aura-500 via-aura-600 to-indigo-700 p-5 shadow-md sm:px-6 sm:py-5">
            <!-- Decorative Background Elements -->
            <div class="absolute -right-6 -top-6 size-32 rounded-full bg-white/20 blur-2xl"></div>
            <div class="absolute -bottom-8 -left-8 size-32 rounded-full bg-indigo-900/30 blur-2xl"></div>
            
            <!-- Grid Pattern Overlay for Texture -->
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAwIDEwIEwgNDAgMTAgTSAxMCAwIEwgMTAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIgc3Ryb2tlLXdpZHRoPSIyIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmurlCtncmlkKSIvPjwvc3ZnPg==')] opacity-40"></div>

            <div class="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex-1">
                    <div class="mb-2 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-white backdrop-blur-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>{{ __('ui.nav.profile') }}</span>
                    </div>
                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">
                        {{ __('candidate_portal.account.title') }}
                    </h1>
                    <p class="mt-1 max-w-2xl text-sm text-aura-50">
                        {{ __('candidate_portal.account.subtitle') }}
                    </p>
                </div>
                

            </div>
        </div>

        <x-glass-card
            id="candidate-account-profile"
            :title="__('candidate_portal.account.profile.title')"
            :subtitle="__('candidate_portal.account.profile.subtitle')">
            <form method="POST" action="{{ route('candidate.profile.update', ['company' => $company->slug]) }}" class="grid gap-3 md:grid-cols-2">
                @csrf

                <x-form-field :label="__('candidate_portal.account.profile.full_name')" name="full_name" required>
                    <input type="text" name="full_name" value="{{ old('full_name', $candidate->full_name) }}" required class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.email')" name="email" required>
                    <input type="email" name="email" value="{{ old('email', $candidate->email) }}" required class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.phone')" name="phone">
                    <input type="text" name="phone" value="{{ old('phone', $candidate->phone) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.location')" name="location">
                    <input type="text" name="location" value="{{ old('location', $candidate->location) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.years_experience')" name="years_experience">
                    <input type="number" name="years_experience" min="0" max="60" value="{{ old('years_experience', $candidate->years_experience) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.last_company')" name="last_company">
                    <input type="text" name="last_company" value="{{ old('last_company', $candidate->last_company) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.main_skills')" name="main_skills" :help="__('candidate_portal.account.profile.main_skills_help')" class="md:col-span-2">
                    <textarea name="main_skills" rows="2" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">{{ old('main_skills', $candidate->main_skills) }}</textarea>
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.diploma_type')" name="diploma_type">
                    <input type="text" name="diploma_type" value="{{ old('diploma_type', $candidate->diploma_type) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.school_type')" name="school_type">
                    <select name="school_type" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <option value="">{{ __('candidate_portal.account.profile.school_type_placeholder') }}</option>
                        <option value="moroccan" @selected(old('school_type', $candidate->school_type) === 'moroccan')>{{ __('candidate_portal.account.profile.school_type_moroccan') }}</option>
                        <option value="foreign" @selected(old('school_type', $candidate->school_type) === 'foreign')>{{ __('candidate_portal.account.profile.school_type_foreign') }}</option>
                    </select>
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.school_name')" name="school_name">
                    <input type="text" name="school_name" value="{{ old('school_name', $candidate->school_name) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <x-form-field :label="__('candidate_portal.account.profile.school_country')" name="school_country">
                    <input type="text" name="school_country" value="{{ old('school_country', $candidate->school_country) }}" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                </x-form-field>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-xl bg-aura-700 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-aura-800">
                        {{ __('candidate_portal.account.profile.submit') }}
                    </button>
                </div>
            </form>
        </x-glass-card>

        <x-glass-card
            id="candidate-security"
            :title="__('candidate_portal.security.title')"
            :subtitle="__('candidate_portal.security.subtitle')">
            <form method="POST" action="{{ route('candidate.password.update', ['company' => $company->slug]) }}" class="grid gap-3 lg:grid-cols-3">
                @csrf

                <div>
                    <label for="candidate-current-password" class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                        {{ __('candidate_portal.security.current_password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <input id="candidate-current-password" type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 pr-12 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="candidate-current-password"
                            data-show-label="{{ __('candidate_portal.security.toggle_show') }}"
                            data-hide-label="{{ __('candidate_portal.security.toggle_hide') }}"
                            aria-label="{{ __('candidate_portal.security.toggle_show') }}"
                            class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                        >
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                <path d="m1 1 22 22" />
                            </svg>
                            <span class="sr-only" data-password-toggle-label>{{ __('candidate_portal.security.toggle_show') }}</span>
                        </button>
                    </div>
                    @error('current_password')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="candidate-new-password" class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                        {{ __('candidate_portal.security.new_password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <input id="candidate-new-password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 pr-12 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="candidate-new-password"
                            data-show-label="{{ __('candidate_portal.security.toggle_show') }}"
                            data-hide-label="{{ __('candidate_portal.security.toggle_hide') }}"
                            aria-label="{{ __('candidate_portal.security.toggle_show') }}"
                            class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                        >
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                <path d="m1 1 22 22" />
                            </svg>
                            <span class="sr-only" data-password-toggle-label>{{ __('candidate_portal.security.toggle_show') }}</span>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="candidate-confirm-password" class="block text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                        {{ __('candidate_portal.security.confirm_password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <input id="candidate-confirm-password" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-slate-200/70 bg-white px-3 py-2.5 pr-12 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="candidate-confirm-password"
                            data-show-label="{{ __('candidate_portal.security.toggle_show') }}"
                            data-hide-label="{{ __('candidate_portal.security.toggle_hide') }}"
                            aria-label="{{ __('candidate_portal.security.toggle_show') }}"
                            class="absolute inset-y-0 right-0 my-1 mr-1 inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-weightless hover:bg-slate-50 hover:text-slate-900"
                        >
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.66 21.66 0 0 1 5.06-6.94" />
                                <path d="M9.9 4.24A10.96 10.96 0 0 1 12 4c7 0 11 8 11 8a21.58 21.58 0 0 1-2.16 3.19" />
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" />
                                <path d="m1 1 22 22" />
                            </svg>
                            <span class="sr-only" data-password-toggle-label>{{ __('candidate_portal.security.toggle_show') }}</span>
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-600">{{ __('candidate_portal.security.helper') }}</p>
                    <button type="submit" class="rounded-xl bg-aura-700 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-aura-800">
                        {{ __('candidate_portal.security.submit') }}
                    </button>
                </div>
            </form>
        </x-glass-card>

        <x-glass-card
            id="candidate-account-notifications"
            :title="__('candidate_portal.account.notifications.title')"
            :subtitle="__('candidate_portal.account.notifications.subtitle')">
            <form method="POST" action="{{ route('candidate.notification-preferences.update', ['company' => $company->slug]) }}" class="space-y-3">
                @csrf

                @foreach($notificationPreferences as $key => $enabled)
                    <label class="flex items-center justify-between gap-3 rounded-2xl border border-white/80 bg-white/70 p-4 transition-weightless hover:bg-white">
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">{{ __('candidate_portal.account.notifications.types.'.$key.'.title') }}</span>
                            <span class="mt-1 block text-xs text-slate-600">{{ __('candidate_portal.account.notifications.types.'.$key.'.description') }}</span>
                        </span>
                        <input type="checkbox" name="preferences[{{ $key }}]" value="1" class="size-5 shrink-0 rounded border-aura-300 text-aura-600 focus:ring-aura-400" @checked($enabled)>
                    </label>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-aura-700 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-aura-800">
                        {{ __('candidate_portal.account.notifications.submit') }}
                    </button>
                </div>
            </form>
        </x-glass-card>

        <x-glass-card
            id="candidate-account-language"
            :title="__('candidate_portal.account.language.title')"
            :subtitle="__('candidate_portal.account.language.subtitle')">
            <form method="POST" action="{{ route('candidate.locale.update', ['company' => $company->slug]) }}" class="flex flex-wrap items-end gap-3">
                @csrf

                <x-form-field :label="__('candidate_portal.account.language.label')" name="locale">
                    <select name="locale" class="w-full rounded-xl border border-aura-200/40 bg-white/80 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-aura-400 focus:ring-aura-300">
                        <option value="fr" @selected($currentLocale === 'fr')>{{ __('candidate_portal.account.language.options.fr') }}</option>
                        <option value="en" @selected($currentLocale === 'en')>{{ __('candidate_portal.account.language.options.en') }}</option>
                    </select>
                </x-form-field>

                <button type="submit" class="rounded-xl bg-aura-700 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-aura-800">
                    {{ __('candidate_portal.account.language.submit') }}
                </button>
            </form>
        </x-glass-card>

        <x-glass-card
            id="candidate-account-danger"
            :title="__('candidate_portal.account.danger.title')"
            :subtitle="__('candidate_portal.account.danger.subtitle')">
            <form
                method="POST"
                action="{{ route('candidate.account.delete', ['company' => $company->slug]) }}"
                class="space-y-3"
                onsubmit="return confirm('{{ __('candidate_portal.account.danger.confirm') }}');"
            >
                @csrf

                <x-form-field :label="__('candidate_portal.account.danger.password_label')" name="current_password" required>
                    <input type="password" name="current_password" required autocomplete="current-password" class="w-full max-w-sm rounded-xl border border-danger-200/70 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-danger-400 focus:ring-danger-300">
                </x-form-field>

                <div>
                    <button type="submit" class="rounded-xl bg-danger-600 px-4 py-2.5 text-sm font-semibold text-white transition-weightless hover:bg-danger-700">
                        {{ __('candidate_portal.account.danger.submit') }}
                    </button>
                </div>
            </form>
        </x-glass-card>
    </div>
</x-shell-layout>
