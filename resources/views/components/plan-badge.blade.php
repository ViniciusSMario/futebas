@props([
    // App\Enums\Plan
    'plan',
    // Por padrão o selo só aparece para quem assina: marcar todo mundo com
    // "Free" transformaria o plano padrão em uma etiqueta de inferioridade
    // no lugar onde as pessoas só querem jogar bola.
    'always' => false,
])

@php
    $isDefault = $plan === \App\Enums\Plan::default();

    $styles = [
        'pro' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/30',
        'clube' => 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-400/30',
    ][$plan->value] ?? 'bg-pitch-800 text-pitch-300 ring-1 ring-white/10';
@endphp

@unless ($isDefault && ! $always)
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest {$styles}"]) }}>
        @unless ($isDefault)
            <x-heroicon-s-bolt class="w-3 h-3" />
        @endunless
        {{ $plan->label() }}
    </span>
@endunless
