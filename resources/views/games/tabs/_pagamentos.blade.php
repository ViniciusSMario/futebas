<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        <x-stat-card icon="heroicon-o-banknotes" :label="__('Esperado')" value="{{ 'R$ '.number_format($financialSummary['expected'], 2, ',', '.') }}" color="blue" />
        <x-stat-card icon="heroicon-o-check-circle" :label="__('Recebido')" value="{{ 'R$ '.number_format($financialSummary['received'], 2, ',', '.') }}" color="emerald" />
        <x-stat-card icon="heroicon-o-clock" :label="__('Pendente')" value="{{ 'R$ '.number_format($financialSummary['pending'], 2, ',', '.') }}" color="amber" />
    </div>

    <section>
        <div class="flex items-center gap-2 mb-3">
            <h3 class="text-lg font-extrabold text-white">{{ __('Jogadores Confirmados') }}</h3>
            <x-badge color="emerald">{{ $gamePlayers->count() }}</x-badge>
        </div>

        @if ($gamePlayers->isEmpty())
            <x-empty-state icon="heroicon-o-currency-dollar" :title="__('Nenhum jogador confirmado ainda.')" />
        @else
            <div class="space-y-3">
                @foreach ($gamePlayers as $gamePlayer)
                    <x-game-participant-row :game="$game" :gamePlayer="$gamePlayer" :actions="['payment']" />
                @endforeach
            </div>
        @endif
    </section>
</div>
