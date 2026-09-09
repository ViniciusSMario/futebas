@props(['status'])

{{-- Este componente só aparece sobre o cartão branco do `<x-auth-shell>`,
     onde o `text-emerald-400` que ele usava antes era verde-claro em fundo
     branco — quase ilegível justamente na frase que mais importa da tela de
     recuperação de senha ("enviamos o link"). --}}
@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm font-semibold text-green-800']) }}>
        <x-heroicon-s-check-circle class="w-4 h-4 shrink-0 mt-0.5 text-green-600" />
        <span>{{ $status }}</span>
    </div>
@endif
