<x-auth-shell>
    <h1 class="text-2xl font-extrabold text-gray-900 text-center">{{ __('Confirme seu e-mail') }}</h1>
    <p class="mt-2 text-sm text-gray-500 text-center leading-relaxed">
        {{ __('Mandamos um link para o e-mail que você cadastrou. Clique nele para liberar sua conta — se não chegou, a gente manda de novo.') }}
    </p>

    @if (session('status') == 'verification-link-sent')
        <p class="mt-4 flex items-center justify-center gap-1.5 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm font-semibold text-green-700">
            <x-heroicon-s-check-circle class="w-4 h-4 shrink-0" />
            {{ __('Enviamos um link novo. Confira sua caixa de entrada.') }}
        </p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf

        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-green-700 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest shadow-lg shadow-green-700/30 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('Reenviar o e-mail') }}
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 flex justify-center">
        @csrf

        <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 underline underline-offset-2 rounded focus:outline-none focus:ring-2 focus:ring-green-500">
            {{ __('Sair') }}
        </button>
    </form>
</x-auth-shell>
