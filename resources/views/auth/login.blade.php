<x-auth-shell>
    <h1 class="text-2xl font-extrabold text-gray-900 text-center">{{ __('Entrar') }}</h1>
    <p class="mt-1 text-sm text-gray-500 text-center">{{ __('Bem-vindo de volta! Vamos para o jogo.') }}</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Senha')" />
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-green-700 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest shadow-lg shadow-green-700/30 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('Entrar') }}
        </button>

        <div class="flex flex-col items-center gap-2 text-sm pt-2">
            @if (Route::has('password.request'))
                <a class="text-green-700 hover:text-green-900 font-medium" href="{{ route('password.request') }}">
                    {{ __('Esqueci minha senha') }}
                </a>
            @endif

            <a class="text-gray-600 hover:text-gray-900" href="{{ route('register') }}">
                {{ __('Ainda não tenho uma conta') }} - <span class="font-semibold text-green-700">{{ __('Criar conta') }}</span>
            </a>
        </div>
    </form>
</x-auth-shell>
