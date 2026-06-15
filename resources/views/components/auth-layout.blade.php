@props([
    'title' => null,
    'containerWidth' => 'max-w-xl',
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
    <body class="aura-background min-h-full">
        <div class="flex min-h-screen flex-col">
            <main class="mx-auto flex w-full max-w-6xl flex-1 items-center justify-center px-4 py-10">
                <div class="w-full {{ $containerWidth }}">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
