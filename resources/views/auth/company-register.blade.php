@extends('layouts.public')

@section('title', __('platform.register_company').' | '.config('app.name'))

@section('content')
    <div class="mx-auto w-full max-w-6xl">
        <section class="relative overflow-hidden rounded-[30px] border border-slate-200/70 bg-white shadow-[0_28px_80px_-30px_rgba(15,23,42,0.45)]">
            <div class="pointer-events-none absolute -left-28 -bottom-20 h-80 w-80 rounded-full bg-success-300/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-24 top-8 h-72 w-72 rounded-full bg-primary-300/30 blur-3xl"></div>

            <div class="relative grid lg:grid-cols-[1fr_1.35fr]">
                <aside class="border-b border-slate-200 bg-gradient-to-br from-primary-900 via-primary-800 to-slate-900 px-7 py-8 text-slate-100 lg:border-b-0 lg:border-r lg:border-primary-700 lg:px-10 lg:py-12">
                    <div class="mx-auto h-14 w-52 overflow-hidden rounded-xl bg-white/95 p-1.5 shadow-sm">
                        <img
                            src="{{ asset('images/numa-lockup-public.png') }}"
                            alt="{{ config('app.name') }} logo"
                            class="h-full w-full object-contain object-center"
                        >
                    </div>

                    <h1 class="mt-8 text-3xl font-semibold leading-tight text-white">{{ __('platform.register_company') }}</h1>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300">{{ __('platform.register_company_subtitle') }}</p>

                    <div class="mt-8 space-y-3 text-sm">
                        <div class="rounded-2xl border border-success-300/25 bg-success-400/10 px-4 py-3 text-success-100">
                            Launch a branded hiring workspace in minutes.
                        </div>
                        <div class="rounded-2xl border border-primary-300/25 bg-primary-400/10 px-4 py-3 text-primary-100">
                            Invite your team and streamline every hiring stage.
                        </div>
                    </div>
                </aside>

                <div class="px-6 py-8 sm:px-8 lg:px-10 lg:py-12">
                    <form method="POST" action="{{ route('company.register.store') }}" class="space-y-5">
                        @csrf

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form-field :label="__('platform.company_name')" name="company_name" required>
                                <input type="text" name="company_name" value="{{ old('company_name') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                            </x-form-field>

                            <x-form-field :label="__('platform.company_slug')" name="company_slug" required :help="__('platform.company_slug_help')">
                                <input type="text" name="company_slug" value="{{ old('company_slug') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                            </x-form-field>
                        </div>

                        <x-form-field :label="__('profile.full_name')" name="full_name" required>
                            <input type="text" name="full_name" value="{{ old('full_name') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                        </x-form-field>

                        <x-form-field :label="__('auth.email')" name="email" required>
                            <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                        </x-form-field>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form-field :label="__('auth.password')" name="password" required>
                                <div class="relative">
                                    <input id="company-register-password" type="password" name="password" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 pr-12 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                                    <button
                                        type="button"
                                        data-password-toggle
                                        data-password-target="company-register-password"
                                        data-show-label="{{ __('auth.toggle_show') }}"
                                        data-hide-label="{{ __('auth.toggle_hide') }}"
                                        aria-label="{{ __('auth.toggle_show') }}"
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
                                        <span class="sr-only" data-password-toggle-label>{{ __('auth.toggle_show') }}</span>
                                    </button>
                                </div>
                            </x-form-field>

                            <x-form-field :label="__('auth.password_confirmation')" name="password_confirmation" required>
                                <div class="relative">
                                    <input id="company-register-password-confirmation" type="password" name="password_confirmation" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 pr-12 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                                    <button
                                        type="button"
                                        data-password-toggle
                                        data-password-target="company-register-password-confirmation"
                                        data-show-label="{{ __('auth.toggle_show') }}"
                                        data-hide-label="{{ __('auth.toggle_hide') }}"
                                        aria-label="{{ __('auth.toggle_show') }}"
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
                                        <span class="sr-only" data-password-toggle-label>{{ __('auth.toggle_show') }}</span>
                                    </button>
                                </div>
                            </x-form-field>
                        </div>

                        <x-form-field :label="__('profile.locale')" name="locale" required>
                            <select name="locale" data-placeholder="{{ __('platform.locale_placeholder') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                                <option value="en" @selected(old('locale', 'en') === 'en')>{{ __('ui.locale.en') }}</option>
                                <option value="fr" @selected(old('locale') === 'fr')>{{ __('ui.locale.fr') }}</option>
                            </select>
                        </x-form-field>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <label class="inline-flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="agreement" value="1" @checked(old('agreement')) class="mt-0.5 rounded border-primary-300 text-primary-600 focus:ring-primary-400">
                                <span>{{ __('platform.agreement_label') }}</span>
                            </label>
                            @error('agreement')
                                <p class="mt-2 text-xs text-danger-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <button type="submit" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-primary-700">
                                {{ __('platform.submit_registration') }}
                            </button>
                            <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 transition hover:text-slate-900">
                                {{ __('auth.login_action') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                const targetId = button.getAttribute('data-password-target') || '';
                if (targetId === '') {
                    return;
                }

                const input = document.getElementById(targetId);
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }

                const showLabel = button.getAttribute('data-show-label') || 'Show';
                const hideLabel = button.getAttribute('data-hide-label') || 'Hide';
                const eyeOpen = button.querySelector('[data-eye-open]');
                const eyeClosed = button.querySelector('[data-eye-closed]');
                const srLabel = button.querySelector('[data-password-toggle-label]');

                button.addEventListener('click', function () {
                    const shouldShow = input.type === 'password';
                    input.type = shouldShow ? 'text' : 'password';

                    if (eyeOpen instanceof SVGElement && eyeClosed instanceof SVGElement) {
                        eyeOpen.classList.toggle('hidden', shouldShow);
                        eyeClosed.classList.toggle('hidden', !shouldShow);
                    }

                    const nextLabel = shouldShow ? hideLabel : showLabel;
                    button.setAttribute('aria-label', nextLabel);
                    if (srLabel instanceof HTMLElement) {
                        srLabel.textContent = nextLabel;
                    }
                });
            });
        });
    </script>
@endsection
