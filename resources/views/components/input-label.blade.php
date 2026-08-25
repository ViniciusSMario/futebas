@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-pitch-300']) }}>
    {{ $value ?? $slot }}
</label>
