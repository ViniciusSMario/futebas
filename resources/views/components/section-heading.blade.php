@props(['title', 'subtitle' => null, 'icon' => null])

<div {{ $attributes->merge(['class' => 'flex items-end justify-between gap-3 mb-3']) }}>
    <div class="min-w-0">
        <h2 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-pitch-300">
            @if ($icon)
                <x-dynamic-component :component="$icon" class="w-4 h-4 text-emerald-400 shrink-0" />
            @endif
            {{ $title }}
        </h2>
        @if ($subtitle)
            <p class="mt-0.5 text-xs text-pitch-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
