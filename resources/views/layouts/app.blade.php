<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover libera as áreas seguras (notch / barra de
             gestos), que a barra inferior e o topo usam abaixo. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · '.config('app.name', 'Futebas') : config('app.name', 'Futebas') }}</title>

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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="font-sans antialiased bg-pitch-950 text-pitch-100"
        x-data="{ sidebarCollapsed: localStorage.getItem('fi_sidebar_collapsed') === 'true' }"
        x-init="$watch('sidebarCollapsed', value => localStorage.setItem('fi_sidebar_collapsed', value))"
    >
        <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:px-4 focus:py-2.5 focus:rounded-xl focus:bg-emerald-400 focus:text-pitch-950 focus:font-bold focus:text-sm">
            {{ __('Pular para o conteúdo') }}
        </a>

        @php
            // Counted once and shared with the sidebar, the mobile top bar
            // and the bottom sheet, all of which show the same badge.
            $unreadNotifications = Auth::user()->unreadNotifications()->count();
        @endphp

        <div class="min-h-screen lg:flex">
            @include('layouts.navigation')

            <div class="flex-1 flex flex-col min-w-0">
                <!-- Mobile top bar -->
                <header
                    class="lg:hidden sticky top-0 z-30 bg-pitch-950/90 backdrop-blur-xl border-b border-pitch-800"
                    style="padding-top: env(safe-area-inset-top);"
                >
                    <div class="flex items-center justify-between h-14 px-3">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-1" aria-label="{{ config('app.name', 'Futebas') }}">
                            <img src="{{ asset('images/logo_white.png') }}" alt="{{ config('app.name', 'Futebas') }}" class="h-8 w-auto">
                        </a>

                        <div class="flex items-center gap-0.5">
                            <a href="{{ route('notifications.index') }}" class="relative tap-target flex items-center justify-center rounded-full text-pitch-300 hover:text-white hover:bg-pitch-800 transition" aria-label="{{ __('Notificações') }}">
                                <x-heroicon-o-bell class="w-6 h-6" />
                                @if ($unreadNotifications)
                                    <span class="absolute top-1.5 right-1.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-emerald-500 text-[10px] font-black text-white ring-2 ring-pitch-950">{{ min($unreadNotifications, 99) }}</span>
                                @endif
                            </a>

                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button class="tap-target flex items-center justify-center rounded-full" aria-label="{{ __('Minha Conta') }}">
                                        <x-avatar :user="Auth::user()" size="sm" ring="ring-2 ring-pitch-800" />
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <div class="px-4 py-3 border-b border-pitch-800">
                                        <p class="text-sm font-bold text-white truncate">{{ Auth::user()->name }}</p>
                                        <p class="text-xs text-pitch-400 truncate">{{ Auth::user()->email }}</p>
                                    </div>

                                    <x-dropdown-link :href="route('profile.edit')">
                                        {{ __('Minha Conta') }}
                                    </x-dropdown-link>

                                    <x-dropdown-link :href="route('subscription.index')">
                                        {{ __('Meu plano') }}
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
                <main id="conteudo" class="flex-1 pb-bottom-nav">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @include('layouts.bottom-nav')
    </body>
</html>
