<x-app-layout>
    <x-slot name="header">
        <x-page-header icon="heroicon-o-calendar-days" :title="__('Disponibilidade')" :subtitle="__('Informe os dias e horários em que você está livre para jogar')" />
    </x-slot>

    @php
        $dayLabels = [
            0 => 'Domingo',
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
        ];

        $selectedDays = old('days', $availabilities->pluck('day_of_week')->all());
        $selectedDays = array_map('intval', $selectedDays);
        $startTime = old('start_time', optional($availabilities->first())->start_time?->format('H:i'));
        $endTime = old('end_time', optional($availabilities->first())->end_time?->format('H:i'));
    @endphp

    <div class="py-6 sm:py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-sm shadow-black/20 p-5 sm:p-8">
                <form method="post" action="{{ route('availability.update') }}" class="space-y-6">
                    @csrf
                    @method('put')

                    <div>
                        <x-input-label :value="__('Atalhos')" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" data-shortcut="today" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-sm font-semibold rounded-full border border-emerald-600/40 text-emerald-400 hover:bg-emerald-500/10 transition">
                                <x-heroicon-o-sun class="w-4 h-4" /> {{ __('Hoje') }}
                            </button>
                            <button type="button" data-shortcut="tomorrow" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-sm font-semibold rounded-full border border-emerald-600/40 text-emerald-400 hover:bg-emerald-500/10 transition">
                                <x-heroicon-o-arrow-right class="w-4 h-4" /> {{ __('Amanhã') }}
                            </button>
                            <button type="button" data-shortcut="weekend" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-sm font-semibold rounded-full border border-emerald-600/40 text-emerald-400 hover:bg-emerald-500/10 transition">
                                <x-heroicon-o-sparkles class="w-4 h-4" /> {{ __('Final de semana') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <x-input-label :value="__('Dias da semana')" />
                        <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach ($dayLabels as $value => $label)
                                <label class="flex items-center justify-center gap-2 rounded-lg border border-pitch-700 px-3 py-2.5 text-sm font-medium text-pitch-200 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 has-[:checked]:text-emerald-300 transition cursor-pointer">
                                    <input type="checkbox" name="days[]" value="{{ $value }}" class="day-checkbox rounded bg-pitch-800 border-pitch-600 text-emerald-600 shadow-sm focus:ring-emerald-500" @checked(in_array($value, $selectedDays))>
                                    {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('days')" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="start_time" :value="__('Horário inicial')" />
                            <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$startTime" required />
                            <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
                        </div>

                        <div>
                            <x-input-label for="end_time" :value="__('Horário final')" />
                            <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full rounded-lg focus:border-emerald-500 focus:ring-emerald-500" :value="$endTime" required />
                            <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <button type="submit" class="inline-flex items-center px-6 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-emerald-600 hover:bg-emerald-700 shadow-lg shadow-emerald-600/20 transition">
                            {{ __('Salvar') }}
                        </button>

                        @if (session('status') === 'availability-updated')
                            <p
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition
                                x-init="setTimeout(() => show = false, 2000)"
                                class="text-sm font-medium text-emerald-400 flex items-center gap-1"
                            ><x-heroicon-o-check-circle class="w-4 h-4" /> {{ __('Salvo.') }}</p>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const checkboxes = document.querySelectorAll('.day-checkbox');

            function setDays(days) {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = days.includes(parseInt(checkbox.value, 10));
                });
            }

            document.querySelectorAll('[data-shortcut]').forEach((button) => {
                button.addEventListener('click', () => {
                    const today = new Date().getDay();
                    const tomorrow = (today + 1) % 7;

                    switch (button.dataset.shortcut) {
                        case 'today':
                            setDays([today]);
                            break;
                        case 'tomorrow':
                            setDays([tomorrow]);
                            break;
                        case 'weekend':
                            setDays([0, 6]);
                            break;
                    }
                });
            });
        })();
    </script>
</x-app-layout>
