@props([
    'icon' => null,
    'label',
    'value',
    'color' => 'emerald',
    // Linha extra abaixo do número (ex.: "3 nesta semana").
    'hint' => null,
    // Quando informado, o card inteiro vira link.
    'href' => null,
])

@php
    $colors = [
        'emerald' => ['bg-emerald-500/15 text-emerald-400', 'hover:border-emerald-500/40'],
        'blue' => ['bg-blue-500/15 text-blue-400', 'hover:border-blue-500/40'],
        'sky' => ['bg-sky-500/15 text-sky-400', 'hover:border-sky-500/40'],
        'amber' => ['bg-amber-500/15 text-amber-400', 'hover:border-amber-500/40'],
        'red' => ['bg-red-500/15 text-red-400', 'hover:border-red-500/40'],
        'violet' => ['bg-violet-500/15 text-violet-400', 'hover:border-violet-500/40'],
        'gray' => ['bg-pitch-800 text-pitch-300', 'hover:border-pitch-600'],
    ];

    [$iconClasses, $hoverBorder] = $colors[$color] ?? $colors['gray'];

    // Números ganham escala e tabular-nums; rótulos curtos (ex.: "Goleiro")
    // não podem estourar a caixa no celular, então caem um degrau.
    $isNumeric = is_numeric($value);
    $valueClasses = $isNumeric
        ? 'text-2xl sm:text-3xl tabular-nums'
        : (Illuminate\Support\Str::length((string) $value) > 8 ? 'text-base sm:text-lg' : 'text-xl sm:text-2xl');

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'group flex flex-col justify-between gap-3 rounded-2xl bg-pitch-900 border border-pitch-800 shadow-card p-4 transition '.($href ? $hoverBorder.' hover:-translate-y-0.5' : '')]) }}
>
    <div class="flex items-start justify-between gap-2">
        @if ($icon)
            <span class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0 {{ $iconClasses }} {{ $href ? 'group-hover:scale-105 transition-transform' : '' }}">
                <x-dynamic-component :component="$icon" class="w-5 h-5" />
            </span>
        @endif

        @if ($href)
            <x-heroicon-o-arrow-up-right class="w-4 h-4 text-pitch-600 group-hover:text-emerald-400 transition shrink-0" />
        @endif
    </div>

    <div class="min-w-0">
        <p class="font-black text-white leading-none truncate {{ $valueClasses }}">{{ $value }}</p>
        <p class="mt-1.5 text-xs font-semibold text-pitch-400 leading-tight">{{ $label }}</p>
        @if ($hint)
            <p class="mt-0.5 text-[11px] text-pitch-500 leading-tight truncate">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
