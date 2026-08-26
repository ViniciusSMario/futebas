@props(['playerProfile'])

@php
    $rate = $playerProfile->attendance_rate;
    $hasHistory = $playerProfile->hasAttendanceHistory();

    // Below 70% turning up is a pattern, not bad luck.
    $rateColor = match (true) {
        $rate === null => 'gray',
        (float) $rate >= 90 => 'emerald',
        (float) $rate >= 70 => 'amber',
        default => 'red',
    };
@endphp

<div {{ $attributes->merge(['class' => 'bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6']) }}>
    <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400">
        <x-heroicon-o-chart-bar class="w-4 h-4" /> {{ __('Histórico') }}
    </h3>

    @if (! $hasHistory)
        <p class="mt-3 text-sm text-pitch-400">
            {{ __('Ainda não há partidas finalizadas no histórico deste jogador.') }}
        </p>
    @else
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
            <x-stat-card icon="heroicon-o-trophy" :label="__('Partidas jogadas')" :value="$playerProfile->games_played" color="emerald" />
            <x-stat-card
                icon="heroicon-o-hand-raised"
                :label="__('Presença')"
                :value="$rate === null ? '-' : number_format((float) $rate, 0, ',', '.').'%'"
                :color="$rateColor"
            />
            <x-stat-card icon="heroicon-o-user-minus" :label="__('Faltas')" :value="$playerProfile->no_shows" :color="$playerProfile->no_shows > 0 ? 'red' : 'gray'" />
            <x-stat-card icon="heroicon-o-clock" :label="__('Cancelou antes')" :value="$playerProfile->cancellations" color="gray" />
        </div>

        <p class="mt-3 text-xs text-pitch-500">
            {{ __('Presença considera as partidas finalizadas em que estava confirmado. Cancelar com antecedência não conta como falta.') }}
        </p>
    @endif
</div>
