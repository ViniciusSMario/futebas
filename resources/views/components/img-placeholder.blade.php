@props([
    // Aspect ratio as CSS shorthand ('16/9', '4/5', '1/1', '9/16'). Applied
    // inline because Tailwind can't JIT a class it never sees in source.
    'ratio' => '16/9',
    // What this image is, in plain pt_BR — shown inside the placeholder.
    'label' => 'Imagem',
    // Suggested file, relative to public/ (e.g. 'images/landing/hero.jpg').
    'path' => null,
    // Suggested export size, e.g. '1600x900'.
    'size' => null,
    // Art direction for whoever produces the asset.
    'note' => null,
    'icon' => 'heroicon-o-photo',
    // 'dark' on the near-black app surfaces, 'light' on white sections,
    // 'accent' to mark the one image that carries a section.
    'tone' => 'dark',
    'rounded' => 'rounded-3xl',
    // Drop the real file in and pass it here — the placeholder steps aside
    // and the markup around it (ratio, rounding, cropping) stays identical.
    'src' => null,
    'alt' => null,
    // Above-the-fold images shouldn't be lazy-loaded.
    'eager' => false,
])

@php
    $tones = [
        'dark' => [
            'frame' => 'border-white/15 bg-white/[0.035]',
            'icon' => 'bg-white/10 text-pitch-200',
            'label' => 'text-pitch-100',
            'meta' => 'text-pitch-400',
            'chip' => 'bg-black/40 text-pitch-300 ring-1 ring-white/10',
            'hatch' => 'rgba(255,255,255,0.05)',
        ],
        'light' => [
            'frame' => 'border-gray-300 bg-gray-50',
            'icon' => 'bg-white text-gray-400 shadow-sm',
            'label' => 'text-gray-700',
            'meta' => 'text-gray-400',
            'chip' => 'bg-white text-gray-500 ring-1 ring-gray-200',
            'hatch' => 'rgba(15,23,42,0.045)',
        ],
        'accent' => [
            'frame' => 'border-emerald-400/40 bg-emerald-500/[0.07]',
            'icon' => 'bg-emerald-500/15 text-emerald-300',
            'label' => 'text-emerald-50',
            'meta' => 'text-emerald-300/70',
            'chip' => 'bg-emerald-950/60 text-emerald-300 ring-1 ring-emerald-400/20',
            'hatch' => 'rgba(16,185,129,0.09)',
        ],
    ];

    $t = $tones[$tone] ?? $tones['dark'];
@endphp

{{-- Nenhum dos dois ramos define `w-*`/`h-*`: `merge()` só concatena classes
     e o Tailwind resolve pela ordem na folha de estilo, onde `w-full` vem
     depois de `w-10`. Uma dimensão padrão aqui venceria a do chamador, e o
     ramo com `src` sairia de um tamanho diferente do placeholder — um avatar
     de 40px viraria full-bleed no instante em que a foto real entrasse. O
     componente cuida do recorte, da proporção e do arredondamento; a caixa é
     de quem usa. --}}
@if ($src)
    <img
        src="{{ Illuminate\Support\Str::startsWith($src, ['http://', 'https://', '/']) ? $src : asset($src) }}"
        alt="{{ $alt ?? $label }}"
        loading="{{ $eager ? 'eager' : 'lazy' }}"
        decoding="async"
        @if ($eager) fetchpriority="high" @endif
        style="aspect-ratio: {{ $ratio }};"
        {{ $attributes->merge(['class' => "object-cover {$rounded}"]) }}
    >
@else
    <div
        style="aspect-ratio: {{ $ratio }};"
        role="img"
        aria-label="{{ __('Espaço reservado para imagem') }}: {{ $label }}"
        {{ $attributes->merge(['class' => "relative overflow-hidden border-2 border-dashed {$rounded} {$t['frame']}"]) }}
    >
        {{-- Diagonal hatch, so an empty slot reads as "reserved" rather
             than as a component that failed to render. --}}
        <div
            class="pointer-events-none absolute inset-0"
            style="background-image: repeating-linear-gradient(135deg, {{ $t['hatch'] }} 0 1px, transparent 1px 11px);"
        ></div>

        <div class="relative h-full w-full flex flex-col items-center justify-center gap-2 p-3 text-center">
            <span class="flex items-center justify-center w-9 h-9 xs:w-11 xs:h-11 rounded-xl shrink-0 {{ $t['icon'] }}">
                <x-dynamic-component :component="$icon" class="w-5 h-5 xs:w-6 xs:h-6" />
            </span>

            <p class="text-[11px] xs:text-xs font-bold leading-tight max-w-[26ch] {{ $t['label'] }}">{{ $label }}</p>

            @if ($size || $path)
                <p class="hidden xs:flex flex-wrap items-center justify-center gap-1.5 text-[10px] font-medium {{ $t['meta'] }}">
                    @if ($size)
                        <span class="tabular-nums">{{ $size }}</span>
                    @endif
                    @if ($path)
                        <span class="px-1.5 py-0.5 rounded font-mono {{ $t['chip'] }}">{{ $path }}</span>
                    @endif
                </p>
            @endif

            @if ($note)
                <p class="hidden sm:block text-[10px] leading-snug max-w-[34ch] {{ $t['meta'] }}">{{ $note }}</p>
            @endif
        </div>
    </div>
@endif
