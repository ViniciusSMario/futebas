@php
    $game = $entry['game'];
    $badgeColor = match ($entry['bucket']) {
        'confirmadas' => 'emerald',
        'pendentes' => 'amber',
        default => 'gray',
    };
    $isConfirmedPlayer = $entry['role'] === 'player' && $entry['bucket'] === 'confirmadas';
    $canCancelParticipation = $isConfirmedPlayer && $game->isCancellableByPlayer();

    $gamePlayer = $entry['game_player'] ?? null;
    $hasCheckedIn = $gamePlayer?->hasCheckedIn() ?? false;
    $canCheckIn = $gamePlayer?->canCheckIn() ?? false;
@endphp

<div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 flex flex-col gap-3 hover:shadow-md hover:shadow-black/30 hover:border-pitch-700 transition">
    <div class="flex items-center justify-between gap-2">
        <x-badge :color="$badgeColor">{{ $entry['status_label'] }}</x-badge>
        <span class="text-[11px] font-bold text-pitch-500 uppercase tracking-wide">
            {{ $entry['role'] === 'organizer' ? __('Organizador') : __('Convidado') }}
        </span>
    </div>

    <div>
        <p class="text-lg font-extrabold text-white">{{ $game->date->format('d/m/Y') }}</p>
        <p class="text-sm text-pitch-400 flex items-center gap-1">
            <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
            {{ $game->start_time->format('H:i') }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif
        </p>
    </div>

    <dl class="text-sm text-pitch-200 space-y-1.5 pt-2 border-t border-pitch-800">
        <div class="flex justify-between gap-2">
            <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}</dt>
            <dd class="font-semibold text-right">{{ $game->location }}</dd>
        </div>
        <div class="flex justify-between gap-2">
            <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Modalidade') }}</dt>
            <dd class="font-semibold">{{ $game->modality }}</dd>
        </div>
        @if ($entry['team'])
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-user-group class="w-4 h-4" /> {{ __('Time') }}</dt>
                <dd class="font-semibold">{{ $entry['team'] }}</dd>
            </div>
        @endif
        @if ($entry['position'])
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-flag class="w-4 h-4" /> {{ __('Posição') }}</dt>
                <dd class="font-semibold">{{ $entry['position'] }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-2">
            <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valor') }}</dt>
            <dd class="font-bold text-white">R$ {{ number_format((float) $game->price, 2, ',', '.') }}</dd>
        </div>
    </dl>

    @if ($hasCheckedIn)
        <div class="flex items-center justify-between gap-2 mt-1 rounded-xl bg-emerald-500/10 border border-emerald-500/30 px-3 py-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-emerald-400">
                <x-heroicon-s-check-circle class="w-4 h-4 shrink-0" /> {{ __('Presença confirmada') }}
            </span>
            @if ($game->isCheckInOpen())
                <form method="post" action="{{ route('games.check-in.undo', $game) }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="text-[11px] font-semibold text-pitch-400 hover:text-white underline">
                        {{ __('Desfazer') }}
                    </button>
                </form>
            @endif
        </div>
    @elseif ($canCheckIn)
        <form method="post" action="{{ route('games.check-in', $game) }}" class="mt-1">
            @csrf
            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-500 hover:bg-emerald-400 shadow-sm shadow-emerald-500/30 transition">
                <x-heroicon-o-hand-raised class="w-4 h-4" /> {{ __('Confirmar presença') }}
            </button>
        </form>
    @endif

    @if ($entry['role'] === 'organizer')
        <a href="{{ route('games.show', $game) }}" class="inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
            <x-heroicon-o-cog-6-tooth class="w-4 h-4" /> {{ __('Gerenciar') }}
        </a>
    @endif

    @if ($entry['role'] === 'organizer' && $game->isEligibleToFinish())
        <form method="post" action="{{ route('games.finish', $game) }}">
            @csrf
            @method('patch')
            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-amber-600 hover:bg-amber-700 transition">
                <x-heroicon-o-flag class="w-4 h-4" /> {{ __('Finalizar Partida') }}
            </button>
        </form>
    @elseif ($entry['role'] === 'organizer' && $entry['bucket'] === 'finalizadas' && $game->hasEnded())
        <a href="{{ route('ratings.index', $game) }}" class="inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
            <x-heroicon-o-star class="w-4 h-4" /> {{ __('Avaliar Jogadores') }}
        </a>
    @endif

    @if ($canCancelParticipation)
        <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'cancel-participation-{{ $game->id }}')"
            class="w-full inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-red-400 border border-red-900/60 bg-red-950/40 hover:bg-red-900/40 transition">
            <x-heroicon-o-x-circle class="w-4 h-4" /> {{ __('Cancelar participação') }}
        </button>

        <x-modal name="cancel-participation-{{ $game->id }}" focusable>
            <form method="post" action="{{ route('games.leave', $game) }}" class="p-6">
                @csrf
                @method('delete')

                <h2 class="text-lg font-medium text-white">
                    {{ __('Cancelar sua participação?') }}
                </h2>

                <p class="mt-1 text-sm text-pitch-400">
                    {{ __('Você está cancelando sua participação na partida de :date, às :time. Se quiser, informe o motivo — o organizador poderá ver essa justificativa.', ['date' => $game->date->format('d/m/Y'), 'time' => $game->start_time->format('H:i')]) }}
                </p>

                <div class="mt-4">
                    <x-input-label for="reason-{{ $game->id }}" :value="__('Justificativa (opcional)')" />
                    <textarea id="reason-{{ $game->id }}" name="reason" rows="3" maxlength="500"
                        class="mt-1 block w-full bg-pitch-800 border-pitch-700 text-white placeholder-pitch-500 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm"
                        placeholder="{{ __('Ex: imprevisto de última hora...') }}"></textarea>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">
                        {{ __('Voltar') }}
                    </x-secondary-button>

                    <x-danger-button>
                        {{ __('Confirmar cancelamento') }}
                    </x-danger-button>
                </div>
            </form>
        </x-modal>
    @elseif ($isConfirmedPlayer)
        <p class="text-[11px] text-pitch-500 text-center mt-1">
            {{ __('Cancelamento indisponível: faltam menos de 24h para a partida.') }}
        </p>
    @endif
</div>
