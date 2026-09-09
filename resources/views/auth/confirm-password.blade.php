<x-auth-shell>
    <h1 class="text-2xl font-extrabold text-gray-900 text-center">{{ __('Confirme sua senha') }}</h1>
    <p class="mt-1 text-sm text-gray-500 text-center">
        {{ __('Esta é uma área protegida. Digite sua senha para continuar.') }}
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Senha')" />
            <input id="password" type="password" name="password" required autofocus autocomplete="current-password"
                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-green-700 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest shadow-lg shadow-green-700/30 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('Confirmar') }}
        </button>
    </form>
</x-auth-shell>
