<x-auth-shell max-width="max-w-3xl">
    @php
        $selectedDays = array_map('intval', old('days', []));
        $forcePlayerRole = $forcePlayerRole ?? false;
    @endphp

    <div x-data="{ role: @js($forcePlayerRole ? 'player' : old('role')) }">
        @isset($intendedGame)
            <div class="max-w-md mx-auto mb-6 flex items-center gap-2 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                <span class="text-lg"></span>
                {{ __('Crie sua conta de jogador para entrar em ":name".', ['name' => $intendedGame->team_name]) }}
            </div>
        @endisset

        @unless ($forcePlayerRole)
            <template x-if="!role">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 text-center">
                        {{ __('Como você vai usar o Futebas?') }}
                    </h1>
                    <p class="mt-2 text-sm text-gray-500 text-center">
                        {{ __('Escolha o perfil que combina com você.') }}
                    </p>

                    <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <button type="button" @click="role = 'player'"
                            class="group text-left p-6 rounded-2xl border-2 border-gray-200 bg-white hover:border-green-500 hover:shadow-xl transition transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <h2 class="mt-0 text-lg font-extrabold text-gray-900 uppercase tracking-wide">{{ __('Jogador') }}</h2>
                            <p class="mt-0 text-sm text-gray-600">{{ __('Quero encontrar partidas e jogar futebol.') }}</p>
                        </button>

                        <button type="button" @click="role = 'organizer'"
                            class="group text-left p-6 rounded-2xl border-2 border-gray-200 bg-white hover:border-green-500 hover:shadow-xl transition transform hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <h2 class="mt-0 text-lg font-extrabold text-gray-900 uppercase tracking-wide">{{ __('Organizador') }}</h2>
                            <p class="mt-0 text-sm text-gray-600">{{ __('Preciso encontrar jogadores para completar minhas partidas.') }}</p>
                        </button>
                    </div>

                    <p class="mt-6 text-center text-sm text-gray-600">
                        {{ __('Já tem conta?') }}
                        <a href="{{ route('login') }}" class="font-semibold text-green-700 hover:text-green-900">{{ __('Entrar') }}</a>
                    </p>
                </div>
            </template>
        @endunless

        <template x-if="role">
            <div class="max-w-md mx-auto">
                @unless ($forcePlayerRole)
                    <button type="button" @click="role = null" class="text-sm text-gray-500 hover:text-gray-800 mb-4 inline-flex items-center gap-1">
                        &larr; {{ __('Escolher outro perfil') }}
                    </button>
                @endunless

                <div class="flex items-center gap-3 mb-6">
                    <span class="text-4xl" x-text="role === 'player' ? '' : ''"></span>
                    <div>
                        <h1 class="text-xl font-extrabold text-gray-900" x-text="role === 'player' ? '{{ __('Cadastro de Jogador') }}' : '{{ __('Cadastro de Organizador') }}'"></h1>
                        <p class="text-sm text-gray-500" x-text="role === 'player' ? '{{ __('Quero jogar.') }}' : '{{ __('Preciso de jogadores.') }}'"></p>
                    </div>
                </div>

                <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <input type="hidden" name="role" :value="role">

                    <div>
                        <x-input-label for="name" :value="__('Nome')" />
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name"
                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('E-mail')" />
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="password" :value="__('Senha')" />
                            <input id="password" type="password" name="password" required autocomplete="new-password"
                                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="password_confirmation" :value="__('Confirmar senha')" />
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                                class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="photo" :value="__('Foto')" />
                        <input id="photo" type="file" name="photo" accept="image/*" class="mt-1 block w-full text-sm text-gray-700">
                        <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Telefone')" />
                        <input id="phone" type="text" name="phone" value="{{ old('phone') }}" data-phone-mask required placeholder="(00) 00000-0000"
                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="state" :value="__('Estado')" />
                            <select id="state" name="state" required data-state-select="city" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                <option value="">{{ __('Selecione...') }}</option>
                                @foreach (\App\Models\PlayerProfile::STATES as $uf => $stateName)
                                    <option value="{{ $uf }}" @selected(old('state') === $uf)>{{ $stateName }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('state')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="city" :value="__('Cidade')" />
                            <select id="city" name="city" required data-selected="{{ old('city') }}" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                @if (old('city'))
                                    <option value="{{ old('city') }}" selected>{{ old('city') }}</option>
                                @else
                                    <option value="">{{ __('Selecione o estado primeiro') }}</option>
                                @endif
                            </select>
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>
                    </div>

                    <template x-if="role === 'player'">
                        <div class="space-y-5">
                            <div>
                                <x-input-label for="birth_date" :value="__('Data de nascimento')" />
                                <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}" required
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="position_primary" :value="__('Posição principal')" />
                                <select id="position_primary" name="position_primary" required class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                    <option value="">{{ __('Selecione') }}</option>
                                    @foreach (\App\Models\PlayerProfile::POSITIONS as $position)
                                        <option value="{{ $position }}" @selected(old('position_primary') === $position)>{{ $position }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('position_primary')" class="mt-2" />
                            </div>

                            <div>
                                <span class="block font-medium text-sm text-gray-700 mb-2">{{ __('Outras posições') }}</span>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach (\App\Models\PlayerProfile::POSITIONS as $position)
                                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:text-green-800 transition cursor-pointer">
                                            <input type="checkbox" name="positions_secondary[]" value="{{ $position }}" @checked(in_array($position, old('positions_secondary', []))) class="rounded border-gray-300 text-green-600 focus:ring-green-600">
                                            {{ $position }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('positions_secondary')" class="mt-2" />
                            </div>

                            <div>
                                <span class="block font-medium text-sm text-gray-700 mb-2">{{ __('Modalidades') }}</span>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    @foreach (\App\Models\PlayerProfile::MODALITIES as $modality)
                                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:text-green-800 transition cursor-pointer">
                                            <input type="checkbox" name="modalities[]" value="{{ $modality }}" @checked(in_array($modality, old('modalities', []))) class="rounded border-gray-300 text-green-600 focus:ring-green-600">
                                            {{ $modality }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('modalities')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="level" :value="__('Nível')" />
                                <select id="level" name="level" required class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                    <option value="">{{ __('Selecione') }}</option>
                                    @foreach (\App\Models\PlayerProfile::LEVELS as $level)
                                        <option value="{{ $level }}" @selected(old('level') === $level)>{{ $level }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('level')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="price_per_game" :value="__('Valor por partida (R$)')" />
                                <input id="price_per_game" type="number" step="0.01" min="0" name="price_per_game" value="{{ old('price_per_game') }}" required
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                <x-input-error :messages="$errors->get('price_per_game')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label :value="__('Disponibilidade')" />
                                <p class="mt-1 text-sm text-gray-500">{{ __('Selecione os dias da semana e horários que você costuma jogar.') }}</p>
                                <div class="mt-3 grid grid-cols-4 gap-2 text-sm text-gray-700">
                                    @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $i => $label)
                                        <label class="inline-flex items-center gap-1">
                                            <input type="checkbox" name="days[]" value="{{ $i }}" class="reg-day-checkbox rounded border-gray-300 text-green-600 focus:ring-green-600" @checked(in_array($i, $selectedDays, true))>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('days')" class="mt-2" />

                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="start_time" :value="__('Horário inicial')" />
                                        <input id="start_time" type="time" name="start_time" value="{{ old('start_time') }}" required
                                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                        <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="end_time" :value="__('Horário final')" />
                                        <input id="end_time" type="time" name="end_time" value="{{ old('end_time') }}" required
                                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
                                        <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 bg-green-700 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest shadow-lg shadow-green-700/30 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('Criar conta') }}
                    </button>
                </form>
            </div>
        </template>
    </div>

    <script>
        (function () {
            document.querySelectorAll('[data-shortcut]').forEach((button) => {
                button.addEventListener('click', () => {
                    const checkboxes = document.querySelectorAll('.reg-day-checkbox');
                    const today = new Date().getDay();
                    const tomorrow = (today + 1) % 7;

                    const days = {
                        today: [today],
                        tomorrow: [tomorrow],
                        weekend: [0, 6],
                    }[button.dataset.shortcut];

                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = days.includes(parseInt(checkbox.value, 10));
                    });
                });
            });
        })();
    </script>
</x-auth-shell>
