@props([
    'title' => null,
    'company' => null,
    'wide' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="aura-background min-h-full" data-select2-warning="{{ __('ui.toasts.select2_fallback') }}">
        <div class="mx-auto min-h-screen w-full {{ $wide ? 'max-w-[98%] xl:max-w-[1600px] 2xl:max-w-[1800px]' : 'max-w-7xl' }} px-4 py-6 sm:px-6 lg:px-8">
            <header class="mb-6 rounded-2xl border border-white/70 bg-white/70 px-5 py-4 shadow-[0_20px_55px_-36px_rgba(100,103,242,0.65)] backdrop-blur-2xl">
                @php
                    $activeLocale = app()->getLocale();
                    $candidateFaqTabActive = request()->routeIs('candidate.faq*');
                    $candidatePortalTabActive = request()->routeIs('candidate.*') && ! $candidateFaqTabActive;

                    $tabBaseClasses = 'portal-menu-item rounded-lg border px-3 py-1.5 text-xs font-medium transition-weightless';
                    $tabActiveClasses = 'border-aura-400/70 bg-aura-100/90 text-aura-900 shadow-sm';
                    $tabInactiveClasses = 'border-aura-200/50 bg-white/80 text-slate-800 hover:bg-white';
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.34em] text-aura-700/85">{{ __('career.brand.eyebrow') }}</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $company?->name ?? config('app.name') }}</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('locale.switch', ['locale' => 'en']) }}" data-portal-menu-item @class([$tabBaseClasses, $activeLocale === 'en' ? $tabActiveClasses : $tabInactiveClasses])>{{ __('ui.locale.en') }}</a>
                        <a href="{{ route('locale.switch', ['locale' => 'fr']) }}" data-portal-menu-item @class([$tabBaseClasses, $activeLocale === 'fr' ? $tabActiveClasses : $tabInactiveClasses])>{{ __('ui.locale.fr') }}</a>
                        @auth
                            @if($company)
                                <a href="{{ route('candidate.portal', ['company' => $company->slug]) }}" data-portal-menu-item @class([$tabBaseClasses, $candidatePortalTabActive ? $tabActiveClasses : $tabInactiveClasses])>{{ __('candidate_portal.applications.title') }}</a>
                                <a href="{{ route('candidate.faq', ['company' => $company->slug]) }}" data-portal-menu-item @class([$tabBaseClasses, $candidateFaqTabActive ? $tabActiveClasses : $tabInactiveClasses])>{{ __('candidate_portal.faq.title') }}</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" data-portal-menu-item @class([$tabBaseClasses, $tabInactiveClasses])>
                                    {{ __('auth.logout') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" data-portal-menu-item @class([$tabBaseClasses, $tabInactiveClasses])>{{ __('auth.login_title') }}</a>
                        @endauth
                    </div>
                </div>
            </header>
            {{ $slot }}
        </div>
    </body>
</html>
