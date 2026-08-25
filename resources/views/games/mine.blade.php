<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-trophy" :title="__('Minhas Partidas')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            @if (session('status') === 'game-created')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Solicitação publicada com sucesso.') }}
                </p>
            @elseif (session('status') === 'game-finished')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Partida finalizada. Agora você já pode avaliar os jogadores.') }}
                </p>
            @elseif (session('status') === 'already-requested')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Você já solicitou participação nesse Game — acompanhe o andamento abaixo.') }}
                </p>
            @elseif (session('status') === 'checked-in')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Presença confirmada! O organizador já sabe que você vai.') }}
                </p>
            @elseif (session('status') === 'check-in-undone')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Sua confirmação de presença foi desfeita.') }}
                </p>
            @elseif (session('status') === 'left-game')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Sua participação foi cancelada.') }}
                </p>
            @endif

            <section>
                <div class="flex items-center gap-2 mb-3">
                    <h3 class="text-lg font-extrabold text-white">{{ __('Confirmadas') }}</h3>
                    <x-badge color="emerald">{{ $confirmadas->count() }}</x-badge>
                </div>
                @if ($confirmadas->isEmpty())
                    <x-empty-state icon="heroicon-o-check-circle" :title="__('Nenhuma partida confirmada no momento.')" />
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($confirmadas as $entry)
                            @include('games._card', ['entry' => $entry])
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <div class="flex items-center gap-2 mb-3">
                    <h3 class="text-lg font-extrabold text-white">{{ __('Pendentes') }}</h3>
                    <x-badge color="amber">{{ $pendentes->count() }}</x-badge>
                </div>
                @if ($pendentes->isEmpty())
                    <x-empty-state icon="heroicon-o-clock" :title="__('Nenhum convite pendente.')" />
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($pendentes as $entry)
                            @include('games._card', ['entry' => $entry])
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <div class="flex items-center gap-2 mb-3">
                    <h3 class="text-lg font-extrabold text-white">{{ __('Finalizadas') }}</h3>
                    <x-badge color="gray">{{ $finalizadas->count() }}</x-badge>
                </div>
                @if ($finalizadas->isEmpty())
                    <x-empty-state icon="heroicon-o-flag" :title="__('Nenhuma partida finalizada ainda.')" />
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($finalizadas as $entry)
                            @include('games._card', ['entry' => $entry])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
