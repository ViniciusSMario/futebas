@props(['sosRequest', 'tone' => 'default'])

@php
    $label = $sosRequest->deadlineLabel();
    $urgency = $sosRequest->deadlineUrgency();

    // Sobre o degradê vermelho da chamada não há contraste para semáforo:
    // qualquer cor de urgência some no fundo. Lá o prazo é branco, e quem
    // dá o recado é a própria frase ("Faltam 40 minutos").
    $classes = $tone === 'on-dark'
        ? 'bg-white/20 text-white'
        : match ($urgency) {
            'urgent' => 'bg-red-500/15 text-red-300',
            'soon' => 'bg-amber-500/15 text-amber-400',
            'past' => 'bg-pitch-800 text-pitch-400',
            default => 'bg-pitch-800 text-pitch-300',
        };

    $icon = $urgency === 'urgent' ? 'heroicon-s-clock' : 'heroicon-o-clock';
@endphp

{{-- Sem prazo não há contagem a fazer, e um "sem prazo" escrito na tela
     seria ruído: a chamada simplesmente vale enquanto a partida valer. --}}
@if ($label !== null)
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold {$classes}"]) }}>
        <x-dynamic-component :component="$icon" class="w-3.5 h-3.5 shrink-0" />
        {{ $label }}
    </span>
@endif
