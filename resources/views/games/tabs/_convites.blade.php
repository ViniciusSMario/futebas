<div class="space-y-6">
    <a href="{{ route('games.invitations.search', $game) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
        <x-heroicon-o-magnifying-glass class="w-4 h-4" /> {{ __('Buscar Jogadores') }}
    </a>

    <section>
        <div class="flex items-center gap-2 mb-3">
            <h3 class="text-lg font-extrabold text-white">{{ __('Convites Pendentes') }}</h3>
            <x-badge color="amber">{{ $invitations->count() }}</x-badge>
        </div>

        @if ($invitations->isEmpty())
            <x-empty-state icon="heroicon-o-envelope-open" :title="__('Nenhum convite pendente.')" :description="__('Busque jogadores cadastrados e convide para esse Game.')" />
        @else
            <div class="space-y-3">
                @foreach ($invitations as $invitation)
                    <div class="flex items-center justify-between gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 p-4">
                        <div class="min-w-0">
                            <p class="font-bold text-white truncate">{{ $invitation->user->name }}</p>
                            <p class="text-sm text-pitch-400">
                                @if ($invitation->position) {{ $invitation->position }} &middot; @endif
                                R$ {{ number_format((float) ($invitation->value ?? $game->price), 2, ',', '.') }}
                            </p>
                        </div>
                        <x-badge color="amber">{{ __('Aguardando') }}</x-badge>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
