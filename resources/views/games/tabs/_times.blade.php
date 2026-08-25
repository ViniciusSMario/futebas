@php
    $teamColors = ['blue', 'red', 'emerald', 'amber', 'gray'];
@endphp

<div class="space-y-6">
    <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
        <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
            <x-heroicon-o-sparkles class="w-4 h-4" /> {{ __('Sortear Times') }}
        </h3>

        <form method="post" action="{{ route('game-teams.draw', $game) }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div>
                <x-input-label for="teams_count" :value="__('Quantidade de times')" />
                <select id="teams_count" name="teams_count" class="mt-1 block w-32 rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                    @for ($n = 2; $n <= 6; $n++)
                        <option value="{{ $n }}" @selected(old('teams_count', $gameTeams->count() ?: 2) == $n)>{{ $n }}</option>
                    @endfor
                </select>
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                <x-heroicon-o-sparkles class="w-4 h-4" /> {{ $gameTeams->isEmpty() ? __('Sortear Times') : __('Novo Sorteio') }}
            </button>
        </form>
    </section>

    @if ($gameTeams->isEmpty())
        <x-empty-state icon="heroicon-o-flag" :title="__('Nenhum time sorteado ainda.')" :description="__('Confirme jogadores na aba Participantes e clique em Sortear Times.')" />
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($gameTeams as $index => $gameTeam)
                @php $color = $teamColors[$index % count($teamColors)]; @endphp
                <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <x-badge :color="$color">{{ $gameTeam->name }}</x-badge>
                        <span class="text-xs text-pitch-500">{{ trans_choice(':count jogador|:count jogadores', $gameTeam->gamePlayers->count(), ['count' => $gameTeam->gamePlayers->count()]) }}</span>
                    </div>
                    <ul class="space-y-2">
                        @foreach ($gameTeam->gamePlayers as $gamePlayer)
                            <li class="flex items-center gap-2 text-sm text-pitch-200">
                                <div class="h-7 w-7 rounded-full bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 flex items-center justify-center text-[11px] font-extrabold text-emerald-300 shrink-0">
                                    {{ Str::upper(Str::substr($gamePlayer->displayName(), 0, 1)) }}
                                </div>
                                {{ $gamePlayer->displayName() }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</div>
