<section>
    <header>
        <h2 class="text-lg font-medium text-white">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-pitch-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" @if ($user->hasRole(\App\Models\User::ROLE_ORGANIZER)) enctype="multipart/form-data" @endif class="mt-6 space-y-6">
        @csrf
        @method('patch')

        @if ($user->hasRole(\App\Models\User::ROLE_ORGANIZER))
            <div class="flex items-center gap-4">
                @if ($user->photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($user->photo_path) }}" alt="{{ __('Foto atual') }}" class="h-16 w-16 rounded-2xl object-cover shrink-0 ring-2 ring-white shadow-sm">
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
        @endif

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        @if ($user->hasRole(\App\Models\User::ROLE_ORGANIZER))
            @php
                $selectedState = old('state', $user->state);
                $selectedCity = old('city', $user->city);
            @endphp

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

            <div>
                <x-input-label for="phone" :value="__('Telefone')" />
                <x-text-input id="phone" name="phone" type="text" data-phone-mask class="mt-1 block w-full" :value="old('phone', $user->phone)" required placeholder="(00) 00000-0000" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>
        @endif

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-pitch-200">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-pitch-300 hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-pitch-900 focus:ring-emerald-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-emerald-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-pitch-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
