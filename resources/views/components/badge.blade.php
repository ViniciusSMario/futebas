@props(['color' => 'gray'])

@php
$colors = [
    'emerald' => 'bg-emerald-500/15 text-emerald-400',
    'blue' => 'bg-blue-500/15 text-blue-400',
    'amber' => 'bg-amber-500/15 text-amber-400',
    'red' => 'bg-red-500/15 text-red-400',
    'gray' => 'bg-pitch-800 text-pitch-300',
][$color] ?? 'bg-pitch-800 text-pitch-300';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {$colors}"]) }}>
    {{ $slot }}
</span>
