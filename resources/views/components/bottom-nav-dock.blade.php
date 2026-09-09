@props([
    'active' => false,
    'icon' => null,
    'label' => null,
])

{{-- A vaga elevada da barra inferior: a ação que a pessoa abriu o app para
     fazer. Sobe acima da barra com posicionamento absoluto para não mexer na
     altura das outras quatro, e o anel na cor da barra é o que faz o recorte
     parecer parte dela.

     A centralização é explícita (`left-1/2 -translate-x-1/2`) e não herdada do
     `items-center` do flex: a posição estática de um filho absoluto dentro de
     um container flex é justamente o canto onde os navegadores divergem, e
     aqui um desvio de alguns pixels põe o botão principal do app torto. --}}
<a {{ $attributes->merge(['class' => 'relative flex flex-col items-center justify-end pb-2 min-w-0 min-h-[56px]']) }}>
    <span class="absolute -top-6 left-1/2 -translate-x-1/2 flex items-center justify-center w-14 h-14 rounded-2xl ring-4 ring-pitch-900 shadow-lg transition active:scale-95 {{ $active ? 'bg-emerald-300 text-pitch-950 shadow-emerald-400/40' : 'bg-emerald-400 text-pitch-950 shadow-emerald-500/30' }}">
        @if ($icon)
            <x-dynamic-component :component="$icon" class="w-7 h-7" />
        @endif
    </span>

    <span class="text-[11px] font-black leading-none {{ $active ? 'text-emerald-300' : 'text-emerald-400' }}">{{ $label ?? $slot }}</span>
</a>
