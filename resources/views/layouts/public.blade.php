<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name'))</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="aura-background min-h-full" data-select2-warning="{{ __('ui.toasts.select2_fallback') }}">
        <div class="min-h-screen" x-data="{ openMobileNav: false }">
            <header class="sticky top-0 z-40 border-b border-white/65 bg-white/85 backdrop-blur-2xl">
                <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('public.home') }}" class="flex items-center">
                        <img
                            src="{{ asset('images/numa-lockup-public.png') }}"
                            alt="{{ config('app.name') }} logo"
                            class="h-10 w-auto object-contain"
                            loading="eager"
                            decoding="async"
                        >
                    </a>

                    <nav class="hidden items-center gap-1 md:flex">
                        @php
                            $links = [
                                ['route' => 'public.home', 'active' => ['public.home'], 'label' => __('public_site.nav.home')],
                                ['route' => 'public.jobs.index', 'active' => ['public.jobs.*'], 'label' => __('public_site.nav.jobs')],
                                ['route' => 'public.about', 'active' => ['public.about'], 'label' => __('public_site.nav.about')],
                                ['route' => 'public.contact', 'active' => ['public.contact'], 'label' => __('public_site.nav.contact')],
                            ];
                        @endphp
                        @foreach($links as $link)
                            <a
                                href="{{ route($link['route']) }}"
                                @class([
                                    'rounded-lg px-3 py-2 text-sm font-medium transition-weightless',
                                    request()->routeIs(...$link['active']) ? 'bg-aura-100 text-aura-900' : 'text-slate-700 hover:bg-slate-100',
                                ])
                            >
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </nav>

                    <div class="hidden items-center gap-2 md:flex">
                        @guest
                            <a
                                href="{{ route('login') }}"
                                class="rounded-xl border border-aura-300/70 bg-white px-4 py-2 text-sm font-semibold text-aura-800 transition-weightless hover:bg-aura-50"
                                data-testid="public-header-login"
                            >
                                {{ __('public_site.nav.login') }}
                            </a>
                        @endguest
                        <a href="{{ route('public.entry.candidate') }}" class="rounded-xl border border-success-300/70 bg-success-50 px-4 py-2 text-sm font-semibold text-success-900 transition-weightless hover:bg-success-100/80">
                            {{ __('public_site.nav.entry_candidate') }}
                        </a>
                        @guest
                            <a href="{{ route('public.entry.company') }}" class="rounded-xl bg-aura-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-aura-700">
                                {{ __('public_site.nav.entry_company') }}
                            </a>
                        @else
                            <a href="{{ route('auth.company.dispatch') }}" class="rounded-xl bg-aura-600 px-4 py-2 text-sm font-semibold text-white transition-weightless hover:bg-aura-700">
                                {{ __('public_site.nav.workspace') }}
                            </a>
                        @endguest
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg border border-aura-300/50 bg-white px-3 py-2 text-sm font-semibold text-slate-800 md:hidden"
                        @click="openMobileNav = !openMobileNav"
                        :aria-expanded="openMobileNav ? 'true' : 'false'"
                    >
                        {{ __('public_site.nav.menu') }}
                    </button>
                </div>

                <div x-cloak x-show="openMobileNav" class="border-t border-slate-200 bg-white/95 px-4 py-3 md:hidden">
                    <div class="space-y-1">
                        <a
                            href="{{ route('public.home') }}"
                            @class([
                                'block rounded-lg px-3 py-2 text-sm font-medium',
                                request()->routeIs('public.home') ? 'bg-aura-100 text-aura-900' : 'text-slate-800 hover:bg-slate-100',
                            ])
                        >
                            {{ __('public_site.nav.home') }}
                        </a>
                        <a
                            href="{{ route('public.jobs.index') }}"
                            @class([
                                'block rounded-lg px-3 py-2 text-sm font-medium',
                                request()->routeIs('public.jobs.*') ? 'bg-aura-100 text-aura-900' : 'text-slate-800 hover:bg-slate-100',
                            ])
                        >
                            {{ __('public_site.nav.jobs') }}
                        </a>
                        <a
                            href="{{ route('public.about') }}"
                            @class([
                                'block rounded-lg px-3 py-2 text-sm font-medium',
                                request()->routeIs('public.about') ? 'bg-aura-100 text-aura-900' : 'text-slate-800 hover:bg-slate-100',
                            ])
                        >
                            {{ __('public_site.nav.about') }}
                        </a>
                        <a
                            href="{{ route('public.contact') }}"
                            @class([
                                'block rounded-lg px-3 py-2 text-sm font-medium',
                                request()->routeIs('public.contact') ? 'bg-aura-100 text-aura-900' : 'text-slate-800 hover:bg-slate-100',
                            ])
                        >
                            {{ __('public_site.nav.contact') }}
                        </a>
                        @guest
                            <a
                                href="{{ route('login') }}"
                                class="mt-2 block rounded-lg border border-aura-300/70 bg-white px-3 py-2 text-sm font-semibold text-aura-900"
                                data-testid="public-mobile-login"
                            >
                                {{ __('public_site.nav.login') }}
                            </a>
                            <a href="{{ route('public.entry.candidate') }}" class="mt-2 block rounded-lg border border-success-300/70 bg-success-50 px-3 py-2 text-sm font-semibold text-success-900">
                                {{ __('public_site.nav.entry_candidate') }}
                            </a>
                            <a href="{{ route('public.entry.company') }}" class="mt-2 block rounded-lg bg-aura-600 px-3 py-2 text-sm font-semibold text-white">
                                {{ __('public_site.nav.entry_company') }}
                            </a>
                        @else
                            <a href="{{ route('auth.company.dispatch') }}" class="mt-2 block rounded-lg bg-aura-600 px-3 py-2 text-sm font-semibold text-white">{{ __('public_site.nav.workspace') }}</a>
                        @endguest
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                @yield('content')
            </main>

            <footer class="border-t border-white/70 bg-white/80">
                <div class="mx-auto grid w-full max-w-7xl gap-6 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">{{ config('app.name') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('public_site.footer.about_text') }}</p>
                        <p class="mt-3 text-xs font-medium uppercase tracking-[0.18em] text-slate-500">{{ __('ui.brand.developed_by') }}</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">{{ __('public_site.footer.quick_links') }}</h3>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('public.home') }}" class="text-slate-700 transition-weightless hover:text-aura-700">{{ __('public_site.nav.home') }}</a></li>
                            <li><a href="{{ route('public.jobs.index') }}" class="text-slate-700 transition-weightless hover:text-aura-700">{{ __('public_site.nav.jobs') }}</a></li>
                            <li><a href="{{ route('public.about') }}" class="text-slate-700 transition-weightless hover:text-aura-700">{{ __('public_site.nav.about') }}</a></li>
                            <li><a href="{{ route('public.contact') }}" class="text-slate-700 transition-weightless hover:text-aura-700">{{ __('public_site.nav.contact') }}</a></li>
                            <li><a href="{{ route('login') }}" class="text-slate-700 transition-weightless hover:text-aura-700">{{ __('public_site.nav.login') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">{{ __('public_site.footer.contact_title') }}</h3>
                        <ul class="mt-3 space-y-2 text-sm text-slate-600">
                            <li>{{ __('public_site.footer.contact_email') }}</li>
                            <li>{{ __('public_site.footer.contact_phone') }}</li>
                            <li>{{ __('public_site.footer.contact_address') }}</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">{{ __('public_site.footer.follow_us') }}</h3>
                        <p class="mt-3 text-sm text-slate-600">{{ __('public_site.footer.social_placeholder') }}</p>
                    </div>
                </div>
                <div class="border-t border-slate-200/70 py-4 text-center text-xs text-slate-600">
                    {{ __('public_site.footer.copyright', ['year' => now()->year, 'app' => config('app.name')]) }}
                </div>
            </footer>
        </div>
    </body>
</html>
