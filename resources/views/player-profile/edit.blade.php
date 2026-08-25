<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-user" :title="__('Meu Perfil')" :subtitle="__('Complete seus dados para aparecer nas buscas dos organizadores')" />
    </x-slot>

    @php
        $selectedPositions = old('position_primary', $playerProfile->positions[0] ?? null);
        $selectedSecondary = old('positions_secondary', array_slice($playerProfile->positions ?? [], 1));
        $selectedModalities = old('modalities', $playerProfile->modalities ?? []);
        $selectedState = old('state', $playerProfile->state ?? '');
        $selectedCity = old('city', $playerProfile->city ?? '');
        $playsOutsideCity = old('plays_outside_city', $playerProfile->plays_outside_city ?? false);
    @endphp
    <div class="py-6 sm:py-8">
        <div class="max-w-1xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8 items-start">
                <div>
                <form method="post" action="{{ route('player-profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('put')

                    <div class="space-y-6">
                        {{-- Photo + name --}}
                        <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <div class="flex items-center gap-4">
                                @if ($playerProfile?->photo_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($playerProfile->photo_path) }}" alt="{{ __('Foto atual') }}" class="h-16 w-16 rounded-2xl object-cover shrink-0 ring-2 ring-white shadow-sm">
                                @else
                                    <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-emerald-600/30 to-emerald-800/40 flex items-center justify-center text-xl font-extrabold text-emerald-300 shrink-0">
                                        {{ Str::upper(Str::substr($user->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <x-input-label for="photo" :value="__('Foto (opcional)')" class="text-xs" />
                                    <input id="photo" name="photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-pitch-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:uppercase file:tracking-wide file:bg-emerald-500/15 file:text-emerald-400 hover:file:bg-emerald-500/25">
                                </div>
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('photo')" />

                            <div class="mt-5">
                                <x-input-label for="name" :value="__('Nome')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('name', $user->name)" required autofocus />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div class="mt-5">
                                <x-input-label for="birth_date" :value="__('Data de nascimento')" />
                                <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('birth_date', optional($playerProfile?->birth_date)->format('Y-m-d'))" required />
                                <x-input-error class="mt-2" :messages="$errors->get('birth_date')" />
                            </div>
                        </section>

                        {{-- Location + contact --}}
                        <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                                <x-heroicon-o-map-pin class="w-4 h-4" /> {{ __('Localização e contato') }}
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="state" :value="__('Estado')" />
                                    <select id="state" name="state" required data-state-select="city" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                        <option value="">{{ __('Selecione...') }}</option>
                                        @foreach (\App\Models\PlayerProfile::STATES as $uf => $stateName)
                                            <option value="{{ $uf }}" @selected($selectedState === $uf)>{{ $stateName }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('state')" />
                                </div>

                                <div>
                                    <x-input-label for="city" :value="__('Cidade')" />
                                    <select id="city" name="city" required data-selected="{{ $selectedCity }}" class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                        @if ($selectedCity)
                                            <option value="{{ $selectedCity }}" selected>{{ $selectedCity }}</option>
                                        @else
                                            <option value="">{{ __('Selecione o estado primeiro') }}</option>
                                        @endif
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('city')" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <x-input-label for="phone" :value="__('Telefone')" />
                                <x-text-input id="phone" name="phone" type="text" data-phone-mask class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('phone', $playerProfile?->phone)" required placeholder="(00) 00000-0000" />
                                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                            </div>
                        </section>

                        {{-- Sports profile --}}
                        <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                                <x-heroicon-o-trophy class="w-4 h-4" /> {{ __('Perfil esportivo') }}
                            </h3>

                            <div>
                                <x-input-label for="position_primary" :value="__('Posição principal')" />
                                <select id="position_primary" name="position_primary" required class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                    <option value="">{{ __('Selecione...') }}</option>
                                    @foreach (\App\Models\PlayerProfile::POSITIONS as $position)
                                        <option value="{{ $position }}" @selected($selectedPositions === $position)>{{ $position }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('position_primary')" />
                            </div>

                            <div class="mt-4">
                                <span class="block font-medium text-sm text-pitch-300 mb-2">{{ __('Outras posições') }}</span>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach (\App\Models\PlayerProfile::POSITIONS as $position)
                                        <label class="flex items-center gap-2 rounded-lg border border-pitch-700 px-3 py-2 text-sm text-pitch-200 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 has-[:checked]:text-emerald-300 transition cursor-pointer">
                                            <input type="checkbox" name="positions_secondary[]" value="{{ $position }}" @checked(in_array($position, $selectedSecondary)) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            {{ $position }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('positions_secondary')" />
                            </div>

                            <div class="mt-5">
                                <span class="block font-medium text-sm text-pitch-300 mb-2">{{ __('Modalidades') }}</span>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    @foreach (\App\Models\PlayerProfile::MODALITIES as $modality)
                                        <label class="flex items-center gap-2 rounded-lg border border-pitch-700 px-3 py-2 text-sm text-pitch-200 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 has-[:checked]:text-emerald-300 transition cursor-pointer">
                                            <input type="checkbox" name="modalities[]" value="{{ $modality }}" @checked(in_array($modality, $selectedModalities)) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                            {{ $modality }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error class="mt-2" :messages="$errors->get('modalities')" />
                            </div>

                            <div class="mt-5">
                                <x-input-label for="level" :value="__('Nível')" />
                                <select id="level" name="level" required class="mt-1 block w-full rounded-lg bg-pitch-800 border-pitch-700 text-white focus:border-emerald-500 focus:ring-emerald-500 shadow-sm">
                                    <option value="">{{ __('Selecione...') }}</option>
                                    @foreach (\App\Models\PlayerProfile::LEVELS as $level)
                                        <option value="{{ $level }}" @selected(old('level', $playerProfile?->level) === $level)>{{ $level }}</option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('level')" />
                            </div>
                        </section>

                        {{-- Pricing --}}
                        <section class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
                            <h3 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-pitch-400 mb-4">
                                <x-heroicon-o-currency-dollar class="w-4 h-4" /> {{ __('Valores') }}
                            </h3>

                            <div>
                                <x-input-label for="price_per_game" :value="__('Valor por partida (R$)')" />
                                <x-text-input id="price_per_game" name="price_per_game" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('price_per_game', $playerProfile?->price_per_game)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('price_per_game')" />
                            </div>

                            <div class="mt-4">
                                <label class="inline-flex items-center gap-2">
                                    <input id="plays_outside_city" type="checkbox" name="plays_outside_city" value="1" @checked($playsOutsideCity) class="rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500">
                                    <span class="text-sm text-pitch-200">{{ __('Também atua fora da cidade') }}</span>
                                </label>
                                <x-input-error class="mt-2" :messages="$errors->get('plays_outside_city')" />
                            </div>

                            <div id="price_per_game_outside_wrapper" class="mt-4 {{ $playsOutsideCity ? '' : 'hidden' }}">
                                <x-input-label for="price_per_game_outside" :value="__('Valor por partida fora (R$)')" />
                                <x-text-input id="price_per_game_outside" name="price_per_game_outside" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="old('price_per_game_outside', $playerProfile?->price_per_game_outside)" />
                                <x-input-error class="mt-2" :messages="$errors->get('price_per_game_outside')" />
                            </div>
                        </section>

                        <div class="flex items-center gap-4 sticky bottom-4 sm:static">
                            <button type="submit" class="inline-flex items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                                {{ __('Salvar') }}
                            </button>

                            @if (session('status') === 'player-profile-updated')
                                <p
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-transition
                                    x-init="setTimeout(() => show = false, 2000)"
                                    class="text-sm font-medium text-emerald-400 flex items-center gap-1"
                                ><x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Salvo.') }}</p>
                            @endif
                        </div>
                    </div>
                </form>
                </div>

                @if ($playerProfile)
                    <div class="space-y-3 lg:sticky lg:top-6">
                        <x-player-card :player-profile="$playerProfile" />
                        <p class="text-sm text-pitch-400 flex items-center justify-center gap-1">
                            <x-heroicon-o-map-pin class="w-4 h-4 shrink-0" /> {{ $playerProfile->city }}@if ($playerProfile->state), {{ $playerProfile->state }}@endif
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>


    <script>
        (function () {
            const outsideCheckbox = document.getElementById('plays_outside_city');
            const outsideWrapper = document.getElementById('price_per_game_outside_wrapper');

            outsideCheckbox.addEventListener('change', () => {
                outsideWrapper.classList.toggle('hidden', !outsideCheckbox.checked);
            });
        })();
    </script>
</x-app-layout>
