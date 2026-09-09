@php
    $isPlayer = Auth::user()->hasRole(\App\Models\User::ROLE_PLAYER);
    $isGoalkeeper = Auth::user()->isGoalkeeper();
@endphp

<nav
    x-data="{ moreOpen: false }"
    class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-pitch-900/95 backdrop-blur-xl border-t border-pitch-800"
    style="padding-bottom: env(safe-area-inset-bottom);"
    aria-label="{{ __('Navegação principal') }}"
>
    {{-- A vaga do meio é elevada e guarda o gesto que a pessoa veio fazer:
         o organizador cria uma partida, o jogador procura uma. Tudo o mais
         é navegação; por isso só esses dois saem da fileira. --}}
    <div class="grid grid-cols-5 w-full">
        @if ($isPlayer)
            <x-bottom-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="heroicon-o-home">{{ __('Início') }}</x-bottom-nav-link>
            <x-bottom-nav-link :href="route('games.mine')" :active="request()->routeIs('games.mine')" icon="heroicon-o-trophy">{{ __('Partidas') }}</x-bottom-nav-link>

            <x-bottom-nav-dock
                :href="route('games.search')"
                :active="request()->routeIs('games.search')"
                icon="heroicon-s-magnifying-glass"
                :label="__('Buscar')"
                :aria-label="__('Procurar Partidas')"
            />

            {{-- Only one slot left before "Mais": a goalkeeper gets SOS,
                 everyone else gets invitations. Whichever loses the slot is
                 picked up by the "Mais" sheet below. --}}
            @if ($isGoalkeeper)
                <x-bottom-nav-link :href="route('sos-opportunities.index')" :active="request()->routeIs('sos-opportunities.*')" icon="heroicon-o-megaphone">{{ __('SOS') }}</x-bottom-nav-link>
            @else
                <x-bottom-nav-link :href="route('invitations.index')" :active="request()->routeIs('invitations.index')" icon="heroicon-o-envelope">{{ __('Convites') }}</x-bottom-nav-link>
            @endif
        @else
            <x-bottom-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="heroicon-o-home">{{ __('Início') }}</x-bottom-nav-link>
            {{-- "Jogadores" e não "Buscar": para quem organiza, procurar é
                 sempre procurar gente - e quase sempre um goleiro. --}}
            <x-bottom-nav-link :href="route('players.search')" :active="request()->routeIs('players.search') || request()->routeIs('players.show')" icon="heroicon-o-magnifying-glass">{{ __('Jogadores') }}</x-bottom-nav-link>

            <x-bottom-nav-dock
                :href="route('games.create')"
                :active="request()->routeIs('games.create')"
                icon="heroicon-s-plus"
                :label="__('Criar')"
                :aria-label="__('Criar Partida')"
            />

            <x-bottom-nav-link :href="route('games.mine')" :active="request()->routeIs('games.mine')" icon="heroicon-o-trophy">{{ __('Partidas') }}</x-bottom-nav-link>
        @endif

        <button
            @click="moreOpen = true"
            type="button"
            class="relative flex flex-col items-center justify-center gap-1 pt-3 pb-2 min-h-[56px] text-pitch-400 active:text-pitch-200 transition"
        >
            <span class="relative">
                <x-heroicon-o-bars-3 class="w-6 h-6" />
                @if ($unreadNotifications)
                    <span class="absolute -top-1 -right-1.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-pitch-900"></span>
                @endif
            </span>
            <span class="text-[11px] font-bold leading-none">{{ __('Mais') }}</span>
        </button>
    </div>

    {{-- "Mais" bottom sheet --}}
    <div x-show="moreOpen" style="display: none;" class="fixed inset-0 z-50" @keydown.escape.window="moreOpen = false">
        <div x-show="moreOpen" x-transition.opacity @click="moreOpen = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>

        <div
            x-show="moreOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 inset-x-0 max-h-[85vh] overflow-y-auto scrollbar-slim bg-pitch-900 rounded-t-3xl border-t border-pitch-800 shadow-2xl shadow-black/70 px-4 pt-3"
            style="padding-bottom: calc(env(safe-area-inset-bottom) + 1rem);"
        >
            <div class="w-10 h-1.5 bg-pitch-700 rounded-full mx-auto"></div>

            {{-- Cabeçalho com a conta: transforma a folha em "menu do
                 usuário" e não só num depósito do que não coube. --}}
            <a href="{{ route('profile.edit') }}" @click="moreOpen = false" class="mt-4 flex items-center gap-3 rounded-2xl bg-pitch-800/60 border border-pitch-700 p-3">
                <x-avatar :user="Auth::user()" size="md" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-white truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-pitch-400 truncate">{{ $isPlayer ? __('Jogador') : __('Organizador') }}</p>
                </div>
                <x-heroicon-o-chevron-right class="w-5 h-5 text-pitch-500 shrink-0" />
            </a>

            @php
                // Só o que não coube na barra de baixo, por papel.
                $moreLinks = $isPlayer
                    ? array_values(array_filter([
                        $isGoalkeeper
                            ? ['invitations.index', route('invitations.index'), 'heroicon-o-envelope', __('Convites')]
                            : null,
                        ['player-profile.edit', route('player-profile.edit'), 'heroicon-o-user', __('Perfil do Jogador')],
                        ['availability.edit', route('availability.edit'), 'heroicon-o-calendar-days', __('Disponibilidade')],
                        ['ratings.show', route('ratings.show', Auth::user()->id), 'heroicon-o-star', __('Avaliações')],
                    ]))
                    : [
                        // O SOS deixou a barra para a vaga do meio ficar com
                        // "Criar", mas continua a uma toque de distância aqui,
                        // no painel e na própria busca de jogadores - e quem
                        // se candidatou já avisou por notificação.
                        ['sos.*', route('sos.index'), 'heroicon-o-megaphone', __('SOS Goleiro')],
                        ['game-series.*', route('game-series.index'), 'heroicon-o-arrow-path', __('Peladas Semanais')],
                    ];
            @endphp

            <div class="mt-3 grid grid-cols-1 gap-1">
                @foreach ($moreLinks as [$pattern, $href, $icon, $label])
                    <a
                        href="{{ $href }}"
                        @click="moreOpen = false"
                        class="flex items-center gap-3 rounded-xl px-3 min-h-[52px] text-base font-semibold transition {{ request()->routeIs($pattern) ? 'bg-emerald-500/10 text-emerald-300' : 'text-pitch-200 active:bg-pitch-800' }}"
                    >
                        <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-pitch-800 text-pitch-300 shrink-0">
                            <x-dynamic-component :component="$icon" class="w-5 h-5" />
                        </span>
                        {{ $label }}
                    </a>
                @endforeach

                <a
                    href="{{ route('subscription.index') }}"
                    @click="moreOpen = false"
                    class="flex items-center gap-3 rounded-xl px-3 min-h-[52px] text-base font-semibold transition {{ request()->routeIs('subscription.*') ? 'bg-emerald-500/10 text-emerald-300' : 'text-pitch-200 active:bg-pitch-800' }}"
                >
                    <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-pitch-800 text-pitch-300 shrink-0">
                        <x-heroicon-o-sparkles class="w-5 h-5" />
                    </span>
                    <span class="flex-1">{{ __('Meu plano') }}</span>
                    <x-plan-badge :plan="Auth::user()->currentPlan()" />
                </a>

                {{-- Só aparece para quem ainda pode instalar: some sozinha
                     dentro do app instalado, e existe porque dispensar o
                     cartão não pode virar beco sem saída - no iPhone não há
                     prompt do navegador para reaparecer por conta própria. --}}
                <button
                    x-cloak
                    x-show="$store.install.available"
                    type="button"
                    @click="moreOpen = false; $store.install.show()"
                    class="flex items-center gap-3 rounded-xl px-3 min-h-[52px] text-base font-semibold text-pitch-200 active:bg-pitch-800 transition"
                >
                    <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-emerald-500/15 text-emerald-400 shrink-0">
                        <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                    </span>
                    {{ __('Instalar o app') }}
                </button>

                <a
                    href="{{ route('notifications.index') }}"
                    @click="moreOpen = false"
                    class="flex items-center gap-3 rounded-xl px-3 min-h-[52px] text-base font-semibold transition {{ request()->routeIs('notifications.*') ? 'bg-emerald-500/10 text-emerald-300' : 'text-pitch-200 active:bg-pitch-800' }}"
                >
                    <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-pitch-800 text-pitch-300 shrink-0">
                        <x-heroicon-o-bell class="w-5 h-5" />
                    </span>
                    <span class="flex-1">{{ __('Notificações') }}</span>
                    @if ($unreadNotifications)
                        <span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full bg-emerald-500 text-[11px] font-black text-white">{{ min($unreadNotifications, 99) }}</span>
                    @endif
                </a>

                <a
                    href="{{ route('profile.edit') }}"
                    @click="moreOpen = false"
                    class="flex items-center gap-3 rounded-xl px-3 min-h-[52px] text-base font-semibold transition {{ request()->routeIs('profile.edit') ? 'bg-emerald-500/10 text-emerald-300' : 'text-pitch-200 active:bg-pitch-800' }}"
                >
                    <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-pitch-800 text-pitch-300 shrink-0">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                    </span>
                    {{ __('Minha Conta') }}
                </a>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-3 pt-3 border-t border-pitch-800">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 rounded-xl px-3 min-h-[52px] text-base font-semibold text-red-400 active:bg-red-500/10 transition">
                    <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-red-500/10 shrink-0">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5" />
                    </span>
                    {{ __('Sair') }}
                </button>
            </form>
        </div>
    </div>
</nav>
