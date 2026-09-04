<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-magnifying-glass" :title="__('Buscar Jogadores')" :subtitle="$game->team_name" />
    </x-slot>

    @php
        $hasActiveFilters = collect($filters)->filter()->isNotEmpty();
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-4 sm:p-6">
                <form method="get" action="{{ route('games.invitations.search', $game) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
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

                    <x-city-select any :state="$filters['state'] ?? ''" :city="$filters['city'] ?? ''" />

                    <div>
                        <x-input-label for="level" :value="__('Nível')" />
                        <select id="level" name="level" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                            <option value="">{{ __('Qualquer nível') }}</option>
                            @foreach (\App\Models\PlayerProfile::LEVELS as $level)
                                <option value="{{ $level }}" @selected(($filters['level'] ?? '') === $level)>{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lg:col-span-3 flex items-center gap-4 pt-1">
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                            {{ __('Buscar') }}
                        </button>
                        @if ($hasActiveFilters)
                            <a href="{{ route('games.invitations.search', $game) }}" class="text-sm font-medium text-pitch-400 hover:text-white">
                                {{ __('Limpar filtros') }}
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <p class="text-sm text-pitch-400">
                {{ trans_choice(':count jogador encontrado|:count jogadores encontrados', $players->total(), ['count' => $players->total()]) }}
            </p>

            @if ($players->isEmpty())
                <x-empty-state icon="heroicon-o-magnifying-glass" :title="__('Nenhum jogador encontrado')" :description="__('Tente ajustar os filtros para ver mais resultados.')" />
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($players as $playerProfile)
                        <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 flex flex-col hover:shadow-md hover:shadow-black/30 hover:border-emerald-600/40 transition">
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
                            </div>

                            <form method="post" action="{{ route('games.invitations.store', [$game, $playerProfile]) }}" class="mt-4">
                                @csrf
                                <input type="hidden" name="position" value="{{ $playerProfile->positions[0] ?? '' }}">
                                <button type="submit" class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-500 transition">
                                    <x-heroicon-o-envelope class="w-4 h-4" /> {{ __('Convidar') }}
                                </button>
                            </form>
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
