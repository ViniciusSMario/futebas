<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-magnifying-glass" :title="__('Procurar Jogadores')" :subtitle="__('Filtre por posição, modalidade, nível e disponibilidade')" />
    </x-slot>

    @php
        $selectedAvailability = $filters['availability'] ?? '';
        $hasActiveFilters = collect($filters)->filter()->isNotEmpty();
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-4 sm:p-6">
                <form method="get" action="{{ route('players.search') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="position" :value="__('Posição')" />
                        <select id="position" name="position" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            <option value="">{{ __('Qualquer posição') }}</option>
                            @foreach (\App\Models\PlayerProfile::POSITIONS as $position)
                                <option value="{{ $position }}" @selected(($filters['position'] ?? '') === $position)>{{ $position }}</option>
                            @endforeach
                        </select>
                    </div>

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
                        <x-input-label for="city" :value="__('Cidade')" />
                        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$filters['city'] ?? ''" placeholder="{{ __('Ex: São Paulo') }}" />
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

                    <div class="lg:col-span-3 flex items-center gap-4 pt-1">
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                            {{ __('Buscar') }}
                        </button>
                        @if ($hasActiveFilters)
                            <a href="{{ route('players.search') }}" class="text-sm font-medium text-pitch-400 hover:text-white">
                                {{ __('Limpar filtros') }}
                            </a>
                        @endif
                    </div>
                </form>
            </div>

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
                                    <h3 class="font-bold text-white truncate">{{ $playerProfile->user->name }}</h3>
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

                            <a href="{{ route('players.show', $playerProfile) }}" class="mt-4 inline-flex justify-center items-center px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-500 transition">
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
