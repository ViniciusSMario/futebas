@props([
    // Um App\Models\User; a foto sai de users.photo_path e, se não houver,
    // da foto esportiva em player_profiles.photo_path.
    'user' => null,
    // Ou passe nome/foto direto, para participantes convidados sem conta.
    'name' => null,
    'photo' => null,
    'size' => 'md',
    // Anel na cor do fundo, para avatares sobrepostos.
    'ring' => null,
])

@php
    $displayName = $name ?? $user?->name ?? '?';
    $photoPath = $photo ?? $user?->photo_path ?? $user?->playerProfile?->photo_path;

    $sizes = [
        'xs' => ['w-7 h-7', 'text-[10px]'],
        'sm' => ['w-9 h-9', 'text-xs'],
        'md' => ['w-11 h-11', 'text-sm'],
        'lg' => ['w-14 h-14', 'text-lg'],
        'xl' => ['w-20 h-20', 'text-2xl'],
    ];

    [$box, $text] = $sizes[$size] ?? $sizes['md'];

    $initial = Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($displayName, 0, 1));
@endphp

@if ($photoPath)
    <img
        src="{{ Illuminate\Support\Facades\Storage::url($photoPath) }}"
        alt="{{ $displayName }}"
        loading="lazy"
        decoding="async"
        {{ $attributes->merge(['class' => "{$box} rounded-full object-cover shrink-0 bg-pitch-800 ".($ring ?? '')]) }}
    >
@else
    <span
        aria-hidden="true"
        {{ $attributes->merge(['class' => "{$box} {$text} flex items-center justify-center rounded-full shrink-0 bg-gradient-to-br from-emerald-500/25 to-emerald-700/25 text-emerald-300 font-black select-none ".($ring ?? '')]) }}
    >{{ $initial }}</span>
@endif
