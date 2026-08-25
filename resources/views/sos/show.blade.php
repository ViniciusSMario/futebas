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
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('sos.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-pitch-400 hover:text-white">
                &larr; {{ __('Meus SOS') }}
            </a>

            @if (session('error'))
                <p class="rounded-xl bg-amber-500/10 border border-amber-500/30 px-4 py-3 text-sm font-medium text-amber-300 flex items-start gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" /> {{ session('error') }}
                </p>
            @endif

            @if (session('status') === 'sos-published')
                <p class="rounded-xl bg-emerald-500/10 border border-emerald-500/30 px-4 py-3 text-sm font-medium text-emerald-300 flex items-start gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
                    {{ trans_choice('SOS publicado! Avisamos :count goleiro da região.|SOS publicado! Avisamos :count goleiros da região.', $sosRequest->notified_count, ['count' => $sosRequest->notified_count]) }}
                </p>
            @elseif (session('status') === 'sos-accepted')
                <p class="rounded-xl bg-emerald-500/10 border border-emerald-500/30 px-4 py-3 text-sm font-medium text-emerald-300 flex items-start gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" /> {{ __('Goleiro confirmado na partida. Os demais candidatos foram avisados.') }}
                </p>
            @elseif (session('status') === 'sos-rejected')
                <p class="text-sm font-medium text-pitch-300">{{ __('Candidatura recusada.') }}</p>
            @elseif (session('status') === 'sos-cancelled')
                <p class="text-sm font-medium text-pitch-300">{{ __('SOS cancelado.') }}</p>
            @endif

            {{-- The call --}}
            <section class="rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 text-white p-5 sm:p-6 shadow-lg shadow-red-500/20">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-widest text-red-50">{{ __('Sua chamada') }}</p>
                        <p class="mt-1 text-lg sm:text-xl font-extrabold">
                            {{ $game->date->format('d/m/Y') }} &middot; {{ $game->start_time->format('H:i') }} &middot; {{ $game->modality }}
                        </p>
                        <p class="mt-1 text-sm text-red-50 flex items-center gap-1">
                            <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" /> {{ $game->location }}, {{ $game->city }}
                        </p>
                    </div>

                    <x-sos-status-badge :sos-request="$sosRequest" class="shrink-0 !bg-white/20 !text-white" />
                </div>

                <div class="mt-4 pt-4 border-t border-white/20 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <span class="font-bold">{{ __('Valor oferecido') }}: R$ {{ number_format((float) $sosRequest->offered_value, 2, ',', '.') }}</span>
                    <span class="text-red-50 flex items-center gap-1">
                        <x-heroicon-o-paper-airplane class="w-4 h-4" />
                        {{ trans_choice(':count goleiro avisado|:count goleiros avisados', $sosRequest->notified_count, ['count' => $sosRequest->notified_count]) }}
                    </span>
                    @if ($sosRequest->expires_at)
                        <span class="text-red-50 flex items-center gap-1">
                            <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Até') }} {{ $sosRequest->expires_at->format('d/m H:i') }}
                        </span>
                    @endif
                </div>

                @if ($sosRequest->message)
                    <p class="mt-3 text-sm text-red-50 italic">"{{ $sosRequest->message }}"</p>
                @endif
            </section>

            {{-- Candidates --}}
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-bold uppercase tracking-wide text-pitch-400">{{ __('Candidaturas') }}</h3>

                @if ($isLive)
                    <form method="post" action="{{ route('sos.cancel', $sosRequest) }}" onsubmit="return confirm('{{ __('Cancelar este SOS? Os candidatos pendentes serão avisados.') }}')">
                        @csrf
                        @method('patch')
                        <button type="submit" class="text-xs font-bold uppercase tracking-widest text-pitch-400 hover:text-red-400 transition">{{ __('Cancelar SOS') }}</button>
                    </form>
                @endif
            </div>

            @if ($applications->isEmpty())
                <x-empty-state icon="heroicon-o-hand-raised" :title="__('Ninguém se candidatou ainda')" :description="__('Assim que um goleiro responder, ele aparece aqui e você recebe uma notificação.')" />
            @else
                <div class="space-y-3">
                    @foreach ($applications as $application)
                        @php
                            $profile = $application->user->playerProfile;
                            $isCheaper = (float) $application->asking_price <= (float) $sosRequest->offered_value;
                            $sameCity = $profile?->city === $game->city;
                        @endphp

                        <div @class([
                            'bg-pitch-900 rounded-2xl border shadow-sm shadow-black/20 p-5',
                            'border-emerald-500/50' => $application->status === SosApplication::STATUS_ACCEPTED,
                            'border-pitch-800' => $application->status !== SosApplication::STATUS_ACCEPTED,
                            'opacity-60' => in_array($application->status, [SosApplication::STATUS_REJECTED, SosApplication::STATUS_WITHDRAWN], true),
                        ])>
                            <div class="flex items-start gap-4">
                                @if ($profile?->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($profile->photo_path) }}" alt="{{ $application->user->name }}" class="h-14 w-14 rounded-full object-cover shrink-0 ring-2 ring-pitch-800">
                                @else
                                    <div class="h-14 w-14 rounded-full bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 flex items-center justify-center text-lg font-extrabold text-emerald-300 shrink-0">
                                        {{ Str::upper(Str::substr($application->user->name, 0, 1)) }}
                                    </div>
                                @endif

                                <div class="min-w-0 grow">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="font-bold text-white truncate">{{ $application->user->name }}</h4>

                                        @if ($application->status === SosApplication::STATUS_ACCEPTED)
                                            <x-badge color="emerald">{{ __('Escolhido') }}</x-badge>
                                        @elseif ($application->status === SosApplication::STATUS_REJECTED)
                                            <x-badge color="gray">{{ __('Recusado') }}</x-badge>
                                        @elseif ($application->status === SosApplication::STATUS_WITHDRAWN)
                                            <x-badge color="gray">{{ __('Desistiu') }}</x-badge>
                                        @else
                                            <x-badge color="amber">{{ __('Pendente') }}</x-badge>
                                        @endif
                                    </div>

                                    <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        {{-- The three things the organizer weighs: price, distance, reputation. --}}
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Pede') }}</p>
                                            <p @class(['font-extrabold', 'text-emerald-400' => $isCheaper, 'text-amber-400' => ! $isCheaper])>
                                                R$ {{ number_format((float) $application->asking_price, 2, ',', '.') }}
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Cidade') }}</p>
                                            <p class="font-bold text-white text-sm truncate">
                                                {{ $profile?->city ?? '—' }}
                                                @if ($sameCity)
                                                    <span class="text-emerald-400" title="{{ __('Mesma cidade da partida') }}">•</span>
                                                @endif
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Avaliação') }}</p>
                                            <p class="font-bold text-white text-sm">
                                                @if ($profile && $profile->ratings_count > 0)
                                                    ⭐ {{ number_format((float) $profile->average_rating, 1, ',', '.') }}
                                                    <span class="text-pitch-500 font-medium">({{ $profile->ratings_count }})</span>
                                                @else
                                                    <span class="text-pitch-500 font-medium">{{ __('Sem avaliações') }}</span>
                                                @endif
                                            </p>
                                        </div>

                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Nível') }}</p>
                                            <p class="font-bold text-white text-sm">{{ $profile?->level ?? '—' }}</p>
                                        </div>

                                        {{-- Turning up is the thing an SOS actually buys. --}}
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Presença') }}</p>
                                            <p class="font-bold text-sm">
                                                @if ($profile?->attendance_rate !== null)
                                                    <span class="{{ (float) $profile->attendance_rate >= 90 ? 'text-emerald-400' : ((float) $profile->attendance_rate >= 70 ? 'text-amber-400' : 'text-red-400') }}">
                                                        {{ number_format((float) $profile->attendance_rate, 0, ',', '.') }}%
                                                    </span>
                                                    <span class="text-pitch-500 font-medium">({{ $profile->games_played }} {{ __('jogos') }})</span>
                                                @else
                                                    <span class="text-pitch-500 font-medium">{{ __('Sem histórico') }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    @if ($application->message)
                                        <p class="mt-3 text-sm text-pitch-300 italic">"{{ $application->message }}"</p>
                                    @endif

                                    <div class="mt-3 flex flex-wrap items-center gap-3">
                                        @if ($profile)
                                            <a href="{{ route('players.show', $profile) }}" class="text-xs font-bold uppercase tracking-widest text-pitch-400 hover:text-white transition">
                                                {{ __('Ver perfil') }}
                                            </a>
                                        @endif

                                        @if ($isLive && $application->isPending())
                                            <form method="post" action="{{ route('sos.accept', [$sosRequest, $application]) }}">
                                                @csrf
                                                @method('patch')
                                                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                                    <x-heroicon-o-check class="w-4 h-4" /> {{ __('Aceitar') }}
                                                </button>
                                            </form>

                                            <form method="post" action="{{ route('sos.reject', [$sosRequest, $application]) }}">
                                                @csrf
                                                @method('patch')
                                                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-400 hover:text-red-400 transition">
                                                    {{ __('Recusar') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($sosRequest->isFilled())
                <a href="{{ route('games.show', $game) }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-emerald-400 hover:text-emerald-300">
                    <x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Ver a partida') }}
                </a>
            @endif
        </div>
    </div>
</x-app-layout>
