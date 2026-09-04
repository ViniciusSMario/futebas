<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-plus-circle" :title="__('Criar Partida')" :subtitle="__('Organize uma partida e controle os participantes')" />
    </x-slot>

    @php
        $selectedPositions = old('positions', []);
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="post" action="{{ route('games.store') }}">
                @csrf

                <div class="space-y-6">
                    {{-- Match details --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Dados da partida') }}
                        </h3>

                        <div>
                            <x-input-label for="team_name" :value="__('Nome do Game')" />
                            <x-text-input id="team_name" name="team_name" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('team_name')" required autofocus placeholder="{{ __('Ex: Futebol de Quarta') }}" />
                            <x-input-error class="mt-2" :messages="$errors->get('team_name')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="description" :value="__('Descrição (opcional)')" />
                            <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">{{ old('description') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('description')" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="modality" :value="__('Modalidade')" />
                            <select id="modality" name="modality" required class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                <option value="">{{ __('Selecione...') }}</option>
                                @foreach (\App\Models\Game::MODALITIES as $modality)
                                    <option value="{{ $modality }}" @selected(old('modality') === $modality)>{{ $modality }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('modality')" />
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="date" :value="__('Data')" />
                                <x-text-input id="date" name="date" type="date" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('date')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('date')" />
                            </div>

                            <div>
                                <x-input-label for="start_time" :value="__('Horário')" />
                                <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('start_time')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                            </div>

                            <div>
                                <x-input-label for="end_time" :value="__('Término (opcional)')" />
                                <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('end_time')" />
                                <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                            </div>
                        </div>
                    </section>

                    {{-- Location --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="location" :value="__('Local')" />
                                <x-text-input id="location" name="location" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('location')" required placeholder="{{ __('Nome do local / quadra') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('location')" />
                            </div>

                            {{-- Estado e cidade vêm do IBGE: cidade escrita à mão
                                 vira "Terezina" e some da busca de quem procura
                                 por Teresina. Começa na UF de quem organiza. --}}
                            <x-city-select
                                :state="old('state', Auth::user()->state)"
                                :city="old('city', Auth::user()->city)"
                                required
                            />
                        </div>
                    </section>

                    {{-- Capacity --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-user-group class="w-4 h-4" /> {{ __('Vagas e aprovação') }}
                        </h3>

                        <div>
                            <x-input-label for="max_players" :value="__('Quantidade máxima de jogadores')" />
                            <x-text-input id="max_players" name="max_players" type="number" min="2" max="100" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('max_players', 20)" required />
                            <x-input-error class="mt-2" :messages="$errors->get('max_players')" />
                        </div>

                        <div class="mt-4">
                            <label class="flex items-center gap-2 text-sm text-pitch-200">
                                <input type="checkbox" name="requires_approval" value="1" @checked(old('requires_approval', true)) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                {{ __('Aprovar jogadores manualmente antes de confirmar') }}
                            </label>
                            <p class="mt-1 text-xs text-pitch-500">{{ __('Se desmarcado, quem entrar pelo link ou aceitar convite já fica confirmado direto (respeitando o limite de vagas).') }}</p>
                        </div>

                        <div class="mt-4">
                            <label class="flex items-center gap-2 text-sm text-pitch-200">
                                <input type="checkbox" name="organizer_is_playing" value="1" @checked(old('organizer_is_playing')) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                {{ __('Eu também vou jogar') }}
                            </label>
                            <p class="mt-1 text-xs text-pitch-500">{{ __('Você entra confirmado automaticamente e ocupa uma das vagas.') }}</p>
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

                    {{-- Pricing --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valor') }}
                        </h3>

                        <div>
                            <x-input-label for="price" :value="__('Valor estimado por jogador (R$)')" />
                            <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('price')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('price')" />
                            <p class="mt-1 text-xs text-pitch-500">{{ __('É só uma referência para organizar o financeiro, não é uma cobrança automática.') }}</p>
                        </div>
                    </section>

                    <div class="flex items-center gap-4 sticky bottom-4 sm:static">
                        <button type="submit" class="inline-flex items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                            {{ __('Criar Partida') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
