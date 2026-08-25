<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-user-group" :title="__('Dashboard')" :subtitle="__('Seu resumo como organizador')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Greeting hero --}}
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 to-green-700 text-white p-6 sm:p-8 shadow-lg shadow-emerald-600/20">
                <x-heroicon-o-user-group class="pointer-events-none absolute -right-8 -bottom-10 w-56 h-56 opacity-10 select-none" />
                <div class="relative flex items-center gap-4">
                    <span class="hidden sm:flex items-center justify-center w-14 h-14 rounded-2xl bg-white/15 text-2xl font-extrabold shrink-0">
                        {{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}
                    </span>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold flex items-center gap-2">
                            {{ __('Olá, :name!', ['name' => Str::before(Auth::user()->name, ' ')]) }}
                            <x-heroicon-o-hand-raised class="w-6 h-6 sm:w-7 sm:h-7" />
                        </h1>
                        <p class="mt-1 text-emerald-50 font-medium">
                            {{ __('Seu futebol. Sua região. Sua partida.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                <x-stat-card icon="heroicon-o-trophy" :label="__('Partidas criadas')" :value="$gamesCreatedCount" color="emerald" />
                <x-stat-card icon="heroicon-o-envelope" :label="__('Convites pendentes')" :value="$pendingInvitationsSentCount" color="amber" />
                <x-stat-card icon="heroicon-o-map-pin" :label="__('Jogadores na região')" :value="$playersNearbyCount ?? '—'" color="blue" class="col-span-2 sm:col-span-1" />
            </div>

            {{-- SOS Goleiro --}}
            <a href="{{ route('sos.index') }}" class="group relative block rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 text-white p-6 shadow-lg shadow-red-500/30 hover:shadow-xl hover:shadow-red-500/40 transition transform hover:-translate-y-0.5">
                <span class="absolute top-4 right-4 inline-flex items-center rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wider animate-pulse">
                    {{ $pendingSosApplicationsCount > 0 ? trans_choice(':count candidatura|:count candidaturas', $pendingSosApplicationsCount, ['count' => $pendingSosApplicationsCount]) : __('Urgente') }}
                </span>
                <div class="flex items-center gap-4">
                    <span class="flex items-center justify-center w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white/15 shrink-0">
                        <x-heroicon-o-exclamation-triangle class="w-8 h-8 sm:w-9 sm:h-9" />
                    </span>
                    <div>
                        <p class="text-xl sm:text-2xl font-extrabold uppercase tracking-wide">
                            {{ __('Preciso de Goleiro') }}
                        </p>
                        <p class="mt-1 text-sm text-red-50">
                            @if ($pendingSosApplicationsCount > 0)
                                {{ __('Goleiros esperando sua decisão. Compare valor, local e avaliação.') }}
                            @else
                                {{ __('Avise a região e ache um goleiro rapidinho.') }}
                            @endif
                        </p>
                    </div>
                </div>
            </a>

            {{-- Quick actions --}}
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wide text-pitch-400 mb-3">{{ __('Acesso rápido') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-action-card :href="route('players.search')" icon="heroicon-o-magnifying-glass" color="emerald" :title="__('Procurar Jogadores')" :description="__('Filtre por posição, nível e valor')" />
                    <x-action-card :href="route('games.create')" icon="heroicon-o-plus-circle" color="blue" :title="__('Criar Game')" :description="__('Organize uma nova partida')" />
                    <x-action-card :href="route('games.mine')" icon="heroicon-o-trophy" color="gray" :title="__('Minhas Partidas')" :description="__('Acompanhe suas solicitações')" />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
