@props([
    'href',
    'icon',
    'title',
    'description' => null,
    'color' => 'emerald',
    // Selo curto no canto (ex.: "3 novos").
    'badge' => null,
])

@php
    $styles = [
        'emerald' => ['bg-emerald-500/15 text-emerald-400', 'hover:border-emerald-500/40'],
        'blue' => ['bg-blue-500/15 text-blue-400', 'hover:border-blue-500/40'],
        'sky' => ['bg-sky-500/15 text-sky-400', 'hover:border-sky-500/40'],
        'amber' => ['bg-amber-500/15 text-amber-400', 'hover:border-amber-500/40'],
        'red' => ['bg-red-500/15 text-red-400', 'hover:border-red-500/40'],
        'violet' => ['bg-violet-500/15 text-violet-400', 'hover:border-violet-500/40'],
        'gray' => ['bg-pitch-800 text-pitch-300', 'hover:border-pitch-600'],
    ];

    [$iconClasses, $hoverBorder] = $styles[$color] ?? $styles['gray'];
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => "group relative flex items-center gap-4 rounded-2xl bg-pitch-900 border border-pitch-800 shadow-card p-4 sm:p-5 min-h-[76px] {$hoverBorder} hover:-translate-y-0.5 active:translate-y-0 transition duration-200"]) }}
>
    <span class="flex items-center justify-center w-11 h-11 rounded-xl shrink-0 group-hover:scale-105 transition-transform {{ $iconClasses }}">
        <x-dynamic-component :component="$icon" class="w-6 h-6" />
    </span>

    <div class="min-w-0 flex-1">
        <p class="font-bold text-white leading-tight">{{ $title }}</p>
        @if ($description)
            <p class="mt-0.5 text-xs text-pitch-400 leading-snug">{{ $description }}</p>
        @endif
    </div>

    @if ($badge)
        <span class="shrink-0 inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-emerald-500 text-[11px] font-black text-white">{{ $badge }}</span>
    @else
        {{-- Seta discreta: dá o affordance de "leva a algum lugar" sem
             competir com o título no celular. --}}
        <x-heroicon-o-chevron-right class="w-5 h-5 shrink-0 text-pitch-600 group-hover:text-emerald-400 group-hover:translate-x-0.5 transition" />
    @endif
</a>
