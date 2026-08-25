@props(['current', 'max'])

@php
    $current = (int) $current;
    $max = max(1, (int) $max);
    $percentage = min(100, (int) round(($current / $max) * 100));
    $isFull = $current >= $max;
    $remaining = max(0, $max - $current);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="text-sm font-bold text-white">
            {{ __(':current/:max jogadores', ['current' => $current, 'max' => $max]) }}
        </p>
        @if ($isFull)
            <span class="inline-flex items-center gap-1 text-xs font-extrabold uppercase tracking-wide text-red-400">
                <x-heroicon-o-lock-closed class="w-3.5 h-3.5" /> {{ __('Game lotado') }}
            </span>
        @else
            <span class="text-xs font-semibold text-emerald-400">
                {{ trans_choice(':count vaga restante|:count vagas restantes', $remaining, ['count' => $remaining]) }}
            </span>
        @endif
    </div>
    <div class="h-2.5 rounded-full bg-pitch-800 overflow-hidden">
        <div class="h-full rounded-full {{ $isFull ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ $percentage }}%"></div>
    </div>
</div>
