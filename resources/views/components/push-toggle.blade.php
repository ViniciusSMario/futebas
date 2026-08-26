{{--
    Push notification opt-in for the current device.

    Browsers only grant permission from a user gesture, so this is always a
    button the user presses — never something the app asks for on load.
    A user with several devices sees this on each of them independently.
--}}
<div x-data="pushNotifications" x-init="init()" class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-6">
    <div class="flex items-start gap-4">
        <span class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0"
              :class="subscribed ? 'bg-emerald-500/15 text-emerald-400' : 'bg-pitch-800 text-pitch-400'">
            <x-heroicon-o-bell-alert class="w-5 h-5" />
        </span>

        <div class="min-w-0 grow">
            <h3 class="font-bold text-white">{{ __('Notificações no celular') }}</h3>
            <p class="mt-1 text-sm text-pitch-400">
                {{ __('Receba um alerta na hora em que um SOS aparecer na sua região, mesmo com o app fechado.') }}
            </p>

            <template x-if="permission === 'unsupported'">
                <p class="mt-3 text-sm text-amber-400 flex items-start gap-1.5">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0 mt-0.5" />
                    {{ __('Este navegador não suporta notificações. No iPhone, adicione o app à Tela de Início primeiro.') }}
                </p>
            </template>

            <template x-if="permission === 'denied'">
                <p class="mt-3 text-sm text-amber-400 flex items-start gap-1.5">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0 mt-0.5" />
                    {{ __('As notificações foram bloqueadas. Libere nas configurações do navegador para este site.') }}
                </p>
            </template>

            <div class="mt-4 flex flex-wrap items-center gap-2" x-show="permission !== 'unsupported' && permission !== 'denied'">
                <button
                    type="button"
                    x-show="! subscribed"
                    :disabled="busy"
                    @click="enable()"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 transition"
                >
                    <x-heroicon-o-bell class="w-4 h-4" /> {{ __('Ativar notificações') }}
                </button>

                <button
                    type="button"
                    x-show="subscribed"
                    style="display: none;"
                    :disabled="busy"
                    @click="sendTest()"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-emerald-400 bg-emerald-500/10 hover:bg-emerald-500/20 disabled:opacity-60 transition"
                >
                    <x-heroicon-o-paper-airplane class="w-4 h-4" /> {{ __('Enviar teste') }}
                </button>

                <button
                    type="button"
                    x-show="subscribed"
                    style="display: none;"
                    :disabled="busy"
                    @click="disable()"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-400 hover:text-white transition"
                >
                    {{ __('Desativar') }}
                </button>
            </div>

            <p x-show="message" style="display: none;" x-text="message" class="mt-3 text-sm text-pitch-300"></p>
        </div>
    </div>
</div>
