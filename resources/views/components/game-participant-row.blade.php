{{-- `viewer` decide o que a mesma linha conta.

     Para o organizador ela é uma ficha de administração: quanto a pessoa
     deve, se pagou, se faltou, e os botões que mexem nisso. Para quem
     apenas joga a partida ela é só "quem vai" — dinheiro alheio e a falta
     que o organizador anotou não são assunto de time, e a linha mostraria
     os dois se o padrão fosse mostrar tudo. --}}
@props([
    'game',
    'gamePlayer',
    'actions' => ['confirm', 'payment', 'remove'],
    'ratedUserIds' => null,
    'viewer' => 'organizer',
])

@php
    $isGuest = $gamePlayer->isGuest();
    $participant = $gamePlayer->participant();
    $position = ! $isGuest ? ($participant->playerProfile->positions[0] ?? null) : null;

    $statusColor = match ($gamePlayer->status) {
        'confirmed' => 'emerald',
        'waiting_list' => 'amber',
        'pending' => 'amber',
        default => 'gray',
    };

    $statusLabel = match ($gamePlayer->status) {
        'confirmed' => __('Confirmado'),
        'waiting_list' => __('Lista de espera'),
        'pending' => __('Pendente'),
        default => __('Cancelado'),
    };

    $isPaid = $gamePlayer->payment_status === 'paid';
    $isCancelled = $gamePlayer->status === 'cancelled';
    $isConfirmed = $gamePlayer->status === 'confirmed';

    // Attendance only starts meaning something once the check-in window
    // has opened. A guest contact has no account to check in with, so
    // "hasn't confirmed" is never said about them - only the organizer's
    // own no-show mark applies.
    $checkInStarted = $game->hasCheckInStarted();
    $hasCheckedIn = $gamePlayer->hasCheckedIn();
    $isNoShow = (bool) $gamePlayer->no_show;
    $isRated = ! $isGuest && $ratedUserIds !== null && $ratedUserIds->contains($gamePlayer->user_id);

    $isAdminView = $viewer === 'organizer';
    $actions = $isAdminView ? $actions : [];
@endphp

<div class="flex flex-wrap items-center gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 p-4">
    @if (! $isGuest && $participant->photo_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::url($participant->photo_path) }}" alt="{{ $participant->name }}" class="h-11 w-11 rounded-full object-cover shrink-0 ring-2 ring-pitch-800">
    @else
        <div class="h-11 w-11 rounded-full {{ $isGuest ? 'bg-pitch-800 text-pitch-400' : 'bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 text-emerald-300' }} flex items-center justify-center text-sm font-extrabold shrink-0">
            {{ Str::upper(Str::substr($participant->name, 0, 1)) }}
        </div>
    @endif

    <div class="min-w-0 flex-1">
        <p class="font-bold text-white truncate flex items-center gap-1.5">
            {{ $participant->name }}
            @if ($isGuest)
                <x-badge color="gray" class="text-[10px] py-0.5">{{ __('Sem cadastro') }}</x-badge>
            @endif
        </p>
        <p class="text-xs text-pitch-400 truncate">
            @if ($isAdminView)
                @if ($position) {{ $position }} &middot; @endif
                R$ {{ number_format((float) ($gamePlayer->amount_due ?? 0), 2, ',', '.') }}
            @else
                {{ $position ?? ($isGuest ? __('Convidado') : __('Sem posição informada')) }}
            @endif
        </p>
    </div>

    <div class="flex items-center gap-1.5 shrink-0">
        <x-badge :color="$statusColor">{{ $statusLabel }}</x-badge>
        @if ($isAdminView && ! $isCancelled)
            <x-badge :color="$isPaid ? 'emerald' : 'amber'">{{ $isPaid ? __('Pago') : __('Pendente') }}</x-badge>
        @endif

        @if ($isConfirmed && $isNoShow && $isAdminView)
            <x-badge color="red">{{ __('Faltou') }}</x-badge>
        @elseif ($isConfirmed && $hasCheckedIn)
            <x-badge color="emerald">
                <x-heroicon-s-check-circle class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" /> {{ __('Presente') }}
            </x-badge>
        @elseif ($isConfirmed && $checkInStarted && ! $isGuest)
            <x-badge color="amber">{{ __('Não confirmou') }}</x-badge>
        @endif
    </div>

    @if ($isAdminView && $isCancelled && $gamePlayer->cancellation_reason)
        <p class="w-full text-xs text-pitch-400 bg-pitch-800/60 border border-pitch-800 rounded-lg px-3 py-2">
            <span class="font-semibold text-pitch-300">{{ __('Motivo do cancelamento:') }}</span>
            {{ $gamePlayer->cancellation_reason }}
        </p>
    @endif

    @if ($isAdminView && $isCancelled && ! $isGuest && $ratedUserIds !== null)
        <div class="w-full flex items-center justify-end pt-1">
            @if ($isRated)
                <x-badge color="emerald">
                    <x-heroicon-s-check-circle class="w-3.5 h-3.5 inline -mt-0.5" /> {{ __('Avaliado') }}
                </x-badge>
            @else
                <a href="{{ route('ratings.create', [$game, $gamePlayer->user]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-white bg-emerald-600 hover:bg-emerald-700 transition">
                    <x-heroicon-o-star class="w-3.5 h-3.5" /> {{ __('Avaliar') }}
                </a>
            @endif
        </div>
    @endif

    @if ($actions !== [])
        <div class="w-full flex flex-wrap items-center gap-2 pt-3 mt-1 border-t border-pitch-800">
            @if (in_array('confirm', $actions) && in_array($gamePlayer->status, ['waiting_list', 'pending']))
                <form method="post" action="{{ route('game-players.confirm', [$game, $gamePlayer]) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-white bg-emerald-600 hover:bg-emerald-700 transition">
                        <x-heroicon-o-check class="w-3.5 h-3.5" /> {{ __('Confirmar') }}
                    </button>
                </form>
            @endif

            @if (in_array('payment', $actions) && $gamePlayer->status !== 'cancelled')
                <form method="post" action="{{ route('game-players.payment', [$game, $gamePlayer]) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide transition
                        {{ $isPaid ? 'text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700' : 'text-white bg-emerald-600 hover:bg-emerald-700' }}">
                        <x-heroicon-o-currency-dollar class="w-3.5 h-3.5" /> {{ $isPaid ? __('Marcar como Pendente') : __('Marcar como Pago') }}
                    </button>
                </form>
            @endif

            @if (in_array('no-show', $actions) && $isConfirmed && $checkInStarted)
                <form method="post" action="{{ route('game-players.no-show', [$game, $gamePlayer]) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide transition
                        {{ $isNoShow ? 'text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700' : 'text-amber-300 bg-amber-500/10 border border-amber-500/30 hover:bg-amber-500/20' }}">
                        <x-heroicon-o-user-minus class="w-3.5 h-3.5" /> {{ $isNoShow ? __('Desmarcar falta') : __('Marcar falta') }}
                    </button>
                </form>
            @endif

            @if (in_array('remove', $actions) && $gamePlayer->status !== 'cancelled')
                <form
                    method="post"
                    action="{{ route('game-players.destroy', [$game, $gamePlayer]) }}"
                    data-confirm="{{ __('Remover :nome da partida?', ['nome' => $participant->name]) }}"
                    data-confirm-text="{{ __('A vaga é liberada e quem estiver na lista de espera pode assumi-la.') }}"
                    data-confirm-button="{{ __('Remover') }}"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-red-300 bg-red-500/10 border border-red-500/30 hover:bg-red-500/20 transition">
                        <x-heroicon-o-trash class="w-3.5 h-3.5" /> {{ __('Remover') }}
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
