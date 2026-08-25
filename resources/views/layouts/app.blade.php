<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- PWA: installable on the phone home screen, and the public half
             of the VAPID pair the browser needs to subscribe to push. -->
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <meta name="theme-color" content="#0b0f0d">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Futebas') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/icons/apple-touch-icon.png') }}">
        <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="font-sans antialiased bg-pitch-950 text-pitch-100"
        x-data="{ sidebarCollapsed: localStorage.getItem('fi_sidebar_collapsed') === 'true' }"
        x-init="$watch('sidebarCollapsed', value => localStorage.setItem('fi_sidebar_collapsed', value))"
    >
        @php
            // Counted once and shared with the sidebar, the mobile top bar
            // and the bottom sheet, all of which show the same badge.
            $unreadNotifications = Auth::user()->unreadNotifications()->count();
        @endphp

        <div class="min-h-screen lg:flex">
            @include('layouts.navigation')

            <div class="flex-1 flex flex-col min-w-0">
                <!-- Mobile top bar -->
                <header class="lg:hidden sticky top-0 z-30 flex items-center justify-between h-14 px-4 bg-pitch-900 border-b border-pitch-800">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-extrabold text-white">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-400">
                            <x-heroicon-o-trophy class="w-5 h-5" />
                        </span>
                        {{ config('app.name', 'Futebas') }}
                    </a>

                    <div class="flex items-center gap-1">
                    <a href="{{ route('notifications.index') }}" class="relative flex items-center justify-center w-9 h-9 rounded-full text-pitch-300 hover:text-white transition" aria-label="{{ __('Notificações') }}">
                        <x-heroicon-o-bell class="w-6 h-6" />
                        @if ($unreadNotifications)
                            <span class="absolute top-1 right-1 flex items-center justify-center min-w-4 h-4 px-1 rounded-full bg-emerald-500 text-[10px] font-bold text-white">{{ min($unreadNotifications, 99) }}</span>
                        @endif
                    </a>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="flex items-center justify-center w-9 h-9 rounded-full bg-pitch-800 text-sm font-bold text-pitch-200">
                                {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr(Auth::user()->name, 0, 1)) }}
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Minha Conta') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Sair') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                    </div>
                </header>

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-pitch-900 border-b border-pitch-800">
                        <div class="max-w-7xl mx-auto py-4 lg:py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="flex-1 pb-24 lg:pb-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @include('layouts.bottom-nav')
    </body>
</html>
