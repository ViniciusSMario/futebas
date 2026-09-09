@php
    $game = $entry['game'];
    $badgeColor = match ($entry['bucket']) {
        'confirmadas' => 'emerald',
        'pendentes' => 'amber',
        default => 'gray',
    };
    $isConfirmedPlayer = $entry['role'] === 'player' && $entry['bucket'] === 'confirmadas';

    $gamePlayer = $entry['game_player'] ?? null;
    $hasCheckedIn = $gamePlayer?->hasCheckedIn() ?? false;
    $canCheckIn = $gamePlayer?->canCheckIn() ?? false;

    $isOrganizer = $entry['role'] === 'organizer';

    // Um convite pendente ainda não é participação: quem foi convidado e
    // não respondeu não tem vaga nesta partida, e a tela dela responde 403.
    // Esse cartão continua levando à resposta, em "Convites".
    $canOpenGame = $isOrganizer || $gamePlayer !== null;

    // Hoje e amanhã merecem destaque: são os dois dias em que o cartão
    // deixa de ser lembrete e vira a próxima coisa a fazer.
    $isImminent = $game->date->isToday() || $game->date->isTomorrow();
@endphp

<div class="bg-pitch-900 rounded-2xl border {{ $isImminent && $entry['bucket'] === 'confirmadas' ? 'border-emerald-500/40' : 'border-pitch-800' }} shadow-sm shadow-black/20 p-5 flex flex-col gap-3 hover:shadow-md hover:shadow-black/30 hover:border-pitch-700 transition">
    <div class="flex items-center justify-between gap-2">
        <x-badge :color="$badgeColor">{{ $entry['status_label'] }}</x-badge>
        <span class="text-[11px] font-bold text-pitch-500 uppercase tracking-wide">
            {{ $isOrganizer ? __('Organizador') : __('Convidado') }}
        </span>
    </div>

    {{-- O nome da partida é o título, e a data vem no vocabulário de quem
         marca pelada: "Hoje", "Amanhã", "Sáb, 12/09". Antes o título era
         `12/09/2026` e o nome não aparecia em lugar nenhum — três peladas
         confirmadas viravam três cartões idênticos identificados por uma
         data que ninguém usa para se lembrar de nada. --}}
    @if ($canOpenGame)
        <a href="{{ route('games.show', $game) }}" class="group flex items-start gap-2 -m-1 p-1 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
            <span class="min-w-0 flex-1">
                <span class="block text-lg font-extrabold text-white leading-tight truncate group-hover:text-emerald-300 transition">{{ $game->team_name }}</span>
                <span class="mt-0.5 flex items-center gap-1 text-sm font-bold {{ $isImminent ? 'text-emerald-400' : 'text-pitch-300' }}">
                    <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
                    {{ $game->whenLabel() }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif
                </span>
            </span>
            <x-heroicon-o-chevron-right class="w-5 h-5 shrink-0 mt-0.5 text-pitch-600 group-hover:text-emerald-400 group-hover:translate-x-0.5 transition" />
        </a>
    @else
        <div>
            <p class="text-lg font-extrabold text-white leading-tight truncate">{{ $game->team_name }}</p>
            <p class="mt-0.5 flex items-center gap-1 text-sm font-bold {{ $isImminent ? 'text-emerald-400' : 'text-pitch-300' }}">
                <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
                {{ $game->whenLabel() }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif
            </p>
        </div>
    @endif

    <dl class="text-sm text-pitch-200 space-y-1.5 pt-2 border-t border-pitch-800">
        <div class="flex justify-between gap-2">
            <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}</dt>
            <dd class="text-right min-w-0 truncate">
                <a href="{{ $game->mapUrl() }}" target="_blank" rel="noopener" class="font-semibold text-emerald-400 hover:text-emerald-300 underline decoration-emerald-500/40 underline-offset-2">
                    {{ $game->location }}
                </a>
            </dd>
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

    @if ($isOrganizer)
        <a href="{{ route('games.show', $game) }}" class="inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
            <x-heroicon-o-cog-6-tooth class="w-4 h-4" /> {{ __('Gerenciar') }}
        </a>
    @elseif ($canOpenGame)
        {{-- O jogador não tinha para onde ir a partir daqui: quem vai,
             como os times ficaram e o endereço da partida ficavam todos
             atrás de uma tela que só o organizador abria. --}}
        <a href="{{ route('games.show', $game) }}" class="inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-100 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
            <x-heroicon-o-user-group class="w-4 h-4" /> {{ __('Ver partida') }}
        </a>
    @endif

    @if ($isOrganizer && $game->isEligibleToFinish())
        <form method="post" action="{{ route('games.finish', $game) }}">
            @csrf
            @method('patch')
            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-amber-600 hover:bg-amber-700 transition">
                <x-heroicon-o-flag class="w-4 h-4" /> {{ __('Finalizar Partida') }}
            </button>
        </form>
    @elseif ($isOrganizer && $entry['bucket'] === 'finalizadas' && $game->hasEnded())
        <a href="{{ route('ratings.index', $game) }}" class="inline-flex items-center justify-center gap-1.5 mt-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
            <x-heroicon-o-star class="w-4 h-4" /> {{ __('Avaliar Jogadores') }}
        </a>
    @endif

    @if ($isConfirmedPlayer)
        <div class="mt-1">
            <x-cancel-participation :game="$game" />
        </div>
    @endif
</div>
