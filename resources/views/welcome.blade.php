<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ config('app.name', 'Futebas') }} - {{ __('Seu futebol. Sua região. Sua partida.') }}</title>
    <meta name="description" content="{{ __('Conecte-se com jogadores e organizadores da sua região. Encontre quem falta para completar sua partida de futebol.') }}">
    <meta name="theme-color" content="#080f0c">

    {{-- Open Graph: este link vai circular no WhatsApp e no Instagram, então
         a prévia faz parte da landing. Coloque o arquivo em
         public/images/landing/og-image.jpg (1200x630). --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'Futebas') }}">
    <meta property="og:title" content="{{ config('app.name', 'Futebas') }} - {{ __('Faltou jogador? A gente resolve.') }}">
    <meta property="og:description" content="{{ __('Conecte-se com jogadores e organizadores da sua região. Encontre quem falta para completar sua partida de futebol.') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('images/landing/og-image.jpg') }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-pitch-950 text-pitch-100 overflow-x-hidden">

@php
    // Declarado uma vez e reaproveitado no menu desktop, no menu mobile e no
    // rodapé, para os três nunca saírem de sincronia.
    $navLinks = [
        ['#como-funciona', __('Como funciona')],
        ['#recursos', __('Recursos')],
        ['#sos', __('SOS Goleiro')],
        ['#duvidas', __('Dúvidas')],
    ];
@endphp

{{-- ==================== NAVEGAÇÃO ==================== --}}
<header
    x-data="{ open: false, scrolled: false }"
    @scroll.window="scrolled = window.scrollY > 12"
    class="fixed top-0 inset-x-0 z-50 transition-colors duration-300"
    :class="scrolled || open ? 'bg-pitch-950/90 backdrop-blur-xl border-b border-white/10' : 'bg-transparent'"
>
    <nav class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8" aria-label="{{ __('Navegação principal') }}">
        <div class="flex items-center justify-between h-16 sm:h-20">
            <a href="/" class="flex items-center shrink-0" aria-label="{{ config('app.name', 'Futebas') }}">
                <img src="{{ asset('images/logo_white.png') }}" alt="{{ config('app.name', 'Futebas') }}" class="h-9 sm:h-11 w-auto">
            </a>

            <div class="hidden lg:flex items-center gap-1">
                @foreach ($navLinks as [$href, $label])
                    <a href="{{ $href }}" class="px-3.5 py-2 text-sm font-semibold text-pitch-300 hover:text-white rounded-lg hover:bg-white/5 transition">{{ $label }}</a>
                @endforeach
            </div>

            <div class="hidden sm:flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-bold text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/20 transition">
                        {{ __('Ir para o app') }}
                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                    </a>
                @else
                    <a href="{{ route('login') }}" class="px-4 py-2.5 text-sm font-semibold text-pitch-200 hover:text-white transition">
                        {{ __('Entrar') }}
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl text-sm font-bold text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/20 hover:-translate-y-0.5 transition">
                        {{ __('Criar conta grátis') }}
                    </a>
                @endauth
            </div>

            <button
                @click="open = ! open"
                :aria-expanded="open"
                aria-controls="menu-mobile"
                class="sm:hidden tap-target inline-flex items-center justify-center rounded-xl text-pitch-200 hover:bg-white/10 transition"
            >
                <span class="sr-only">{{ __('Abrir menu') }}</span>
                <x-heroicon-o-bars-3 x-show="! open" class="w-6 h-6" />
                <x-heroicon-o-x-mark x-show="open" x-cloak class="w-6 h-6" />
            </button>
        </div>

        {{-- Menu mobile --}}
        <div
            id="menu-mobile"
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="sm:hidden pb-5 space-y-1"
        >
            @foreach ($navLinks as [$href, $label])
                <a href="{{ $href }}" @click="open = false" class="flex items-center min-h-[44px] px-3 text-base font-semibold text-pitch-200 hover:text-white rounded-xl hover:bg-white/5">{{ $label }}</a>
            @endforeach

            <div class="pt-3 flex flex-col gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex justify-center items-center min-h-[48px] px-4 rounded-xl text-sm font-bold text-pitch-950 bg-emerald-400">
                        {{ __('Ir para o app') }}
                    </a>
                @else
                    <a href="{{ route('register') }}" class="inline-flex justify-center items-center min-h-[48px] px-4 rounded-xl text-sm font-bold text-pitch-950 bg-emerald-400">
                        {{ __('Criar conta grátis') }}
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex justify-center items-center min-h-[48px] px-4 rounded-xl text-sm font-semibold text-white border border-white/15 hover:bg-white/5">
                        {{ __('Já tenho conta') }}
                    </a>
                @endauth
            </div>
        </div>
    </nav>
</header>

<main>
    {{-- ==================== HERO ==================== --}}
    <section class="relative overflow-hidden pt-28 sm:pt-32 lg:pt-40 pb-20 sm:pb-24">
        <div class="absolute inset-0 mesh-pitch" aria-hidden="true"></div>
        <div class="absolute inset-0 field-lines opacity-40" aria-hidden="true"></div>
        <div class="absolute inset-0 noise pointer-events-none" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -top-40 -left-32 w-[26rem] h-[26rem] rounded-full bg-emerald-500/10 blur-3xl animate-pulse-soft" aria-hidden="true"></div>
        <div class="pointer-events-none absolute top-1/3 -right-40 w-[30rem] h-[30rem] rounded-full bg-emerald-400/10 blur-3xl" aria-hidden="true"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-[1.05fr_1fr] gap-14 lg:gap-14 items-center">

                {{-- Coluna de texto --}}
                <div class="text-center lg:text-left">
                    <span class="animate-slide-up inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full glass text-xs sm:text-sm font-semibold text-emerald-200">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                        </span>
                        {{ __('Seu futebol. Sua região. Sua partida.') }}
                    </span>

                    <h1 class="animate-slide-up mt-5 text-[2.6rem] leading-[1.05] xs:text-5xl sm:text-6xl lg:text-[4.25rem] font-black tracking-tight text-white" style="animation-delay: 0.1s">
                        {{ __('Faltou jogador?') }}
                        <span class="block gradient-text pb-5">{{ __('A gente resolve.') }}</span>
                    </h1>

                    <p class="animate-slide-up mt-1 text-base sm:text-lg text-pitch-200 leading-relaxed max-w-lg mx-auto lg:mx-0" style="animation-delay: 0.2s">
                        {{ __('Organize a pelada, chame o time e complete as vagas com jogadores da sua região, tudo em um lugar só.') }}
                    </p>

                    <div class="animate-slide-up mt-8 flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-3" style="animation-delay: 0.3s">
                        <a href="{{ route('register') }}" class="btn-shine inline-flex justify-center items-center gap-2 min-h-[52px] px-7 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-emerald-400 text-pitch-950 shadow-xl shadow-emerald-500/25 hover:bg-emerald-300 hover:-translate-y-0.5 active:translate-y-0 transition">
                            <x-heroicon-o-bolt class="w-5 h-5" />
                            {{ __('Quero jogar') }}
                        </a>
                        <a href="{{ route('register') }}" class="inline-flex justify-center items-center gap-2 min-h-[52px] px-7 rounded-2xl font-extrabold text-sm uppercase tracking-wider glass text-white hover:bg-white/15 hover:-translate-y-0.5 active:translate-y-0 transition">
                            <x-heroicon-o-user-group class="w-5 h-5" />
                            {{ __('Preciso de jogadores') }}
                        </a>
                    </div>

                    {{-- Prova social --}}
                    <div class="animate-slide-up mt-8 flex items-center justify-center lg:justify-start gap-3" style="animation-delay: 0.4s">
                        <div class="flex -space-x-2.5">
                            @foreach (range(1, 4) as $i)
                                <x-img-placeholder
                                    ratio="1/1"
                                    :label="__('Foto')"
                                    :src="asset('images/landing/jogador-'.$i.'.jpg')"
                                    icon="heroicon-o-user"
                                    rounded="rounded-full"
                                    class="w-10 h-10 ring-2 ring-pitch-950"
                                />
                            @endforeach
                        </div>
                        <div class="text-left">
                            <div class="flex items-center gap-0.5 text-emerald-400">
                                @foreach (range(1, 5) as $star)
                                    <x-heroicon-s-star class="w-3.5 h-3.5" />
                                @endforeach
                            </div>
                            <p class="text-xs sm:text-sm text-pitch-300 font-medium">{{ __('Peladeiros já usam o Futebas na região') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Coluna visual: foto principal + card flutuante --}}
                <div class="animate-slide-up relative" style="animation-delay: 0.35s">
                    <div class="relative rounded-4xl overflow-hidden shadow-2xl shadow-black/60 ring-1 ring-white/10">
                        <x-img-placeholder
                            ratio="4/5"
                            tone="accent"
                            rounded="rounded-none"
                            :label="__('Foto principal - jogadores em ação')"
                            src="{{ asset('images/landing/hero.png') }}"
                            size="1200x1500"
                            :note="__('Vertical, alto contraste, rosto visível. É a primeira imagem que o público vê.')"
                            :eager="true"
                            class="w-full"
                        />
                        {{-- Escurece a base da foto para o card flutuante ter contraste. --}}
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-pitch-950 via-pitch-950/70 to-transparent"></div>
                    </div>

                    {{-- Card "partida" - mostra o produto sem depender de screenshot. --}}
                    <div class="absolute -bottom-6 left-3 right-3 sm:left-8 sm:right-8 lg:-left-8 lg:right-6 rounded-2xl bg-pitch-900/95 backdrop-blur border border-white/10 shadow-2xl shadow-black/60 p-4">
                        <div class="flex items-center gap-3">
                            <span class="flex flex-col items-center justify-center w-12 h-12 rounded-xl bg-emerald-500/15 text-emerald-400 shrink-0">
                                <span class="text-[10px] font-bold uppercase leading-none">{{ __('Qui') }}</span>
                                <span class="text-lg font-black leading-none">19</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-extrabold text-white truncate">{{ __('Pelada de quinta - Society') }}</p>
                                <p class="text-xs text-pitch-400 flex items-center gap-1 truncate">
                                    <x-heroicon-o-map-pin class="w-3.5 h-3.5 shrink-0" />
                                    {{ __('Arena Central · 20:00') }}
                                </p>
                            </div>
                            <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 text-[11px] font-bold">
                                {{ __('2 vagas') }}
                            </span>
                        </div>
                        <div class="mt-3 h-1.5 rounded-full bg-pitch-800 overflow-hidden">
                            <div class="h-full w-5/6 rounded-full bg-gradient-to-r from-emerald-500 to-emerald-300"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== FAIXA DE NÚMEROS ==================== --}}
    <section class="relative border-y border-white/10 bg-pitch-900/60">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8 text-center">
                @foreach ([
                    ['heroicon-o-user-group', '+2.000', 'Jogadores cadastrados'],
                    ['heroicon-o-trophy', '+500', 'Peladas organizadas'],
                    ['heroicon-o-clock', '15 min', 'Para achar um goleiro'],
                    ['heroicon-o-map-pin', '30+', 'Cidades do interior'],
                ] as $i => [$icon, $value, $label])
                    <div class="reveal" data-delay="{{ $i + 1 }}">
                        <x-dynamic-component :component="$icon" class="w-5 h-5 mx-auto text-emerald-400" />
                        <dt class="sr-only">{{ __($label) }}</dt>
                        <dd class="mt-2 text-2xl sm:text-3xl font-black text-white tabular-nums">{{ $value }}</dd>
                        <p class="mt-0.5 text-xs sm:text-sm text-pitch-400 font-medium">{{ __($label) }}</p>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ==================== SOS GOLEIRO ==================== --}}
    <section id="sos" class="py-16 sm:py-24 scroll-mt-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="reveal from-scale relative overflow-hidden rounded-4xl bg-gradient-to-br from-red-600 via-red-500 to-orange-500 shadow-2xl shadow-red-900/40">
                <div class="absolute inset-0 noise pointer-events-none" aria-hidden="true"></div>

                <div class="relative grid lg:grid-cols-[1fr_0.85fr] items-center">
                    <div class="p-6 sm:p-10 lg:p-14 text-center lg:text-left">
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1.5 text-[11px] font-black uppercase tracking-widest text-white">
                            <x-heroicon-o-megaphone class="w-4 h-4" />
                            {{ __('SOS Goleiro') }}
                        </span>

                        <h2 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-white leading-[1.1]">
                            {{ __('Faltou goleiro') }}<br class="hidden sm:block">
                            {{ __('em cima da hora?') }}
                        </h2>

                        <p class="mt-4 text-red-50 text-base sm:text-lg leading-relaxed max-w-md mx-auto lg:mx-0">
                            {{ __('Publique a chamada, avise todos os goleiros da região e escolha o melhor comparando valor, cidade e avaliação.') }}
                        </p>

                        <ul class="mt-6 space-y-2.5 text-left max-w-sm mx-auto lg:mx-0">
                            @foreach ([
                                'Todos os goleiros da região são avisados na hora',
                                'Cada um responde com o próprio valor',
                                'Você aceita apenas um, os outros são recusados sozinhos',
                            ] as $item)
                                <li class="flex items-start gap-2.5 text-sm sm:text-base text-white font-medium">
                                    <x-heroicon-s-check-circle class="w-5 h-5 shrink-0 text-white/90 mt-0.5" />
                                    {{ __($item) }}
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('register') }}" class="mt-8 inline-flex justify-center items-center gap-2 min-h-[52px] w-full sm:w-auto px-8 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-white text-red-600 shadow-xl shadow-red-900/30 hover:bg-red-50 hover:-translate-y-0.5 transition">
                            {{ __('Preciso de um goleiro') }}
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </a>
                    </div>

                    <div class="px-6 pb-6 sm:px-10 sm:pb-10 lg:p-10">
                        <x-img-placeholder
                            ratio="3/4"
                            tone="light"
                            :label="__('Foto de goleiro - defesa / luvas')"
                            src="images/landing/sos-goleiro.jpg"
                            size="900x1200"
                            :note="__('Vertical, momento de defesa. Fica sobre fundo vermelho: prefira imagem escura e contrastada.')"
                            icon="heroicon-o-hand-raised"
                            rounded="rounded-3xl"
                            class="w-full max-w-sm mx-auto shadow-2xl shadow-red-950/40"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== COMO FUNCIONA ==================== --}}
    <section id="como-funciona" class="py-16 sm:py-24 scroll-mt-20 bg-pitch-900/40 border-y border-white/5">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="reveal text-center max-w-xl mx-auto">
                <span class="inline-block text-xs font-black uppercase tracking-widest text-emerald-400">{{ __('Simples assim') }}</span>
                <h2 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-white">{{ __('Como funciona') }}</h2>
                <p class="mt-4 text-pitch-300 leading-relaxed">{{ __('Três passos para nunca mais cancelar uma pelada por falta de jogador.') }}</p>
            </div>

            <ol class="mt-12 sm:mt-16 grid gap-6 sm:gap-8 sm:grid-cols-3">
                @foreach ([
                    ['01', 'Crie seu perfil', 'Posição, modalidade, nível, valor e os dias em que você pode jogar.', 'images/landing/passo-1.jpg', 'heroicon-o-user-plus'],
                    ['02', 'Monte ou entre na partida', 'Publique a pelada ou procure jogos abertos perto de você.', 'images/landing/passo-2.jpg', 'heroicon-o-calendar-days'],
                    ['03', 'Complete o time', 'Convide, sorteie os times, controle os pagamentos e avalie no fim.', 'images/landing/passo-3.jpg', 'heroicon-o-trophy'],
                ] as $i => [$num, $title, $desc, $img, $icon])
                    <li class="reveal group" data-delay="{{ $i + 1 }}">
                        <div class="h-full rounded-3xl bg-pitch-900 border border-white/10 overflow-hidden hover:border-emerald-500/40 hover:-translate-y-1 transition duration-300">
                            <div class="relative">
                                <x-img-placeholder
                                    ratio="16/10"
                                    :label="__($title)"
                                    :src="$img"
                                    size="800x500"
                                    :icon="$icon"
                                    rounded="rounded-none"
                                    class="w-full"
                                />
                                <span class="absolute top-3 left-3 flex items-center justify-center w-11 h-11 rounded-2xl bg-pitch-950/85 backdrop-blur text-emerald-400 text-base font-black ring-1 ring-emerald-500/30">
                                    {{ $num }}
                                </span>
                            </div>
                            <div class="p-5 sm:p-6">
                                <h3 class="text-lg font-extrabold text-white">{{ __($title) }}</h3>
                                <p class="mt-2 text-sm text-pitch-300 leading-relaxed">{{ __($desc) }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ==================== PARA QUEM É ==================== --}}
    <section class="py-16 sm:py-24">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="reveal text-center max-w-xl mx-auto">
                <span class="inline-block text-xs font-black uppercase tracking-widest text-emerald-400">{{ __('Feito para você') }}</span>
                <h2 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-white">{{ __('Dos dois lados da linha') }}</h2>
            </div>

            <div class="mt-12 grid gap-5 sm:gap-6 lg:grid-cols-2">
                @foreach ([
                    [
                        'Para jogadores',
                        'Encontre peladas abertas na sua região, receba convites e construa sua reputação a cada jogo.',
                        ['Partidas abertas perto de você', 'Convites direto no celular', 'Card de jogador com as suas notas'],
                        'images/landing/jogadores.jpg', 'heroicon-o-bolt', 'Quero jogar', 'emerald',
                    ],
                    [
                        'Para organizadores',
                        'Crie a partida, controle as vagas, sorteie os times e saiba exatamente quem já pagou.',
                        ['Lista de espera automática', 'Sorteio de times equilibrado', 'Controle de pagamento por jogador'],
                        'images/landing/organizadores.jpg', 'heroicon-o-clipboard-document-check', 'Preciso de jogadores', 'sky',
                    ],
                ] as $i => [$title, $desc, $bullets, $img, $icon, $cta, $accent])
                    @php
                        $accentClasses = [
                            'emerald' => ['text-emerald-400', 'bg-emerald-500/15', 'hover:border-emerald-500/40', 'bg-emerald-400 text-pitch-950 hover:bg-emerald-300'],
                            'sky' => ['text-sky-400', 'bg-sky-500/15', 'hover:border-sky-500/40', 'bg-sky-400 text-pitch-950 hover:bg-sky-300'],
                        ][$accent];
                    @endphp
                    <div class="reveal {{ $i === 0 ? 'from-left' : 'from-right' }} flex flex-col rounded-4xl bg-pitch-900 border border-white/10 overflow-hidden {{ $accentClasses[2] }} hover:-translate-y-1 transition duration-300" data-delay="{{ $i + 1 }}">
                        <x-img-placeholder
                            ratio="16/9"
                            :label="__($title)"
                            :src="$img"
                            size="1000x563"
                            :icon="$icon"
                            rounded="rounded-none"
                            class="w-full"
                        />

                        <div class="flex-1 flex flex-col p-6 sm:p-8">
                            <span class="flex items-center justify-center w-12 h-12 rounded-2xl {{ $accentClasses[1] }} {{ $accentClasses[0] }}">
                                <x-dynamic-component :component="$icon" class="w-6 h-6" />
                            </span>

                            <h3 class="mt-4 text-xl sm:text-2xl font-black text-white tracking-tight">{{ __($title) }}</h3>
                            <p class="mt-2 text-pitch-300 leading-relaxed">{{ __($desc) }}</p>

                            <ul class="mt-5 space-y-2">
                                @foreach ($bullets as $bullet)
                                    <li class="flex items-start gap-2 text-sm text-pitch-200">
                                        <x-heroicon-s-check-circle class="w-[18px] h-[18px] shrink-0 mt-0.5 {{ $accentClasses[0] }}" />
                                        {{ __($bullet) }}
                                    </li>
                                @endforeach
                            </ul>

                            <a href="{{ route('register') }}" class="mt-7 inline-flex justify-center items-center gap-2 min-h-[48px] px-6 rounded-2xl font-extrabold text-xs uppercase tracking-wider transition {{ $accentClasses[3] }}">
                                {{ __($cta) }}
                                <x-heroicon-o-arrow-right class="w-4 h-4" />
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== RECURSOS ==================== --}}
    <section id="recursos" class="py-16 sm:py-24 scroll-mt-20 bg-pitch-900/40 border-y border-white/5">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-[0.9fr_1.1fr] gap-12 lg:gap-16 items-center">

                {{-- Mockup de celular --}}
                <div class="reveal from-left order-2 lg:order-1 mx-auto w-full max-w-[280px] sm:max-w-[320px]">
                    <div class="relative rounded-[2.5rem] bg-pitch-950 p-2.5 ring-1 ring-white/15 shadow-2xl shadow-emerald-900/30">
                        <div class="absolute top-2.5 left-1/2 -translate-x-1/2 w-24 h-5 rounded-b-2xl bg-pitch-950 z-10"></div>
                        <x-img-placeholder
                            ratio="9/19.5"
                            tone="accent"
                            :label="__('Print do app')"
                            src="images/landing/app-tela.jpg"
                            size="1080x2340"
                            :note="__('Captura real da tela de partidas, no tema escuro.')"
                            icon="heroicon-o-device-phone-mobile"
                            rounded="rounded-[2rem]"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="order-1 lg:order-2">
                    <div class="reveal text-center lg:text-left">
                        <span class="inline-block text-xs font-black uppercase tracking-widest text-emerald-400">{{ __('Tudo em um lugar') }}</span>
                        <h2 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-white">{{ __('O que você tem no Futebas') }}</h2>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-4">
                        @foreach ([
                            ['heroicon-o-magnifying-glass', 'Buscar jogadores', 'Filtre por posição, nível e valor'],
                            ['heroicon-o-megaphone', 'SOS Goleiro', 'Goleiro de última hora em minutos'],
                            ['heroicon-o-arrow-path', 'Pelada semanal', 'Toda semana, gerada sozinha'],
                            ['heroicon-o-users', 'Sorteio de times', 'Times equilibrados em um toque'],
                            ['heroicon-o-banknotes', 'Controle de pagamento', 'Quem pagou e quem ainda falta'],
                            ['heroicon-o-star', 'Avaliações', 'Pontualidade, desempenho e postura'],
                            ['heroicon-o-share', 'Link público', 'Compartilhe no grupo do WhatsApp'],
                            ['heroicon-o-bell-alert', 'Notificações', 'Push no celular, mesmo fechado'],
                        ] as $i => [$icon, $label, $hint])
                            <div class="reveal group rounded-2xl bg-pitch-900 border border-white/10 p-4 sm:p-5 hover:border-emerald-500/40 hover:bg-pitch-800/60 transition duration-300" data-delay="{{ min($i + 1, 6) }}">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-400 group-hover:scale-110 transition-transform">
                                    <x-dynamic-component :component="$icon" class="w-5 h-5" />
                                </span>
                                <p class="mt-3 text-sm font-bold text-white leading-tight">{{ __($label) }}</p>
                                <p class="mt-1 text-xs text-pitch-400 leading-snug">{{ __($hint) }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== DEPOIMENTOS ==================== --}}
    <section class="py-16 sm:py-24">
        <div class="max-w-6xl mx-auto">
            <div class="reveal px-4 sm:px-6 lg:px-8 text-center max-w-xl mx-auto">
                <span class="inline-block text-xs font-black uppercase tracking-widest text-emerald-400">{{ __('Quem já joga') }}</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('O que dizem por aí') }}</h2>
            </div>

            {{-- No celular vira um carrossel deslizável; a partir de sm, grade. --}}
            <div class="mt-10 snap-row px-4 sm:px-6 lg:px-8 sm:grid sm:grid-cols-3 sm:gap-6 sm:overflow-visible">
                @foreach ([
                    ['Marcelo A.', 'Organizador · Society', 'Antes eu passava a semana correndo atrás de gente no WhatsApp. Agora publico a pelada e as vagas se completam sozinhas.', 'images/landing/jogador-1.jpg'],
                    ['Rafa Souza', 'Goleiro · Futsal', 'Já peguei três jogos pelo SOS. Eu digo o meu valor e o organizador escolhe, sem ficar pedindo favor.', 'images/landing/jogador-2.jpg'],
                    ['Tiago M.', 'Jogador · Campo', 'Cheguei numa cidade nova sem conhecer ninguém e em uma semana já estava jogando toda quinta.', 'images/landing/jogador-3.jpg'],
                ] as $i => [$name, $role, $quote, $photo])
                    <figure class="reveal w-[85vw] xs:w-[78vw] sm:w-auto flex flex-col rounded-3xl bg-pitch-900 border border-white/10 p-6 hover:border-emerald-500/30 transition" data-delay="{{ $i + 1 }}">
                        <div class="flex items-center gap-0.5 text-emerald-400">
                            @foreach (range(1, 5) as $star)
                                <x-heroicon-s-star class="w-4 h-4" />
                            @endforeach
                        </div>
                        <blockquote class="mt-4 flex-1 text-sm sm:text-base text-pitch-100 leading-relaxed">
                            &ldquo;{{ __($quote) }}&rdquo;
                        </blockquote>
                        <figcaption class="mt-6 flex items-center gap-3">
                            <x-img-placeholder
                                ratio="1/1"
                                :label="__('Foto')"
                                :src="$photo"
                                size="200x200"
                                icon="heroicon-o-user"
                                rounded="rounded-full"
                                class="w-11 h-11 shrink-0"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-white truncate">{{ $name }}</p>
                                <p class="text-xs text-pitch-400 truncate">{{ __($role) }}</p>
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== DÚVIDAS ==================== --}}
    <section id="duvidas" class="py-16 sm:py-24 scroll-mt-20 bg-pitch-900/40 border-y border-white/5">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="reveal text-center">
                <span class="inline-block text-xs font-black uppercase tracking-widest text-emerald-400">{{ __('Dúvidas') }}</span>
                <h2 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Perguntas frequentes') }}</h2>
            </div>

            <div class="mt-10 space-y-3">
                @foreach ([
                    ['O Futebas é grátis?', 'É. Criar conta, montar a sua pelada e entrar em partidas não custa nada. O valor por jogo é combinado entre você e o organizador.'],
                    ['Preciso ter conta para entrar numa partida?', 'Não. O organizador pode compartilhar o link público da partida e você entra como convidado, só com nome e telefone.'],
                    ['Como funciona a lista de espera?', 'Quando a partida lota, quem chega depois entra na lista de espera. Se alguém desiste, a vaga é preenchida automaticamente e a pessoa é avisada.'],
                    ['Sou goleiro. Como recebo as chamadas de SOS?', 'Basta marcar "Goleiro" no seu perfil. A partir daí, toda chamada aberta na sua região chega no seu celular e você responde com o seu valor.'],
                    ['Dá para instalar no celular?', 'Dá. O Futebas é um app instalável: abra no navegador e toque em "Adicionar à tela de início". Ele funciona como aplicativo, com notificações.'],
                ] as $i => [$question, $answer])
                    <div
                        x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }"
                        class="reveal rounded-2xl bg-pitch-900 border overflow-hidden transition-colors"
                        :class="open ? 'border-emerald-500/30' : 'border-white/10'"
                        data-delay="{{ min($i + 1, 5) }}"
                    >
                        <button
                            @click="open = ! open"
                            :aria-expanded="open"
                            class="w-full flex items-center justify-between gap-4 text-left p-5 min-h-[60px] hover:bg-white/[0.03] transition"
                        >
                            <span class="text-sm sm:text-base font-bold text-white">{{ __($question) }}</span>
                            <x-heroicon-o-chevron-down class="w-5 h-5 shrink-0 text-emerald-400 transition-transform duration-200" ::class="open && 'rotate-180'" />
                        </button>
                        <div x-show="open" x-cloak>
                            <p class="px-5 pb-5 text-sm text-pitch-300 leading-relaxed">{{ __($answer) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== CTA FINAL ==================== --}}
    <section class="relative overflow-hidden">
        {{-- Foto de fundo em largura total. --}}
        <div class="absolute inset-0" aria-hidden="true">
            <x-img-placeholder
                ratio="16/9"
                :label="__('Foto de fundo - pelada à noite')"
                src="images/landing/organizadores.jpg"
                size="1920x1080"
                :note="__('Panorâmica e escura. Fica atrás do texto, com sobreposição verde por cima.')"
                icon="heroicon-o-photo"
                rounded="rounded-none"
                class="w-full h-full"
            />
            <div class="absolute inset-0 bg-gradient-to-br from-pitch-950/95 via-emerald-950/90 to-pitch-950/95"></div>
            <div class="absolute inset-0 mesh-pitch"></div>
        </div>

        <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 text-center">
            <h2 class="reveal text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.08] text-white">
                {{ __('Seu próximo jogo') }}
                <span class="block gradient-text">{{ __('começa aqui.') }}</span>
            </h2>

            <p class="reveal mt-5 text-base sm:text-lg text-pitch-200 leading-relaxed max-w-lg mx-auto" data-delay="1">
                {{ __('Crie sua conta em menos de um minuto e encontre quem falta para completar a sua partida.') }}
            </p>

            <div class="reveal mt-9 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3" data-delay="2">
                <a href="{{ route('register') }}" class="btn-shine inline-flex justify-center items-center gap-2 min-h-[54px] px-8 rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-emerald-400 text-pitch-950 shadow-xl shadow-emerald-500/25 hover:bg-emerald-300 hover:-translate-y-0.5 transition">
                    {{ __('Criar conta grátis') }}
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                </a>
                <a href="{{ route('login') }}" class="inline-flex justify-center items-center min-h-[54px] px-8 rounded-2xl font-extrabold text-sm uppercase tracking-wider glass text-white hover:bg-white/15 transition">
                    {{ __('Entrar') }}
                </a>
            </div>

            <p class="reveal mt-5 text-xs text-pitch-400 flex items-center justify-center gap-1.5" data-delay="3">
                <x-heroicon-o-check-badge class="w-4 h-4 text-emerald-400" />
                {{ __('Sem mensalidade. Sem cartão de crédito.') }}
            </p>
        </div>
    </section>
</main>

{{-- ==================== RODAPÉ ==================== --}}
<footer class="bg-pitch-950 border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <img src="{{ asset('images/logo_white.png') }}" alt="{{ config('app.name', 'Futebas') }}" class="h-10 w-auto">
                <p class="mt-4 text-sm text-pitch-400 leading-relaxed max-w-xs">
                    {{ __('Seu futebol. Sua região. Sua partida. Conectando jogadores e organizadores do interior.') }}
                </p>
                <div class="mt-5 flex items-center gap-2">
                    @foreach ([
                        ['https://instagram.com', 'Instagram', 'heroicon-o-camera'],
                        ['https://wa.me/', 'WhatsApp', 'heroicon-o-chat-bubble-left-right'],
                        ['mailto:contato@futebas.app', 'E-mail', 'heroicon-o-envelope'],
                    ] as [$href, $label, $icon])
                        <a href="{{ $href }}" target="_blank" rel="noopener" aria-label="{{ $label }}"
                           class="tap-target inline-flex items-center justify-center rounded-xl bg-pitch-900 border border-white/10 text-pitch-300 hover:text-emerald-400 hover:border-emerald-500/40 transition">
                            <x-dynamic-component :component="$icon" class="w-5 h-5" />
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-xs font-black uppercase tracking-widest text-pitch-500">{{ __('Produto') }}</h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($navLinks as [$href, $label])
                        <li><a href="{{ $href }}" class="text-sm text-pitch-300 hover:text-emerald-400 transition">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-black uppercase tracking-widest text-pitch-500">{{ __('Conta') }}</h3>
                <ul class="mt-4 space-y-2.5">
                    @auth
                        <li><a href="{{ route('dashboard') }}" class="text-sm text-pitch-300 hover:text-emerald-400 transition">{{ __('Ir para o app') }}</a></li>
                    @else
                        <li><a href="{{ route('register') }}" class="text-sm text-pitch-300 hover:text-emerald-400 transition">{{ __('Criar conta') }}</a></li>
                        <li><a href="{{ route('login') }}" class="text-sm text-pitch-300 hover:text-emerald-400 transition">{{ __('Entrar') }}</a></li>
                    @endauth
                </ul>
            </div>
        </div>

        <div class="mt-12 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-xs text-pitch-500">&copy; {{ date('Y') }} {{ config('app.name', 'Futebas') }}. {{ __('Todos os direitos reservados.') }}</p>
            <p class="text-xs text-pitch-500 flex items-center gap-1.5">
                <x-heroicon-o-map-pin class="w-3.5 h-3.5" />
                {{ __('Desenvolvido por InovaFlow.') }}
            </p>
        </div>
    </div>
</footer>

{{-- ==================== BARRA FIXA DE CTA (MOBILE) ====================
     Aparece depois do hero: no celular a decisão acontece durante a rolagem,
     e o topo já saiu de vista. --}}
@guest
    <div
        x-data="{ shown: false }"
        @scroll.window="shown = window.scrollY > 700"
        x-show="shown"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="sm:hidden fixed bottom-0 inset-x-0 z-40 px-4 pt-3 bg-pitch-950/95 backdrop-blur-xl border-t border-white/10"
        style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.75rem);"
    >
        <div class="flex items-center gap-2.5">
            <a href="{{ route('register') }}" class="flex-1 inline-flex justify-center items-center gap-2 min-h-[50px] rounded-2xl font-extrabold text-sm uppercase tracking-wider bg-emerald-400 text-pitch-950 shadow-lg shadow-emerald-500/25">
                <x-heroicon-o-bolt class="w-5 h-5" />
                {{ __('Criar conta grátis') }}
            </a>
            <a href="{{ route('login') }}" class="tap-target inline-flex items-center justify-center px-4 min-h-[50px] rounded-2xl font-bold text-sm text-white border border-white/15">
                {{ __('Entrar') }}
            </a>
        </div>
    </div>
@endguest

</body>
</html>
