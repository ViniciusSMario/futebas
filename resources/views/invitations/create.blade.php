<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-envelope" :title="__('Convidar para Partida')" :subtitle="__('Convidar :name', ['name' => $playerProfile->user->name])" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('players.show', $playerProfile) }}" class="inline-flex items-center gap-1 text-sm font-medium text-pitch-400 hover:text-white">
                &larr; {{ __('Voltar para o perfil') }}
            </a>

            @if ($games->isEmpty())
                <x-empty-state icon="heroicon-o-trophy" :title="__('Nenhuma solicitação disponível')" :description="__('Você não tem nenhuma partida em aberto para convidar este jogador, ou já convidou ele para todas. Crie uma nova solicitação primeiro.')">
                    <x-slot name="action">
                        <a href="{{ route('games.create') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            {{ __('Criar Solicitação') }}
                        </a>
                    </x-slot>
                </x-empty-state>
            @else
                <form method="post" action="{{ route('invitations.store', $playerProfile) }}">
                    @csrf

                    <div class="space-y-6">
                        {{-- Match selection --}}
                        <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                                <x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Escolha a partida') }}
                            </h3>

                            <div class="space-y-3">
                                @foreach ($games as $game)
                                    <label class="flex items-start gap-3 rounded-xl border border-pitch-700 p-4 cursor-pointer has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 transition">
                                        <input type="radio" name="game_id" value="{{ $game->id }}" class="mt-1 rounded-full bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500" @checked((string) old('game_id') === (string) $game->id) required>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="font-bold text-white">{{ $game->team_name }}</p>
                                                <span class="font-bold text-emerald-400 shrink-0">R$ {{ number_format((float) $game->price, 2, ',', '.') }}</span>
                                            </div>
                                            <p class="text-sm text-pitch-300 mt-0.5">
                                                {{ $game->date->format('d/m/Y') }} &middot; {{ $game->start_time->format('H:i') }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif
                                            </p>
                                            <p class="text-sm text-pitch-400 flex items-center gap-1 mt-0.5">
                                                <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" /> {{ $game->location }}, {{ $game->city }}
                                            </p>
                                            <p class="text-xs text-pitch-500 mt-1">
                                                {{ $game->modality }} &middot; {{ __('Posições') }}: {{ implode(', ', $game->positions ?? []) }}
                                            </p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('game_id')" />
                        </section>

                        {{-- Invitation details --}}
                        <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                                <x-heroicon-o-flag class="w-4 h-4" /> {{ __('Detalhes do convite') }}
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="position" :value="__('Posição')" />
                                    <select id="position" name="position" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                        <option value="">{{ __('Usar posição do jogador') }}</option>
                                        @foreach (\App\Models\Game::POSITIONS as $position)
                                            <option value="{{ $position }}" @selected(old('position') === $position)>{{ $position }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('position')" />
                                </div>

                                <div>
                                    <x-input-label for="value" :value="__('Valor (R$)')" />
                                    <x-text-input id="value" name="value" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('value')" placeholder="{{ __('Usar valor da partida') }}" />
                                    <x-input-error class="mt-2" :messages="$errors->get('value')" />
                                </div>
                            </div>
                        </section>

                        <div class="flex items-center gap-4 sticky bottom-4 sm:static">
                            <button type="submit" class="inline-flex items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                                {{ __('Enviar Convite') }}
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
