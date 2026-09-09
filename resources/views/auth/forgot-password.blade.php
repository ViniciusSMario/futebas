<x-auth-shell>
    <h1 class="text-2xl font-extrabold text-gray-900 text-center">{{ __('Esqueci minha senha') }}</h1>
    <p class="mt-1 text-sm text-gray-500 text-center">
        {{ __('Informe seu e-mail e mandamos um link para você criar uma senha nova.') }}
    </p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-green-700 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest shadow-lg shadow-green-700/30 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('Enviar o link') }}
        </button>

        <div class="flex justify-center text-sm pt-2">
            <a class="text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                {{ __('Lembrei a senha —') }} <span class="font-semibold text-green-700">{{ __('voltar para entrar') }}</span>
            </a>
        </div>
    </form>
</x-auth-shell>
