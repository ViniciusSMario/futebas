@php
    $isOrganizer = $user->hasRole(\App\Models\User::ROLE_ORGANIZER);
@endphp

<section>
    <header>
        <h2 class="flex items-center gap-2 text-base font-black text-white">
            <x-heroicon-o-user-circle class="w-5 h-5 text-emerald-400 shrink-0" />
            {{ __('Dados da conta') }}
        </h2>

        <p class="mt-1 text-sm text-pitch-400">
            {{ $isOrganizer
                ? __('Nome, contato e a região onde você organiza. É o que os jogadores veem ao entrar nas suas peladas.')
                : __('Nome e e-mail de acesso. Posição, nível e valor ficam no perfil de jogador.') }}
        </p>
    </header>

    {{-- Fora do formulário principal de propósito: são dois envios
         diferentes, e um <form> não pode ficar dentro do outro. --}}
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" @if ($isOrganizer) enctype="multipart/form-data" @endif class="mt-5 space-y-5">
        @csrf
        @method('patch')

        @if ($isOrganizer)
            <div class="flex items-center gap-4">
                <x-avatar :user="$user" size="lg" ring="ring-2 ring-pitch-800" />

                <div class="flex-1 min-w-0">
                    <x-input-label for="photo" :value="__('Foto (opcional)')" />
                    <input id="photo" name="photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-pitch-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:uppercase file:tracking-wide file:bg-emerald-500/15 file:text-emerald-400 hover:file:bg-emerald-500/25">
                    <x-input-error class="mt-2" :messages="$errors->get('photo')" />
                </div>
            </div>
        @endif

        <div>
            <x-input-label for="name" :value="__('Nome')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full rounded-lg min-h-[44px]" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        @if ($isOrganizer)
            {{-- O par estado+cidade sai do componente do app, e não do
                 <select> escrito à mão que estava aqui: aquele mandava uma
                 opção só e dependia do JavaScript para preencher a lista,
                 enquanto o componente já serve as cidades da UF escolhida
                 direto do servidor. O `<div>` da grade é de quem chama —
                 o componente devolve duas células irmãs. --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-city-select
                    :state="old('state', $user->state)"
                    :city="old('city', $user->city)"
                    required
                />
            </div>

            <div>
                <x-input-label for="phone" :value="__('Telefone')" />
                <x-text-input id="phone" name="phone" type="text" data-phone-mask class="mt-1 block w-full rounded-lg min-h-[44px]" :value="old('phone', $user->phone)" required placeholder="(00) 00000-0000" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>
        @endif

        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full rounded-lg min-h-[44px]" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 rounded-xl bg-amber-500/10 border border-amber-500/30 px-4 py-3">
                    <p class="flex items-start gap-2 text-sm text-amber-200">
                        <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0 mt-0.5" />
                        <span>
                            {{ __('Seu e-mail ainda não foi confirmado.') }}
                            <button form="send-verification" class="font-bold underline underline-offset-2 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400 rounded">
                                {{ __('Reenviar o e-mail de confirmação.') }}
                            </button>
                        </span>
                    </p>

                    {{-- Fica aqui, colado ao campo de onde o pedido saiu, e não
                         no toast do layout: é aviso do campo, não retorno de
                         página. Ver App\Support\Flash::HANDLED_IN_PLACE. --}}
                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 flex items-center gap-1.5 text-sm font-semibold text-emerald-400">
                            <x-heroicon-s-check-circle class="w-4 h-4 shrink-0" />
                            {{ __('Enviamos um link novo para o seu e-mail.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
            <x-heroicon-o-check class="w-4 h-4" /> {{ __('Salvar dados') }}
        </button>
    </form>
</section>
