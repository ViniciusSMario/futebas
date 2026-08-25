<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-pitch-950 text-pitch-100 min-h-screen">
        <header class="flex items-center h-14 px-4 sm:px-6 border-b border-pitch-800">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-extrabold text-white">
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-400">
                    <x-heroicon-o-trophy class="w-5 h-5" />
                </span>
                {{ config('app.name', 'Futebas') }}
            </a>
        </header>

        <main class="py-8 sm:py-12">
            {{ $slot }}
        </main>
    </body>
</html>
