@props(['icon' => 'heroicon-o-inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'text-center bg-pitch-900 border border-dashed border-pitch-700 rounded-2xl p-8 sm:p-10']) }}>
    <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-pitch-800 text-pitch-500">
        <x-dynamic-component :component="$icon" class="w-7 h-7" />
    </span>
    <p class="mt-3 font-bold text-pitch-100">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-sm text-pitch-400 max-w-sm mx-auto">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-5">
            {{ $action }}
        </div>
    @endisset
</div>
