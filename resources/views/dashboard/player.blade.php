<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-trophy" :title="__('Dashboard')" :subtitle="__('Seu resumo como jogador')" />
    </x-slot>

    @php
        $dayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $position = $playerProfile->positions[0] ?? null;
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-1xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8 items-start">
                <div>
                    {{-- Greeting hero --}}
                    <div
                        class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 to-green-700 text-white p-6 sm:p-8 shadow-lg shadow-emerald-600/20">
                        <x-heroicon-o-trophy
                            class="pointer-events-none absolute -right-8 -bottom-10 w-56 h-56 opacity-10 select-none" />
                        <div class="relative flex items-center gap-4">
                            <span
                                class="hidden sm:flex items-center justify-center w-14 h-14 rounded-2xl bg-white/15 text-2xl font-extrabold shrink-0">
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

                    @if (!$playerProfile)
                        <x-empty-state icon="heroicon-o-pencil-square" :title="__('Complete seu perfil esportivo')"
                            :description="__('Falta pouco! Informe posição, modalidade, nível e valor para aparecer nas buscas dos organizadores.')">
                            <x-slot name="action">
                                <a href="{{ route('player-profile.edit') }}"
                                    class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                    {{ __('Completar perfil') }}
                                </a>
                            </x-slot>
                        </x-empty-state>
                    @else
                        {{-- Stats --}}
                        <div class="grid grid-cols-3 sm:grid-cols-3 mt-3 gap-3 sm:gap-4">
                            <x-stat-card icon="heroicon-o-flag" :label="__('Posição')" :value="$position ?? '—'"
                                color="emerald" />
                            <x-stat-card icon="heroicon-o-chart-bar" :label="__('Nível')" :value="$playerProfile->level"
                                color="blue" />
                            <x-stat-card icon="heroicon-o-envelope" :label="__('Convites pendentes')"
                                :value="$pendingInvitationsCount" color="red" />
                        </div>

                        {{-- Availability summary --}}
                        <div class="bg-pitch-900 mt-3 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="text-sm font-bold uppercase tracking-wide text-pitch-400">
                                    {{ __('Disponibilidade') }}
                                </h2>
                                <a href="{{ route('availability.edit') }}"
                                    class="text-xs font-bold text-emerald-400 hover:text-emerald-300 uppercase tracking-wide">
                                    {{ __('Editar') }}
                                </a>
                            </div>

                            @if ($availabilities->isEmpty())
                                <p class="mt-3 text-sm text-pitch-300">
                                    {{ __('Nenhuma disponibilidade cadastrada.') }}
                                    <a href="{{ route('availability.edit') }}"
                                        class="text-emerald-400 font-semibold underline">{{ __('Informar agora') }}</a>
                                </p>
                            @else
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($availabilities as $availability)
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-pitch-800 border border-pitch-700 text-xs font-semibold text-pitch-200">
                                            {{ $dayLabels[$availability->day_of_week] }}
                                            <span
                                                class="text-pitch-500 font-normal">{{ $availability->start_time->format('H:i') }}–{{ $availability->end_time->format('H:i') }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Match-day check-in: only shows while the window is
                         open and the player still hasn't confirmed. --}}
                    @foreach ($checkInPending as $gamePlayer)
                        <div class="mt-3 rounded-2xl bg-pitch-900 border border-emerald-500/40 p-5 shadow-lg shadow-emerald-500/10">
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="flex items-center justify-center w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 shrink-0">
                                    <x-heroicon-o-hand-raised class="w-7 h-7" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-lg font-extrabold text-white">{{ __('Você joga hoje!') }}</p>
                                    <p class="mt-0.5 text-sm text-pitch-300">
                                        {{ $gamePlayer->game->start_time->format('H:i') }} &middot; {{ $gamePlayer->game->location }}
                                    </p>
                                </div>
                                <form method="post" action="{{ route('games.check-in', $gamePlayer->game) }}" class="w-full sm:w-auto">
                                    @csrf
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-500 hover:bg-emerald-400 shadow-sm shadow-emerald-500/30 transition">
                                        {{ __('Confirmar presença') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach

                    {{-- SOS calls in the player's region — goalkeepers only --}}
                    @if (Auth::user()->isGoalkeeper())
                    <a href="{{ route('sos-opportunities.index') }}" class="group relative block mt-3 rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 text-white p-5 shadow-lg shadow-red-500/30 hover:shadow-xl hover:shadow-red-500/40 transition transform hover:-translate-y-0.5">
                        @if ($pendingSosApplicationsCount > 0)
                            <span class="absolute top-4 right-4 inline-flex items-center rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wider">
                                {{ trans_choice(':count candidatura|:count candidaturas', $pendingSosApplicationsCount, ['count' => $pendingSosApplicationsCount]) }}
                            </span>
                        @endif

                        <div class="flex items-center gap-4">
                            <span class="flex items-center justify-center w-12 h-12 rounded-2xl bg-white/15 shrink-0">
                                <x-heroicon-o-megaphone class="w-7 h-7" />
                            </span>
                            <div>
                                <p class="text-lg sm:text-xl font-extrabold uppercase tracking-wide">{{ __('SOS na sua região') }}</p>
                                <p class="mt-0.5 text-sm text-red-50">{{ __('Chamadas pagas de última hora. Candidate-se e o organizador escolhe.') }}</p>
                            </div>
                        </div>
                    </a>
                    @endif

                    {{-- Quick actions --}}
                    <div>
                        <h2 class="text-sm mt-3 font-bold uppercase tracking-wide text-pitch-400 mb-3">
                            {{ __('Acesso rápido') }}
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <x-action-card :href="route('games.search')" icon="heroicon-o-magnifying-glass" color="emerald"
                                :title="__('Procurar Partidas')" :description="__('Peladas abertas na sua região')" />
                            <x-action-card :href="route('games.mine')" icon="heroicon-o-trophy" color="emerald"
                                :title="__('Minhas Partidas')" :description="__('Confirmadas, pendentes e finalizadas')" />
                            <x-action-card :href="route('invitations.index')" icon="heroicon-o-envelope" color="blue"
                                :title="__('Convites')" :description="__('Veja quem quer contar com você')" />
                            <x-action-card :href="route('player-profile.edit')" icon="heroicon-o-user" color="gray"
                                :title="__('Meu Perfil')" :description="__('Dados esportivos e foto')" />
                        </div>
                    </div>
                </div>
                @if ($playerProfile)
                    <div class="space-y-3 lg:sticky lg:top-6">
                        <x-player-card :player-profile="$playerProfile" />
                        <p class="text-sm text-pitch-400 flex items-center justify-center gap-1">
                            <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" />
                            {{ $playerProfile->city }}@if ($playerProfile->state), {{ $playerProfile->state }}@endif
                        </p>
                    </div>
                @endif
            </div>
        </div>
</x-app-layout>