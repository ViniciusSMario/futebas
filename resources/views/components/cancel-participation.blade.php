@props(['game'])

{{-- O jogador desistindo da própria vaga. Vive num componente porque
     acontece em dois lugares — o cartão de "Minhas Partidas" e a tela da
     partida — e um cancelamento com justificativa opcional não é markup
     que se queira manter em duas cópias.

     É modal e não `confirm()` de propósito: o texto precisa dizer de que
     partida se trata e abrir espaço para o motivo, que o organizador vai
     ler. --}}
@if ($game->isOpen() && $game->isCancellableByPlayer())
    <button
        type="button"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'cancel-participation-{{ $game->id }}')"
        {{ $attributes->merge(['class' => 'w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-red-400 border border-red-900/60 bg-red-950/40 hover:bg-red-900/40 transition']) }}
    >
        <x-heroicon-o-x-circle class="w-4 h-4" /> {{ __('Cancelar participação') }}
    </button>

    <x-modal name="cancel-participation-{{ $game->id }}" focusable>
        <form method="post" action="{{ route('games.leave', $game) }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-white">
                {{ __('Cancelar sua participação?') }}
            </h2>

            <p class="mt-1 text-sm text-pitch-400">
                {{ __('Você está cancelando sua participação em :partida, :quando. Se quiser, informe o motivo - o organizador poderá ver essa justificativa.', [
                    'partida' => $game->team_name,
                    'quando' => mb_strtolower($game->whenLabel()),
                ]) }}
            </p>

            <div class="mt-4">
                <x-input-label for="reason-{{ $game->id }}" :value="__('Justificativa (opcional)')" />
                <textarea id="reason-{{ $game->id }}" name="reason" rows="3" maxlength="500"
                    class="mt-1 block w-full bg-pitch-800 border-pitch-700 text-white placeholder-pitch-500 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm"
                    placeholder="{{ __('Ex: imprevisto de última hora...') }}"></textarea>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Voltar') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Confirmar cancelamento') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
@elseif ($game->isOpen())
    <p class="text-[11px] text-pitch-500 text-center">
        {{ __('Cancelamento indisponível: faltam menos de 24h para a partida.') }}
    </p>
@endif
