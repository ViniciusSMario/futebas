@php
    $tabs = [
        'informacoes' => ['label' => __('Informações'), 'icon' => 'heroicon-o-information-circle'],
        'participantes' => ['label' => __('Participantes'), 'icon' => 'heroicon-o-user-group'],
        'convites' => ['label' => __('Convites'), 'icon' => 'heroicon-o-envelope'],
        'pagamentos' => ['label' => __('Pagamentos'), 'icon' => 'heroicon-o-currency-dollar'],
        'times' => ['label' => __('Times'), 'icon' => 'heroicon-o-flag'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-trophy" :title="$game->team_name" :subtitle="$game->date->format('d/m/Y').' · '.$game->start_time->format('H:i').' · '.$game->location" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'player-added')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Jogador adicionado ao Game.') }}
                </p>
            @elseif (session('status') === 'player-confirmed')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Jogador confirmado.') }}
                </p>
            @elseif (session('status') === 'player-removed')
                <p class="flex items-center gap-1.5 text-sm font-medium text-pitch-300">
                    <x-heroicon-o-x-circle class="w-4 h-4" /> {{ __('Jogador removido do Game.') }}
                </p>
            @elseif (session('status') === 'payment-updated')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Pagamento atualizado.') }}
                </p>
            @elseif (session('status') === 'no-show-updated')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Presença do jogador atualizada.') }}
                </p>
            @elseif (session('status') === 'invitation-sent')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Convite enviado.') }}
                </p>
            @elseif (session('status') === 'teams-drawn')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Times sorteados.') }}
                </p>
            @elseif (session('status') === 'game-updated')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Game atualizado.') }}
                </p>
            @elseif (session('status') === 'game-cancelled')
                <p class="flex items-center gap-1.5 text-sm font-medium text-pitch-300">
                    <x-heroicon-o-x-circle class="w-4 h-4" /> {{ __('Game cancelado.') }}
                </p>
            @elseif (session('status') === 'rating-saved')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Avaliação salva com sucesso.') }}
                </p>
            @elseif (session('status') === 'already-rated')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-information-circle class="w-4 h-4" /> {{ __('Você já avaliou este jogador nesta partida.') }}
                </p>
            @endif

            @if ($errors->any())
                <p class="flex items-center gap-1.5 text-sm font-medium text-red-400">
                    <x-heroicon-o-x-circle class="w-4 h-4" /> {{ $errors->first() }}
                </p>
            @endif

            <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0">
                @foreach ($tabs as $key => $meta)
                    <a href="{{ route('games.show', ['game' => $game, 'tab' => $key]) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wide whitespace-nowrap transition
                              {{ $tab === $key ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20' : 'bg-pitch-900 border border-pitch-800 text-pitch-300 hover:text-white hover:border-pitch-700' }}">
                        <x-dynamic-component :component="$meta['icon']" class="w-4 h-4" />
                        {{ $meta['label'] }}
                    </a>
                @endforeach
            </div>

            @if ($tab === 'informacoes')
                @include('games.tabs._informacoes')
            @elseif ($tab === 'participantes')
                @include('games.tabs._participantes')
            @elseif ($tab === 'convites')
                @include('games.tabs._convites')
            @elseif ($tab === 'pagamentos')
                @include('games.tabs._pagamentos')
            @elseif ($tab === 'times')
                @include('games.tabs._times')
            @endif
        </div>
    </div>
</x-app-layout>
