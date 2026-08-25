<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-arrow-path" :title="$series->team_name"
            :subtitle="__('Toda :day às :time · :location', ['day' => $series->dayName(), 'time' => $series->start_time->format('H:i'), 'location' => $series->location])" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'series-created')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Pelada criada! As próximas partidas já estão no calendário.') }}
                </p>
            @elseif (session('status') === 'member-added')
                <p class="flex items-center gap-1.5 text-sm font-medium text-emerald-400">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Mensalista adicionado e já incluído nas partidas marcadas.') }}
                </p>
            @elseif (session('status') === 'member-removed')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Mensalista removido da série. Ele continua nas partidas que já foram criadas.') }}
                </p>
            @elseif (session('status') === 'series-ended')
                <p class="flex items-center gap-1.5 text-sm font-medium text-amber-400">
                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Pelada encerrada. Nenhuma partida nova será criada.') }}
                </p>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-badge :color="$series->isActive() ? 'emerald' : 'gray'">
                    {{ $series->isActive() ? __('Ativa') : __('Encerrada') }}
                </x-badge>

                @if ($series->isActive())
                    <form method="post" action="{{ route('game-series.end', $series) }}" onsubmit="return confirm('{{ __('Encerrar essa pelada? As partidas já marcadas continuam valendo, mas nenhuma nova será criada.') }}')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-red-300 bg-red-500/10 border border-red-500/30 hover:bg-red-500/20 transition">
                            <x-heroicon-o-x-circle class="w-4 h-4" /> {{ __('Encerrar pelada') }}
                        </button>
                    </form>
                @endif
            </div>

            {{-- Upcoming occurrences --}}
            <section>
                <div class="flex items-center gap-2 mb-3">
                    <h3 class="text-lg font-extrabold text-white">{{ __('Próximas partidas') }}</h3>
                    <x-badge color="emerald">{{ $upcoming->count() }}</x-badge>
                </div>

                @if ($upcoming->isEmpty())
                    <x-empty-state icon="heroicon-o-calendar-days" :title="__('Nenhuma partida no calendário')"
                        :description="$series->isActive()
                            ? __('As próximas datas aparecem aqui automaticamente.')
                            : __('Essa pelada foi encerrada e não gera mais partidas.')" />
                @else
                    <div class="space-y-2">
                        @foreach ($upcoming as $game)
                            <a href="{{ route('games.show', $game) }}" class="flex flex-wrap items-center gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 p-4 hover:border-emerald-600/40 transition">
                                <div class="flex flex-col items-center justify-center w-14 h-14 rounded-xl bg-pitch-800 shrink-0">
                                    <span class="text-lg font-extrabold text-white leading-none">{{ $game->date->format('d') }}</span>
                                    <span class="text-[11px] font-semibold text-pitch-400 uppercase">{{ $game->date->format('m') }}</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-white">{{ $game->date->format('d/m/Y') }}</p>
                                    <p class="text-xs text-pitch-400 flex items-center gap-1">
                                        <x-heroicon-o-clock class="w-3.5 h-3.5 shrink-0" />
                                        {{ $game->start_time->format('H:i') }}@if ($game->end_time)–{{ $game->end_time->format('H:i') }}@endif
                                    </p>
                                </div>

                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-bold text-white">{{ $game->confirmedPlayersCount() }}/{{ $game->max_players }}</p>
                                    <p class="text-[11px] text-pitch-500 uppercase tracking-wide">{{ __('confirmados') }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Regulars --}}
            <section>
                <div class="flex items-center gap-2 mb-3">
                    <h3 class="text-lg font-extrabold text-white">{{ __('Mensalistas') }}</h3>
                    <x-badge color="blue">{{ $members->count() }}</x-badge>
                </div>

                <p class="text-sm text-pitch-400 mb-3">
                    {{ __('Entram automaticamente, já confirmados, em cada partida da série.') }}
                </p>

                @if ($members->isEmpty())
                    <x-empty-state icon="heroicon-o-user-group" :title="__('Nenhum mensalista ainda')" :description="__('Adicione quem joga toda semana para não precisar convidar de novo.')" />
                @else
                    <div class="space-y-2">
                        @foreach ($members as $member)
                            <div class="flex items-center gap-3 bg-pitch-900 rounded-2xl border border-pitch-800 p-4">
                                <div class="h-10 w-10 rounded-full {{ $member->isGuest() ? 'bg-pitch-800 text-pitch-400' : 'bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 text-emerald-300' }} flex items-center justify-center text-sm font-extrabold shrink-0">
                                    {{ Str::upper(Str::substr($member->displayName(), 0, 1)) }}
                                </div>

                                <p class="min-w-0 flex-1 font-bold text-white truncate flex items-center gap-1.5">
                                    {{ $member->displayName() }}
                                    @if ($member->isGuest())
                                        <x-badge color="gray" class="text-[10px] py-0.5">{{ __('Sem cadastro') }}</x-badge>
                                    @endif
                                </p>

                                <form method="post" action="{{ route('game-series.members.destroy', [$series, $member]) }}" onsubmit="return confirm('{{ __('Remover esse mensalista da série?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-red-300 bg-red-500/10 border border-red-500/30 hover:bg-red-500/20 transition">
                                        <x-heroicon-o-trash class="w-3.5 h-3.5" /> {{ __('Remover') }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Add a regular --}}
            @if ($series->isActive())
                <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6 space-y-5">
                    <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400">
                        <x-heroicon-o-user-plus class="w-4 h-4" /> {{ __('Adicionar mensalista') }}
                    </h3>

                    <form method="get" action="{{ route('game-series.show', $series) }}" class="flex flex-wrap gap-3">
                        <x-text-input name="q" type="search" class="flex-1 min-w-48 rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$q" placeholder="{{ __('Buscar por nome, e-mail ou telefone') }}" />
                        <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 transition">
                            {{ __('Buscar') }}
                        </button>
                    </form>

                    @if ($q !== '')
                        @if ($userResults->isEmpty() && $guestResults->isEmpty())
                            <p class="text-sm text-pitch-400">{{ __('Ninguém encontrado com esse termo.') }}</p>
                        @else
                            <div class="space-y-2">
                                @foreach ($userResults as $user)
                                    <div class="flex items-center gap-3 bg-pitch-800/60 rounded-xl border border-pitch-800 p-3">
                                        <p class="min-w-0 flex-1">
                                            <span class="block font-bold text-white truncate">{{ $user->name }}</span>
                                            <span class="block text-xs text-pitch-400 truncate">{{ $user->email }}</span>
                                        </p>
                                        <form method="post" action="{{ route('game-series.members.store', $series) }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                                <x-heroicon-o-plus class="w-3.5 h-3.5" /> {{ __('Adicionar') }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach

                                @foreach ($guestResults as $guest)
                                    <div class="flex items-center gap-3 bg-pitch-800/60 rounded-xl border border-pitch-800 p-3">
                                        <p class="min-w-0 flex-1">
                                            <span class="block font-bold text-white truncate">{{ $guest->name }}</span>
                                            <span class="block text-xs text-pitch-400">{{ __('Sem cadastro') }}</span>
                                        </p>
                                        <form method="post" action="{{ route('game-series.members.store', $series) }}">
                                            @csrf
                                            <input type="hidden" name="guest_player_id" value="{{ $guest->id }}">
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wide text-white bg-emerald-600 hover:bg-emerald-700 transition">
                                                <x-heroicon-o-plus class="w-3.5 h-3.5" /> {{ __('Adicionar') }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif

                    <form method="post" action="{{ route('game-series.members.store', $series) }}" class="pt-4 border-t border-pitch-800 space-y-3">
                        @csrf
                        <p class="text-sm font-semibold text-pitch-200">{{ __('Ou cadastre alguém sem conta no app') }}</p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <x-input-label for="new_guest_name" :value="__('Nome')" />
                                <x-text-input id="new_guest_name" name="new_guest_name" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" />
                            </div>
                            <div>
                                <x-input-label for="new_guest_phone" :value="__('Telefone (opcional)')" />
                                <x-text-input id="new_guest_phone" name="new_guest_phone" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" />
                            </div>
                            <div>
                                <x-input-label for="new_guest_email" :value="__('E-mail (opcional)')" />
                                <x-text-input id="new_guest_email" name="new_guest_email" type="email" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" />
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('new_guest_name')" />

                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-200 bg-pitch-800 border border-pitch-700 hover:bg-pitch-700 transition">
                            <x-heroicon-o-user-plus class="w-4 h-4" /> {{ __('Cadastrar e adicionar') }}
                        </button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
