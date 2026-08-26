@props(['active' => false, 'icon' => null, 'badge' => null])

@php
    $isActive = (bool) ($active ?? false);
@endphp

<a {{ $attributes->merge(['class' => 'relative flex flex-col items-center justify-center gap-1 pt-3 pb-2 min-h-[56px] transition '.($isActive ? 'text-emerald-400' : 'text-pitch-400 active:text-pitch-200')]) }}>
    {{-- Barra no topo do item ativo: no celular o rótulo é pequeno demais
         para carregar sozinho o estado "você está aqui". --}}
    <span class="absolute top-0 h-0.5 w-8 rounded-full transition-colors {{ $isActive ? 'bg-emerald-400' : 'bg-transparent' }}"></span>

    <span class="relative">
        @if ($icon)
            <x-dynamic-component :component="$icon" class="w-6 h-6" />
        @endif
        @if ($badge)
            <span class="absolute -top-1.5 -right-2 flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-emerald-500 text-[10px] font-black text-white ring-2 ring-pitch-900">{{ $badge }}</span>
        @endif
    </span>

    <span class="text-[11px] font-bold leading-none">{{ $slot }}</span>
</a>
