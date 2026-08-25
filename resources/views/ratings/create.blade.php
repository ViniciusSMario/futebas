<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-star" :title="__('Avaliar Jogador')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('ratings.index', $game) }}" class="inline-flex items-center gap-1 text-sm font-medium text-pitch-400 hover:text-white">
                &larr; {{ __('Voltar') }}
            </a>

            <form method="post" action="{{ route('ratings.store', [$game, $player]) }}">
                @csrf

                <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6 space-y-6">
                    <h3 class="text-lg font-extrabold text-white">
                        {{ __('Como foi jogar com :name?', ['name' => $player->name]) }}
                    </h3>

                    <x-input-error :messages="$errors->get('rating')" />

                    <x-star-rating-input name="overall_rating" :label="__('Avaliação geral')" />
                    <x-star-rating-input name="punctuality_rating" :label="__('Pontualidade')" />
                    <x-star-rating-input name="behavior_rating" :label="__('Comportamento')" />
                    <x-star-rating-input name="performance_rating" :label="__('Desempenho')" />

                    <div>
                        <x-input-label for="comment" :value="__('Comentário')" />
                        <textarea
                            id="comment"
                            name="comment"
                            rows="4"
                            placeholder="{{ __('Excelente jogador e muito pontual.') }}"
                            class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white placeholder:text-pitch-500 focus:border-emerald-500 focus:ring-emerald-500 shadow-sm"
                        >{{ old('comment') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('comment')" />
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-6 sticky bottom-4 sm:static">
                    <button type="submit" class="inline-flex items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                        {{ __('Salvar Avaliação') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
