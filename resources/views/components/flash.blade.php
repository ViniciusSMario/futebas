@php
    use App\Support\Flash;

    $status = Flash::resolve(session('status'));
    $error = session('error');

    // Se os dois vierem juntos, o que deu errado é o que a pessoa precisa
    // ler. Na prática nenhum controller manda os dois, mas a precedência
    // tem de estar decidida aqui e não no acaso da ordem.
    [$text, $tone] = $error
        ? [$error, 'error']
        : [$status['text'] ?? null, $status['tone'] ?? Flash::INFO];

    [$classes, $icon] = match ($tone) {
        'error' => ['bg-red-950/95 border-red-500/40 text-red-100', 'heroicon-o-exclamation-triangle'],
        Flash::WARNING => ['bg-amber-950/95 border-amber-500/40 text-amber-100', 'heroicon-o-exclamation-circle'],
        Flash::SUCCESS => ['bg-emerald-950/95 border-emerald-500/40 text-emerald-100', 'heroicon-s-check-circle'],
        default => ['bg-pitch-800/95 border-pitch-700 text-pitch-100', 'heroicon-o-information-circle'],
    };

    // Sucesso e informação somem sozinhos; erro fica até alguém fechar. Um
    // erro explica por que algo não aconteceu, e some antes de ser lido é
    // pior do que não ter aparecido.
    $timeout = match ($tone) {
        'error' => null,
        Flash::WARNING => 8000,
        default => 5000,
    };
@endphp

{{-- Nada a dizer, nada no DOM: o toast não é um lugar que fica vazio
     esperando. --}}
@if ($text)
    {{-- Nasce visível, e não escondido esperando o Alpine mandar aparecer.
         A diferença aparece no dia em que o JavaScript falha: com
         `show: false` + `x-cloak` o aviso é engolido inteiro, e a pessoa
         fica sem saber se a ação valeu. Assim ele degrada para uma faixa
         estática que só não some sozinha — o custo é a animação de
         entrada, que num aviso que já chega com a página quase não se vê. --}}
    <div
        x-data="{ show: true }"
        @if ($timeout) x-init="setTimeout(() => show = false, {{ $timeout }})" @endif
        x-show="show"
        x-transition:leave="transition ease-in duration-200 motion-reduce:transition-none"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 -translate-y-2 motion-reduce:translate-y-0"
        {{-- Fixo, e não no fluxo do conteúdo: antes isto era um `<p>` no
             topo da página, e quem marcava um pagamento no fim de uma lista
             de doze jogadores voltava com a confirmação escrita fora da
             tela. --}}
        class="fixed inset-x-0 flash-top z-50 px-4 pointer-events-none"
    >
        <div
            role="{{ in_array($tone, ['error', Flash::WARNING], true) ? 'alert' : 'status' }}"
            aria-live="{{ $tone === 'error' ? 'assertive' : 'polite' }}"
            class="pointer-events-auto mx-auto max-w-md flex items-start gap-3 rounded-2xl border backdrop-blur-xl shadow-2xl shadow-black/50 px-4 py-3 {{ $classes }}"
        >
            <x-dynamic-component :component="$icon" class="w-5 h-5 shrink-0 mt-0.5" />

            <p class="min-w-0 flex-1 text-sm font-semibold leading-snug">{{ $text }}</p>

            <button
                type="button"
                @click="show = false"
                class="shrink-0 -me-1 -mt-0.5 inline-flex items-center justify-center w-8 h-8 rounded-lg opacity-70 hover:opacity-100 transition"
                aria-label="{{ __('Fechar aviso') }}"
            >
                <x-heroicon-o-x-mark class="w-4 h-4" />
            </button>
        </div>
    </div>
@endif
