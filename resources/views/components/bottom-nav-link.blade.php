@props(['active' => false, 'icon' => null])

@php
$classes = ($active ?? false) ? 'text-emerald-400' : 'text-pitch-400';
@endphp

<a {{ $attributes->merge(['class' => "flex flex-col items-center justify-center gap-0.5 py-2.5 transition {$classes}"]) }}>
    @if ($icon)
        <x-dynamic-component :component="$icon" class="w-6 h-6" />
    @endif
    <span class="text-[11px] font-semibold">{{ $slot }}</span>
</a>
