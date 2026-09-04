<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-megaphone" :title="__('Novo SOS')" :subtitle="__('Avise os goleiros da região e escolha quem entra')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            @if (session('error'))
                <p class="mb-5 rounded-xl bg-amber-500/10 border border-amber-500/30 px-4 py-3 text-sm font-medium text-amber-300 flex items-start gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" /> {{ session('error') }}
                </p>
            @endif

            {{-- Quanto ainda cabe no plano deste mês. Aparece antes do
                 formulário porque descobrir que acabou depois de preencher
                 tudo é a pior hora de descobrir. --}}
            <x-quota-notice :feature="\App\Enums\Feature::SOS_REQUESTS" class="mb-5" />

            <form
                method="post"
                action="{{ route('sos.store') }}"
                x-data="{ source: '{{ old('source', $games->isEmpty() ? 'new' : 'existing') }}' }"
            >
                @csrf

                <div class="space-y-6">
                    {{-- Which match --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Para qual partida?') }}
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition"
                                :class="source === 'existing' ? 'border-emerald-500 bg-emerald-500/5' : 'border-pitch-700 hover:border-pitch-600'"
                                @if ($games->isEmpty()) aria-disabled="true" @endif
                            >
                                <input type="radio" name="source" value="existing" x-model="source" @disabled($games->isEmpty()) class="mt-0.5 text-emerald-600 bg-pitch-800 border-pitch-600 focus:ring-emerald-500">
                                <span class="min-w-0">
                                    <span class="block font-bold text-white text-sm">{{ __('Uma partida já criada') }}</span>
                                    <span class="block text-xs text-pitch-400 mt-0.5">
                                        @if ($games->isEmpty())
                                            {{ __('Você não tem partidas abertas sem SOS ativo.') }}
                                        @else
                                            {{ trans_choice(':count partida disponível|:count partidas disponíveis', $games->count(), ['count' => $games->count()]) }}
                                        @endif
                                    </span>
                                </span>
                            </label>

                            <label
                                class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition"
                                :class="source === 'new' ? 'border-emerald-500 bg-emerald-500/5' : 'border-pitch-700 hover:border-pitch-600'"
                            >
                                <input type="radio" name="source" value="new" x-model="source" class="mt-0.5 text-emerald-600 bg-pitch-800 border-pitch-600 focus:ring-emerald-500">
                                <span class="min-w-0">
                                    <span class="block font-bold text-white text-sm">{{ __('Criar uma partida agora') }}</span>
                                    <span class="block text-xs text-pitch-400 mt-0.5">{{ __('Só o essencial: data, horário e local.') }}</span>
                                </span>
                            </label>
                        </div>

                        <x-input-error class="mt-2" :messages="$errors->get('source')" />

                        {{-- Existing match --}}
                        <div class="mt-4" x-show="source === 'existing'" x-cloak>
                            <x-input-label for="game_id" :value="__('Partida')" />
                            <select id="game_id" name="game_id" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                <option value="">{{ __('Selecione...') }}</option>
                                @foreach ($games as $game)
                                    <option value="{{ $game->id }}" @selected((int) old('game_id') === $game->id)>
                                        {{ $game->date->format('d/m') }} {{ $game->start_time->format('H:i') }} - {{ $game->team_name }} ({{ $game->location }}, {{ $game->city }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('game_id')" />
                        </div>

                        {{-- New match --}}
                        <div class="mt-4 space-y-4" x-show="source === 'new'" x-cloak>
                            <div>
                                <x-input-label for="team_name" :value="__('Nome do time / pelada')" />
                                <x-text-input id="team_name" name="team_name" type="text" class="mt-1 block w-full rounded-lg" :value="old('team_name')" placeholder="{{ __('Ex.: Pelada da quinta') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('team_name')" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="date" :value="__('Data')" />
                                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full rounded-lg" :value="old('date')" />
                                    <x-input-error class="mt-2" :messages="$errors->get('date')" />
                                </div>

                                <div>
                                    <x-input-label for="start_time" :value="__('Início')" />
                                    <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full rounded-lg" :value="old('start_time')" />
                                    <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                                </div>

                                <div>
                                    <x-input-label for="end_time" :value="__('Término')" />
                                    <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full rounded-lg" :value="old('end_time')" />
                                    <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="location" :value="__('Local')" />
                                    <x-text-input id="location" name="location" type="text" class="mt-1 block w-full rounded-lg" :value="old('location')" placeholder="{{ __('Nome do local / quadra') }}" />
                                    <x-input-error class="mt-2" :messages="$errors->get('location')" />
                                </div>

                                {{-- Estado e cidade do IBGE. O SOS depende disso mais
                                     que qualquer outra tela: é por cidade e estado que
                                     ele decide quais goleiros avisar. --}}
                                <x-city-select
                                    :state="old('state', Auth::user()->state)"
                                    :city="old('city')"
                                />
                            </div>

                            <div>
                                <x-input-label for="modality" :value="__('Modalidade')" />
                                <select id="modality" name="modality" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                    <option value="">{{ __('Selecione...') }}</option>
                                    @foreach (\App\Models\Game::MODALITIES as $modality)
                                        <option value="{{ $modality }}" @selected(old('modality') === $modality)>{{ $modality }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('modality')" />
                            </div>
                        </div>
                    </section>

                    {{-- The call itself --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                            <x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('A chamada') }}
                        </h3>

                        <div class="flex items-center gap-2 rounded-xl bg-pitch-800/60 px-4 py-3 text-sm">
                            <span class="text-lg">🧤</span>
                            <span class="font-bold text-white">{{ __('Goleiro') }}</span>
                        </div>

                        <div class="mt-4">
                            <x-input-label for="offered_value" :value="__('Valor oferecido (R$)')" />
                            <x-text-input id="offered_value" name="offered_value" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg" :value="old('offered_value')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('offered_value')" />
                            <p class="mt-1.5 text-xs text-pitch-500">{{ __('É o que você paga ao goleiro. Ele pode fazer uma contraproposta ao se candidatar.') }}</p>
                        </div>

                        <div class="mt-4">
                            <x-input-label for="message" :value="__('Recado (opcional)')" />
                            <textarea id="message" name="message" rows="2" maxlength="500" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white placeholder-pitch-500 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="{{ __('Ex.: precisa levar luva própria.') }}">{{ old('message') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('message')" />
                        </div>
                    </section>

                    <div class="rounded-2xl bg-pitch-900/60 border border-pitch-800 p-4 text-sm text-pitch-400 flex items-start gap-2">
                        <x-heroicon-o-information-circle class="w-5 h-5 shrink-0 text-pitch-500" />
                        <p>{{ __('Ao publicar, todos os goleiros cadastrados na região da partida recebem uma notificação. As candidaturas ficam pendentes até você escolher uma.') }}</p>
                    </div>

                    <div class="flex items-center gap-4 sticky bottom-4 sm:static">
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-500 hover:to-orange-400 shadow-lg shadow-red-500/30 transition">
                            <x-heroicon-o-megaphone class="w-4 h-4" /> {{ __('Publicar SOS') }}
                        </button>

                        <a href="{{ route('sos.index') }}" class="text-sm font-medium text-pitch-400 hover:text-white">{{ __('Cancelar') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
