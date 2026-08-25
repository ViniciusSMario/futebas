<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-pencil-square" :title="__('Editar Game')" :subtitle="$game->team_name" />
    </x-slot>

    @php
        $selectedPositions = old('positions', $game->positions ?? []);
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="post" action="{{ route('games.update', $game) }}">
                @csrf
                @method('PATCH')

                <div class="space-y-6">
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Dados da partida') }}
                        </h3>

                        <div>
                            <x-input-label for="team_name" :value="__('Nome do Game')" />
                            <x-text-input id="team_name" name="team_name" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('team_name', $game->team_name)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('team_name')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="description" :value="__('Descrição (opcional)')" />
                            <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">{{ old('description', $game->description) }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('description')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="modality" :value="__('Modalidade')" />
                            <select id="modality" name="modality" required class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                @foreach (\App\Models\Game::MODALITIES as $modality)
                                    <option value="{{ $modality }}" @selected(old('modality', $game->modality) === $modality)>{{ $modality }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('modality')" />
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="date" :value="__('Data')" />
                                <x-text-input id="date" name="date" type="date" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('date', $game->date->format('Y-m-d'))" required />
                                <x-input-error class="mt-2" :messages="$errors->get('date')" />
                            </div>

                            <div>
                                <x-input-label for="start_time" :value="__('Horário')" />
                                <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('start_time', $game->start_time->format('H:i'))" required />
                                <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                            </div>

                            <div>
                                <x-input-label for="end_time" :value="__('Término (opcional)')" />
                                <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('end_time', $game->end_time?->format('H:i'))" />
                                <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                            </div>
                        </div>
                    </section>

                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="location" :value="__('Local')" />
                                <x-text-input id="location" name="location" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('location', $game->location)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('location')" />
                            </div>

                            <div>
                                <x-input-label for="city" :value="__('Cidade')" />
                                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('city', $game->city)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('city')" />
                            </div>
                        </div>
                    </section>

                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-user-group class="w-4 h-4" /> {{ __('Vagas e aprovação') }}
                        </h3>

                        <div>
                            <x-input-label for="max_players" :value="__('Quantidade máxima de jogadores')" />
                            <x-text-input id="max_players" name="max_players" type="number" min="2" max="100" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('max_players', $game->max_players)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('max_players')" />
                        </div>

                        <div class="mt-4">
                            <label class="flex items-center gap-2 text-sm text-pitch-200">
                                <input type="checkbox" name="requires_approval" value="1" @checked(old('requires_approval', $game->requires_approval)) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                {{ __('Aprovar jogadores manualmente antes de confirmar') }}
                            </label>
                        </div>

                        <div class="mt-4">
                            <x-input-label :value="__('Posições desejadas (opcional)')" />
                            <div class="mt-1 grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach (\App\Models\Game::POSITIONS as $position)
                                    <label class="flex items-center gap-2 rounded-lg border border-pitch-700 px-3 py-2 text-sm text-pitch-200 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 has-[:checked]:text-emerald-300 transition cursor-pointer">
                                        <input type="checkbox" name="positions[]" value="{{ $position }}" @checked(in_array($position, $selectedPositions)) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                        {{ $position }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('positions')" />
                        </div>
                    </section>

                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valor') }}
                        </h3>

                        <div>
                            <x-input-label for="price" :value="__('Valor estimado por jogador (R$)')" />
                            <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('price', $game->price)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('price')" />
                        </div>
                    </section>

                    <div class="flex items-center gap-4 sticky bottom-4 sm:static">
                        <button type="submit" class="inline-flex items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                            {{ __('Salvar Alterações') }}
                        </button>
                        <a href="{{ route('games.show', $game) }}" class="inline-flex items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
                            {{ __('Cancelar') }}
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
