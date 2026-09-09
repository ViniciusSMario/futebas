@php
    // A participação de quem está vendo. Um organizador que também joga
    // tem as duas coisas na mesma tela: administra acima, confirma
    // presença aqui como qualquer um.
    $participation = $viewerGamePlayer ?? null;
    $participationStatus = $participation?->status;
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
            @if ($isOrganizer && $game->isOpen())
                <div class="flex items-center gap-2">
                    <a href="{{ route('games.edit', $game) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
                        <x-heroicon-o-pencil-square class="w-4 h-4" /> {{ __('Editar') }}
                    </a>
                    <form
                        method="post"
                        action="{{ route('games.cancel', $game) }}"
                        data-confirm="{{ __('Cancelar esta partida?') }}"
                        data-confirm-text="{{ __('Todos os participantes serão avisados, e qualquer SOS aberto para ela é encerrado junto. Não dá para desfazer.') }}"
                        data-confirm-button="{{ __('Sim, cancelar') }}"
                    >
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
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-calendar-days class="w-4 h-4" /> {{ __('Quando') }}</dt>
                <dd class="font-semibold text-right">{{ $game->whenLabel() }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif</dd>
            </div>
            {{-- O endereço é um link para o mapa, e não texto: a última coisa
                 que alguém faz com esta tela é sair de casa para o campo. --}}
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}</dt>
                {{-- Fluxo inline, e não `inline-flex`: `location` é texto
                     digitado à mão e um nome comprido de quadra precisa
                     quebrar em linha. Num flex container o texto vira um
                     item que não encolhe abaixo do seu min-content, e a
                     página inteira sai rolando de lado — o que aqui não é
                     defeito estético, é a barra de baixo perdendo metade
                     dos botões. --}}
                <dd class="text-right min-w-0">
                    <a href="{{ $game->mapUrl() }}" target="_blank" rel="noopener" class="font-semibold text-emerald-400 hover:text-emerald-300 underline decoration-emerald-500/40 underline-offset-2 break-words">
                        {{ $game->location }}, {{ $game->city }}
                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 inline -mt-0.5" />
                    </a>
                </dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-flag class="w-4 h-4" /> {{ __('Modalidade') }}</dt>
                <dd class="font-semibold">{{ $game->modality }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valor estimado por jogador') }}</dt>
                <dd class="font-bold text-white">R$ {{ number_format((float) $game->price, 2, ',', '.') }}</dd>
            </div>
            @if ($isOrganizer)
                <div class="flex justify-between gap-2">
                    <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-shield-check class="w-4 h-4" /> {{ __('Aprovação') }}</dt>
                    <dd class="font-semibold">{{ $game->requires_approval ? __('Manual pelo organizador') : __('Automática') }}</dd>
                </div>
            @else
                <div class="flex justify-between gap-2">
                    <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-user class="w-4 h-4" /> {{ __('Organizador') }}</dt>
                    <dd class="font-semibold">{{ $game->user->name }}</dd>
                </div>
            @endif
        </dl>

        @if ($game->description)
            <p class="text-sm text-pitch-300 pt-2 border-t border-pitch-800">{{ $game->description }}</p>
        @endif
    </section>

    {{-- A vaga de quem está vendo. Fica logo abaixo da partida porque no
         dia do jogo é a única coisa que se vem fazer aqui. --}}
    @if ($participation)
        <section class="bg-pitch-900 rounded-2xl border {{ $participation->hasCheckedIn() ? 'border-emerald-500/40' : 'border-pitch-800' }} shadow-sm shadow-black/20 p-5 sm:p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400">
                    <x-heroicon-o-user-circle class="w-4 h-4" /> {{ __('Sua vaga') }}
                </h3>

                @if ($participation->hasCheckedIn())
                    <x-badge color="emerald">
                        <x-heroicon-s-check-circle class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" /> {{ __('Presença confirmada') }}
                    </x-badge>
                @else
                    <x-badge :color="$participationStatus === 'confirmed' ? 'emerald' : 'amber'">
                        {{ match ($participationStatus) {
                            'confirmed' => __('Confirmado'),
                            'waiting_list' => __('Lista de espera'),
                            default => __('Aguardando aprovação'),
                        } }}
                    </x-badge>
                @endif
            </div>

            @if ($participation->canCheckIn() && ! $participation->hasCheckedIn())
                <form method="post" action="{{ route('games.check-in', $game) }}">
                    @csrf
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-6 min-h-[48px] rounded-xl font-black text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/25 transition">
                        <x-heroicon-o-hand-raised class="w-4 h-4" /> {{ __('Confirmar presença') }}
                    </button>
                </form>
            @elseif ($participation->hasCheckedIn() && $game->isCheckInOpen())
                <form method="post" action="{{ route('games.check-in.undo', $game) }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-300 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
                        {{ __('Desfazer presença') }}
                    </button>
                </form>
            @endif

            {{-- O organizador desmarca a própria partida cancelando-a, não
                 saindo dela: oferecer "cancelar participação" a quem organiza
                 seria um botão que promete a coisa errada. --}}
            @unless ($isOrganizer)
                <x-cancel-participation :game="$game" />
            @endunless
        </section>
    @endif

    @if ($isOrganizer)
        <x-share-game :game="$game" />
    @endif
</div>
