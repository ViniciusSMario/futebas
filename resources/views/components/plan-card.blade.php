@props([
    // App\Enums\Plan
    'plan',
    // Destaca visualmente o plano recomendado.
    'featured' => false,
    // Marca o plano que a pessoa já tem.
    'current' => false,
])

@php
    $price = $plan->price();
    // Classe inteira, e não montada com interpolação: o Tailwind procura
    // nomes literais nos arquivos para saber o que gerar.
    $check = $plan->value === 'clube' ? 'text-amber-400' : 'text-emerald-400';
@endphp

<div
    {{ $attributes->merge(['class' => 'relative flex flex-col rounded-3xl p-6 sm:p-7 border transition '.($featured
        ? 'bg-pitch-900 border-emerald-500/40 shadow-glow'
        : 'bg-pitch-900/70 border-white/10 shadow-card hover:border-white/20')]) }}
>
    @if ($featured || $current)
        <span class="absolute -top-3 left-6 inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest {{ $current ? 'bg-white text-pitch-950' : 'bg-emerald-400 text-pitch-950' }}">
            {{ $current ? __('Seu plano') : __('Mais escolhido') }}
        </span>
    @endif

    <div class="flex items-baseline justify-between gap-3">
        <h3 class="text-xl font-black text-white">{{ $plan->label() }}</h3>
        <x-plan-badge :plan="$plan" />
    </div>

    <p class="mt-1 text-sm text-pitch-400">{{ $plan->tagline() }}</p>

    <p class="mt-5 flex items-end gap-1.5">
        @if ($price > 0)
            <span class="text-4xl font-black text-white tabular-nums">R$ {{ number_format($price, 2, ',', '.') }}</span>
            <span class="pb-1.5 text-sm font-semibold text-pitch-400">{{ __('/mês') }}</span>
        @else
            <span class="text-4xl font-black text-white">{{ __('Grátis') }}</span>
            <span class="pb-1.5 text-sm font-semibold text-pitch-400">{{ __('para sempre') }}</span>
        @endif
    </p>

    {{-- As linhas saem dos mesmos limites que o app aplica (Plan::bullets()),
         então não existe promessa aqui que o gate não cumpra lá. --}}
    <ul class="mt-6 space-y-2.5 flex-1">
        @foreach ($plan->bullets() as $index => $bullet)
            <li class="flex items-start gap-2.5 text-sm {{ $index === 0 && $plan->inherits() ? 'font-bold text-white' : 'text-pitch-200' }}">
                <x-heroicon-s-check-circle class="w-4 h-4 shrink-0 mt-0.5 {{ $check }}" />
                <span>{{ $bullet }}</span>
            </li>
        @endforeach
    </ul>

    @isset($action)
        <div class="mt-7">{{ $action }}</div>
    @endisset
</div>
