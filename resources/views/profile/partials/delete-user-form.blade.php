@php
    $isOrganizer = $user->hasRole(\App\Models\User::ROLE_ORGANIZER);
@endphp

<section class="space-y-5">
    <header>
        <h2 class="flex items-center gap-2 text-base font-black text-white">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-400 shrink-0" />
            {{ __('Excluir minha conta') }}
        </h2>

        {{-- O texto do scaffold falava em "baixar seus dados antes", que aqui
             não quer dizer nada. O que precisa ser dito é o que a exclusão
             leva junto: as chaves estrangeiras são `cascadeOnDelete`, então
             para quem organiza somem também as partidas criadas — e com elas
             a vaga de todo mundo que estava dentro. --}}
        <p class="mt-1 text-sm text-pitch-300 leading-relaxed">
            {{ $isOrganizer
                ? __('A exclusão é definitiva e leva junto as partidas que você criou, com os participantes, pagamentos e avaliações delas. Quem estava confirmado perde a vaga sem aviso.')
                : __('A exclusão é definitiva e leva junto seu perfil de jogador, seu histórico de partidas e as avaliações que você recebeu.') }}
        </p>
    </header>

    <button
        type="button"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-red-300 bg-red-500/10 border border-red-500/40 hover:bg-red-500/20 transition"
    >
        <x-heroicon-o-trash class="w-4 h-4" /> {{ __('Excluir minha conta') }}
    </button>

    {{-- Modal, e não o diálogo de confirmação do SweetAlert: aqui a
         confirmação é a senha, e ação destrutiva que pede entrada continua
         no `<x-modal>`. --}}
    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-black text-white">
                {{ __('Excluir a conta de vez?') }}
            </h2>

            <p class="mt-1.5 text-sm text-pitch-400 leading-relaxed">
                {{ __('Não dá para desfazer. Digite sua senha para confirmar.') }}
            </p>

            <div class="mt-5">
                <x-input-label for="password" :value="__('Senha')" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="block w-full rounded-lg min-h-[44px]"
                    placeholder="{{ __('Sua senha') }}"
                    autocomplete="current-password"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <button
                    type="button"
                    x-on:click="$dispatch('close')"
                    class="inline-flex items-center justify-center px-5 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition"
                >
                    {{ __('Voltar') }}
                </button>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 px-5 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-red-600 hover:bg-red-500 transition"
                >
                    <x-heroicon-o-trash class="w-4 h-4" /> {{ __('Excluir conta') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
