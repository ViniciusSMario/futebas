@php
    $publicUrl = route('public-games.show', $game);
@endphp

<div class="space-y-6">
    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6 space-y-5">
        <div class="flex items-center justify-between gap-2">
            <x-badge :color="match ($game->status) {
                'open' => 'emerald',
                'cancelled' => 'red',
                default => 'gray',
            }">
                {{ match ($game->status) {
                    'open' => __('Aberto'),
                    'cancelled' => __('Cancelado'),
                    default => __('Finalizado'),
                } }}
            </x-badge>
            @if ($game->isOpen())
                <div class="flex items-center gap-2">
                    <a href="{{ route('games.edit', $game) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
                        <x-heroicon-o-pencil-square class="w-4 h-4" /> {{ __('Editar') }}
                    </a>
                    <form method="post" action="{{ route('games.cancel', $game) }}" onsubmit="return confirm('{{ __('Cancelar esse Game?') }}')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-red-300 bg-red-500/10 border border-red-500/30 hover:bg-red-500/20 transition">
                            <x-heroicon-o-x-circle class="w-4 h-4" /> {{ __('Cancelar') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <x-slots-progress :current="$confirmedCount" :max="$game->max_players" />

        <dl class="text-sm text-pitch-200 space-y-2.5 pt-2 border-t border-pitch-800">
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-calendar-days class="w-4 h-4" /> {{ __('Data') }}</dt>
                <dd class="font-semibold">{{ $game->date->format('d/m/Y') }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-clock class="w-4 h-4" /> {{ __('Horário') }}</dt>
                <dd class="font-semibold">{{ $game->start_time->format('H:i') }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}</dt>
                <dd class="font-semibold text-right">{{ $game->location }}, {{ $game->city }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-flag class="w-4 h-4" /> {{ __('Modalidade') }}</dt>
                <dd class="font-semibold">{{ $game->modality }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valor estimado por jogador') }}</dt>
                <dd class="font-bold text-white">R$ {{ number_format((float) $game->price, 2, ',', '.') }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-shield-check class="w-4 h-4" /> {{ __('Aprovação') }}</dt>
                <dd class="font-semibold">{{ $game->requires_approval ? __('Manual pelo organizador') : __('Automática') }}</dd>
            </div>
        </dl>

        @if ($game->description)
            <p class="text-sm text-pitch-300 pt-2 border-t border-pitch-800">{{ $game->description }}</p>
        @endif
    </section>

    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-3">
            <x-heroicon-o-link class="w-4 h-4" /> {{ __('Link público') }}
        </h3>
        <p class="text-sm text-pitch-400 mb-3">{{ __('Compartilhe esse link para qualquer pessoa entrar no Game, mesmo sem conta no Futebas.') }}</p>
        <div class="flex items-center gap-2">
            <input type="text" readonly value="{{ $publicUrl }}" onclick="this.select()" class="flex-1 min-w-0 rounded-lg bg-pitch-800 border-pitch-700 text-white text-sm focus:border-emerald-500 focus:ring-emerald-500">
            <a href="{{ $publicUrl }}" target="_blank" class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition">
                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
            </a>
        </div>
    </section>
</div>
