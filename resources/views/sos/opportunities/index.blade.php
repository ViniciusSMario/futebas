@php
    use App\Models\SosApplication;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-megaphone" :title="__('SOS na sua região')" :subtitle="__('Chamadas pagas de última hora')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'sos-withdrawn')
                <p class="text-sm font-medium text-pitch-300">{{ __('Você saiu da disputa.') }}</p>
            @endif

            @if ($playerProfile === null)
                <x-empty-state icon="heroicon-o-user-circle" :title="__('Complete seu perfil de jogador')" :description="__('Precisamos saber sua posição, modalidades e cidade para te avisar dos SOS da sua região.')">
                    <x-slot name="action">
                        <a href="{{ route('player-profile.edit') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            {{ __('Preencher perfil') }}
                        </a>
                    </x-slot>
                </x-empty-state>
            @elseif (! $isGoalkeeper)
                {{-- The whole SOS surface is goalkeeper-only. --}}
                <x-empty-state icon="heroicon-o-hand-raised" :title="__('O SOS é só para goleiros')" :description="__('Essas chamadas de última hora são exclusivas para quem joga na posição de goleiro. Se você também pega, adicione Goleiro às suas posições no perfil.')">
                    <x-slot name="action">
                        <a href="{{ route('player-profile.edit') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            {{ __('Editar posições') }}
                        </a>
                    </x-slot>
                </x-empty-state>
            @else
                <x-quota-notice :feature="\App\Enums\Feature::SOS_APPLICATIONS" />

                <x-push-toggle />

                <section>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-pitch-400 mb-3">{{ __('Abertas agora') }}</h3>

                    @if ($opportunities->isEmpty())
                        <x-empty-state icon="heroicon-o-hand-raised" :title="__('Nenhum SOS aberto')" :description="__('Ative as notificações acima e avisamos assim que aparecer uma chamada na sua região.')" />
                    @else
                        <div class="space-y-3">
                            @foreach ($opportunities as $sosRequest)
                                @php
                                    $application = $applicationsByRequest->get($sosRequest->id);
                                @endphp

                                <a href="{{ route('sos-opportunities.show', $sosRequest) }}" class="block bg-pitch-900 rounded-2xl border border-pitch-800 hover:border-pitch-700 shadow-sm shadow-black/20 p-5 transition">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="font-bold text-white truncate">
                                                    🧤 {{ $sosRequest->game->date->format('d/m') }} &middot; {{ $sosRequest->game->start_time->format('H:i') }}
                                                </h4>
                                                @if ($application?->isPending())
                                                    <x-badge color="amber">{{ __('Candidatura enviada') }}</x-badge>
                                                @endif
                                            </div>

                                            <p class="mt-1 text-sm text-pitch-400 flex items-center gap-1 truncate">
                                                <x-heroicon-o-map-pin class="w-3.5 h-3.5 shrink-0" /> {{ $sosRequest->game->location }}, {{ $sosRequest->game->city }}
                                            </p>
                                            <p class="mt-1 text-xs text-pitch-500">{{ $sosRequest->game->modality }} &middot; {{ __('Organizado por') }} {{ $sosRequest->organizer->name }}</p>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Paga') }}</p>
                                            <p class="font-extrabold text-emerald-400">R$ {{ number_format((float) $sosRequest->offered_value, 2, ',', '.') }}</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if ($applications->isNotEmpty())
                    <section>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-pitch-400 mb-3">{{ __('Minhas candidaturas') }}</h3>

                        <div class="space-y-2">
                            @foreach ($applications as $application)
                                @php
                                    $game = $application->sosRequest->game;

                                    [$color, $label] = match ($application->status) {
                                        SosApplication::STATUS_ACCEPTED => ['emerald', __('Você foi escolhido')],
                                        SosApplication::STATUS_REJECTED => ['gray', __('Não foi dessa vez')],
                                        SosApplication::STATUS_WITHDRAWN => ['gray', __('Você desistiu')],
                                        default => ['amber', __('Aguardando o organizador')],
                                    };
                                @endphp

                                <div class="flex items-center justify-between gap-3 bg-pitch-900 rounded-xl border border-pitch-800 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-white truncate">
                                            {{ $game->date->format('d/m') }} {{ $game->start_time->format('H:i') }} &middot; {{ $game->location }}
                                        </p>
                                        <p class="text-xs text-pitch-400">{{ __('Você pediu') }} R$ {{ number_format((float) $application->asking_price, 2, ',', '.') }}</p>
                                    </div>

                                    <x-badge :color="$color" class="shrink-0">{{ $label }}</x-badge>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
