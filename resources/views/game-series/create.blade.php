<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-arrow-path" :title="__('Nova Pelada Semanal')" :subtitle="__('Cadastre uma vez; as partidas passam a ser criadas sozinhas')" />
    </x-slot>

    @php
        $selectedPositions = old('positions', []);
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="post" action="{{ route('game-series.store') }}">
                @csrf

                <div class="space-y-6">
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Dados da pelada') }}
                        </h3>

                        <div>
                            <x-input-label for="team_name" :value="__('Nome da pelada')" />
                            <x-text-input id="team_name" name="team_name" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('team_name')" required autofocus placeholder="{{ __('Ex: Pelada de Quinta') }}" />
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
                                <x-input-label for="day_of_week" :value="__('Dia da semana')" />
                                <select id="day_of_week" name="day_of_week" required class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                    @foreach (\App\Models\GameSeries::DAYS_OF_WEEK as $value => $label)
                                        <option value="{{ $value }}" @selected((string) old('day_of_week') === (string) $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('day_of_week')" />
                            </div>
                            <div>
                                <x-input-label for="start_time" :value="__('Início')" />
                                <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('start_time')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                            </div>
                            <div>
                                <x-input-label for="end_time" :value="__('Término (opcional)')" />
                                <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('end_time')" />
                                <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-pitch-500">
                            {{ __('As partidas das próximas :weeks semanas são criadas automaticamente.', ['weeks' => \App\Models\GameSeries::WEEKS_AHEAD]) }}
                        </p>
                    </section>

                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local e vagas') }}
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="location" :value="__('Local')" />
                                <x-text-input id="location" name="location" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('location')" required placeholder="{{ __('Ex: Arena Society') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('location')" />
                            </div>
                            <div>
                                <x-input-label for="city" :value="__('Cidade')" />
                                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('city', Auth::user()->city)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('city')" />
                            </div>
                            <div>
                                <x-input-label for="max_players" :value="__('Máximo de jogadores')" />
                                <x-text-input id="max_players" name="max_players" type="number" min="2" max="100" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('max_players', 14)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('max_players')" />
                            </div>
                            <div>
                                <x-input-label for="price" :value="__('Valor por jogador (R$)')" />
                                <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('price', '0')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('price')" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-input-label :value="__('Posições procuradas (opcional)')" />
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (\App\Models\Game::POSITIONS as $position)
                                    <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-pitch-800 border border-pitch-700 text-xs font-semibold text-pitch-200 cursor-pointer">
                                        <input type="checkbox" name="positions[]" value="{{ $position }}" @checked(in_array($position, $selectedPositions))
                                            class="rounded bg-pitch-900 border-pitch-600 text-emerald-600 focus:ring-emerald-500">
                                        {{ $position }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('positions')" />
                        </div>

                        <div class="mt-5 space-y-3 pt-4 border-t border-pitch-800">
                            <label for="requires_approval" class="flex items-start gap-3 cursor-pointer">
                                <input id="requires_approval" name="requires_approval" type="checkbox" value="1" @checked(old('requires_approval'))
                                    class="mt-0.5 rounded bg-pitch-800 border-pitch-700 text-emerald-600 focus:ring-emerald-500">
                                <span class="text-sm text-pitch-200">
                                    {{ __('Aprovar cada novo participante manualmente') }}
                                    <span class="block text-xs text-pitch-500">{{ __('Não afeta os mensalistas: eles entram sempre confirmados.') }}</span>
                                </span>
                            </label>

                            <label for="organizer_is_playing" class="flex items-start gap-3 cursor-pointer">
                                <input id="organizer_is_playing" name="organizer_is_playing" type="checkbox" value="1" @checked(old('organizer_is_playing', true))
                                    class="mt-0.5 rounded bg-pitch-800 border-pitch-700 text-emerald-600 focus:ring-emerald-500">
                                <span class="text-sm text-pitch-200">
                                    {{ __('Eu também jogo') }}
                                    <span class="block text-xs text-pitch-500">{{ __('Entra como mensalista em todas as partidas da série.') }}</span>
                                </span>
                            </label>
                        </div>
                    </section>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Criar pelada semanal') }}</x-primary-button>
                        <a href="{{ route('game-series.index') }}" class="text-sm font-medium text-pitch-400 hover:text-white">{{ __('Cancelar') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
