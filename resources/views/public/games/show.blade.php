<x-public-layout>
    <div class="max-w-xl mx-auto px-4 sm:px-6">
        <div class="bg-pitch-900 rounded-3xl border border-pitch-800 shadow-lg shadow-black/30 p-6 sm:p-8 space-y-6">
            @if (session('status') === 'joined-confirmed' || session('status') === 'joined-game')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Você está confirmado nesse Game!') }}
                </p>
            @elseif (session('status') === 'joined-pending')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Pedido enviado! Aguarde a confirmação do organizador.') }}
                </p>
            @elseif (session('status') === 'joined-waiting-list')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Game lotado, você entrou na lista de espera.') }}
                </p>
            @endif

            @if ($errors->any())
                <p class="flex items-center gap-1.5 text-sm font-medium text-red-400">
                    <x-heroicon-o-x-circle class="w-4 h-4" /> {{ $errors->first() }}
                </p>
            @endif

            <div>
                <x-badge :color="match ($game->status) {
                    'open' => 'emerald',
                    'cancelled' => 'red',
                    default => 'gray',
                }">
                    {{ match ($game->status) {
                        'open' => __('Aberto'),
                        'cancelled' => __('Cancelado'),
                        default => __('Finalizado'),
                    } }}
                </x-badge>
                <h1 class="mt-3 text-2xl sm:text-3xl font-extrabold text-white flex items-center gap-2">
                    <x-heroicon-o-trophy class="w-7 h-7 text-emerald-400 shrink-0" /> {{ $game->team_name }}
                </h1>
                <p class="mt-1 text-sm text-pitch-400">{{ __('Organizado por :name', ['name' => $game->user->name]) }}</p>
            </div>

            <dl class="text-sm text-pitch-200 space-y-2.5 py-4 border-y border-pitch-800">
                <div class="flex justify-between gap-2">
                    <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-calendar-days class="w-4 h-4" /> {{ __('Data') }}</dt>
                    <dd class="font-semibold">{{ $game->date->format('d/m/Y') }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-clock class="w-4 h-4" /> {{ __('Horário') }}</dt>
                    <dd class="font-semibold">{{ $game->start_time->format('H:i') }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Local') }}</dt>
                    <dd class="font-semibold text-right">{{ $game->location }}, {{ $game->city }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-flag class="w-4 h-4" /> {{ __('Modalidade') }}</dt>
                    <dd class="font-semibold">{{ $game->modality }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-pitch-500 flex items-center gap-1"><x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valor estimado') }}</dt>
                    <dd class="font-bold text-white">R$ {{ number_format((float) $game->price, 2, ',', '.') }}</dd>
                </div>
            </dl>

            @if ($game->description)
                <p class="text-sm text-pitch-300">{{ $game->description }}</p>
            @endif

            <x-slots-progress :current="$game->confirmedPlayersCount()" :max="$game->max_players" />

            @if ($game->isOpen())
                @auth
                    <div class="text-center pt-2">
                        <p class="font-bold text-white mb-3">{{ __('Quer jogar essa partida?') }}</p>
                        <a href="{{ route('public-games.join', $game) }}" class="inline-flex items-center justify-center gap-2 w-full px-6 py-3.5 rounded-xl font-extrabold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/30 transition">
                            <x-heroicon-o-hand-raised class="w-5 h-5" />
                            {{ $game->isFull() ? __('Entrar na Lista de Espera') : __('Quero Participar') }}
                        </a>
                    </div>
                @else
                    <div class="pt-2 space-y-4">
                        <p class="font-bold text-white text-center">{{ __('Quer jogar essa partida?') }}</p>

                        <form method="post" action="{{ route('public-games.join-guest', $game) }}" class="space-y-3 bg-pitch-800/50 rounded-xl p-4 border border-pitch-800">
                            <p class="text-xs font-bold uppercase tracking-wide text-pitch-400">{{ __('Jogar sem cadastro') }}</p>
                            @csrf
                            <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('Seu nome') }}" class="block w-full rounded-lg bg-pitch-900 border-pitch-700 text-white text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <div class="grid grid-cols-2 gap-3">
                                <input type="text" name="phone" value="{{ old('phone') }}" data-phone-mask placeholder="{{ __('Celular (opcional)') }}" class="block w-full rounded-lg bg-pitch-900 border-pitch-700 text-white text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('E-mail (opcional)') }}" class="block w-full rounded-lg bg-pitch-900 border-pitch-700 text-white text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                            <x-input-error class="mt-1" :messages="$errors->get('name')" />
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-extrabold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/30 transition">
                                <x-heroicon-o-hand-raised class="w-5 h-5" />
                                {{ $game->isFull() ? __('Entrar na Lista de Espera') : __('Confirmar Presença') }}
                            </button>
                        </form>

                        <div class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-wide text-pitch-600">
                            <span class="h-px flex-1 bg-pitch-800"></span> {{ __('ou') }} <span class="h-px flex-1 bg-pitch-800"></span>
                        </div>

                        <a href="{{ route('public-games.join', $game) }}" class="inline-flex items-center justify-center gap-2 w-full px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
                            <x-heroicon-o-user-plus class="w-4 h-4" /> {{ __('Criar Conta e Participar') }}
                        </a>

                        <p class="text-center text-xs text-pitch-500">
                            {{ __('Já tem conta?') }}
                            <a href="{{ route('public-games.login', $game) }}" class="font-semibold text-emerald-400 hover:text-emerald-300">{{ __('Entrar') }}</a>
                        </p>
                    </div>
                @endauth
            @else
                <p class="text-center text-sm font-semibold text-pitch-400">
                    {{ __('Esse Game não está mais aceitando participantes.') }}
                </p>
            @endif
        </div>
    </div>
</x-public-layout>
