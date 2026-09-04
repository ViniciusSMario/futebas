<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-exclamation-triangle" :title="__('SOS Goleiro')" :subtitle="__('Suas chamadas e as candidaturas recebidas')">
            <x-slot name="action">
                <a href="{{ route('sos.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-500 hover:to-orange-400 shadow-lg shadow-red-500/20 transition">
                    <x-heroicon-o-megaphone class="w-4 h-4" /> {{ __('Novo SOS') }}
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-quota-notice :feature="\App\Enums\Feature::SOS_REQUESTS" />

            @if ($sosRequests->isEmpty())
                <x-empty-state icon="heroicon-o-megaphone" :title="__('Nenhum SOS publicado')" :description="__('Publique uma chamada e avisamos na hora todos os goleiros cadastrados na região da partida.')">
                    <x-slot name="action">
                        <a href="{{ route('sos.create') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            {{ __('Publicar SOS') }}
                        </a>
                    </x-slot>
                </x-empty-state>
            @else
                <div class="space-y-3">
                    @foreach ($sosRequests as $sosRequest)
                        @php
                            $pending = $sosRequest->pendingApplicationsCount();
                        @endphp

                        <a href="{{ route('sos.show', $sosRequest) }}" class="block bg-pitch-900 rounded-2xl border border-pitch-800 hover:border-pitch-700 shadow-sm shadow-black/20 p-5 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-bold text-white truncate">🧤 {{ $sosRequest->game->date->format('d/m') }} {{ $sosRequest->game->start_time->format('H:i') }}</h3>
                                        <x-sos-status-badge :sos-request="$sosRequest" />
                                    </div>

                                    <p class="mt-1 text-sm text-pitch-400 flex items-center gap-1 truncate">
                                        <x-heroicon-o-map-pin class="w-3.5 h-3.5 shrink-0" /> {{ $sosRequest->game->location }}, {{ $sosRequest->game->city }}
                                    </p>
                                </div>

                                <div class="text-right shrink-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Oferecido') }}</p>
                                    <p class="font-extrabold text-white">R$ {{ number_format((float) $sosRequest->offered_value, 2, ',', '.') }}</p>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-pitch-800 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-pitch-400">
                                <span class="flex items-center gap-1">
                                    <x-heroicon-o-paper-airplane class="w-3.5 h-3.5" />
                                    {{ trans_choice(':count goleiro avisado|:count goleiros avisados', $sosRequest->notified_count, ['count' => $sosRequest->notified_count]) }}
                                </span>

                                @if ($pending > 0)
                                    <span class="flex items-center gap-1 font-bold text-amber-400">
                                        <x-heroicon-o-hand-raised class="w-3.5 h-3.5" />
                                        {{ trans_choice(':count candidatura aguardando|:count candidaturas aguardando', $pending, ['count' => $pending]) }}
                                    </span>
                                @elseif ($sosRequest->isFilled())
                                    <span class="flex items-center gap-1 font-bold text-emerald-400">
                                        <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> {{ __('Goleiro confirmado') }}
                                    </span>
                                @else
                                    <span>{{ __('Nenhuma candidatura ainda') }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            <x-push-toggle />
        </div>
    </div>
</x-app-layout>
