<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-star" :title="__('Avaliar Jogadores')" :subtitle="$game->team_name.' · '.$game->date->format('d/m/Y')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('games.mine') }}" class="inline-flex items-center gap-1 text-sm font-medium text-pitch-400 hover:text-white">
                &larr; {{ __('Voltar para Minhas Partidas') }}
            </a>

            @if (session('status') === 'rating-saved')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Avaliação salva com sucesso.') }}
                </p>
            @elseif (session('status') === 'already-rated')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-information-circle class="w-4 h-4" /> {{ __('Você já avaliou este jogador nesta partida.') }}
                </p>
            @endif

            @if ($participants->isEmpty())
                <x-empty-state icon="heroicon-o-user-group" :title="__('Nenhum jogador confirmado nesta partida.')" />
            @else
                <div class="space-y-3">
                    @foreach ($participants as $participant)
                        @php
                            $player = $participant['user'];
                            $isRated = $ratedUserIds->contains($player->id);
                        @endphp
                        <div class="flex items-center justify-between gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-10 w-10 rounded-xl bg-pitch-800 flex items-center justify-center text-sm font-bold text-pitch-200 shrink-0">
                                    {{ Str::upper(Str::substr($player->name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-white truncate">{{ $player->name }}</p>
                                    @if ($participant['position'])
                                        <p class="text-xs text-pitch-400">{{ $participant['position'] }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($isRated)
                                <x-badge color="emerald">
                                    <x-heroicon-s-check-circle class="w-3.5 h-3.5 inline -mt-0.5" /> {{ __('Avaliado') }}
                                </x-badge>
                            @else
                                <a href="{{ route('ratings.create', [$game, $player]) }}" class="shrink-0 inline-flex items-center gap-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                    <x-heroicon-o-star class="w-4 h-4" /> {{ __('Avaliar') }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
