@extends('layouts.public')

@section('title', __('auth.login_title').' | '.config('app.name'))

@section('content')
    <div class="mx-auto w-full max-w-5xl">
        <section class="relative overflow-hidden rounded-[30px] border border-slate-200/70 bg-white shadow-[0_28px_80px_-30px_rgba(15,23,42,0.45)]">
            <div class="pointer-events-none absolute -left-24 -top-24 h-64 w-64 rounded-full bg-success-400/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-24 bottom-0 h-72 w-72 rounded-full bg-primary-400/30 blur-3xl"></div>

            <div class="relative grid lg:grid-cols-[1.1fr_1fr]">
                <aside class="border-b border-slate-200 bg-gradient-to-br from-primary-900 via-primary-800 to-slate-900 px-7 py-8 text-slate-100 lg:border-b-0 lg:border-r lg:border-primary-700 lg:px-10 lg:py-12">
                    <div class="mx-auto h-14 w-52 overflow-hidden rounded-xl bg-white/95 p-1.5 shadow-sm">
                        <img
                            src="{{ asset('images/numa-lockup-public.png') }}"
                            alt="{{ config('app.name') }} logo"
                            class="h-full w-full object-contain object-center"
                        >
                    </div>

                    <p class="mt-8 text-xs uppercase tracking-[0.24em] text-success-300/90">{{ config('app.name') }}</p>
                    <h1 class="mt-4 text-3xl font-semibold leading-tight text-white">{{ __('auth.login_title') }}</h1>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-300">{{ __('auth.login_subtitle') }}</p>


                </aside>

                <div class="px-6 py-8 sm:px-8 lg:px-10 lg:py-12">
                    @if (session('status'))
                        <x-toast-alert type="success">{{ session('status') }}</x-toast-alert>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                        @csrf

                        <x-form-field :label="__('auth.email')" name="email" required>
                            <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                        </x-form-field>

                        <x-form-field :label="__('auth.password')" name="password" required>
                            <div class="relative">
                                <input id="login-password" type="password" name="password" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 pr-12 text-slate-900 shadow-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-200">
                                <button
                                    type="button"
                                    data-password-toggle
                                    data-password-target="login-password"
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

                        <div class="flex flex-wrap items-center gap-4 pt-1">
                            <button type="submit" class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-primary-700">
                                {{ __('auth.login_action') }}
                            </button>
                            <a href="{{ route('password.request') }}" class="text-sm font-medium text-slate-600 transition hover:text-slate-900">{{ __('auth.forgot_password') }}</a>
                        </div>

                        <div class="rounded-2xl border border-aura-200/60 bg-aura-50/70 px-4 py-3 text-sm text-slate-700">
                            <p>{{ __('auth.login_public_notice') }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('public.entry.company') }}" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-aura-800 transition-weightless hover:bg-aura-50">
                                    {{ __('auth.company_entry_action') }}
                                </a>
                                <a href="{{ route('public.entry.candidate') }}" class="rounded-lg border border-success-300/50 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                    {{ __('auth.candidate_entry_action') }}
                                </a>
                                <a href="{{ route('public.jobs.index') }}" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-aura-800 transition-weightless hover:bg-aura-50">
                                    {{ __('auth.browse_jobs_action') }}
                                </a>
                                <a href="{{ route('public.about') }}" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-aura-800 transition-weightless hover:bg-aura-50">
                                    {{ __('auth.about_us_action') }}
                                </a>
                                <a href="{{ route('public.contact') }}" class="rounded-lg border border-aura-300/50 bg-white px-3 py-1.5 text-xs font-semibold text-aura-800 transition-weightless hover:bg-aura-50">
                                    {{ __('auth.contact_us_action') }}
                                </a>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-primary-200/70 bg-primary-50/60 px-4 py-4 text-sm text-slate-700">
                            <p class="font-semibold text-slate-900">{{ __('auth.new_here_title') }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ __('auth.new_here_subtitle') }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="{{ route('register') }}" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-700">
                                    {{ __('auth.create_company_account_action') }}
                                </a>
                                <a href="{{ route('public.jobs.index') }}" class="rounded-lg border border-success-300/60 bg-success-50 px-3 py-1.5 text-xs font-semibold text-success-800 transition-weightless hover:bg-success-100/80">
                                    {{ __('auth.apply_as_candidate_action') }}
                                </a>
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-slate-600">{{ __('auth.candidate_access_hint') }}</p>
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
