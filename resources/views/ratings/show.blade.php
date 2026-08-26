<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-star" :title="__('Minhas Avaliações')" :subtitle="__('O que organizadores e outros jogadores disseram sobre você')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-1xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if ($playerProfile)
                <x-player-history :player-profile="$playerProfile" />
            @endif

            {{-- How the overall score has moved, oldest to newest --}}
            @if ($ratings->count() > 1)
                @php
                    $trend = $ratings->sortBy('created_at')->values()->take(-12);
                @endphp

                <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                    <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400">
                        <x-heroicon-o-arrow-trending-up class="w-4 h-4" /> {{ __('Evolução das notas') }}
                    </h3>

                    <div class="mt-4 flex items-end justify-between gap-1.5 h-24">
                        @foreach ($trend as $rating)
                            <div class="flex-1 flex flex-col items-center gap-1.5 group" title="{{ $rating->created_at->format('d/m/Y') }} - {{ $rating->overall_rating }}/5">
                                <div class="w-full rounded-t bg-gradient-to-t from-emerald-700 to-emerald-500 group-hover:from-emerald-600 group-hover:to-emerald-400 transition"
                                    style="height: {{ (int) round($rating->overall_rating / 5 * 100) }}%"></div>
                                <span class="text-[10px] font-bold text-pitch-500 tabular-nums">{{ $rating->overall_rating }}</span>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-3 text-xs text-pitch-500">
                        {{ __('Suas últimas :count avaliações, da mais antiga para a mais recente.', ['count' => $trend->count()]) }}
                    </p>
                </div>
            @endif

            {{-- Summary --}}
            @if ($playerProfile && $playerProfile->ratings_count > 0)
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                    <x-stat-card icon="heroicon-o-star" :label="__('Geral')" :value="number_format((float) $playerProfile->average_rating, 1, ',', '.')" color="amber" />
                    <x-stat-card icon="heroicon-o-clock" :label="__('Pontualidade')" :value="number_format((float) $playerProfile->average_punctuality, 1, ',', '.')" color="blue" />
                    <x-stat-card icon="heroicon-o-face-smile" :label="__('Comportamento')" :value="number_format((float) $playerProfile->average_behavior, 1, ',', '.')" color="emerald" />
                    <x-stat-card icon="heroicon-o-bolt" :label="__('Desempenho')" :value="number_format((float) $playerProfile->average_performance, 1, ',', '.')" color="gray" />
                </div>
            @endif

            {{-- Ratings list --}}
            @if ($ratings->isEmpty())
                <x-empty-state icon="heroicon-o-star" :title="__('Nenhuma avaliação ainda')" :description="__('Depois que você jogar suas primeiras partidas, as avaliações dos organizadores vão aparecer aqui.')" />
            @else
                <div class="space-y-4">
                    @foreach ($ratings as $rating)
                        <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-bold text-white truncate">{{ $rating->organizer->name }}</p>
                                    @if ($rating->game)
                                        <p class="text-xs text-pitch-400 mt-0.5">
                                            {{ $rating->game->team_name }} &middot; {{ $rating->game->date->format('d/m/Y') }}
                                        </p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-0.5 shrink-0">
                                    @for ($i = 1; $i <= 5; $i++)
                                        @if ($i <= $rating->overall_rating)
                                            <x-heroicon-s-star class="w-5 h-5 text-amber-400" />
                                        @else
                                            <x-heroicon-o-star class="w-5 h-5 text-pitch-700" />
                                        @endif
                                    @endfor
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Pontualidade') }}</p>
                                    <p class="mt-1 font-bold text-white">{{ $rating->punctuality_rating }}/5</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Comportamento') }}</p>
                                    <p class="mt-1 font-bold text-white">{{ $rating->behavior_rating }}/5</p>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Desempenho') }}</p>
                                    <p class="mt-1 font-bold text-white">{{ $rating->performance_rating }}/5</p>
                                </div>
                            </div>

                            @if ($rating->comment)
                                <p class="mt-4 pt-4 border-t border-pitch-800 text-sm text-pitch-300 italic">
                                    &ldquo;{{ $rating->comment }}&rdquo;
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
