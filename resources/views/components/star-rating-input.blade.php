@props(['name', 'label'])

@php
    $current = (int) old($name, 0);
@endphp

<div x-data="{ value: {{ $current }}, hover: 0 }">
    <x-input-label :value="$label" />

    <div class="flex items-center gap-1 mt-1" role="radiogroup" aria-label="{{ $label }}">
        @for ($i = 1; $i <= 5; $i++)
            <label class="cursor-pointer p-0.5 -m-0.5">
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $i }}"
                    class="sr-only"
                    x-model.number="value"
                    @checked($current === $i)
                    required
                >
                <x-heroicon-s-star
                    class="w-8 h-8 transition"
                    x-bind:class="(hover || value) >= {{ $i }} ? 'text-amber-400' : 'text-pitch-700'"
                    @mouseenter="hover = {{ $i }}"
                    @mouseleave="hover = 0"
                    @click="value = {{ $i }}"
                />
            </label>
        @endfor
    </div>

    <x-input-error class="mt-2" :messages="$errors->get($name)" />
</div>
