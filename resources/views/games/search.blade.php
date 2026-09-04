<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-magnifying-glass" :title="__('Procurar Partidas')" :subtitle="__('Peladas abertas na sua região esperando por jogadores')" />
    </x-slot>

    @php
        $hasActiveFilters = collect($filters)->filter()->isNotEmpty();
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-4 sm:p-6">
                <form method="get" action="{{ route('games.search') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="sm:col-span-2 lg:col-span-3">
                        <x-input-label for="q" :value="__('Buscar')" />
                        <x-text-input id="q" name="q" type="search" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$filters['q'] ?? ''" placeholder="{{ __('Time, quadra ou cidade') }}" />
                    </div>

                    <x-city-select any :state="$filters['state'] ?? ''" :city="$filters['city'] ?? ''" />

                    <div>
                        <x-input-label for="modality" :value="__('Modalidade')" />
                        <select id="modality" name="modality" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            <option value="">{{ __('Qualquer modalidade') }}</option>
                            @foreach (\App\Models\Game::MODALITIES as $modality)
                                <option value="{{ $modality }}" @selected(($filters['modality'] ?? '') === $modality)>{{ $modality }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="position" :value="__('Posição procurada')" />
                        <select id="position" name="position" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            <option value="">{{ __('Qualquer posição') }}</option>
                            @foreach (\App\Models\Game::POSITIONS as $position)
                                <option value="{{ $position }}" @selected(($filters['position'] ?? '') === $position)>{{ $position }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="period" :value="__('Quando')" />
                        <select id="period" name="period" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            @foreach (\App\Http\Controllers\GameController::PERIOD_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['period'] ?? '') === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="max_price" :value="__('Valor máximo (R$)')" />
                        <x-text-input id="max_price" name="max_price" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$filters['max_price'] ?? ''" placeholder="{{ __('Ex: 30') }}" />
                    </div>

                    <div class="flex items-end">
                        <label for="with_spots" class="inline-flex items-center gap-2 pb-2.5 cursor-pointer">
                            <input id="with_spots" name="with_spots" type="checkbox" value="1"
                                class="rounded bg-pitch-800 border-pitch-700 text-emerald-600 focus:ring-emerald-500 shadow-sm"
                                @checked(filled($filters['with_spots'] ?? null))>
                            <span class="text-sm font-medium text-pitch-200">{{ __('Somente com vagas') }}</span>
                        </label>
                    </div>

                    <div class="lg:col-span-3 flex items-center gap-4 pt-1">
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                            {{ __('Buscar') }}
                        </button>
                        @if ($hasActiveFilters)
                            <a href="{{ route('games.search') }}" class="text-sm font-medium text-pitch-400 hover:text-white">
                                {{ __('Limpar filtros') }}
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="flex items-center justify-between">
                <p class="text-sm text-pitch-400">
                    {{ trans_choice(':count partida encontrada|:count partidas encontradas', $games->total(), ['count' => $games->total()]) }}
                </p>
            </div>

            @if ($games->isEmpty())
                <x-empty-state icon="heroicon-o-magnifying-glass" :title="__('Nenhuma partida encontrada')"
                    :description="$hasActiveFilters
                        ? __('Tente ajustar os filtros para ver mais resultados.')
                        : __('Ainda não há peladas abertas por aqui. Volte mais tarde ou peça o link da partida ao organizador.')" />
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($games as $game)
                        @include('games._search-card', ['game' => $game])
                    @endforeach
                </div>

                <div>
                    {{ $games->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
