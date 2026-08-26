<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-user-group" :title="__('Início')" :subtitle="__('Seu resumo como organizador')">
            <x-slot name="action">
                <a href="{{ route('games.create') }}" class="hidden lg:inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/20 transition">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    {{ __('Criar Partida') }}
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Saudação. A foto de fundo é o único elemento decorativo da
                 tela: entra pelo placeholder e some atrás do gradiente. --}}
            <section class="relative overflow-hidden rounded-3xl border border-emerald-500/20">
                <div class="absolute inset-0" aria-hidden="true">
                    <x-img-placeholder
                        ratio="21/9"
                        :label="__('Banner do painel')"
                        src="images/landing/organizadores.jpg"
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

            {{-- Próxima partida: o motivo mais provável de o organizador ter
                 aberto o app. Vem antes dos números. --}}
            @if ($nextGame)
                <section>
                    <x-section-heading :title="__('Próxima partida')" icon="heroicon-o-calendar-days">
                        <x-slot name="action">
                            <a href="{{ route('games.mine') }}" class="text-xs font-black uppercase tracking-wide text-emerald-400 hover:text-emerald-300">
                                {{ __('Ver todas') }}
                            </a>
                        </x-slot>
                    </x-section-heading>

                    <x-next-game-card :game="$nextGame" role="organizer" />
                </section>
            @else
                <x-empty-state
                    icon="heroicon-o-calendar-days"
                    :title="__('Nenhuma partida marcada')"
                    :description="__('Crie a próxima pelada e comece a chamar o pessoal da sua região.')"
                >
                    <x-slot name="action">
                        <a href="{{ route('games.create') }}" class="inline-flex items-center gap-2 px-6 min-h-[48px] rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 transition">
                            <x-heroicon-o-plus class="w-4 h-4" />
                            {{ __('Criar Partida') }}
                        </a>
                    </x-slot>
                </x-empty-state>
            @endif

            {{-- SOS Goleiro. Sobe para cá quando há gente esperando decisão. --}}
            <a href="{{ route('sos.index') }}" class="group relative block overflow-hidden rounded-3xl bg-gradient-to-br from-red-600 via-red-500 to-orange-500 p-5 sm:p-6 shadow-glow-red hover:-translate-y-0.5 transition duration-200">
                <div class="absolute inset-0 noise pointer-events-none" aria-hidden="true"></div>

                <div class="relative flex items-center gap-4">
                    <span class="flex items-center justify-center w-14 h-14 rounded-2xl bg-white/20 shrink-0">
                        <x-heroicon-o-megaphone class="w-8 h-8 text-white" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-lg sm:text-xl font-black uppercase tracking-wide text-white">
                                {{ __('Preciso de Goleiro') }}
                            </p>
                            @if ($pendingSosApplicationsCount > 0)
                                <span class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-red-600 animate-pulse">
                                    {{ trans_choice(':count candidatura|:count candidaturas', $pendingSosApplicationsCount, ['count' => $pendingSosApplicationsCount]) }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-red-50 leading-snug">
                            @if ($pendingSosApplicationsCount > 0)
                                {{ __('Goleiros esperando sua decisão. Compare valor, local e avaliação.') }}
                            @else
                                {{ __('Avise a região e ache um goleiro rapidinho.') }}
                            @endif
                        </p>
                    </div>

                    <x-heroicon-o-chevron-right class="hidden xs:block w-6 h-6 shrink-0 text-white/70 group-hover:translate-x-0.5 transition" />
                </div>
            </a>

            {{-- Números --}}
            <section>
                <x-section-heading :title="__('Seus números')" icon="heroicon-o-chart-bar" />

                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <x-stat-card
                        icon="heroicon-o-trophy"
                        :label="__('Partidas criadas')"
                        :value="$gamesCreatedCount"
                        color="emerald"
                        :href="route('games.mine')"
                    />
                    <x-stat-card
                        icon="heroicon-o-envelope"
                        :label="__('Convites pendentes')"
                        :value="$pendingInvitationsSentCount"
                        color="amber"
                    />
                    <x-stat-card
                        icon="heroicon-o-map-pin"
                        :label="__('Jogadores na região')"
                        :value="$playersNearbyCount ?? '-'"
                        :hint="Auth::user()->city"
                        color="blue"
                        :href="route('players.search')"
                        class="col-span-2 lg:col-span-1"
                    />
                </div>
            </section>

            {{-- Acesso rápido --}}
            <section>
                <x-section-heading :title="__('Acesso rápido')" icon="heroicon-o-bolt" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <x-action-card :href="route('players.search')" icon="heroicon-o-magnifying-glass" color="emerald" :title="__('Procurar Jogadores')" :description="__('Filtre por posição, nível e valor')" />
                    <x-action-card :href="route('games.create')" icon="heroicon-o-plus-circle" color="blue" :title="__('Criar Partida')" :description="__('Organize uma nova pelada')" />
                    <x-action-card :href="route('game-series.index')" icon="heroicon-o-arrow-path" color="violet" :title="__('Peladas Semanais')" :description="__('Sua pelada fixa, gerada sozinha')" />
                    <x-action-card :href="route('games.mine')" icon="heroicon-o-trophy" color="gray" :title="__('Minhas Partidas')" :description="__('Acompanhe e finalize os jogos')" />
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
