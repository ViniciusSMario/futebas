@props(['href', 'icon', 'title', 'description' => null, 'color' => 'emerald'])

@php
$styles = [
    'emerald' => ['bg-emerald-500/15 text-emerald-400', 'hover:border-emerald-500/40'],
    'blue' => ['bg-blue-500/15 text-blue-400', 'hover:border-blue-500/40'],
    'gray' => ['bg-pitch-800 text-pitch-300', 'hover:border-pitch-600'],
][$color] ?? ['bg-pitch-800 text-pitch-300', 'hover:border-pitch-600'];
[$iconClasses, $hoverBorder] = $styles;
@endphp

<a href="{{ $href }}" class="group flex items-center gap-4 rounded-2xl bg-pitch-900 border border-pitch-800 shadow-sm shadow-black/20 p-5 hover:shadow-md hover:shadow-black/30 {{ $hoverBorder }} hover:-translate-y-0.5 transition">
    <span class="flex items-center justify-center w-8 h-8 rounded-xl shrink-0 group-hover:scale-105 transition-transform {{ $iconClasses }}">
        <x-dynamic-component :component="$icon" class="w-6 h-6" />
    </span>
    <div class="min-w-0">
        <p class="font-bold text-white">{{ $title }}</p>
        @if ($description)
            <p class="text-xs text-pitch-400">{{ $description }}</p>
        @endif
    </div>
</a>
