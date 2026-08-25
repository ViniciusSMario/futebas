@props(['active' => false, 'icon' => null, 'badge' => null])

@php
$classes = ($active ?? false)
    ? 'bg-emerald-500/15 text-emerald-400'
    : 'text-pitch-300 hover:bg-pitch-800 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => "relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {$classes}"]) }}
    :class="sidebarCollapsed && 'justify-center'"
    title="{{ $slot }}"
>
    @if ($icon)
        <span class="shrink-0">
            <x-dynamic-component :component="$icon" class="w-5 h-5" />
            {{-- With the sidebar collapsed the label is gone, so the count
                 becomes a dot on the icon instead. --}}
            @if ($badge)
                <span x-show="sidebarCollapsed" style="display: none;" class="absolute top-1.5 right-2 w-2 h-2 rounded-full bg-emerald-400"></span>
            @endif
        </span>
    @endif
    <span x-show="!sidebarCollapsed" x-transition.opacity.duration.150ms class="whitespace-nowrap">{{ $slot }}</span>

    @if ($badge)
        <span x-show="!sidebarCollapsed" style="display: none;" class="ms-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-emerald-500 text-[11px] font-bold text-white">{{ $badge }}</span>
    @endif
</a>
