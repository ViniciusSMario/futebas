@php
    $confirmed = $game->confirmedPlayersCount();
    $isFull = $game->isFull();
    $wantedPositions = $game->positions ?: [];
@endphp

<div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 flex flex-col gap-3 hover:shadow-md hover:shadow-black/30 hover:border-emerald-600/40 transition">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h3 class="font-extrabold text-white truncate">{{ $game->team_name }}</h3>
            <p class="text-xs text-pitch-400 truncate flex items-center gap-1">
                <x-heroicon-o-user class="w-3.5 h-3.5 shrink-0" /> {{ $game->user->name }}
            </p>
        </div>
        <x-badge color="gray">{{ $game->modality }}</x-badge>
    </div>

    <div class="pt-2 border-t border-pitch-800">
        <p class="text-lg font-extrabold text-white">{{ $game->date->format('d/m/Y') }}</p>
        <p class="text-sm text-pitch-400 flex items-center gap-1">
            <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
            {{ $game->start_time->format('H:i') }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif
        </p>
    </div>

    <p class="text-sm text-pitch-200 flex items-start gap-1.5">
        <x-heroicon-o-map-pin class="w-4 h-4 shrink-0 mt-0.5 text-pitch-500" />
        <span class="min-w-0">
            <span class="font-semibold">{{ $game->location }}</span>
            <span class="block text-xs text-pitch-500">{{ $game->city }}</span>
        </span>
    </p>

    @if ($wantedPositions)
        <div class="flex flex-wrap gap-1.5">
            @foreach ($wantedPositions as $position)
                <x-badge color="emerald">{{ $position }}</x-badge>
            @endforeach
        </div>
    @endif

    <x-slots-progress :current="$confirmed" :max="$game->max_players" class="pt-1" />

    <div class="flex items-center justify-between gap-2 pt-2 border-t border-pitch-800 grow">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Valor') }}</p>
            <p class="font-extrabold text-white">R$ {{ number_format((float) $game->price, 2, ',', '.') }}</p>
        </div>
        @if ($game->requires_approval)
            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-400">
                <x-heroicon-o-shield-check class="w-3.5 h-3.5 shrink-0" /> {{ __('Sujeito a aprovação') }}
            </span>
        @endif
    </div>

    <a href="{{ route('public-games.show', $game) }}"
        class="inline-flex justify-center items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white transition {{ $isFull ? 'bg-pitch-700 hover:bg-pitch-600' : 'bg-emerald-600 hover:bg-emerald-500' }}">
        <x-heroicon-o-arrow-right-circle class="w-4 h-4" />
        {{ $isFull ? __('Entrar na lista de espera') : __('Ver e participar') }}
    </a>
</div>
