<x-app-layout>
    <x-slot name="title">{{ __('Meu plano') }}</x-slot>

    <x-slot name="header">
        <x-page-header icon="heroicon-o-sparkles" :title="__('Meu plano')" :subtitle="__('O que a sua conta libera hoje e como mudar isso')" />
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('error'))
                <p class="rounded-xl bg-amber-500/10 border border-amber-500/30 px-4 py-3 text-sm font-medium text-amber-300 flex items-start gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" /> {{ session('error') }}
                </p>
            @endif

            @if (session('status') === 'subscription-processing')
                <p class="rounded-xl bg-emerald-500/10 border border-emerald-500/30 px-4 py-3 text-sm font-medium text-emerald-300 flex items-start gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
                    {{ __('Pagamento recebido! Estamos confirmando com a operadora — seu plano é liberado em instantes.') }}
                </p>
            @endif

            @if (session('status') === 'subscription-simulated')
                <p class="rounded-xl bg-blue-500/10 border border-blue-500/30 px-4 py-3 text-sm font-medium text-blue-300 flex items-start gap-2">
                    <x-heroicon-o-beaker class="w-5 h-5 shrink-0" />
                    {{ __('Plano trocado no modo de teste, sem cobrança nenhuma.') }}
                </p>
            @endif

            {{-- ==================== PLANO ATUAL ==================== --}}
            <div class="bg-pitch-900 rounded-2xl border border-pitch-800 shadow-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-black uppercase tracking-widest text-pitch-500">{{ __('Plano atual') }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <h2 class="text-2xl font-black text-white">{{ $currentPlan->label() }}</h2>
                            <x-plan-badge :plan="$currentPlan" />
                        </div>
                        <p class="mt-1 text-sm text-pitch-400">{{ $currentPlan->tagline() }}</p>
                    </div>

                    @if ($subscription?->stripe_subscription_id && $billingConfigured)
                        <a href="{{ route('subscription.portal') }}" class="inline-flex items-center gap-1.5 min-h-[44px] px-4 rounded-xl font-bold text-xs uppercase tracking-widest text-white bg-pitch-800 hover:bg-pitch-700 border border-white/10 transition">
                            <x-heroicon-o-credit-card class="w-4 h-4" /> {{ __('Gerenciar assinatura') }}
                        </a>
                    @endif
                </div>

                {{-- Situações que mudam o que a pessoa precisa fazer agora:
                     pagamento falhando, teste acabando, cancelamento com data
                     marcada. Todas continuam com o plano de pé. --}}
                @if ($subscription?->isPastDue())
                    <p class="mt-4 rounded-xl bg-amber-500/10 border border-amber-500/30 px-4 py-3 text-sm font-medium text-amber-300 flex items-start gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0" />
                        {{ __('A última cobrança não passou. Seu plano continua valendo enquanto tentamos de novo — vale conferir o cartão.') }}
                    </p>
                @elseif ($subscription?->onGracePeriod())
                    <p class="mt-4 rounded-xl bg-pitch-800/60 border border-white/10 px-4 py-3 text-sm text-pitch-300 flex items-start gap-2">
                        <x-heroicon-o-information-circle class="w-5 h-5 shrink-0" />
                        {{ __('Assinatura cancelada. Você continua no :plan até :date.', ['plan' => $currentPlan->label(), 'date' => $subscription->ends_at->format('d/m/Y')]) }}
                    </p>
                @elseif ($subscription?->onTrial())
                    <p class="mt-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 px-4 py-3 text-sm text-emerald-300 flex items-start gap-2">
                        <x-heroicon-o-gift class="w-5 h-5 shrink-0" />
                        {{ __('Período de teste até :date.', ['date' => $subscription->trial_ends_at->format('d/m/Y')]) }}
                    </p>
                @endif

                {{-- ==================== USO DO CICLO ==================== --}}
                <div class="mt-6 pt-6 border-t border-pitch-800">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="text-[11px] font-black uppercase tracking-widest text-pitch-500">{{ __('Uso deste mês') }}</p>
                        <p class="text-xs text-pitch-500">{{ __('Zera em :date', ['date' => $periodEnd->format('d/m')]) }}</p>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($usage as $row)
                            @php
                                $limit = $row['limit'];
                                $used = $row['used'];
                                // Sem teto não há barra para desenhar; com
                                // teto, a barra estoura em 100% e não passa
                                // disso mesmo se o organizador tiver sido
                                // liberado por outro caminho.
                                $percent = $limit === null || $limit === 0 ? 0 : min(100, (int) round($used / $limit * 100));
                            @endphp

                            <div class="rounded-xl bg-pitch-800/50 border border-white/5 p-4">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="text-sm font-bold text-white">{{ $row['feature']->label() }}</p>
                                    <p class="text-sm font-black text-white tabular-nums">
                                        {{ $used }}<span class="text-pitch-500">/{{ $limit === null ? '∞' : $limit }}</span>
                                    </p>
                                </div>

                                <div class="mt-3 h-1.5 rounded-full bg-pitch-900 overflow-hidden">
                                    <div
                                        class="h-full rounded-full {{ $percent >= 100 ? 'bg-amber-400' : 'bg-emerald-400' }}"
                                        style="width: {{ $limit === null ? 100 : $percent }}%"
                                    ></div>
                                </div>

                                <p class="mt-2 text-xs text-pitch-400">
                                    @if ($limit === null)
                                        {{ __('Sem limite no seu plano.') }}
                                    @elseif ($row['remaining'] === 0)
                                        {{ __('Acabou por este mês.') }}
                                    @else
                                        {{ trans_choice('Resta :count.|Restam :count.', $row['remaining'], ['count' => $row['remaining']]) }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ==================== CATÁLOGO ==================== --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 pt-2">
                @foreach ($plans as $plan)
                    @php
                        $isCurrent = $plan === $currentPlan;
                        $isUpgrade = $plan->rank() > $currentPlan->rank();
                    @endphp

                    <x-plan-card :plan="$plan" :current="$isCurrent" :featured="! $isCurrent && $plan->value === 'pro'">
                        <x-slot name="action">
                            @if ($isCurrent)
                                <span class="flex items-center justify-center gap-1.5 min-h-[48px] px-5 rounded-xl font-bold text-xs uppercase tracking-widest bg-pitch-800 text-pitch-400 border border-white/5">
                                    <x-heroicon-o-check class="w-4 h-4" /> {{ __('Seu plano') }}
                                </span>
                            @elseif (! $plan->isPaid())
                                {{-- Voltar para o Free é cancelar, e cancelamento
                                     mora no portal de cobrança, junto das faturas. --}}
                                <a href="{{ $billingConfigured ? route('subscription.portal') : route('subscription.index') }}" class="flex items-center justify-center min-h-[48px] px-5 rounded-xl font-bold text-xs uppercase tracking-widest text-pitch-300 border border-white/10 hover:bg-pitch-800 transition">
                                    {{ __('Cancelar assinatura') }}
                                </a>
                            @elseif ($canSwitchManually)
                                <form method="POST" action="{{ route('subscription.simulate', $plan) }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center justify-center gap-1.5 min-h-[48px] px-5 rounded-xl font-bold text-xs uppercase tracking-widest bg-blue-500/20 text-blue-200 border border-blue-400/30 hover:bg-blue-500/30 transition">
                                        <x-heroicon-o-beaker class="w-4 h-4" /> {{ __('Testar :plan', ['plan' => $plan->label()]) }}
                                    </button>
                                </form>
                            @elseif ($billingConfigured)
                                <form method="POST" action="{{ route('subscription.checkout', $plan) }}">
                                    @csrf
                                    <button type="submit" class="btn-shine w-full flex items-center justify-center gap-1.5 min-h-[48px] px-5 rounded-xl font-extrabold text-xs uppercase tracking-widest bg-emerald-400 text-pitch-950 hover:bg-emerald-300 transition">
                                        {{ $isUpgrade ? __('Assinar :plan', ['plan' => $plan->label()]) : __('Mudar para :plan', ['plan' => $plan->label()]) }}
                                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                                    </button>
                                </form>
                            @else
                                <span class="flex items-center justify-center gap-1.5 min-h-[48px] px-5 rounded-xl font-bold text-xs uppercase tracking-widest bg-pitch-800 text-pitch-500 border border-white/5">
                                    <x-heroicon-o-clock class="w-4 h-4" /> {{ __('Em breve') }}
                                </span>
                            @endif
                        </x-slot>
                    </x-plan-card>
                @endforeach
            </div>

            @unless ($billingConfigured)
                <p class="text-center text-xs text-pitch-500">
                    {{ __('A cobrança ainda não está ativa nesta instalação — todo mundo continua no plano Free, com o app inteiro funcionando.') }}
                </p>
            @endunless

            <p class="text-center text-xs text-pitch-500">
                {{ __('Cancele quando quiser. O plano vale até o fim do período já pago.') }}
            </p>
        </div>
    </div>
</x-app-layout>
