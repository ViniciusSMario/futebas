@php
    // A ordem é a da vida da partida: o que ela é, quem vai, quem falta
    // responder, quem deve, como ficaram os times. Quem só joga recebe o
    // recorte de `GameController::PARTICIPANT_TABS`, montado no controller
    // e não aqui, para que a aba escondida também não tenha rota aberta.
    $tabMeta = [
        'informacoes' => ['label' => __('Informações'), 'icon' => 'heroicon-o-information-circle'],
        'participantes' => ['label' => __('Participantes'), 'icon' => 'heroicon-o-user-group'],
        'convites' => ['label' => __('Convites'), 'icon' => 'heroicon-o-envelope'],
        'pagamentos' => ['label' => __('Pagamentos'), 'icon' => 'heroicon-o-currency-dollar'],
        'times' => ['label' => __('Times'), 'icon' => 'heroicon-o-flag'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            icon="heroicon-o-trophy"
            :title="$game->team_name"
            :subtitle="$game->whenLabel().' · '.$game->location"
            :back="route('games.mine')"
        />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0">
                @foreach ($tabs as $key)
                    <a href="{{ route('games.show', ['game' => $game, 'tab' => $key]) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wide whitespace-nowrap transition
                              {{ $tab === $key ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20' : 'bg-pitch-900 border border-pitch-800 text-pitch-300 hover:text-white hover:border-pitch-700' }}">
                        <x-dynamic-component :component="$tabMeta[$key]['icon']" class="w-4 h-4" />
                        {{ $tabMeta[$key]['label'] }}
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
