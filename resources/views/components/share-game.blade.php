@props(['game'])

@php
    $url = route('public-games.show', $game);

    // O recado que vai junto no WhatsApp, montado aqui porque é aqui que
    // estão os dados. Sem ele o organizador cola uma URL solta no grupo e
    // digita a pelada de novo à mão — que é exatamente o trabalho que este
    // app existe para tirar dele.
    $summary = implode(' ', array_filter([
        $game->team_name.' — '.$game->whenLabel().'.',
        $game->location.', '.$game->city.'.',
        $game->isFull()
            ? __('Lotada, mas dá para entrar na lista de espera.')
            : trans_choice('Resta :count vaga.|Restam :count vagas.', $game->spotsRemaining(), ['count' => $game->spotsRemaining()]),
    ]));

    $whatsappUrl = 'https://wa.me/?text='.rawurlencode($summary.' '.__('Entra aí:').' '.$url);
@endphp

<section
    {{ $attributes->merge(['class' => 'bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6']) }}
    x-data="shareLink({
        url: @js($url),
        title: @js($game->team_name),
        text: @js($summary),
    })"
>
    <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400">
        <x-heroicon-o-share class="w-4 h-4" /> {{ __('Chamar gente') }}
    </h3>
    <p class="mt-1.5 text-sm text-pitch-400">
        {{ __('Qualquer pessoa com esse link entra na partida, mesmo sem conta no Futebas.') }}
    </p>

    {{-- A folha nativa do sistema, que é o caminho bom: entrega WhatsApp,
         Instagram e o resto de uma vez, já com o texto pronto. Fica atrás de
         `x-cloak` porque quem decide se ela existe é o navegador — no
         desktop ela quase nunca existe, e o botão simplesmente não aparece
         em vez de trocar de rótulo na frente da pessoa. --}}
    <button
        x-cloak
        x-show="canShare"
        type="button"
        @click="share()"
        class="mt-4 w-full inline-flex items-center justify-center gap-2 px-6 min-h-[48px] rounded-xl font-black text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/25 transition"
    >
        <x-heroicon-o-share class="w-4 h-4" /> {{ __('Compartilhar') }}
    </button>

    <div class="mt-2 grid grid-cols-1 xs:grid-cols-2 gap-2">
        {{-- `<a href>` puro: é o único dos três que funciona com o
             JavaScript quebrado, e é para onde esse link vai em nove de cada
             dez vezes. --}}
        <a
            href="{{ $whatsappUrl }}"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center justify-center gap-2 px-4 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-emerald-300 bg-emerald-500/10 border border-emerald-500/30 hover:bg-emerald-500/20 transition"
        >
            <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" /> {{ __('WhatsApp') }}
        </a>

        <button
            type="button"
            @click="copy()"
            class="inline-flex items-center justify-center gap-2 px-4 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest transition"
            :class="state === 'copied'
                ? 'text-emerald-300 bg-emerald-500/15 border border-emerald-500/40'
                : 'text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700'"
        >
            <x-heroicon-s-check x-cloak x-show="state === 'copied'" class="w-4 h-4" />
            <x-heroicon-o-clipboard-document x-show="state !== 'copied'" class="w-4 h-4" />
            <span x-text="state === 'copied' ? @js(__('Copiado!')) : @js(__('Copiar link'))">{{ __('Copiar link') }}</span>
        </button>
    </div>

    <p x-cloak x-show="state === 'failed'" class="mt-2 text-xs text-amber-400">
        {{ __('Não consegui copiar por aqui. Selecione o link abaixo e copie na mão.') }}
    </p>

    {{-- O endereço à vista: dá para conferir para onde o link aponta, e
         continua selecionável se nada acima funcionar. --}}
    <input
        type="text"
        readonly
        value="{{ $url }}"
        onclick="this.select()"
        aria-label="{{ __('Link público da partida') }}"
        class="mt-3 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-pitch-300 text-xs focus:border-emerald-500 focus:ring-emerald-500"
    >
</section>
