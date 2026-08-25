<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-arrow-path" :title="__('Peladas Semanais')" :subtitle="__('Séries que se repetem toda semana, com seus mensalistas')">
            <x-slot name="action">
                <a href="{{ route('game-series.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                    <x-heroicon-o-plus-circle class="w-4 h-4" /> {{ __('Nova pelada') }}
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'series-ended')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Pelada encerrada. As partidas já marcadas continuam valendo.') }}
                </p>
            @endif

            @if ($series->isEmpty())
                <x-empty-state icon="heroicon-o-arrow-path" :title="__('Nenhuma pelada semanal ainda')"
                    :description="__('Cadastre a sua pelada de toda semana uma única vez: as partidas passam a ser criadas sozinhas e os mensalistas já entram confirmados.')">
                    <x-slot name="action">
                        <a href="{{ route('game-series.create') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            {{ __('Criar pelada semanal') }}
                        </a>
                    </x-slot>
                </x-empty-state>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($series as $one)
                        @php
                            $next = $one->games()->where('status', \App\Models\Game::STATUS_OPEN)->upcoming()->orderBy('date')->first();
                        @endphp

                        <a href="{{ route('game-series.show', $one) }}" class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 flex flex-col gap-3 hover:border-emerald-600/40 hover:shadow-md hover:shadow-black/30 transition">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="font-extrabold text-white truncate">{{ $one->team_name }}</h3>
                                    <p class="text-sm text-pitch-400">
                                        {{ __('Toda :day às :time', ['day' => $one->dayName(), 'time' => $one->start_time->format('H:i')]) }}
                                    </p>
                                </div>
                                <x-badge :color="$one->isActive() ? 'emerald' : 'gray'">
                                    {{ $one->isActive() ? __('Ativa') : __('Encerrada') }}
                                </x-badge>
                            </div>

                            <p class="text-sm text-pitch-300 flex items-center gap-1.5">
                                <x-heroicon-o-map-pin class="w-4 h-4 shrink-0 text-pitch-500" />
                                <span class="truncate">{{ $one->location }}, {{ $one->city }}</span>
                            </p>

                            <div class="pt-3 border-t border-pitch-800 flex items-center justify-between gap-2 text-sm">
                                <span class="text-pitch-400 flex items-center gap-1.5">
                                    <x-heroicon-o-user-group class="w-4 h-4 shrink-0" />
                                    {{ trans_choice(':count mensalista|:count mensalistas', $one->members_count, ['count' => $one->members_count]) }}
                                </span>
                                @if ($next)
                                    <span class="font-bold text-emerald-400">{{ __('Próxima: :date', ['date' => $next->date->format('d/m')]) }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
