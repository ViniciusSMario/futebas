@php
    $confirmed = $gamePlayers->where('status', 'confirmed');
    $waitingList = $gamePlayers->where('status', 'waiting_list');
    $pending = $gamePlayers->where('status', 'pending');
    $cancelled = $gamePlayers->where('status', 'cancelled');

    $checkedIn = $confirmed->filter(fn ($gamePlayer) => $gamePlayer->hasCheckedIn());
    $missing = $confirmed->count() - $checkedIn->count();
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('game-players.create', $game) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
            <x-heroicon-o-user-plus class="w-4 h-4" /> {{ __('Adicionar Jogador') }}
        </a>
        <a href="{{ route('games.invitations.search', $game) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
            <x-heroicon-o-magnifying-glass class="w-4 h-4" /> {{ __('Buscar Jogadores') }}
        </a>
    </div>

    <x-slots-progress :current="$confirmed->count()" :max="$game->max_players" />

    @if ($game->hasCheckInStarted() && $confirmed->isNotEmpty())
        <div class="rounded-2xl border border-pitch-800 bg-pitch-900 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-pitch-400 flex items-center gap-1.5">
                        <x-heroicon-o-hand-raised class="w-4 h-4" /> {{ __('Presença de hoje') }}
                    </h3>
                    <p class="mt-1 text-sm text-pitch-300">
                        {{ __(':checked de :total confirmaram presença', ['checked' => $checkedIn->count(), 'total' => $confirmed->count()]) }}
                    </p>
                </div>
                @if ($missing > 0)
                    <x-badge color="amber">{{ trans_choice(':count sem confirmar|:count sem confirmar', $missing, ['count' => $missing]) }}</x-badge>
                @else
                    <x-badge color="emerald">{{ __('Todos confirmaram') }}</x-badge>
                @endif
            </div>
            <p class="mt-3 text-xs text-pitch-500">
                {{ __('O check-in abre :hours horas antes do início e é feito pelo próprio jogador. Convidados sem cadastro não confirmam por aqui.', ['hours' => \App\Models\Game::CHECK_IN_OPENS_HOURS_BEFORE]) }}
            </p>
        </div>
    @endif

    <section>
        <div class="flex items-center gap-2 mb-3">
            <h3 class="text-lg font-extrabold text-white">{{ __('Confirmados') }}</h3>
            <x-badge color="emerald">{{ $confirmed->count() }}</x-badge>
        </div>
        @if ($confirmed->isEmpty())
            <x-empty-state icon="heroicon-o-user-group" :title="__('Nenhum jogador confirmado ainda.')" />
        @else
            <div class="space-y-3">
                @foreach ($confirmed as $gamePlayer)
                    <x-game-participant-row :game="$game" :gamePlayer="$gamePlayer" :actions="['payment', 'no-show', 'remove']" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($waitingList->isNotEmpty())
        <section>
            <div class="flex items-center gap-2 mb-3">
                <h3 class="text-lg font-extrabold text-white">{{ __('Lista de Espera') }}</h3>
                <x-badge color="amber">{{ $waitingList->count() }}</x-badge>
            </div>
            <div class="space-y-3">
                @foreach ($waitingList as $index => $gamePlayer)
                    <div class="flex items-start gap-2">
                        <span class="mt-4 shrink-0 flex items-center justify-center w-6 h-6 rounded-full bg-pitch-800 text-xs font-bold text-pitch-400">{{ $index + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <x-game-participant-row :game="$game" :gamePlayer="$gamePlayer" :actions="['confirm', 'remove']" />
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($pending->isNotEmpty())
        <section>
            <div class="flex items-center gap-2 mb-3">
                <h3 class="text-lg font-extrabold text-white">{{ __('Pendentes de Aprovação') }}</h3>
                <x-badge color="amber">{{ $pending->count() }}</x-badge>
            </div>
            <div class="space-y-3">
                @foreach ($pending as $gamePlayer)
                    <x-game-participant-row :game="$game" :gamePlayer="$gamePlayer" :actions="['confirm', 'remove']" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($cancelled->isNotEmpty())
        <section>
            <div class="flex items-center gap-2 mb-3">
                <h3 class="text-lg font-extrabold text-white">{{ __('Removidos') }}</h3>
                <x-badge color="gray">{{ $cancelled->count() }}</x-badge>
            </div>
            <div class="space-y-3">
                @foreach ($cancelled as $gamePlayer)
                    <x-game-participant-row :game="$game" :gamePlayer="$gamePlayer" :actions="[]" :ratedUserIds="$ratedUserIds" />
                @endforeach
            </div>
        </section>
    @endif
</div>
