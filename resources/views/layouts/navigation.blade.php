<aside
    :class="sidebarCollapsed ? 'lg:w-20' : 'lg:w-64'"
    class="hidden lg:flex lg:flex-col lg:sticky lg:top-0 lg:h-screen lg:shrink-0 bg-pitch-900 border-r border-pitch-800 transition-all duration-200 ease-in-out"
>
    <div class="flex items-center h-16 px-4 border-b border-pitch-800" :class="sidebarCollapsed && 'justify-center'">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-extrabold text-white overflow-hidden">
            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/15 text-emerald-400 shrink-0">
                <x-heroicon-o-trophy class="w-5 h-5" />
            </span>
            <span x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms class="whitespace-nowrap">{{ __('Futebas') }}</span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="heroicon-o-home">
            {{ __('Dashboard') }}
        </x-sidebar-link>

        @if (Auth::user()->hasRole(\App\Models\User::ROLE_PLAYER))
            <x-sidebar-link :href="route('games.search')" :active="request()->routeIs('games.search')" icon="heroicon-o-magnifying-glass">
                {{ __('Procurar Partidas') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('player-profile.edit')" :active="request()->routeIs('player-profile.edit')" icon="heroicon-o-user">
                {{ __('Perfil do Jogador') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('availability.edit')" :active="request()->routeIs('availability.edit')" icon="heroicon-o-calendar-days">
                {{ __('Disponibilidade') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('invitations.index')" :active="request()->routeIs('invitations.index')" icon="heroicon-o-envelope">
                {{ __('Convites') }}
            </x-sidebar-link>
            @if (Auth::user()->isGoalkeeper())
                <x-sidebar-link :href="route('sos-opportunities.index')" :active="request()->routeIs('sos-opportunities.*')" icon="heroicon-o-megaphone">
                    {{ __('SOS Goleiro') }}
                </x-sidebar-link>
            @endif
            <x-sidebar-link :href="route('ratings.show', Auth::user()->id)" :active="request()->routeIs('ratings.show')" icon="heroicon-o-star">
                {{ __('Avaliações') }}
            </x-sidebar-link>
        @else
            <x-sidebar-link :href="route('players.search')" :active="request()->routeIs('players.search') || request()->routeIs('players.show')" icon="heroicon-o-magnifying-glass">
                {{ __('Procurar Jogadores') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('sos.index')" :active="request()->routeIs('sos.index')" icon="heroicon-o-exclamation-triangle">
                {{ __('SOS Goleiro') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('game-series.index')" :active="request()->routeIs('game-series.*')" icon="heroicon-o-arrow-path">
                {{ __('Peladas Semanais') }}
            </x-sidebar-link>
            <x-sidebar-link :href="route('games.create')" :active="request()->routeIs('games.create')" icon="heroicon-o-plus-circle">
                {{ __('Criar Jogo') }}
            </x-sidebar-link>
        @endif

        <x-sidebar-link :href="route('games.mine')" :active="request()->routeIs('games.mine')" icon="heroicon-o-trophy">
            {{ __('Minhas Partidas') }}
        </x-sidebar-link>

        <x-sidebar-link
            :href="route('notifications.index')"
            :active="request()->routeIs('notifications.*')"
            icon="heroicon-o-bell"
            :badge="$unreadNotifications ?: null"
        >
            {{ __('Notificações') }}
        </x-sidebar-link>
    </nav>

    <div class="border-t border-pitch-800 p-3 space-y-1">
        <div class="flex items-center gap-3 rounded-lg px-3 py-2 mb-1" :class="sidebarCollapsed && 'justify-center'">
            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-pitch-800 text-xs font-bold text-pitch-200 shrink-0">
                {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr(Auth::user()->name, 0, 1)) }}
            </span>
            <div x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms class="min-w-0">
                <p class="text-sm font-semibold text-white truncate">{{ Auth::user()->name }}</p>
                <p class="text-xs text-pitch-400 truncate">{{ Auth::user()->email }}</p>
            </div>
        </div>

        <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')" icon="heroicon-o-cog-6-tooth">
            {{ __('Minha Conta') }}
        </x-sidebar-link>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-pitch-300 hover:bg-red-500/10 hover:text-red-400 transition" :class="sidebarCollapsed && 'justify-center'" title="{{ __('Sair') }}">
                <span class="shrink-0"><x-heroicon-o-arrow-right-on-rectangle class="w-5 h-5" /></span>
                <span x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms class="whitespace-nowrap">{{ __('Sair') }}</span>
            </button>
        </form>

        <button
            @click="sidebarCollapsed = ! sidebarCollapsed"
            type="button"
            class="w-full flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-pitch-400 hover:bg-pitch-800 transition"
            :class="sidebarCollapsed && 'justify-center'"
        >
            <span class="shrink-0" x-show="!sidebarCollapsed"><x-heroicon-o-chevron-double-left class="w-5 h-5" /></span>
            <span class="shrink-0" x-show="sidebarCollapsed" style="display: none;"><x-heroicon-o-chevron-double-right class="w-5 h-5" /></span>
            <span x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms class="whitespace-nowrap">{{ __('Recolher menu') }}</span>
        </button>
    </div>
</aside>
