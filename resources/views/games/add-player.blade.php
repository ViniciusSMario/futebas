<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-user-plus" :title="__('Adicionar Jogador')" :subtitle="$game->team_name" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <form method="get" action="{{ route('game-players.create', $game) }}" class="flex items-center gap-3">
                <x-text-input name="q" type="text" class="flex-1 rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$q" placeholder="{{ __('Nome, telefone ou e-mail') }}" autofocus />
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" /> {{ __('Buscar') }}
                </button>
            </form>

            @if ($q === '')
                <x-empty-state icon="heroicon-o-magnifying-glass" :title="__('Busque um jogador ou contato já cadastrado')" :description="__('Digite o nome, telefone ou e-mail de quem você quer adicionar ao Game.')" />
            @else
                @if ($userResults->isEmpty() && $guestResults->isEmpty())
                    <x-empty-state icon="heroicon-o-user-group" :title="__('Nenhum resultado encontrado para :q', ['q' => $q])" />
                @else
                    @if ($userResults->isNotEmpty())
                        <section>
                            <h3 class="text-sm font-bold uppercase tracking-wide text-pitch-400 mb-3">{{ __('Jogadores cadastrados no Futebas') }}</h3>
                            <div class="space-y-3">
                                @foreach ($userResults as $user)
                                    <div class="flex items-center gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 p-4">
                                        @if ($user->photo_path)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($user->photo_path) }}" alt="{{ $user->name }}" class="h-11 w-11 rounded-full object-cover shrink-0 ring-2 ring-pitch-800">
                                        @else
                                            <div class="h-11 w-11 rounded-full bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 flex items-center justify-center text-sm font-extrabold text-emerald-300 shrink-0">
                                                {{ Str::upper(Str::substr($user->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-white truncate">{{ $user->name }}</p>
                                            <p class="text-xs text-pitch-400 truncate">{{ $user->email }}@if ($user->phone) &middot; {{ $user->phone }} @endif</p>
                                        </div>
                                        <form method="post" action="{{ route('game-players.store', $game) }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                                            <button type="submit" class="inline-flex items-center gap-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                                <x-heroicon-o-plus class="w-4 h-4" /> {{ __('Adicionar') }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($guestResults->isNotEmpty())
                        <section>
                            <h3 class="text-sm font-bold uppercase tracking-wide text-pitch-400 mb-3">{{ __('Seus contatos (sem conta no app)') }}</h3>
                            <div class="space-y-3">
                                @foreach ($guestResults as $guestPlayer)
                                    <div class="flex items-center gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 p-4">
                                        <div class="h-11 w-11 rounded-full bg-pitch-800 flex items-center justify-center text-sm font-extrabold text-pitch-400 shrink-0">
                                            {{ Str::upper(Str::substr($guestPlayer->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-white truncate">{{ $guestPlayer->name }}</p>
                                            <p class="text-xs text-pitch-400 truncate">
                                                @if ($guestPlayer->phone){{ $guestPlayer->phone }}@endif
                                                @if ($guestPlayer->phone && $guestPlayer->email) &middot; @endif
                                                @if ($guestPlayer->email){{ $guestPlayer->email }}@endif
                                            </p>
                                        </div>
                                        <form method="post" action="{{ route('game-players.store', $game) }}">
                                            @csrf
                                            <input type="hidden" name="guest_player_id" value="{{ $guestPlayer->id }}">
                                            <button type="submit" class="inline-flex items-center gap-1 px-4 py-2 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                                <x-heroicon-o-plus class="w-4 h-4" /> {{ __('Adicionar') }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif
            @endif

            <section class="bg-pitch-900 rounded-2xl border border-dashed border-pitch-700 p-5 sm:p-6">
                <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-1">
                    <x-heroicon-o-identification class="w-4 h-4" /> {{ __('Não tem conta no Futebas?') }}
                </h3>
                <p class="text-xs text-pitch-500 mb-4">{{ __('Cadastre um contato novo, ele fica salvo para você adicionar em outros Games sem precisar digitar tudo de novo.') }}</p>

                <form method="post" action="{{ route('game-players.store', $game) }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="new_guest_name" :value="__('Nome')" />
                        <x-text-input id="new_guest_name" name="new_guest_name" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('new_guest_name')" placeholder="{{ __('Ex: Fabrício Lemos') }}" />
                        <x-input-error class="mt-2" :messages="$errors->get('new_guest_name')" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="new_guest_phone" :value="__('Telefone (opcional)')" />
                            <x-text-input id="new_guest_phone" name="new_guest_phone" type="text" data-phone-mask class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('new_guest_phone')" placeholder="(00) 00000-0000" />
                            <x-input-error class="mt-2" :messages="$errors->get('new_guest_phone')" />
                        </div>
                        <div>
                            <x-input-label for="new_guest_email" :value="__('E-mail (opcional)')" />
                            <x-text-input id="new_guest_email" name="new_guest_email" type="email" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('new_guest_email')" />
                            <x-input-error class="mt-2" :messages="$errors->get('new_guest_email')" />
                        </div>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 transition">
                        <x-heroicon-o-plus class="w-4 h-4" /> {{ __('Cadastrar e Adicionar ao Game') }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
