<nav x-data="{ moreOpen: false }" class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-pitch-900 border-t border-pitch-800" style="padding-bottom: env(safe-area-inset-bottom);">
    <div class="grid grid-cols-5">
        @if (Auth::user()->hasRole(\App\Models\User::ROLE_PLAYER))
            <x-bottom-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="heroicon-o-home">{{ __('Início') }}</x-bottom-nav-link>
            <x-bottom-nav-link :href="route('games.search')" :active="request()->routeIs('games.search')" icon="heroicon-o-magnifying-glass">{{ __('Buscar') }}</x-bottom-nav-link>
            <x-bottom-nav-link :href="route('games.mine')" :active="request()->routeIs('games.mine')" icon="heroicon-o-trophy">{{ __('Partidas') }}</x-bottom-nav-link>
            {{-- Only one slot left before "Mais": a goalkeeper gets SOS,
                 everyone else gets invitations. Whichever loses the slot is
                 picked up by the "Mais" sheet below. --}}
            @if (Auth::user()->isGoalkeeper())
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

        <button @click="moreOpen = true" type="button" class="flex flex-col items-center justify-center gap-0.5 py-2.5 text-pitch-400">
            <x-heroicon-o-ellipsis-horizontal class="w-6 h-6" />
            <span class="text-[11px] font-semibold">{{ __('Mais') }}</span>
        </button>
    </div>

    {{-- "Mais" bottom sheet --}}
    <div x-show="moreOpen" style="display: none;" class="fixed inset-0 z-50" @keydown.escape.window="moreOpen = false">
        <div x-show="moreOpen" x-transition.opacity @click="moreOpen = false" class="absolute inset-0 bg-black/40"></div>

        <div
            x-show="moreOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 inset-x-0 bg-pitch-900 rounded-t-2xl shadow-2xl shadow-black/50 p-4 space-y-1"
            style="padding-bottom: calc(env(safe-area-inset-bottom) + 1rem);"
        >
            <div class="w-10 h-1.5 bg-pitch-700 rounded-full mx-auto mb-3"></div>

            @if (Auth::user()->hasRole(\App\Models\User::ROLE_PLAYER))
                {{-- A goalkeeper's bottom bar spends its last slot on SOS,
                     so invitations only live here for them. --}}
                @if (Auth::user()->isGoalkeeper())
                    <x-responsive-nav-link :href="route('invitations.index')" :active="request()->routeIs('invitations.index')">
                        <span class="inline-flex items-center gap-2"><x-heroicon-o-envelope class="w-5 h-5 text-pitch-500" /> {{ __('Convites') }}</span>
                    </x-responsive-nav-link>
                @endif

                <x-responsive-nav-link :href="route('player-profile.edit')" :active="request()->routeIs('player-profile.edit')">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-user class="w-5 h-5 text-pitch-500" /> {{ __('Perfil do Jogador') }}</span>
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('availability.edit')" :active="request()->routeIs('availability.edit')">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-calendar-days class="w-5 h-5 text-pitch-500" /> {{ __('Disponibilidade') }}</span>
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('games.create')" :active="request()->routeIs('games.create')">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-plus-circle class="w-5 h-5 text-pitch-500" /> {{ __('Criar Solicitação') }}</span>
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('game-series.index')" :active="request()->routeIs('game-series.*')">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-arrow-path class="w-5 h-5 text-pitch-500" /> {{ __('Peladas Semanais') }}</span>
                </x-responsive-nav-link>
            @endif

            <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                <span class="inline-flex items-center gap-2">
                    <x-heroicon-o-bell class="w-5 h-5 text-pitch-500" /> {{ __('Notificações') }}
                    @if ($unreadNotifications)
                        <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-emerald-500 text-[11px] font-bold text-white">{{ min($unreadNotifications, 99) }}</span>
                    @endif
                </span>
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                <span class="inline-flex items-center gap-2"><x-heroicon-o-cog-6-tooth class="w-5 h-5 text-pitch-500" /> {{ __('Minha Conta') }}</span>
            </x-responsive-nav-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5 text-pitch-500" /> {{ __('Sair') }}</span>
                </x-responsive-nav-link>
            </form>
        </div>
    </div>
</nav>
