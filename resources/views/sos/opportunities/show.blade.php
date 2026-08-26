@php
    use App\Models\SosApplication;

    $game = $sosRequest->game;
    $isLive = $sosRequest->isOpen();
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-megaphone" :title="__('SOS Goleiro')" :subtitle="$game->team_name" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('sos-opportunities.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-pitch-400 hover:text-white">
                &larr; {{ __('Voltar') }}
            </a>

            @if (session('error'))
                <p class="rounded-xl bg-amber-500/10 border border-amber-500/30 px-4 py-3 text-sm font-medium text-amber-300 flex items-start gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" /> {{ session('error') }}
                </p>
            @endif

            @if (session('status') === 'sos-applied')
                <p class="rounded-xl bg-emerald-500/10 border border-emerald-500/30 px-4 py-3 text-sm font-medium text-emerald-300 flex items-start gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" /> {{ __('Candidatura enviada! O organizador vai comparar as propostas e avisamos você da decisão.') }}
                </p>
            @endif

            {{-- The call --}}
            <section class="rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 text-white p-5 sm:p-6 shadow-lg shadow-red-500/20">
                <p class="text-xs font-bold uppercase tracking-widest text-red-50">🧤 {{ __('Precisa-se de goleiro') }}</p>
                <p class="mt-1 text-lg sm:text-xl font-extrabold">
                    {{ $game->date->format('d/m/Y') }} &middot; {{ $game->start_time->format('H:i') }}
                    @if ($game->end_time) – {{ $game->end_time->format('H:i') }} @endif
                </p>
                <p class="mt-1 text-sm text-red-50 flex items-center gap-1">
                    <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" /> {{ $game->location }}, {{ $game->city }}
                </p>

                <div class="mt-4 pt-4 border-t border-white/20 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <span class="font-bold text-base">R$ {{ number_format((float) $sosRequest->offered_value, 2, ',', '.') }}</span>
                    <span class="text-red-50"> {{ $game->modality }}</span>
                    <span class="text-red-50">{{ __('Organizador') }}: {{ $sosRequest->organizer->name }}</span>
                </div>

                @if ($sosRequest->message)
                    <p class="mt-3 text-sm text-red-50 italic">"{{ $sosRequest->message }}"</p>
                @endif
            </section>

            {{-- How the competition works, so a pending status is never a surprise --}}
            <div class="rounded-2xl bg-pitch-900/60 border border-pitch-800 p-4 text-sm text-pitch-400 flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 shrink-0 text-pitch-500" />
                <p>
                    {{ __('Sua candidatura fica pendente até o organizador escolher. Ele compara valor, localização e avaliações.') }}
                    @if ($competitorsCount > 0)
                        <strong class="text-pitch-200">{{ trans_choice('Há :count outro goleiro na disputa.|Há :count outros goleiros na disputa.', $competitorsCount, ['count' => $competitorsCount]) }}</strong>
                    @endif
                </p>
            </div>

            {{-- Being chosen is checked first: accepting fills the request,
                 so this reads as "closed" otherwise, and the winner would
                 never be told they won. --}}
            @if ($application && $application->status === SosApplication::STATUS_ACCEPTED)
                <div class="rounded-2xl bg-emerald-500/10 border border-emerald-500/30 p-5 text-emerald-300">
                    <p class="font-bold flex items-center gap-2"><x-heroicon-o-check-badge class="w-5 h-5" /> {{ __('Você foi escolhido!') }}</p>
                    <p class="mt-1 text-sm">{{ __('Você já está confirmado na partida.') }}</p>
                </div>
            @elseif (! $isLive)
                <x-empty-state icon="heroicon-o-clock" :title="__('Esta chamada foi encerrada')" :description="__('O organizador já escolheu um goleiro, cancelou a chamada, ou a partida já começou.')" />
            @elseif ($alreadyInGame)
                <div class="rounded-2xl bg-pitch-900 border border-pitch-800 p-5 text-pitch-300">
                    <p class="font-bold text-white flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-400" /> {{ __('Você já está nessa partida') }}
                    </p>
                    <p class="mt-1 text-sm">{{ __('A chamada é para a partida em que você já entrou, então não há como se candidatar a ela.') }}</p>
                </div>
            @else
                {{-- Apply / revise the bid --}}
                <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                    <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                        <x-heroicon-o-hand-raised class="w-4 h-4" />
                        {{ $application?->isPending() ? __('Ajustar minha proposta') : __('Me candidatar') }}
                    </h3>

                    <form method="post" action="{{ route('sos-opportunities.apply', $sosRequest) }}">
                        @csrf

                        <div>
                            <x-input-label for="asking_price" :value="__('Quanto você quer receber (R$)')" />
                            <x-text-input
                                id="asking_price"
                                name="asking_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="mt-1 block w-full rounded-lg"
                                :value="old('asking_price', $application?->asking_price ?? $sosRequest->offered_value)"
                                required
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('asking_price')" />
                            <p class="mt-1.5 text-xs text-pitch-500">{{ __('Aceitar o valor oferecido aumenta suas chances.') }}</p>
                        </div>

                        <div class="mt-4">
                            <x-input-label for="message" :value="__('Mensagem (opcional)')" />
                            <textarea id="message" name="message" rows="2" maxlength="500" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white placeholder-pitch-500 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm" placeholder="{{ __('Ex.: chego 15 minutos antes.') }}">{{ old('message', $application?->message) }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('message')" />
                        </div>

                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                <x-heroicon-o-hand-raised class="w-4 h-4" />
                                {{ $application?->isPending() ? __('Atualizar proposta') : __('Me candidatar') }}
                            </button>
                        </div>
                    </form>

                    @if ($application?->isPending())
                        <form method="post" action="{{ route('sos-opportunities.withdraw', $sosRequest) }}" class="mt-3">
                            @csrf
                            @method('delete')
                            <button type="submit" class="text-xs font-bold uppercase tracking-widest text-pitch-400 hover:text-red-400 transition">
                                {{ __('Desistir da vaga') }}
                            </button>
                        </form>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
