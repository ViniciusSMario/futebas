<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-plus-circle" :title="__('Criar Partida')" :subtitle="__('O essencial cabe numa tela — o resto é opcional')" />
    </x-slot>

    @php
        $selectedPositions = old('positions', []);

        // O painel dobrado abre sozinho quando algo lá dentro foi preenchido
        // ou recusado na validação: um campo com erro escondido faria o
        // formulário recusar sem dizer onde.
        $advancedOpen = filled(old('description'))
            || filled(old('end_time'))
            || filled(old('positions'))
            || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'description')
                || str_starts_with($key, 'end_time')
                || str_starts_with($key, 'positions'));
    @endphp

    <div class="py-5 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="post" action="{{ route('games.store') }}" x-data="{ advanced: {{ $advancedOpen ? 'true' : 'false' }} }">
                @csrf

                <div class="space-y-4">
                    {{-- Um cartão só para o que é obrigatório. A partida da
                         quarta-feira se marca daqui, sem abrir mais nada. --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6 space-y-5">
                        <div>
                            <x-input-label for="team_name" :value="__('Nome da partida')" />
                            <x-text-input id="team_name" name="team_name" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('team_name')" required autofocus placeholder="{{ __('Ex: Futebol de Quarta') }}" />
                            <x-input-error class="mt-2" :messages="$errors->get('team_name')" />
                        </div>

                        {{-- Quatro modalidades: em fileira de toque elas custam
                             um toque, e num select custam três. --}}
                        {{-- min-w-0: sem ele o <fieldset> herda do navegador um
                             `min-inline-size: min-content` e não encolhe. --}}
                        <fieldset class="min-w-0">
                            <legend class="text-sm font-medium text-pitch-300 mb-1.5">{{ __('Modalidade') }}</legend>
                            <div class="grid grid-cols-2 xs:grid-cols-4 gap-2">
                                @foreach (\App\Models\Game::MODALITIES as $modality)
                                    <label class="relative cursor-pointer">
                                        <input type="radio" name="modality" value="{{ $modality }}" @checked(old('modality') === $modality) required class="sr-only peer">
                                        <span class="flex items-center justify-center rounded-xl border border-pitch-700 bg-pitch-800/60 px-3 min-h-[44px] text-sm font-bold text-pitch-300 transition peer-checked:border-emerald-400 peer-checked:bg-emerald-500/15 peer-checked:text-emerald-300 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500">
                                            {{ $modality }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('modality')" />
                        </fieldset>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="date" :value="__('Data')" />
                                <x-text-input id="date" name="date" type="date" min="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('date')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('date')" />
                            </div>

                            <div>
                                <x-input-label for="start_time" :value="__('Horário')" />
                                <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('start_time')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="location" :value="__('Local')" />
                            <x-text-input id="location" name="location" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('location')" required placeholder="{{ __('Nome do local / quadra') }}" />
                            <x-input-error class="mt-2" :messages="$errors->get('location')" />
                        </div>

                        {{-- Estado e cidade vêm do IBGE: cidade escrita à mão
                             vira "Terezina" e some da busca de quem procura
                             por Teresina. Começa na UF de quem organiza. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-city-select
                                :state="old('state', Auth::user()->state)"
                                :city="old('city', Auth::user()->city)"
                                required
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div x-data="{ players: {{ (int) old('max_players', 20) }} }">
                                <x-input-label for="max_players" :value="__('Vagas')" />
                                <div class="mt-1 flex items-center gap-2">
                                    <button type="button" @click="players = Math.min(100, Math.max(2, (Number(players) || 0) - 1))" class="flex items-center justify-center w-11 h-11 shrink-0 rounded-xl bg-pitch-800 border border-pitch-700 text-pitch-200 active:bg-pitch-700 transition" aria-label="{{ __('Menos uma vaga') }}">
                                        <x-heroicon-o-minus class="w-5 h-5" />
                                    </button>
                                    {{-- O value serve para o formulário continuar
                                         inteiro se o JS não subir; o x-model só
                                         o repõe com o mesmo número. --}}
                                    <x-text-input id="max_players" name="max_players" type="number" min="2" max="100" inputmode="numeric" x-model="players" :value="old('max_players', 20)" class="block w-full rounded-lg text-center font-bold focus:border-emerald-500 focus:ring-emerald-500" required />
                                    <button type="button" @click="players = Math.min(100, Math.max(2, (Number(players) || 0) + 1))" class="flex items-center justify-center w-11 h-11 shrink-0 rounded-xl bg-pitch-800 border border-pitch-700 text-pitch-200 active:bg-pitch-700 transition" aria-label="{{ __('Mais uma vaga') }}">
                                        <x-heroicon-o-plus class="w-5 h-5" />
                                    </button>
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('max_players')" />
                            </div>

                            <div>
                                <x-input-label for="price" :value="__('Valor por jogador (R$)')" />
                                <x-text-input id="price" name="price" type="number" step="0.01" min="0" inputmode="decimal" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('price')" required placeholder="{{ __('Ex: 20') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('price')" />
                            </div>
                        </div>

                        <p class="text-xs text-pitch-500">{{ __('O valor é só uma referência para organizar o financeiro, não é uma cobrança automática.') }}</p>
                    </section>

                    {{-- Ajustes. Ficam dobrados porque a maioria das peladas
                         nunca precisa mexer neles. --}}
                    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 overflow-hidden">
                        <button
                            type="button"
                            @click="advanced = ! advanced"
                            class="w-full flex items-center gap-3 px-5 py-4 text-left transition hover:bg-pitch-800/50"
                            :aria-expanded="advanced ? 'true' : 'false'"
                        >
                            <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-pitch-800 text-pitch-300 shrink-0">
                                <x-heroicon-o-adjustments-horizontal class="w-5 h-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-white">{{ __('Ajustes da partida') }}</span>
                                <span class="block text-xs text-pitch-500">{{ __('Descrição, término, aprovação e posições') }}</span>
                            </span>
                            <x-heroicon-o-chevron-down class="w-5 h-5 text-pitch-500 shrink-0 transition" ::class="advanced && 'rotate-180'" />
                        </button>

                        <div
                            x-show="advanced"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            class="px-5 pb-5 space-y-5 border-t border-pitch-800 pt-5"
                        >
                            <div>
                                <x-input-label for="description" :value="__('Descrição (opcional)')" />
                                <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="{{ __('Ex: levar camisa branca e preta') }}">{{ old('description') }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('description')" />
                            </div>

                            <div class="sm:w-1/2">
                                <x-input-label for="end_time" :value="__('Término (opcional)')" />
                                <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('end_time')" />
                                <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                            </div>

                            <div>
                                <label class="flex items-start gap-3 rounded-xl border border-pitch-700 p-3 cursor-pointer has-[:checked]:border-emerald-500/40 has-[:checked]:bg-emerald-500/5 transition">
                                    <input type="checkbox" name="requires_approval" value="1" @checked(old('requires_approval', $errors->any() ? null : true)) class="mt-0.5 rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-pitch-100">{{ __('Aprovar jogadores manualmente') }}</span>
                                        <span class="block mt-0.5 text-xs text-pitch-500">{{ __('Se desmarcado, quem entrar pelo link ou aceitar convite já fica confirmado direto (respeitando o limite de vagas).') }}</span>
                                    </span>
                                </label>
                            </div>

                            <div>
                                <label class="flex items-start gap-3 rounded-xl border border-pitch-700 p-3 cursor-pointer has-[:checked]:border-emerald-500/40 has-[:checked]:bg-emerald-500/5 transition">
                                    <input type="checkbox" name="organizer_is_playing" value="1" @checked(old('organizer_is_playing')) class="mt-0.5 rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-pitch-100">{{ __('Eu também vou jogar') }}</span>
                                        <span class="block mt-0.5 text-xs text-pitch-500">{{ __('Você entra confirmado automaticamente e ocupa uma das vagas.') }}</span>
                                    </span>
                                </label>
                            </div>

                            <div>
                                <x-input-label :value="__('Posições desejadas (opcional)')" />
                                <div class="mt-1 grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach (\App\Models\Game::POSITIONS as $position)
                                        <label class="flex items-center gap-2 rounded-lg border border-pitch-700 px-3 min-h-[44px] text-sm text-pitch-200 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 has-[:checked]:text-emerald-300 transition cursor-pointer">
                                            <input type="checkbox" name="positions[]" value="{{ $position }}" @checked(in_array($position, $selectedPositions)) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            {{ $position }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('positions')" />
                            </div>
                        </div>
                    </section>

                    {{-- O botão acompanha a rolagem no celular: o formulário é
                         curto, mas o teclado aberto come metade da tela. --}}
                    <div class="sticky-action">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center gap-2 px-6 min-h-[52px] rounded-xl font-black text-sm uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/30 transition">
                            <x-heroicon-s-check class="w-5 h-5" />
                            {{ __('Criar Partida') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
