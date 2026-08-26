@php
    $isPlayer = Auth::user()->hasRole(\App\Models\User::ROLE_PLAYER);
    $isGoalkeeper = Auth::user()->isGoalkeeper();
@endphp

{{-- Ação principal flutuante. Só o organizador tem uma ação de "criar":
     o jogador entra em partidas que já existem, e a busca dele já é uma
     aba - um botão duplicando a aba seria só ruído. --}}
@unless ($isPlayer)
    @unless (request()->routeIs('games.create'))
        <a
            href="{{ route('games.create') }}"
            class="lg:hidden fixed right-4 z-40 flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-400 text-pitch-950 shadow-xl shadow-emerald-500/30 active:scale-95 transition"
            style="bottom: calc(5.25rem + env(safe-area-inset-bottom));"
            aria-label="{{ __('Criar Partida') }}"
        >
            <x-heroicon-o-plus class="w-7 h-7" />
        </a>
    @endunless
@endunless

<nav
    x-data="{ moreOpen: false }"
    class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-pitch-900/95 backdrop-blur-xl border-t border-pitch-800"
    style="padding-bottom: env(safe-area-inset-bottom);"
    aria-label="{{ __('Navegação principal') }}"
>
    <div class="grid grid-cols-5">
        @if ($isPlayer)
            <x-bottom-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="heroicon-o-home">{{ __('Início') }}</x-bottom-nav-link>
            <x-bottom-nav-link :href="route('games.search')" :active="request()->routeIs('games.search')" icon="heroicon-o-magnifying-glass">{{ __('Buscar') }}</x-bottom-nav-link>
            <x-bottom-nav-link :href="route('games.mine')" :active="request()->routeIs('games.mine')" icon="heroicon-o-trophy">{{ __('Partidas') }}</x-bottom-nav-link>
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
            <x-bottom-nav-link :href="route('players.search')" :active="request()->routeIs('players.search') || request()->routeIs('players.show')" icon="heroicon-o-magnifying-glass">{{ __('Buscar') }}</x-bottom-nav-link>
            <x-bottom-nav-link :href="route('sos.index')" :active="request()->routeIs('sos.*')" icon="heroicon-o-megaphone">{{ __('SOS') }}</x-bottom-nav-link>
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
                        ['games.create', route('games.create'), 'heroicon-o-plus-circle', __('Criar Partida')],
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
