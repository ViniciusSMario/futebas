<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-trophy" :title="__('Início')" :subtitle="__('Seu resumo como jogador')" />
    </x-slot>

    @php
        $dayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $position = $playerProfile?->positions[0] ?? null;
    @endphp

    <div class="py-5 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8 items-start">

                {{-- Coluna principal --}}
                <div class="space-y-6 min-w-0">

                    {{-- Saudação --}}
                    <section class="relative overflow-hidden rounded-3xl border border-emerald-500/20">
                        <div class="absolute inset-0" aria-hidden="true">
                            <x-img-placeholder
                                ratio="21/9"
                                :label="__('Banner do painel')"
                                src="images/landing/jogadores.jpg"
                                size="1600x680"
                                :note="__('Panorâmica e escura - fica atrás da saudação.')"
                                rounded="rounded-none"
                                class="w-full h-full"
                            />
                            <div class="absolute inset-0 bg-gradient-to-r from-emerald-700/95 via-emerald-800/90 to-pitch-950/90"></div>
                        </div>

                        <div class="relative flex items-center gap-4 p-5 sm:p-7">
                            <x-avatar :user="Auth::user()" size="lg" ring="ring-2 ring-white/20" class="hidden xs:flex" />
                            <div class="min-w-0">
                                <h2 class="text-xl sm:text-3xl font-black text-white truncate">
                                    {{ __('Olá, :name!', ['name' => Str::before(Auth::user()->name, ' ')]) }}
                                </h2>
                                <p class="mt-1 text-sm sm:text-base text-emerald-50/90 font-medium">
                                    {{ __('Seu futebol. Sua região. Sua partida.') }}
                                </p>
                            </div>
                        </div>
                    </section>

                    {{-- Check-in: a única coisa realmente urgente da tela, por
                         isso vem antes de tudo que é resumo. --}}
                    @foreach ($checkInPending as $gamePlayer)
                        <div class="rounded-3xl bg-pitch-900 border border-emerald-500/40 p-5 shadow-glow">
                            <div class="flex flex-wrap items-center gap-4">
                                <span class="flex items-center justify-center w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 shrink-0">
                                    <x-heroicon-o-hand-raised class="w-7 h-7" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-lg font-black text-white">{{ __('Você joga hoje!') }}</p>
                                    <p class="mt-0.5 text-sm text-pitch-300 truncate">
                                        {{ $gamePlayer->game->start_time->format('H:i') }} &middot; {{ $gamePlayer->game->location }}
                                    </p>
                                </div>
                                <form method="post" action="{{ route('games.check-in', $gamePlayer->game) }}" class="w-full sm:w-auto">
                                    @csrf
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-6 min-h-[48px] rounded-xl font-black text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/25 transition">
                                        <x-heroicon-o-check class="w-4 h-4" />
                                        {{ __('Confirmar presença') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach

                    {{-- Perfil incompleto: sem ele o jogador não aparece nas
                         buscas, então essa é a próxima ação, não um aviso. --}}
                    @if (! $playerProfile)
                        <x-empty-state
                            icon="heroicon-o-pencil-square"
                            :title="__('Complete seu perfil esportivo')"
                            :description="__('Falta pouco! Informe posição, modalidade, nível e valor para aparecer nas buscas dos organizadores.')"
                        >
                            <x-slot name="action">
                                <a href="{{ route('player-profile.edit') }}" class="inline-flex items-center gap-2 px-6 min-h-[48px] rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 transition">
                                    {{ __('Completar perfil') }}
                                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                                </a>
                            </x-slot>
                        </x-empty-state>
                    @endif

                    {{-- Próxima partida. Fica fora do bloco acima de propósito:
                         saber quando você joga não depende de ter preenchido o
                         perfil esportivo. --}}
                    @if ($nextGame)
                        <section>
                            <x-section-heading :title="__('Próxima partida')" icon="heroicon-o-calendar-days">
                                <x-slot name="action">
                                    <a href="{{ route('games.mine') }}" class="text-xs font-black uppercase tracking-wide text-emerald-400 hover:text-emerald-300">
                                        {{ __('Ver todas') }}
                                    </a>
                                </x-slot>
                            </x-section-heading>

                            <x-next-game-card :game="$nextGame" role="player" />
                        </section>
                    @else
                        <x-empty-state
                            icon="heroicon-o-calendar-days"
                            :title="__('Nenhuma partida confirmada')"
                            :description="__('Procure peladas abertas na sua região e garanta a sua vaga.')"
                        >
                            <x-slot name="action">
                                <a href="{{ route('games.search') }}" class="inline-flex items-center gap-2 px-6 min-h-[48px] rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 transition">
                                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                                    {{ __('Procurar Partidas') }}
                                </a>
                            </x-slot>
                        </x-empty-state>
                    @endif

                    @if ($playerProfile)
                        {{-- Números --}}
                        <section>
                            <x-section-heading :title="__('Seu perfil')" icon="heroicon-o-identification" />

                            <div class="grid grid-cols-3 gap-3 sm:gap-4">
                                <x-stat-card icon="heroicon-o-flag" :label="__('Posição')" :value="$position ?? '-'" color="emerald" />
                                <x-stat-card icon="heroicon-o-chart-bar" :label="__('Nível')" :value="$playerProfile->level" color="blue" />
                                <x-stat-card
                                    icon="heroicon-o-envelope"
                                    :label="__('Convites')"
                                    :value="$pendingInvitationsCount"
                                    color="amber"
                                    :href="route('invitations.index')"
                                />
                            </div>
                        </section>

                        {{-- Disponibilidade --}}
                        <section class="rounded-3xl bg-pitch-900 border border-pitch-800 shadow-card p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-pitch-300">
                                    <x-heroicon-o-clock class="w-4 h-4 text-emerald-400 shrink-0" />
                                    {{ __('Disponibilidade') }}
                                </h2>
                                <a href="{{ route('availability.edit') }}" class="text-xs font-black text-emerald-400 hover:text-emerald-300 uppercase tracking-wide">
                                    {{ __('Editar') }}
                                </a>
                            </div>

                            @if ($availabilities->isEmpty())
                                <p class="mt-3 text-sm text-pitch-300 leading-relaxed">
                                    {{ __('Nenhuma disponibilidade cadastrada.') }}
                                    <a href="{{ route('availability.edit') }}" class="text-emerald-400 font-bold underline underline-offset-2">{{ __('Informar agora') }}</a>
                                </p>
                            @else
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach ($availabilities as $availability)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-pitch-800 border border-pitch-700 text-xs font-bold text-pitch-200">
                                            {{ $dayLabels[$availability->day_of_week] }}
                                            <span class="text-pitch-400 font-medium tabular-nums">{{ $availability->start_time->format('H:i') }}–{{ $availability->end_time->format('H:i') }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endif

                    {{-- SOS na região - só goleiros --}}
                    @if (Auth::user()->isGoalkeeper())
                        <a href="{{ route('sos-opportunities.index') }}" class="group relative block overflow-hidden rounded-3xl bg-gradient-to-br from-red-600 via-red-500 to-orange-500 p-5 sm:p-6 shadow-glow-red hover:-translate-y-0.5 transition duration-200">
                            <div class="absolute inset-0 noise pointer-events-none" aria-hidden="true"></div>

                            <div class="relative flex items-center gap-4">
                                <span class="flex items-center justify-center w-14 h-14 rounded-2xl bg-white/20 shrink-0">
                                    <x-heroicon-o-megaphone class="w-8 h-8 text-white" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-lg sm:text-xl font-black uppercase tracking-wide text-white">{{ __('SOS na sua região') }}</p>
                                        @if ($pendingSosApplicationsCount > 0)
                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-red-600">
                                                {{ trans_choice(':count candidatura|:count candidaturas', $pendingSosApplicationsCount, ['count' => $pendingSosApplicationsCount]) }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-red-50 leading-snug">{{ __('Chamadas pagas de última hora. Candidate-se e o organizador escolhe.') }}</p>
                                </div>

                                <x-heroicon-o-chevron-right class="hidden xs:block w-6 h-6 shrink-0 text-white/70 group-hover:translate-x-0.5 transition" />
                            </div>
                        </a>
                    @endif

                    {{-- Acesso rápido --}}
                    <section>
                        <x-section-heading :title="__('Acesso rápido')" icon="heroicon-o-bolt" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <x-action-card :href="route('games.search')" icon="heroicon-o-magnifying-glass" color="emerald" :title="__('Procurar Partidas')" :description="__('Peladas abertas na sua região')" />
                            <x-action-card :href="route('games.mine')" icon="heroicon-o-trophy" color="violet" :title="__('Minhas Partidas')" :description="__('Confirmadas, pendentes e finalizadas')" />
                            <x-action-card
                                :href="route('invitations.index')"
                                icon="heroicon-o-envelope"
                                color="blue"
                                :title="__('Convites')"
                                :description="__('Veja quem quer contar com você')"
                                :badge="$pendingInvitationsCount ?: null"
                            />
                            <x-action-card :href="route('player-profile.edit')" icon="heroicon-o-user" color="gray" :title="__('Meu Perfil')" :description="__('Dados esportivos e foto')" />
                        </div>
                    </section>
                </div>

                {{-- Card do jogador. No celular fecha a página; no desktop
                     acompanha a rolagem ao lado. --}}
                @if ($playerProfile)
                    <aside class="space-y-3 lg:sticky lg:top-6">
                        <x-player-card :player-profile="$playerProfile" />

                        @if ($playerProfile->city)
                            <p class="text-sm text-pitch-400 flex items-center justify-center gap-1">
                                <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" />
                                {{ $playerProfile->city }}@if ($playerProfile->state), {{ $playerProfile->state }}@endif
                            </p>
                        @endif

                        <a href="{{ route('player-profile.edit') }}" class="flex items-center justify-center gap-2 w-full min-h-[44px] rounded-xl border border-pitch-700 text-xs font-black uppercase tracking-widest text-pitch-200 hover:bg-pitch-800 hover:text-white transition">
                            <x-heroicon-o-pencil-square class="w-4 h-4" />
                            {{ __('Editar card') }}
                        </a>
                    </aside>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
