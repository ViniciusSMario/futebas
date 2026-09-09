@php
    use App\Models\User;

    $isPlayer = $user->hasRole(User::ROLE_PLAYER);
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            icon="heroicon-o-cog-6-tooth"
            :title="__('Minha Conta')"
            :subtitle="__('Seus dados de acesso e as configurações da conta')"
        />
    </x-slot>

    {{-- `max-w-3xl` e não os `max-w-7xl` com `max-w-xl` por dentro que vinham
         do scaffold: aquilo deixava cartões da largura da tela com o conteúdo
         espremido na beira esquerda. --}}
    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Quem é esta conta. A página se chamava "Profile" e abria
                 direto num formulário de nome e e-mail, sem dizer de quem
                 era nem o que a conta é dentro do app. --}}
            <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-card p-5 sm:p-6">
                <div class="flex items-center gap-4">
                    <x-avatar :user="$user" size="xl" ring="ring-2 ring-pitch-800" />

                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl sm:text-2xl font-black text-white truncate">{{ $user->name }}</h2>
                        <p class="mt-0.5 text-sm text-pitch-400 truncate">{{ $user->email }}</p>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-badge :color="$isPlayer ? 'blue' : 'emerald'">
                                {{ $isPlayer ? __('Jogador') : __('Organizador') }}
                            </x-badge>
                            <x-plan-badge :plan="$user->currentPlan()" always />
                        </div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-pitch-800 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-pitch-500">
                        {{ __('Na pelada desde :quando', ['quando' => $user->created_at->translatedFormat('F \d\e Y')]) }}
                    </p>

                    <a href="{{ route('subscription.index') }}" class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-widest text-emerald-400 hover:text-emerald-300 transition">
                        {{ __('Meu plano') }}
                        <x-heroicon-o-arrow-right class="w-3.5 h-3.5" />
                    </a>
                </div>
            </section>

            {{-- "Minha Conta" e "Perfil do Jogador" são duas telas com nomes
                 quase iguais, e a pergunta que traz alguém até aqui costuma
                 ser "onde mudo minha posição?" — que se responde na outra.
                 Só para jogador: o organizador não tem perfil esportivo. --}}
            @if ($isPlayer)
                <section>
                    <x-section-heading
                        :title="__('Seu lado de campo')"
                        :subtitle="__('Posição, nível, valor e horários ficam no perfil de jogador — é ele que aparece nas buscas dos organizadores.')"
                        icon="heroicon-o-identification"
                    />

                    <div class="grid grid-cols-1 xs:grid-cols-3 gap-2">
                        @foreach ([
                            [route('player-profile.edit'), 'heroicon-o-user', __('Perfil do Jogador')],
                            [route('availability.edit'), 'heroicon-o-calendar-days', __('Disponibilidade')],
                            [route('ratings.show', $user->id), 'heroicon-o-star', __('Avaliações')],
                        ] as [$href, $icon, $label])
                            <a
                                href="{{ $href }}"
                                class="flex items-center gap-3 rounded-2xl bg-pitch-900 border border-pitch-800 px-4 min-h-[56px] text-sm font-bold text-pitch-100 hover:border-pitch-700 hover:text-white transition"
                            >
                                <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-pitch-800 text-pitch-300 shrink-0">
                                    <x-dynamic-component :component="$icon" class="w-5 h-5" />
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                                <x-heroicon-o-chevron-right class="w-4 h-4 text-pitch-600 shrink-0" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-card p-5 sm:p-6">
                @include('profile.partials.update-profile-information-form')
            </section>

            <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-card p-5 sm:p-6">
                @include('profile.partials.update-password-form')
            </section>

            {{-- Borda vermelha, e no fim da página: apagar a conta não é uma
                 configuração entre outras, e não deveria parecer uma. --}}
            <section class="bg-red-950/20 rounded-2xl border border-red-900/50 p-5 sm:p-6">
                @include('profile.partials.delete-user-form')
            </section>
        </div>
    </div>
</x-app-layout>
