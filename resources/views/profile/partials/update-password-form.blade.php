<section>
    <header>
        <h2 class="flex items-center gap-2 text-base font-black text-white">
            <x-heroicon-o-lock-closed class="w-5 h-5 text-emerald-400 shrink-0" />
            {{ __('Senha') }}
        </h2>

        <p class="mt-1 text-sm text-pitch-400">
            {{ __('Use uma senha longa e que você não use em outro lugar.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Senha atual')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full rounded-lg min-h-[44px]" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="update_password_password" :value="__('Nova senha')" />
                <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full rounded-lg min-h-[44px]" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" :value="__('Repita a nova senha')" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full rounded-lg min-h-[44px]" autocomplete="new-password" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 min-h-[44px] rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
            <x-heroicon-o-check class="w-4 h-4" /> {{ __('Trocar senha') }}
        </button>
    </form>
</section>
