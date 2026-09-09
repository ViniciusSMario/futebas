{{--
    Convite para instalar o app na tela de início.

    Dois modos, porque são dois mundos: no Android o navegador entrega um
    instalador de verdade e o cartão vira um botão; no iPhone não existe
    esse instalador, e a única coisa honesta a fazer é ensinar o caminho do
    menu Compartilhar. Quem já usa o app instalado nunca vê nada disso — a
    store devolve `available` falso e o cartão não chega a existir.

    O estado mora em `$store.install` e não aqui porque a folha "Mais" lê o
    mesmo: dispensar o cartão não pode virar um beco sem saída, ainda mais
    no iPhone, onde não há prompt do navegador para reaparecer sozinho.
--}}
<div
    x-cloak
    x-show="$store.install.open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-4 opacity-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-end="translate-y-4 opacity-0"
    class="above-bottom-nav fixed inset-x-0 z-40 px-4"
    role="dialog"
    aria-label="{{ __('Instalar o aplicativo') }}"
>
    <div class="mx-auto max-w-md rounded-2xl border border-emerald-500/25 bg-pitch-900/95 backdrop-blur-xl shadow-2xl shadow-black/60 p-4">
        <div class="flex items-start gap-3">
            {{-- O próprio ícone do app: é o que vai aparecer na tela de
                 início, e mostrar isso vale mais do que descrever. --}}
            <img
                src="{{ asset('images/icons/icon-192.png') }}"
                alt=""
                aria-hidden="true"
                class="w-12 h-12 rounded-xl shrink-0 ring-1 ring-white/10"
            >

            <div class="min-w-0 flex-1">
                <p class="font-black text-white leading-tight">{{ __('Instale o :app no celular', ['app' => config('app.name', 'Futebas')]) }}</p>
                <p class="mt-1 text-xs text-pitch-400 leading-snug">
                    {{ __('Abre direto da tela de início, sem barra de navegador — e os avisos de partida e SOS chegam na hora.') }}
                </p>
            </div>

            <button
                type="button"
                @click="$store.install.dismiss()"
                class="tap-target -me-2 -mt-2 flex items-center justify-center rounded-xl text-pitch-500 hover:text-white transition shrink-0"
                aria-label="{{ __('Agora não') }}"
            >
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>

        {{-- Android e desktop: instalador de verdade, um toque. --}}
        <div x-show="$store.install.installable" class="mt-3 flex items-center gap-2">
            <button
                type="button"
                @click="$store.install.install()"
                class="flex-1 inline-flex justify-center items-center gap-2 px-4 min-h-[44px] rounded-xl font-black text-xs uppercase tracking-widest text-pitch-950 bg-emerald-400 hover:bg-emerald-300 transition"
            >
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                {{ __('Instalar') }}
            </button>

            <button
                type="button"
                @click="$store.install.dismiss()"
                class="px-4 min-h-[44px] rounded-xl text-xs font-bold uppercase tracking-widest text-pitch-400 hover:text-white transition"
            >
                {{ __('Agora não') }}
            </button>
        </div>

        {{-- iPhone: o Safari não oferece instalador nenhum, então o cartão
             deixa de ser um botão e passa a ser uma instrução de duas
             etapas — que é literalmente o que a pessoa vai fazer. --}}
        <ol
            x-show="! $store.install.installable && $store.install.needsIosSteps"
            class="mt-3 space-y-2 rounded-xl bg-pitch-800/60 p-3 text-xs text-pitch-200"
        >
            <li class="flex items-center gap-2">
                <span class="flex items-center justify-center w-5 h-5 rounded-full bg-pitch-700 text-[10px] font-black shrink-0">1</span>
                <span class="flex items-center gap-1.5">
                    {{ __('Toque em') }}
                    <x-heroicon-o-arrow-up-on-square class="w-4 h-4 text-sky-400" />
                    <strong class="font-bold text-white">{{ __('Compartilhar') }}</strong>
                </span>
            </li>
            <li class="flex items-center gap-2">
                <span class="flex items-center justify-center w-5 h-5 rounded-full bg-pitch-700 text-[10px] font-black shrink-0">2</span>
                <span class="flex items-center gap-1.5">
                    {{ __('Escolha') }}
                    <x-heroicon-o-plus-small class="w-4 h-4 text-pitch-300" />
                    <strong class="font-bold text-white">{{ __('Adicionar à Tela de Início') }}</strong>
                </span>
            </li>
        </ol>
    </div>
</div>
