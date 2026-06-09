<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? __('errors.title_default') }} | {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="aura-background min-h-screen text-slate-900">
        <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
            <section class="w-full rounded-2xl border border-white/70 bg-white/72 p-8 shadow-aura backdrop-blur-2xl">
                <p class="text-xs uppercase tracking-[0.3em] text-aura-700/70">{{ __('errors.shared_tagline') }}</p>
                <h1 class="mt-4 text-4xl font-semibold text-slate-900">{{ $headline }}</h1>
                <p class="mt-4 text-base text-slate-700">{{ $message }}</p>
                <a href="{{ route('home') }}" class="mt-8 inline-block rounded-lg border border-aura-300/40 bg-white/80 px-4 py-2 text-sm text-slate-900 transition-weightless hover:bg-white">
                    {{ __('ui.nav.return_home') }}
                </a>
            </section>
        </main>
    </body>
</html>
