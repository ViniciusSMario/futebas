@props([
    // App\Enums\Feature — precisa ser um recurso com teto mensal.
    'feature',
    // Mostra a linha discreta mesmo quando ainda sobra bastante. Fora do
    // "acabou", o aviso é informação, não interrupção.
    'quiet' => false,
])

@php
    $plans = app(\App\Services\PlanService::class);
    $user = Auth::user();

    $limit = $plans->limit($user, $feature);
    $used = $plans->used($user, $feature);
    $remaining = $plans->remaining($user, $feature);
    $upgrade = $plans->upgradeFor($feature, $user->currentPlan());
    $renewsAt = $plans->periodEnd($user);
@endphp

@if ($limit === null)
    {{-- Ilimitado: nada a avisar, e dizer "ilimitado" toda hora vira ruído. --}}
@elseif ($remaining === 0)
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 sm:p-5']) }}>
        <div class="flex items-start gap-3">
            <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-amber-500/20 text-amber-300 shrink-0">
                <x-heroicon-o-lock-closed class="w-5 h-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-amber-100">{{ $feature->exhaustedMessage($limit) }}</p>

                <p class="mt-1 text-xs text-amber-200/80">
                    {{ __('Seu limite volta a zerar em :date.', ['date' => $renewsAt->format('d/m')]) }}
                    @if ($upgrade)
                        {{ __('No :plan, :limit.', ['plan' => $upgrade->label(), 'limit' => mb_strtolower($feature->describeLimit($upgrade->limit($feature)))]) }}
                    @endif
                </p>

                @if ($upgrade)
                    <a href="{{ route('subscription.index') }}" class="mt-3 inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-xl font-bold text-xs uppercase tracking-widest bg-amber-400 text-pitch-950 hover:bg-amber-300 transition">
                        {{ __('Conhecer o :plan', ['plan' => $upgrade->label()]) }}
                        <x-heroicon-o-arrow-right class="w-3.5 h-3.5" />
                    </a>
                @endif
            </div>
        </div>
    </div>
@elseif (! $quiet)
    <p {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-pitch-400']) }}>
        <x-heroicon-o-chart-bar class="w-3.5 h-3.5 shrink-0" />
        <span>{{ __(':used de :limit — :feature deste mês', ['used' => $used, 'limit' => $limit, 'feature' => $feature->label()]) }}</span>
        <a href="{{ route('subscription.index') }}" class="font-bold text-emerald-400 hover:text-emerald-300">{{ __('Ver planos') }}</a>
    </p>
@endif
