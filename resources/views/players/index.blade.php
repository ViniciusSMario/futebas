<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-magnifying-glass" :title="__('Procurar Jogadores')" :subtitle="__('Comece pela posição — o goleiro está a um toque')" />
    </x-slot>

    @php
        $selectedPosition = $filters['position'] ?? '';
        $selectedAvailability = $filters['availability'] ?? '';
        $hasActiveFilters = collect($filters)->filter()->isNotEmpty();

        // O que fica atrás de "Mais filtros". Se algum deles estiver valendo,
        // o painel abre sozinho: um filtro escondido e ativo é a forma mais
        // rápida de a busca parecer quebrada.
        $advancedFilters = ['modality', 'level', 'availability', 'max_price', 'sort'];
        $advancedCount = collect($advancedFilters)->filter(fn ($key) => filled($filters[$key] ?? null))->count();

        $lookingForGoalkeeper = $selectedPosition === 'Goleiro';
    @endphp

    <div class="py-5 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <form
                method="get"
                action="{{ route('players.search') }}"
                x-data="{ advanced: {{ $advancedCount > 0 ? 'true' : 'false' }} }"
                class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-4 sm:p-5 space-y-4"
            >
                {{-- Posição em fileira de toque, e não num select: é o filtro
                     que o organizador usa em toda busca, e nove em cada dez
                     vezes para achar um goleiro. Trocar de posição já busca. --}}
                {{-- Um <div role="radiogroup">, e deliberadamente NÃO um
                     <fieldset>: o navegador aplica `min-inline-size: min-content`
                     em todo fieldset, e a soma dos sete chips é quase o dobro
                     da largura de um celular. O fieldset se recusa a encolher,
                     estica a página inteira na horizontal e leva junto a barra
                     de baixo, que sendo fixa passa a ter a largura do documento
                     e joga metade dos seus itens para fora da tela. Dá para
                     desarmar com `min-width: 0`, mas então a barra de navegação
                     do app inteiro depende de uma classe não ser removida
                     daqui. Um <div> não tem essa mania. --}}
                <div role="radiogroup" aria-labelledby="filtro-posicao">
                    <p id="filtro-posicao" class="text-[11px] font-black uppercase tracking-widest text-pitch-500 mb-2">{{ __('Posição') }}</p>

                    {{-- `flex flex-nowrap overflow-x-auto` repete o que a
                         `.snap-row` já faz de propósito: é essa rolagem que
                         impede a fileira de empurrar a largura da página, e
                         ela não pode depender de uma única classe. --}}
                    <div class="snap-row flex flex-nowrap overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 pb-1">
                        <label class="relative shrink-0 cursor-pointer">
                            <input type="radio" name="position" value="" @checked($selectedPosition === '') onchange="this.form.submit()" class="sr-only peer">
                            <span class="flex items-center rounded-xl border border-pitch-700 bg-pitch-800/60 px-4 min-h-[44px] text-sm font-bold text-pitch-300 transition peer-checked:border-emerald-400 peer-checked:bg-emerald-500/15 peer-checked:text-emerald-300 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500">
                                {{ __('Todas') }}
                            </span>
                        </label>

                        @foreach (\App\Models\PlayerProfile::POSITIONS as $position)
                            @php $isKeeper = $position === 'Goleiro'; @endphp
                            <label class="relative shrink-0 cursor-pointer">
                                <input type="radio" name="position" value="{{ $position }}" @checked($selectedPosition === $position) onchange="this.form.submit()" class="sr-only peer">
                                <span class="flex items-center gap-1.5 rounded-xl border px-4 min-h-[44px] text-sm font-bold transition peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 {{ $isKeeper ? 'border-amber-500/40 bg-amber-500/10 text-amber-300 peer-checked:border-amber-400 peer-checked:bg-amber-400 peer-checked:text-pitch-950' : 'border-pitch-700 bg-pitch-800/60 text-pitch-300 peer-checked:border-emerald-400 peer-checked:bg-emerald-500/15 peer-checked:text-emerald-300' }}">
                                    @if ($isKeeper)
                                        <x-heroicon-o-hand-raised class="w-4 h-4" />
                                    @endif
                                    {{ $position }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Onde. O segundo filtro de toda busca: ninguém chama para a
                     pelada de quarta alguém de outro estado. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-city-select any :state="$filters['state'] ?? ''" :city="$filters['city'] ?? ''">
                        {{-- Cidades próximas é recurso de plano. Para quem não
                             tem, o controle continua visível, desligado e
                             explicado: esconder deixaria a pessoa procurando
                             algo que existe. --}}
                        @if ($canUseNearby)
                            <label class="mt-2 flex items-center gap-2 text-xs text-pitch-300 cursor-pointer">
                                <input type="checkbox" name="nearby" value="1" @checked(! empty($filters['nearby'])) class="rounded border-pitch-600 bg-pitch-800 text-emerald-500 focus:ring-emerald-500">
                                {{ __('Incluir cidades próximas') }}
                            </label>
                        @elseif ($nearbyPlan)
                            <a href="{{ route('subscription.index') }}" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-pitch-400 hover:text-emerald-300 transition">
                                <x-heroicon-o-lock-closed class="w-3.5 h-3.5" />
                                {{ __('Cidades próximas: no plano :plan', ['plan' => $nearbyPlan->label()]) }}
                            </a>
                        @endif
                    </x-city-select>
                </div>

                {{-- O resto é afinação: fica dobrado para a busca caber numa
                     tela de celular sem rolagem. --}}
                <div
                    x-show="advanced"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-1"
                >
                    <div>
                        <x-input-label for="modality" :value="__('Modalidade')" />
                        <select id="modality" name="modality" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            <option value="">{{ __('Qualquer modalidade') }}</option>
                            @foreach (\App\Models\PlayerProfile::MODALITIES as $modality)
                                <option value="{{ $modality }}" @selected(($filters['modality'] ?? '') === $modality)>{{ $modality }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="level" :value="__('Nível')" />
                        <select id="level" name="level" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            <option value="">{{ __('Qualquer nível') }}</option>
                            @foreach (\App\Models\PlayerProfile::LEVELS as $level)
                                <option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="availability" :value="__('Disponibilidade')" />
                        <select id="availability" name="availability" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            @foreach (\App\Http\Controllers\PlayerController::AVAILABILITY_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected($selectedAvailability === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="max_price" :value="__('Valor máximo (R$)')" />
                        <x-text-input id="max_price" name="max_price" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$filters['max_price'] ?? ''" placeholder="{{ __('Ex: 50') }}" />
                    </div>

                    <div>
                        <x-input-label for="sort" :value="__('Ordenar por')" />
                        <select id="sort" name="sort" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            @foreach (\App\Http\Controllers\PlayerController::SORT_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? '') === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-sm shadow-emerald-500/20 transition">
                        <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                        {{ __('Buscar') }}
                    </button>

                    <button
                        type="button"
                        @click="advanced = ! advanced"
                        class="inline-flex items-center gap-1.5 px-3 min-h-[44px] rounded-xl text-sm font-semibold text-pitch-300 hover:text-white transition"
                    >
                        <x-heroicon-o-adjustments-horizontal class="w-4 h-4" />
                        {{ __('Mais filtros') }}
                        @if ($advancedCount > 0)
                            <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-emerald-500 text-[10px] font-black text-white">{{ $advancedCount }}</span>
                        @endif
                    </button>

                    @if ($hasActiveFilters)
                        <a href="{{ route('players.search') }}" class="ms-auto text-sm font-medium text-pitch-400 hover:text-white">
                            {{ __('Limpar filtros') }}
                        </a>
                    @endif
                </div>
            </form>

            {{-- Procurar goleiro no catálogo e chamar a região são a mesma
                 necessidade em dois tempos: quem não achou aqui precisa do
                 SOS agora, não do menu. --}}
            <a
                href="{{ route('sos.index') }}"
                class="group flex items-center gap-3 rounded-2xl px-4 py-3 transition {{ $lookingForGoalkeeper ? 'bg-gradient-to-r from-red-600 to-orange-500 shadow-glow-red' : 'bg-pitch-900 border border-pitch-800 hover:border-red-500/40' }}"
            >
                <span class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0 {{ $lookingForGoalkeeper ? 'bg-white/20 text-white' : 'bg-red-500/10 text-red-400' }}">
                    <x-heroicon-o-megaphone class="w-5 h-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-black uppercase tracking-wide {{ $lookingForGoalkeeper ? 'text-white' : 'text-red-300' }}">{{ __('Preciso de Goleiro') }}</p>
                    <p class="text-xs leading-snug {{ $lookingForGoalkeeper ? 'text-red-50' : 'text-pitch-400' }}">{{ __('Avise todos os goleiros da região e receba propostas.') }}</p>
                </div>
                <x-heroicon-o-chevron-right class="w-5 h-5 shrink-0 {{ $lookingForGoalkeeper ? 'text-white/70' : 'text-pitch-600' }} group-hover:translate-x-0.5 transition" />
            </a>

            <div class="flex items-center justify-between">
                <p class="text-sm text-pitch-400">
                    {{ trans_choice(':count jogador encontrado|:count jogadores encontrados', $players->total(), ['count' => $players->total()]) }}
                </p>
            </div>

            @if ($players->isEmpty())
                <x-empty-state icon="heroicon-o-magnifying-glass" :title="__('Nenhum jogador encontrado')" :description="__('Tente ajustar os filtros para ver mais resultados.')" />
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($players as $playerProfile)
                        <div class="group bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 flex flex-col hover:shadow-md hover:shadow-black/30 hover:border-emerald-600/40 transition">
                            <div class="flex items-center gap-4">
                                @if ($playerProfile->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($playerProfile->photo_path) }}" alt="{{ $playerProfile->user->name }}" class="h-16 w-16 rounded-full object-cover shrink-0 ring-2 ring-pitch-800 shadow-sm">
                                @else
                                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 flex items-center justify-center text-xl font-extrabold text-emerald-300 shrink-0">
                                        {{ Str::upper(Str::substr($playerProfile->user->name, 0, 1)) }}
                                    </div>
                                @endif

                                <div class="min-w-0">
                                    @php
                                        // O plano vem junto do resultado da busca
                                        // (subconsulta em PlayerController::search),
                                        // para este selo e para a ordenação por destaque.
                                        $playerPlan = \App\Enums\Plan::tryFrom((string) $playerProfile->plan);
                                    @endphp

                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-white truncate">{{ $playerProfile->user->name }}</h3>
                                        @if ($playerPlan)
                                            <x-plan-badge :plan="$playerPlan" class="shrink-0" />
                                        @endif
                                    </div>
                                    <p class="text-xs text-pitch-400 truncate flex items-center gap-1">
                                        <x-heroicon-o-map-pin class="w-3.5 h-3.5 shrink-0" /> {{ $playerProfile->city }}@if ($playerProfile->state), {{ $playerProfile->state }}@endif
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-1.5">
                                @if ($playerProfile->positions[0] ?? null)
                                    <x-badge color="emerald">{{ $playerProfile->positions[0] }}</x-badge>
                                @endif
                                <x-badge color="blue">{{ $playerProfile->level }}</x-badge>
                                @if ($playerProfile->modalities)
                                    <x-badge color="gray">{{ implode(', ', $playerProfile->modalities) }}</x-badge>
                                @endif
                            </div>

                            <div class="mt-4 pt-4 border-t border-pitch-800 flex items-end justify-between gap-2 grow">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Valor/partida') }}</p>
                                    <p class="font-extrabold text-white">R$ {{ number_format((float) $playerProfile->price_per_game, 2, ',', '.') }}</p>
                                </div>

                                <div class="text-right space-y-1">
                                    @if ($playerProfile->ratings_count > 0)
                                        <p class="text-xs font-bold text-amber-400 whitespace-nowrap">
                                            ⭐ {{ number_format((float) $playerProfile->average_rating, 1, ',', '.') }}
                                            <span class="font-medium text-pitch-500">({{ $playerProfile->ratings_count }})</span>
                                        </p>
                                    @endif
                                    @if ($playerProfile->attendance_rate !== null)
                                        <p class="text-xs font-bold whitespace-nowrap {{ (float) $playerProfile->attendance_rate >= 90 ? 'text-emerald-400' : ((float) $playerProfile->attendance_rate >= 70 ? 'text-amber-400' : 'text-red-400') }}">
                                            {{ number_format((float) $playerProfile->attendance_rate, 0, ',', '.') }}% {{ __('presença') }}
                                            <span class="font-medium text-pitch-500">({{ $playerProfile->games_played }})</span>
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <a href="{{ route('players.show', $playerProfile) }}" class="mt-4 inline-flex justify-center items-center px-4 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-500 transition">
                                {{ __('Ver Perfil') }}
                            </a>
                        </div>
                    @endforeach
                </div>

                <div>
                    {{ $players->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
