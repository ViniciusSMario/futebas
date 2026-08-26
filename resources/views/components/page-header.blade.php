@props([
    'icon' => null,
    'title',
    'subtitle' => null,
    // URL de "voltar". No celular esse é o gesto principal de navegação
    // dentro de um fluxo, já que a barra inferior só troca de seção.
    'back' => null,
])

<div class="flex items-center gap-3">
    @if ($back)
        <a href="{{ $back }}" class="tap-target -ms-2 inline-flex items-center justify-center rounded-xl text-pitch-300 hover:text-white hover:bg-pitch-800 transition shrink-0" aria-label="{{ __('Voltar') }}">
            <x-heroicon-o-arrow-left class="w-5 h-5" />
        </a>
    @elseif ($icon)
        <span class="hidden sm:flex items-center justify-center w-11 h-11 rounded-2xl bg-emerald-500/15 text-emerald-400 shrink-0">
            <x-dynamic-component :component="$icon" class="w-6 h-6" />
        </span>
    @endif

    <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-black text-white leading-tight truncate">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-pitch-400 mt-0.5 truncate">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($action)
        <div class="ms-auto shrink-0">{{ $action }}</div>
    @endisset
</div>
