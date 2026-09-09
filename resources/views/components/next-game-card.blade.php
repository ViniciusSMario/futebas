@props(['game', 'role' => 'player'])

@php
    $isOrganizer = $role === 'organizer';

    // A tela da partida agora é dos dois. O jogador ia parar no link
    // público, que o mandava de volta para "Minhas Partidas" assim que
    // reconhecia que ele já estava na partida — este cartão era um link
    // que devolvia a pessoa para perto de onde ela saiu.
    $href = route('games.show', $game);

    $weekday = $game->weekdayShort();
    $whenLabel = $game->dayLabel();

    $confirmed = $game->confirmedPlayersCount();
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 'group relative block overflow-hidden rounded-3xl bg-pitch-900 border border-pitch-800 shadow-card hover:border-emerald-500/40 hover:-translate-y-0.5 transition duration-200']) }}
>
    <div class="pointer-events-none absolute inset-0 field-lines opacity-30" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-16 -top-16 w-48 h-48 rounded-full bg-emerald-500/10 blur-2xl" aria-hidden="true"></div>

    <div class="relative p-5 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex flex-col items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500/15 text-emerald-400 shrink-0">
                <span class="text-[10px] font-black uppercase tracking-wide leading-none">{{ Illuminate\Support\Str::upper($weekday) }}</span>
                <span class="text-2xl font-black leading-none mt-1 tabular-nums">{{ $game->date->format('d') }}</span>
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-black uppercase tracking-widest text-emerald-400">
                    {{ $isOrganizer ? __('Sua próxima partida') : __('Você joga') }} · {{ $whenLabel }}
                </p>
                <p class="mt-1 text-lg sm:text-xl font-black text-white leading-tight truncate">{{ $game->team_name }}</p>
                {{-- O `truncate` vai no <span> do texto, não no flex que o
                     contém: num flex container ele não corta nada, e o
                     nome comprido de uma quadra passaria por cima. --}}
                <p class="mt-0.5 flex items-center gap-1 text-sm text-pitch-300">
                    <x-heroicon-o-map-pin class="w-3.5 h-3.5 shrink-0" />
                    <span class="min-w-0 truncate">{{ $game->location }}</span>
                </p>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-pitch-400">
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-clock class="w-3.5 h-3.5 shrink-0" />
                        {{ $game->start_time->format('H:i') }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif
                    </span>
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-trophy class="w-3.5 h-3.5 shrink-0" />
                        {{ $game->modality }}
                    </span>
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-currency-dollar class="w-3.5 h-3.5 shrink-0" />
                        R$ {{ number_format((float) $game->price, 2, ',', '.') }}
                    </span>
                </p>
            </div>

            <x-heroicon-o-chevron-right class="hidden xs:block w-5 h-5 shrink-0 text-pitch-600 group-hover:text-emerald-400 group-hover:translate-x-0.5 transition" />
        </div>

        <x-slots-progress :current="$confirmed" :max="$game->max_players" class="mt-5" />
    </div>
</a>
