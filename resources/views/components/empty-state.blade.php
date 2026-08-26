@props(['icon' => 'heroicon-o-inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'relative overflow-hidden text-center bg-pitch-900 border border-dashed border-pitch-700 rounded-3xl p-8 sm:p-12']) }}>
    <div class="pointer-events-none absolute inset-0 field-lines opacity-30" aria-hidden="true"></div>

    <div class="relative">
        <span class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-pitch-800 text-pitch-500 ring-1 ring-white/5">
            <x-dynamic-component :component="$icon" class="w-8 h-8" />
        </span>
        <p class="mt-4 text-base font-bold text-white">{{ $title }}</p>
        @if ($description)
            <p class="mt-1.5 text-sm text-pitch-400 max-w-sm mx-auto leading-relaxed">{{ $description }}</p>
        @endif
        @isset($action)
            <div class="mt-6">{{ $action }}</div>
        @endisset
    </div>
</div>
