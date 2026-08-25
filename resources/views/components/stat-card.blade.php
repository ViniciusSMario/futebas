@props(['icon' => null, 'label', 'value', 'color' => 'emerald'])

@php
$colors = [
    'emerald' => 'bg-emerald-500/15 text-emerald-400',
    'blue' => 'bg-blue-500/15 text-blue-400',
    'amber' => 'bg-amber-500/15 text-amber-400',
    'red' => 'bg-red-500/15 text-red-400',
    'gray' => 'bg-pitch-800 text-pitch-300',
][$color] ?? 'bg-pitch-800 text-pitch-300';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-2xl bg-pitch-900 border border-pitch-800 shadow-sm shadow-black/20 p-4']) }}>
    @if ($icon)
        <span class="flex items-center justify-center w-11 h-11 rounded-xl shrink-0 {{ $colors }}">
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-xs sm:text-sm font-extrabold text-white leading-tight">{{ $value }}</p>
        <p class="text-xs font-medium text-pitch-400 truncate">{{ $label }}</p>
    </div>
</div>
