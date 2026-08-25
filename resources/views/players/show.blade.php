<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-user" :title="__('Perfil do Jogador')" />
    </x-slot>

    @php
        $dayLabels = [
            0 => 'Domingo',
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
        ];
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('players.search') }}" class="inline-flex items-center gap-1 text-sm font-medium text-pitch-400 hover:text-white">
                &larr; {{ __('Voltar para a busca') }}
            </a>

            @if (session('status') === 'invitation-sent')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Convite enviado com sucesso!') }}
                </p>
            @endif

            {{-- Player card --}}
            <div class="space-y-3">
                <x-player-card :player-profile="$playerProfile" />
                <p class="text-sm text-pitch-400 flex items-center justify-center gap-1">
                    <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" /> {{ $playerProfile->city }}@if ($playerProfile->state), {{ $playerProfile->state }}@endif
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <x-stat-card icon="heroicon-o-flag" :label="__('Posições')" :value="$playerProfile->positions ? implode(', ', $playerProfile->positions) : '—'" color="emerald" />
                <x-stat-card icon="heroicon-o-trophy" :label="__('Modalidades')" :value="$playerProfile->modalities ? implode(', ', $playerProfile->modalities) : '—'" color="blue" />
                <x-stat-card icon="heroicon-o-chart-bar" :label="__('Nível')" :value="$playerProfile->level" color="amber" />
                <x-stat-card icon="heroicon-o-currency-dollar" :label="__('Valor/partida')" :value="'R$ '.number_format((float) $playerProfile->price_per_game, 2, ',', '.')" color="gray" />
                @if ($playerProfile->ratings_count > 0)
                    <x-stat-card
                        icon="heroicon-s-star"
                        :label="__(':count avaliações', ['count' => $playerProfile->ratings_count])"
                        :value="number_format((float) $playerProfile->average_rating, 1, ',', '.').' / 5'"
                        color="amber"
                    />
                @endif
            </div>

            <x-player-history :player-profile="$playerProfile" />

            @if ($playerProfile->plays_outside_city)
                <div class="bg-blue-500/10 border border-blue-500/20 rounded-2xl p-4 text-sm text-blue-300 flex items-center gap-2">
                    <x-heroicon-o-truck class="w-4 h-4 shrink-0" />
                    {{ __('Também atua fora da cidade por') }}
                    <span class="font-bold">R$ {{ number_format((float) $playerProfile->price_per_game_outside, 2, ',', '.') }}</span>
                </div>
            @endif

            <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                <h2 class="text-sm font-bold uppercase tracking-wide text-pitch-400">{{ __('Disponibilidade') }}</h2>
                @if ($playerProfile->user->availabilities->isEmpty())
                    <p class="mt-2 text-sm text-pitch-400">{{ __('Nenhuma disponibilidade informada.') }}</p>
                @else
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($playerProfile->user->availabilities as $availability)
                            <li class="px-3 py-1.5 text-sm font-medium rounded-full bg-pitch-800 border border-pitch-700 text-pitch-200">
                                {{ __($dayLabels[$availability->day_of_week]) }}
                                <span class="text-pitch-500">{{ $availability->start_time->format('H:i') }}–{{ $availability->end_time->format('H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                @if ($playerProfile->user_id === auth()->id())
                    <div class="bg-pitch-800 border border-pitch-700 rounded-2xl p-4 text-center text-sm text-pitch-400">
                        {{ __('Este é o seu perfil.') }}
                    </div>
                @elseif (auth()->user()->hasRole(\App\Models\User::ROLE_ORGANIZER))
                    <a href="{{ route('invitations.create', $playerProfile) }}" class="w-full inline-flex justify-center items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                        {{ __('Convidar para Partida') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
