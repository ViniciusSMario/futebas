<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-exclamation-triangle" :title="__('Preciso de Goleiro')" :subtitle="__('Goleiros disponíveis para a sua partida')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('sos.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-pitch-400 hover:text-white">
                &larr; {{ __('Nova busca') }}
            </a>

            @if (session('status') === 'sos-invite-sent')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-sm font-medium text-emerald-400 flex items-center gap-1"
                ><x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Convite enviado.') }}</p>
            @endif

            {{-- Match summary --}}
            <section class="rounded-2xl bg-gradient-to-r from-red-600 to-orange-500 text-white p-5 sm:p-6 shadow-lg shadow-red-500/20">
                <p class="text-xs font-bold uppercase tracking-widest text-red-50">{{ __('Sua solicitação') }}</p>
                <p class="mt-1 text-lg sm:text-xl font-extrabold">
                    {{ $game->date->format('d/m/Y') }} &middot; {{ $game->start_time->format('H:i') }} &middot; {{ $game->modality }}
                </p>
                <p class="mt-1 text-sm text-red-50 flex items-center gap-1">
                    <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" /> {{ $game->location }}, {{ $game->city }}
                </p>
                <p class="mt-1 text-sm font-bold text-red-50">
                    {{ __('Valor oferecido') }}: R$ {{ number_format((float) $game->price, 2, ',', '.') }}
                </p>
            </section>

            <p class="text-sm text-pitch-400">
                {{ trans_choice(':count goleiro encontrado|:count goleiros encontrados', $goalkeepers->count(), ['count' => $goalkeepers->count()]) }}
            </p>

            @if ($goalkeepers->isEmpty())
                <x-empty-state icon="heroicon-o-hand-raised" :title="__('Nenhum goleiro disponível')" :description="__('Não encontramos goleiros na mesma cidade, modalidade e horário. Tente ajustar a data, o horário ou a modalidade.')">
                    <x-slot name="action">
                        <a href="{{ route('sos.index') }}" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            {{ __('Tentar novamente') }}
                        </a>
                    </x-slot>
                </x-empty-state>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($goalkeepers as $playerProfile)
                        <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 flex flex-col">
                            <div class="flex items-center gap-4">
                                @if ($playerProfile->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($playerProfile->photo_path) }}" alt="{{ $playerProfile->user->name }}" class="h-16 w-16 rounded-full object-cover shrink-0 ring-2 ring-pitch-800 shadow-sm">
                                @else
                                    <div class="h-16 w-16 rounded-full bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 flex items-center justify-center text-xl font-extrabold text-emerald-300 shrink-0">
                                        {{ Str::upper(Str::substr($playerProfile->user->name, 0, 1)) }}
                                    </div>
                                @endif

                                <div class="min-w-0">
                                    <h3 class="font-bold text-white truncate">{{ $playerProfile->user->name }}</h3>
                                    <p class="text-xs text-pitch-400 truncate flex items-center gap-1">
                                        <x-heroicon-o-map-pin class="w-3.5 h-3.5 shrink-0" /> {{ $playerProfile->city }}@if ($playerProfile->state), {{ $playerProfile->state }}@endif
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-1.5">
                                <x-badge color="emerald">🧤 {{ __('Goleiro') }}</x-badge>
                                <x-badge color="blue">⚽ {{ $game->modality }}</x-badge>
                            </div>

                            <div class="mt-4 pt-4 border-t border-pitch-800 grow">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-pitch-500">{{ __('Valor/partida') }}</p>
                                <p class="font-extrabold text-white">💰 R$ {{ number_format((float) $playerProfile->price_per_game, 2, ',', '.') }}</p>
                            </div>

                            @if ($invitedUserIds->contains($playerProfile->user_id))
                                <span class="mt-4 inline-flex justify-center items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-emerald-400 bg-emerald-500/10">
                                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Convidado') }}
                                </span>
                            @else
                                <form method="post" action="{{ route('sos.invite', [$game, $playerProfile]) }}">
                                    @csrf
                                    <button type="submit" class="mt-4 w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-gradient-to-r from-red-600 to-orange-500 hover:from-red-500 hover:to-orange-400 transition">
                                        {{ __('Convidar') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
