<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Futebas') }} — Seu futebol. Sua região. Sua partida.</title>
        <meta name="description" content="Conecte-se com jogadores e organizadores da sua região. Encontre quem falta para completar sua partida de futebol.">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @keyframes float {
                0%, 100% { transform: translateY(0) rotate(0deg); }
                50% { transform: translateY(-16px) rotate(6deg); }
            }
            @keyframes pulse-soft {
                0%, 100% { opacity: 0.4; transform: scale(1); }
                50% { opacity: 0.7; transform: scale(1.05); }
            }
            @keyframes slide-up {
                from { opacity: 0; transform: translateY(28px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes shimmer {
                0% { background-position: -200% center; }
                100% { background-position: 200% center; }
            }
            .animate-float { animation: float 5s ease-in-out infinite; }
            .animate-float-delay { animation: float 5s ease-in-out infinite; animation-delay: 1.8s; }
            .animate-pulse-soft { animation: pulse-soft 3s ease-in-out infinite; }
            .animate-slide-up { animation: slide-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) both; }
            .animate-slide-up-delay-1 { animation: slide-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.12s both; }
            .animate-slide-up-delay-2 { animation: slide-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.24s both; }
            .animate-slide-up-delay-3 { animation: slide-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.36s both; }
            .animate-slide-up-delay-4 { animation: slide-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.48s both; }

            /* Scroll reveal — estado inicial */
            .reveal {
                opacity: 0;
                transform: translateY(32px);
                transition:
                    opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
                    transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
                will-change: opacity, transform;
            }
            .reveal.from-left {
                transform: translateX(-28px);
            }
            .reveal.from-right {
                transform: translateX(28px);
            }
            .reveal.from-scale {
                transform: scale(0.92);
            }
            .reveal.is-visible {
                opacity: 1;
                transform: translateY(0) translateX(0) scale(1);
            }
            /* Stagger delays via CSS custom property */
            .reveal[data-delay="1"] { transition-delay: 0.08s; }
            .reveal[data-delay="2"] { transition-delay: 0.16s; }
            .reveal[data-delay="3"] { transition-delay: 0.24s; }
            .reveal[data-delay="4"] { transition-delay: 0.32s; }
            .reveal[data-delay="5"] { transition-delay: 0.40s; }
            .reveal[data-delay="6"] { transition-delay: 0.48s; }

            @media (prefers-reduced-motion: reduce) {
                .reveal,
                .animate-slide-up,
                .animate-slide-up-delay-1,
                .animate-slide-up-delay-2,
                .animate-slide-up-delay-3,
                .animate-slide-up-delay-4 {
                    opacity: 1 !important;
                    transform: none !important;
                    animation: none !important;
                    transition: none !important;
                }
            }

            .gradient-text {
                background: linear-gradient(135deg, #6ee7b7 0%, #34d399 40%, #a7f3d0 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            .mesh-bg {
                background-image:
                    radial-gradient(at 20% 30%, rgba(16, 185, 129, 0.25) 0px, transparent 50%),
                    radial-gradient(at 80% 20%, rgba(52, 211, 153, 0.2) 0px, transparent 45%),
                    radial-gradient(at 40% 80%, rgba(5, 150, 105, 0.3) 0px, transparent 50%),
                    radial-gradient(at 90% 70%, rgba(16, 185, 129, 0.15) 0px, transparent 40%);
            }
            .glass {
                background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.12);
            }
            .card-glow:hover {
                box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.3), 0 20px 40px -12px rgba(16, 185, 129, 0.25);
            }
            .btn-shine {
                position: relative;
                overflow: hidden;
            }
            .btn-shine::after {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.25) 50%, transparent 60%);
                background-size: 200% 100%;
                animation: shimmer 3s ease-in-out infinite;
                pointer-events: none;
            }
            .noise {
                background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
                opacity: 0.04;
            }
        </style>
    </head>
    <body class="antialiased text-gray-900 bg-white font-sans overflow-x-hidden">
        {{-- NAV --}}
        <header x-data="{ open: false }" class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100/80">
            <nav class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <a href="/" class="flex items-center gap-2.5 group">
                        <span class="font-extrabold text-lg tracking-tight text-gray-900">
                            <img src="{{ asset('images/logo_white.png') }}" alt="Logo" class="h-16 w-auto hidden sm:block">                            
                        </span>
                    </a>
                    <div class="hidden sm:flex items-center gap-1">
                        <a href="#como-funciona" class="px-3.5 py-2 text-sm font-medium text-gray-500 hover:text-emerald-700 rounded-lg hover:bg-emerald-50 transition">{{ __('Como funciona') }}</a>
                        <a href="#recursos" class="px-3.5 py-2 text-sm font-medium text-gray-500 hover:text-emerald-700 rounded-lg hover:bg-emerald-50 transition">{{ __('Recursos') }}</a>
                    </div>
                    <div class="hidden sm:flex items-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/25 transition">
                                {{ __('Ir para o Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-semibold text-gray-600 hover:text-emerald-700 px-3 py-2 transition">
                                {{ __('Entrar') }}
                            </a>
                            <a href="{{ route('register') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/25 hover:-translate-y-0.5 transition">
                                {{ __('Criar conta') }}
                            </a>
                        @endauth
                    </div>
                    <button @click="open = ! open" class="sm:hidden inline-flex items-center justify-center p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{ 'hidden': open }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{ 'hidden': ! open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div :class="{ 'block': open, 'hidden': ! open }" class="hidden sm:hidden pb-4 space-y-1">
                    <a href="#como-funciona" class="block px-3 py-2.5 text-sm font-medium text-gray-600 hover:text-emerald-700 rounded-lg hover:bg-emerald-50">{{ __('Como funciona') }}</a>
                    <a href="#recursos" class="block px-3 py-2.5 text-sm font-medium text-gray-600 hover:text-emerald-700 rounded-lg hover:bg-emerald-50">{{ __('Recursos') }}</a>
                    <div class="pt-3 flex flex-col gap-2">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600">
                                {{ __('Ir para o Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 border border-gray-200 hover:bg-gray-50">
                                {{ __('Entrar') }}
                            </a>
                            <a href="{{ route('register') }}" class="inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600">
                                {{ __('Criar conta') }}
                            </a>
                        @endauth
                    </div>
                </div>
            </nav>
        </header>

        {{-- HERO --}}
        <section class="relative overflow-hidden bg-gradient-to-br from-green-950 via-emerald-900 to-green-800 text-white">
            <div class="absolute inset-0 mesh-bg"></div>
            <div class="absolute inset-0 noise pointer-events-none"></div>
            {{-- Decorative circles --}}
            <div class="pointer-events-none absolute -top-32 -left-32 w-80 h-80 rounded-full border border-white/10 animate-pulse-soft"></div>
            <div class="pointer-events-none absolute -bottom-40 -right-20 w-[28rem] h-[28rem] rounded-full border border-white/8"></div>
            <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[40rem] h-[40rem] rounded-full border border-white/5"></div>
            {{-- Floating balls --}}
            <span class="pointer-events-none absolute top-20 right-[10%] text-7xl opacity-15 animate-float select-none hidden sm:block">⚽</span>
            <span class="pointer-events-none absolute bottom-24 left-[8%] text-5xl opacity-15 animate-float-delay select-none hidden sm:block">⚽</span>
            <span class="pointer-events-none absolute top-1/3 left-[15%] text-3xl opacity-10 animate-float select-none hidden lg:block">🧤</span>

            <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 sm:pt-28 pb-24 sm:pb-32 text-center">
                <div class="animate-slide-up inline-flex items-center gap-2 px-4 py-1.5 rounded-full glass text-sm font-semibold text-emerald-100 mb-6">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ __('Seu futebol. Sua região. Sua partida.') }}
                </div>
                <h1 class="animate-slide-up-delay-1 text-4xl sm:text-6xl lg:text-7xl font-extrabold leading-[1.1] tracking-tight">
                    {{ __('Faltou jogador?') }}
                    <br>
                    <span class="gradient-text">{{ __('A gente resolve.') }}</span>
                </h1>
                <p class="animate-slide-up-delay-2 mt-6 max-w-xl mx-auto text-lg sm:text-xl text-emerald-100/90 leading-relaxed">
                    {{ __('Conecte-se com jogadores e organizadores da sua região. Encontre quem falta para completar sua partida de futebol.') }}
                </p>
                <div class="animate-slide-up-delay-3 mt-10 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4">
                    <a href="{{ route('register') }}" class="btn-shine w-full sm:w-auto inline-flex justify-center items-center gap-2 px-8 py-4 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-emerald-400 text-green-950 shadow-xl shadow-emerald-400/30 hover:bg-emerald-300 hover:-translate-y-1 transition-all duration-300">
                        <span>⚽</span> {{ __('Quero Jogar') }}
                    </a>
                    <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-8 py-4 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-white/10 text-white border border-white/20 backdrop-blur-sm hover:bg-white/20 hover:-translate-y-1 transition-all duration-300">
                        <span></span> {{ __('Preciso de Jogadores') }}
                    </a>
                </div>
                @guest
                    <a href="{{ route('login') }}" class="mt-8 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-200/80 hover:text-white transition group">
                        {{ __('Já tenho uma conta') }}
                        <span class="group-hover:translate-x-1 transition-transform">&rarr;</span>
                    </a>
                @endguest
            </div>
        </section>

        {{-- SOS GOLEIRO — floating card --}}
        <section class="relative z-10 -mt-12 sm:-mt-14 px-4 sm:px-6">
            <div class="max-w-4xl mx-auto">
                <div class="reveal from-scale rounded-3xl bg-gradient-to-r from-rose-600 via-red-500 to-orange-500 text-white p-6 sm:p-8 shadow-2xl shadow-red-500/25 flex flex-col sm:flex-row items-center gap-5 sm:gap-8 hover:scale-[1.01] transition-transform duration-300">
                    <div class="flex items-center justify-center w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/20 backdrop-blur shrink-0">
                        <span class="text-4xl sm:text-5xl">🚨</span>
                    </div>
                    <div class="text-center sm:text-left flex-1">
                        <span class="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold uppercase tracking-widest">
                            {{ __('SOS Goleiro') }}
                        </span>
                        <h2 class="mt-2 text-xl sm:text-2xl font-extrabold leading-tight">{{ __('Faltou goleiro na última hora?') }}</h2>
                        <p class="mt-1.5 text-sm sm:text-base text-red-50/90">
                            {{ __('Encontre goleiros disponíveis na sua região e complete sua partida sem dor de cabeça.') }}
                        </p>
                    </div>
                    <a href="{{ route('register') }}" class="shrink-0 w-full sm:w-auto inline-flex justify-center items-center px-6 py-3.5 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-white text-red-600 shadow-lg hover:bg-red-50 hover:-translate-y-0.5 transition-all">
                        {{ __('Preciso de um Goleiro') }}
                    </a>
                </div>
            </div>
        </section>

        {{-- COMO FUNCIONA --}}
        <section id="como-funciona" class="bg-white py-20 sm:py-28">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="reveal text-center max-w-lg mx-auto">
                    <span class="inline-block text-xs font-bold uppercase tracking-widest text-emerald-600 mb-3">{{ __('Simples assim') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">{{ __('Como funciona') }}</h2>
                    <p class="mt-3 text-gray-500 leading-relaxed">{{ __('Três passos simples para nunca mais faltar jogador.') }}</p>
                </div>
                <div class="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-8 sm:gap-6 relative">
                    {{-- Connector line (desktop) --}}
                    <div class="hidden sm:block absolute top-10 left-[16%] right-[16%] h-px bg-gradient-to-r from-emerald-200 via-emerald-400 to-emerald-200"></div>
                    @foreach ([
                        ['01', 'Crie seu perfil', 'Informe sua posição, modalidade, nível e disponibilidade.'],
                        ['02', 'Encontre ou publique uma partida', 'Procure jogadores ou diga quais jogadores você precisa.'],
                        ['03', 'Complete seu time', 'Envie ou receba convites e combine a partida.'],
                    ] as $i => [$num, $title, $desc])
                        <div class="reveal relative text-center group" data-delay="{{ $i + 1 }}">
                            <div class="mx-auto flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-emerald-50 to-green-100 border border-emerald-100 shadow-sm group-hover:shadow-md group-hover:scale-105 transition-all duration-300">
                                <span class="text-2xl font-black text-emerald-600">{{ $num }}</span>
                            </div>
                            <h3 class="mt-5 text-base font-bold text-gray-900">{{ __($title) }}</h3>
                            <p class="mt-2 text-sm text-gray-500 leading-relaxed max-w-[220px] mx-auto">{{ __($desc) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- PARA QUEM É --}}
        <section class="bg-gray-50/80 py-20 sm:py-28">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="reveal text-center max-w-lg mx-auto">
                    <span class="inline-block text-xs font-bold uppercase tracking-widest text-emerald-600 mb-3">{{ __('Feito para você') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">{{ __('Para quem é?') }}</h2>
                </div>
                <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 gap-5 sm:gap-6">
                    <div class="reveal from-left group relative rounded-3xl bg-white p-8 sm:p-10 shadow-sm border border-gray-100 card-glow hover:-translate-y-1.5 transition-all duration-300 overflow-hidden" data-delay="1">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-emerald-100/60 to-transparent rounded-bl-full"></div>
                        <div class="relative">
                            <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-50 text-3xl mb-5 group-hover:scale-110 transition-transform">⚽</div>
                            <h3 class="text-xl font-extrabold text-gray-900 tracking-tight">{{ __('Jogadores') }}</h3>
                            <p class="mt-2.5 text-gray-500 leading-relaxed">{{ __('Encontre partidas na sua região e tenha mais oportunidades para jogar.') }}</p>
                            <a href="{{ route('register') }}" class="mt-7 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition">
                                {{ __('Quero Jogar') }} <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                    <div class="reveal from-right group relative rounded-3xl bg-white p-8 sm:p-10 shadow-sm border border-gray-100 card-glow hover:-translate-y-1.5 transition-all duration-300 overflow-hidden" data-delay="2">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-green-100/60 to-transparent rounded-bl-full"></div>
                        <div class="relative">
                            <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-green-50 text-3xl mb-5 group-hover:scale-110 transition-transform"></div>
                            <h3 class="text-xl font-extrabold text-gray-900 tracking-tight">{{ __('Organizadores') }}</h3>
                            <p class="mt-2.5 text-gray-500 leading-relaxed">{{ __('Encontre jogadores para completar seu time quando faltar alguém.') }}</p>
                            <a href="{{ route('register') }}" class="mt-7 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider text-white bg-green-700 hover:bg-green-800 shadow-md shadow-green-700/20 transition">
                                {{ __('Preciso de Jogadores') }} <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- PRINCIPAIS RECURSOS --}}
        <section id="recursos" class="bg-white py-20 sm:py-28">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="reveal text-center max-w-lg mx-auto">
                    <span class="inline-block text-xs font-bold uppercase tracking-widest text-emerald-600 mb-3">{{ __('Tudo em um lugar') }}</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 tracking-tight">{{ __('Principais recursos') }}</h2>
                </div>
                <div class="mt-14 grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                    @foreach ([
                        ['🔎', 'Procurar jogadores', 'Ache quem está disponível perto de você'],
                        ['🚨', 'SOS Goleiro', 'Goleiro de última hora em minutos'],
                        ['⚽', 'Criar partidas', 'Monte o jogo e convide o time'],
                        ['📍', 'Sua região', 'Só jogadores e partidas perto de você'],
                        ['🧤', 'Encontre goleiros', 'Filtro dedicado para o goleirão'],
                        ['📅', 'Suas partidas', 'Histórico e próximos jogos num clique'],
                    ] as $i => [$icon, $label, $hint])
                        <div class="reveal group rounded-2xl bg-gray-50/80 border border-gray-100 p-5 sm:p-6 text-center hover:bg-emerald-50/80 hover:border-emerald-200 hover:-translate-y-1 transition-all duration-300" data-delay="{{ $i + 1 }}">
                            <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-white shadow-sm text-2xl group-hover:scale-110 transition-transform">{{ $icon }}</span>
                            <p class="mt-3 text-sm font-bold text-gray-800">{{ __($label) }}</p>
                            <p class="mt-1 text-xs text-gray-400 leading-snug hidden sm:block">{{ __($hint) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- CTA FINAL --}}
        <section class="relative overflow-hidden bg-gradient-to-br from-green-950 via-emerald-900 to-green-800 py-20 sm:py-28 text-white text-center">
            <div class="absolute inset-0 mesh-bg"></div>
            <div class="absolute inset-0 noise pointer-events-none"></div>
            <div class="pointer-events-none absolute top-10 left-[10%] text-6xl opacity-10 animate-float">⚽</div>
            <div class="pointer-events-none absolute bottom-10 right-[12%] text-5xl opacity-10 animate-float-delay">⚽</div>
            <div class="relative max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="reveal text-3xl sm:text-5xl font-extrabold tracking-tight leading-tight">
                    {{ __('Seu próximo jogo') }}
                    <br>
                    <span class="gradient-text">{{ __('pode começar aqui.') }}</span>
                </h2>
                <p class="reveal mt-5 text-emerald-100/90 text-lg leading-relaxed" data-delay="1">{{ __('Entre para o Futebas e encontre quem falta para a sua partida.') }}</p>
                <div class="reveal mt-10 flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4" data-delay="2">
                    <a href="{{ route('register') }}" class="btn-shine w-full sm:w-auto inline-flex justify-center items-center px-8 py-4 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-emerald-400 text-green-950 shadow-xl shadow-emerald-400/30 hover:bg-emerald-300 hover:-translate-y-1 transition-all duration-300">
                        {{ __('Criar Minha Conta') }}
                    </a>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex justify-center items-center px-8 py-4 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-white/10 text-white border border-white/20 backdrop-blur-sm hover:bg-white/20 hover:-translate-y-1 transition-all duration-300">
                        {{ __('Entrar') }}
                    </a>
                </div>
            </div>
        </section>

        {{-- FOOTER --}}
        <footer class="bg-gray-950 text-gray-400">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-center">
                <a href="/" class="inline-flex items-center gap-2.5 group">
                    <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-green-700 text-white text-lg shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">⚽</span>
                    <span class="font-extrabold text-lg text-white tracking-tight">Futebas</span>
                </a>
                <p class="mt-3 text-sm text-gray-500">{{ __('Seu futebol. Sua região. Sua partida.') }}</p>
                <div class="mt-8 pt-6 border-t border-gray-800/80">
                    <p class="text-xs text-gray-600">&copy; {{ date('Y') }} Futebas. {{ __('Todos os direitos reservados.') }}</p>
                </div>
            </div>
        </footer>

        <script>
            (function () {
                const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const els = document.querySelectorAll('.reveal');

                if (prefersReduced || !('IntersectionObserver' in window)) {
                    els.forEach(el => el.classList.add('is-visible'));
                    return;
                }

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    root: null,
                    rootMargin: '0px 0px -8% 0px',
                    threshold: 0.12
                });

                els.forEach(el => observer.observe(el));
            })();
        </script>
    </body>
</html>