@php
    use App\Services\TeamDrawService;

    $teamColors = ['blue', 'red', 'emerald', 'amber', 'violet', 'gray'];
    $balance = $teamBalance ?? [];

    $averages = array_column($balance, 'average');
    $spread = $averages === [] ? 0 : max($averages) - min($averages);

    // Uma diferença de poucos pontos entre times não decide pelada nenhuma;
    // a partir de ~10 já dá para sentir em campo. As classes vão inteiras
    // porque o Tailwind não enxerga nome de classe montado em tempo de
    // execução.
    [$spreadClasses, $spreadLabel] = match (true) {
        $spread <= 3 => ['bg-emerald-500/15 text-emerald-400', __('Times equilibrados')],
        $spread <= 9 => ['bg-amber-500/15 text-amber-400', __('Diferença pequena')],
        default => ['bg-red-500/15 text-red-400', __('Times desequilibrados')],
    };

    $selectedMode = old('mode', TeamDrawService::MODE_BALANCED);
@endphp

<div class="space-y-6">
    {{-- Fora do formulário de propósito: com a partida encerrada o
         formulário não é renderizado, e o recado do controller sobre por que
         o sorteio foi recusado ainda precisa aparecer. --}}
    @error('teams_count')
        <div class="flex items-start gap-2.5 rounded-2xl bg-red-500/10 border border-red-500/30 p-4 text-sm text-red-300">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" />
            {{ $message }}
        </div>
    @enderror

    {{-- Partida encerrada ou cancelada não sorteia mais nada. O formulário
         sai de cena inteiro: oferecer um botão que só responde 403 é pior
         do que não oferecer botão nenhum. --}}
    @unless ($game->isOpen())
        <div class="flex items-start gap-3 rounded-2xl bg-pitch-900 border border-pitch-800 p-4 sm:p-5">
            <span class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0 {{ $game->status === \App\Models\Game::STATUS_CANCELLED ? 'bg-red-500/15 text-red-400' : 'bg-pitch-800 text-pitch-300' }}">
                <x-dynamic-component :component="$game->status === \App\Models\Game::STATUS_CANCELLED ? 'heroicon-o-x-circle' : 'heroicon-o-check-badge'" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-white">
                    {{ $game->status === \App\Models\Game::STATUS_CANCELLED ? __('Partida cancelada') : __('Partida finalizada') }}
                </p>
                <p class="mt-0.5 text-sm text-pitch-400 leading-relaxed">
                    {{ $gameTeams->isEmpty()
                        ? __('Não é mais possível sortear times para esta partida.')
                        : __('Os times abaixo ficam como registro de como a partida foi jogada.') }}
                </p>
            </div>
        </div>
    @endunless

    @if ($game->isOpen())
    <section class="bg-pitch-900 rounded-3xl border border-pitch-800 shadow-card p-5 sm:p-6">
        <h3 class="flex items-center gap-1.5 text-sm font-black uppercase tracking-wide text-pitch-300 mb-4">
            <x-heroicon-o-sparkles class="w-4 h-4 text-emerald-400" /> {{ __('Sortear Times') }}
        </h3>

        <form method="post" action="{{ route('game-teams.draw', $game) }}" class="space-y-5">
            @csrf

            {{-- Modo do sorteio --}}
            <div x-data="{ mode: '{{ $selectedMode }}' }">
                <x-input-label :value="__('Como dividir')" class="mb-2" />

                <div class="grid grid-cols-1 xs:grid-cols-2 gap-2.5">
                    @foreach ([
                        [TeamDrawService::MODE_BALANCED, 'heroicon-o-scale', __('Equilibrado'), __('Espalha os goleiros e iguala a força dos times')],
                        [TeamDrawService::MODE_RANDOM, 'heroicon-o-arrow-path-rounded-square', __('Aleatório'), __('Sorte pura, do jeito que sempre foi')],
                    ] as [$value, $icon, $label, $hint])
                        <label
                            class="relative flex items-start gap-3 rounded-2xl border p-3.5 cursor-pointer transition"
                            :class="mode === '{{ $value }}'
                                ? 'border-emerald-500/60 bg-emerald-500/10'
                                : 'border-pitch-700 bg-pitch-800/40 hover:border-pitch-600'"
                        >
                            <input type="radio" name="mode" value="{{ $value }}" x-model="mode" class="sr-only">

                            <span
                                class="flex items-center justify-center w-9 h-9 rounded-xl shrink-0 transition"
                                :class="mode === '{{ $value }}' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-pitch-800 text-pitch-400'"
                            >
                                <x-dynamic-component :component="$icon" class="w-5 h-5" />
                            </span>

                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-white">{{ $label }}</span>
                                <span class="block text-xs text-pitch-400 leading-snug mt-0.5">{{ $hint }}</span>
                            </span>

                            <x-heroicon-s-check-circle
                                x-show="mode === '{{ $value }}'"
                                x-cloak
                                class="absolute top-2.5 right-2.5 w-5 h-5 text-emerald-400"
                            />
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <x-input-label for="teams_count" :value="__('Quantidade de times')" />
                    <select id="teams_count" name="teams_count" class="mt-1 block w-32 rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                        @for ($n = 2; $n <= 6; $n++)
                            <option value="{{ $n }}" @selected(old('teams_count', $gameTeams->count() ?: 2) == $n)>{{ $n }}</option>
                        @endfor
                    </select>
                </div>

                <button type="submit" class="inline-flex items-center gap-1.5 px-6 min-h-[44px] rounded-xl font-black text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/20 transition">
                    <x-heroicon-o-sparkles class="w-4 h-4" /> {{ $gameTeams->isEmpty() ? __('Sortear Times') : __('Novo Sorteio') }}
                </button>
            </div>

            <p class="flex items-start gap-1.5 text-xs text-pitch-500 leading-snug">
                <x-heroicon-o-information-circle class="w-4 h-4 shrink-0 mt-px" />
                {{ __('O equilíbrio usa a nota do card de cada jogador. Quem ainda não tem nota, inclusive convidados sem cadastro, entra pela média dos demais.') }}
            </p>
        </form>
    </section>
    @endif

    @if ($gameTeams->isEmpty() && $game->isOpen())
        <x-empty-state icon="heroicon-o-flag" :title="__('Nenhum time sorteado ainda.')" :description="__('Confirme jogadores na aba Participantes e clique em Sortear Times.')" />
    @elseif ($gameTeams->isEmpty())
        <x-empty-state icon="heroicon-o-flag" :title="__('Nenhum time foi sorteado.')" :description="__('Esta partida terminou sem divisão de times registrada.')" />
    @else
        {{-- Resultado do equilíbrio: sem isso o organizador não tem como
             distinguir um sorteio equilibrado do antigo, a não ser
             acreditando no rótulo. --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-pitch-900 border border-pitch-800 px-4 py-3">
            <div class="flex items-center gap-2.5">
                <span class="flex items-center justify-center w-9 h-9 rounded-xl shrink-0 {{ $spreadClasses }}">
                    <x-heroicon-o-scale class="w-5 h-5" />
                </span>
                <div>
                    <p class="text-sm font-bold text-white leading-tight">{{ $spreadLabel }}</p>
                    <p class="text-xs text-pitch-400">
                        {{ trans_choice(':count ponto de diferença entre o time mais forte e o mais fraco|:count pontos de diferença entre o time mais forte e o mais fraco', $spread, ['count' => $spread]) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($gameTeams as $index => $gameTeam)
                @php
                    $color = $teamColors[$index % count($teamColors)];
                    $summary = $balance[$gameTeam->id] ?? ['average' => 0, 'goalkeepers' => 0];
                @endphp

                <section class="bg-pitch-900 rounded-3xl border border-pitch-800 shadow-card p-5">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-badge :color="$color">{{ $gameTeam->name }}</x-badge>
                            <span class="text-xs text-pitch-500 truncate">{{ trans_choice(':count jogador|:count jogadores', $gameTeam->gamePlayers->count(), ['count' => $gameTeam->gamePlayers->count()]) }}</span>
                        </div>

                        <span class="shrink-0 text-right">
                            <span class="block text-lg font-black text-white leading-none tabular-nums">{{ $summary['average'] }}</span>
                            <span class="block text-[10px] font-bold uppercase tracking-wide text-pitch-500">{{ __('Média') }}</span>
                        </span>
                    </div>

                    {{-- Um time sem goleiro é o problema que mais estraga uma
                         pelada, então ele é dito, não deduzido da lista. --}}
                    <p class="flex items-center gap-1.5 text-xs font-semibold mb-3 {{ $summary['goalkeepers'] === 0 ? 'text-amber-400' : 'text-pitch-400' }}">
                        <x-heroicon-o-hand-raised class="w-3.5 h-3.5 shrink-0" />
                        {{ $summary['goalkeepers'] === 0
                            ? __('Sem goleiro cadastrado')
                            : trans_choice(':count goleiro|:count goleiros', $summary['goalkeepers'], ['count' => $summary['goalkeepers']]) }}
                    </p>

                    <ul class="space-y-2 pt-3 border-t border-pitch-800">
                        @foreach ($gameTeam->gamePlayers as $gamePlayer)
                            @php
                                $profile = $gamePlayer->user?->playerProfile;
                                $isGoalkeeper = (bool) $profile?->isGoalkeeper();
                            @endphp

                            <li class="flex items-center gap-2.5 text-sm text-pitch-200">
                                <x-avatar
                                    :name="$gamePlayer->displayName()"
                                    :photo="$gamePlayer->user?->photo_path ?? $profile?->photo_path"
                                    size="xs"
                                />

                                <span class="min-w-0 flex-1 truncate">{{ $gamePlayer->displayName() }}</span>

                                @if ($isGoalkeeper)
                                    <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-md bg-amber-500/15 text-amber-400" title="{{ __('Goleiro') }}">
                                        <x-heroicon-o-hand-raised class="w-3 h-3" />
                                    </span>
                                @endif

                                {{-- Um convidado sem cadastro não tem nota. Mostrar
                                     a média que o sorteio usou como se fosse dele
                                     seria inventar um dado. --}}
                                <span class="shrink-0 w-7 text-right text-xs font-bold tabular-nums {{ $profile ? 'text-pitch-300' : 'text-pitch-600' }}">
                                    {{ $profile?->overallScore() ?? '-' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</div>
