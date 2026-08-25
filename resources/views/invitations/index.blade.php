<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-envelope" :title="__('Meus Convites')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            @if (session('status') === 'invitation-accepted')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Convite aceito! A partida foi adicionada às suas partidas confirmadas.') }}
                </p>
            @elseif (session('status') === 'invitation-declined')
                <p class="flex items-center gap-1.5 text-sm font-medium text-pitch-300">
                    <x-heroicon-o-x-circle class="w-4 h-4" /> {{ __('Convite recusado.') }}
                </p>
            @endif

            <section>
                <div class="flex items-center gap-2 mb-4">
                    <h3 class="text-lg font-extrabold text-white">{{ __('Pendentes') }}</h3>
                    <x-badge color="amber">{{ $pendentes->count() }}</x-badge>
                </div>

                @if ($pendentes->isEmpty())
                    <x-empty-state icon="heroicon-o-envelope-open" :title="__('Nenhum convite pendente no momento.')" />
                @else
                    <div class="space-y-4">
                        @foreach ($pendentes as $invitation)
                            @php $game = $invitation->game; @endphp
                            <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6 space-y-4">
                                <p class="text-lg font-extrabold text-white">{{ __('Você está disponível para jogar?') }}</p>

                                <dl class="text-sm text-pitch-200 space-y-2">
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-user-group class="w-4 h-4" /> {{ __('Time') }}</dt>
                                        <dd class="font-semibold text-right">{{ $invitation->team ?? $game->team_name }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-calendar-days class="w-4 h-4" /> {{ __('Data') }}</dt>
                                        <dd class="font-semibold">{{ $game->date->format('d/m') }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-clock class="w-4 h-4" /> {{ __('Horário') }}</dt>
                                        <dd class="font-semibold">{{ $game->start_time->format('H:i') }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}</dt>
                                        <dd class="font-semibold text-right">{{ $game->location }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Modalidade') }}</dt>
                                        <dd class="font-semibold">{{ $game->modality }}</dd>
                                    </div>
                                    @if ($invitation->position)
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-flag class="w-4 h-4" /> {{ __('Posição') }}</dt>
                                            <dd class="font-semibold">{{ $invitation->position }}</dd>
                                        </div>
                                    @endif
                                    <div class="flex justify-between gap-2">
                                        <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valor') }}</dt>
                                        <dd class="font-bold text-white">R$ {{ number_format((float) ($invitation->value ?? $game->price), 2, ',', '.') }}</dd>
                                    </div>
                                </dl>

                                <div class="flex items-center gap-3 pt-2 border-t border-pitch-800">
                                    <form method="post" action="{{ route('invitations.accept', $invitation) }}" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                                            {{ __('Aceitar') }}
                                        </button>
                                    </form>
                                    <form method="post" action="{{ route('invitations.decline', $invitation) }}" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
                                            {{ __('Recusar') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <div class="flex items-center gap-2 mb-4">
                    <h3 class="text-lg font-extrabold text-white">{{ __('Histórico') }}</h3>
                    <x-badge color="gray">{{ $respondidos->count() }}</x-badge>
                </div>

                @if ($respondidos->isEmpty())
                    <x-empty-state icon="heroicon-o-clock" :title="__('Nenhum convite respondido ainda.')" />
                @else
                    <div class="space-y-3">
                        @foreach ($respondidos as $invitation)
                            @php
                                $game = $invitation->game;
                                $isAccepted = $invitation->status === \App\Models\Invitation::STATUS_ACCEPTED;
                            @endphp
                            <div class="flex items-center justify-between gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 p-4">
                                <div class="min-w-0">
                                    <p class="font-bold text-white truncate">{{ $invitation->team ?? $game->team_name }}</p>
                                    <p class="text-sm text-pitch-400">{{ $game->date->format('d/m/Y') }} &middot; {{ $game->start_time->format('H:i') }} &middot; {{ $game->location }}</p>
                                </div>
                                <x-badge :color="$isAccepted ? 'emerald' : 'gray'">{{ $isAccepted ? __('Aceito') : __('Recusado') }}</x-badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
