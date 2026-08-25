@props(['icon' => null, 'title', 'subtitle' => null])

<div class="flex items-center gap-3">
    @if ($icon)
        <span class="hidden sm:flex items-center justify-center w-11 h-11 rounded-2xl bg-emerald-500/15 text-emerald-400 shrink-0">
            <x-dynamic-component :component="$icon" class="w-6 h-6" />
        </span>
    @endif
    <div class="min-w-0">
        <h2 class="text-xl sm:text-2xl font-extrabold text-white leading-tight truncate">{{ $title }}</h2>
        @if ($subtitle)
            <p class="text-sm text-pitch-400 mt-0.5">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($action)
        <div class="ms-auto shrink-0">{{ $action }}</div>
    @endisset
</div>
