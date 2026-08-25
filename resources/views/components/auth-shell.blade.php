@props(['maxWidth' => 'max-w-md'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Futebas') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-green-950 via-green-800 to-green-600 flex flex-col items-center justify-center px-4">
            {{-- Football-field decorative backdrop --}}
            <div class="pointer-events-none absolute inset-0 opacity-[0.08]" style="background-image: radial-gradient(circle, #fff 1.5px, transparent 1.5px); background-size: 28px 28px;"></div>
            <div class="pointer-events-none absolute -top-24 -left-24 w-72 h-72 rounded-full border-[3px] border-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 -right-16 w-96 h-96 rounded-full border-[3px] border-white/10"></div>
            <span class="pointer-events-none absolute top-10 right-6 text-7xl opacity-10 select-none"></span>
            <span class="pointer-events-none absolute bottom-10 left-6 text-6xl opacity-10 select-none"></span>

            <div class="relative w-full {{ $maxWidth }} flex flex-col items-center">
                <a href="/" class="flex flex-col items-center gap-2">
                    <span class="flex items-center gap-2 text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                        <span class="text-4xl sm:text-5xl"></span>
                        <img src="{{ asset('images/logo_white.png') }}" alt="Logo" class="h-32 w-auto">
                    </span>
                </a>

                <div class="w-full bg-white shadow-2xl shadow-green-950/40 rounded-3xl p-6 sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
