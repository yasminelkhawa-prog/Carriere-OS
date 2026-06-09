@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name', 'numa') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="aura-background min-h-full">
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }">
            <aside class="hidden w-72 border-r border-white/70 bg-white/45 backdrop-blur-2xl lg:flex lg:flex-col">
                <div class="border-b border-white/70 px-6 py-5">
                    <p class="text-xs uppercase tracking-[0.35em] text-aura-700/80">{{ __('ui.brand.tagline') }}</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ config('app.name') }}</h1>
                    <p class="mt-2 text-sm text-slate-700">{{ __('ui.brand.subtitle') }}</p>
                </div>
                <nav class="flex-1 space-y-1 px-4 py-5 text-sm">
                    <a
                        href="{{ route('home') }}"
                        data-portal-menu-item
                        @class([
                            'portal-menu-item block rounded-lg px-3 py-2 transition-weightless',
                            request()->routeIs('home') ? 'bg-aura-100 text-aura-900 font-semibold' : 'text-slate-800 hover:bg-aura-100/70',
                        ])
                    >
                        {{ __('ui.nav.home') }}
                    </a>
                    <a
                        href="{{ route('status') }}"
                        data-portal-menu-item
                        @class([
                            'portal-menu-item block rounded-lg px-3 py-2 transition-weightless',
                            request()->routeIs('status') ? 'bg-aura-100 text-aura-900 font-semibold' : 'text-slate-800 hover:bg-aura-100/70',
                        ])
                    >
                        {{ __('ui.nav.status') }}
                    </a>
                </nav>
            </aside>

            <div class="flex min-h-screen flex-1 flex-col">
                <header class="sticky top-0 z-20 border-b border-white/70 bg-white/55 px-4 py-4 backdrop-blur-2xl lg:hidden">
                    <button type="button" class="rounded-xl border border-aura-300/40 bg-white/70 px-3 py-2 text-sm text-slate-900 transition-weightless hover:bg-white" @click="sidebarOpen = true">
                        {{ __('ui.nav.menu') }}
                    </button>
                </header>

                <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 bg-aura-900/20 lg:hidden transition-weightless" @click="sidebarOpen = false"></div>
                <aside x-cloak x-show="sidebarOpen" class="fixed inset-y-0 left-0 z-50 w-72 border-r border-white/70 bg-white/80 px-5 py-6 backdrop-blur-2xl lg:hidden">
                    <button type="button" class="mb-5 rounded-xl border border-aura-300/40 bg-white/80 px-3 py-1.5 text-xs uppercase tracking-wider text-slate-900 transition-weightless hover:bg-white" @click="sidebarOpen = false">
                        {{ __('ui.nav.close') }}
                    </button>
                    <p class="text-xs uppercase tracking-[0.35em] text-aura-700/80">{{ __('ui.brand.tagline') }}</p>
                    <h1 class="mt-2 text-xl font-semibold text-slate-900">{{ config('app.name') }}</h1>
                    <nav class="mt-6 space-y-1 text-sm">
                        <a
                            href="{{ route('home') }}"
                            data-portal-menu-item
                            @class([
                                'portal-menu-item block rounded-lg px-3 py-2 transition-weightless',
                                request()->routeIs('home') ? 'bg-aura-100 text-aura-900 font-semibold' : 'text-slate-800 hover:bg-aura-100/70',
                            ])
                            @click="sidebarOpen = false"
                        >
                            {{ __('ui.nav.home') }}
                        </a>
                        <a
                            href="{{ route('status') }}"
                            data-portal-menu-item
                            @class([
                                'portal-menu-item block rounded-lg px-3 py-2 transition-weightless',
                                request()->routeIs('status') ? 'bg-aura-100 text-aura-900 font-semibold' : 'text-slate-800 hover:bg-aura-100/70',
                            ])
                            @click="sidebarOpen = false"
                        >
                            {{ __('ui.nav.status') }}
                        </a>
                    </nav>
                </aside>

                <main class="flex-1 overflow-y-auto px-4 py-8 sm:px-8">
                    <div class="mx-auto w-full max-w-5xl">
                        {{ $slot }}
                    </div>
                </main>

                <footer class="border-t border-white/70 bg-white/70 px-4 py-3 text-center text-xs text-slate-600 sm:px-8">
                    <p class="font-medium uppercase tracking-[0.18em] text-slate-500">{{ __('ui.brand.developed_by') }}</p>
                </footer>
            </div>
        </div>
    </body>
</html>

