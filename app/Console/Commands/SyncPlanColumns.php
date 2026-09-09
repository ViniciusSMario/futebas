<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Realinha a cópia do plano em `users.plan` com o que a assinatura diz.
 *
 * Quase sempre não há nada a fazer: a cópia é refeita a cada gravação de
 * assinatura. A exceção é a passagem do tempo — uma assinatura que venceu
 * ontem à noite não gravou nada ao vencer, e enquanto o Stripe não avisa
 * (ou se ele nunca avisar, no caso de uma assinatura mantida na mão) a
 * coluna segue apontando para o plano antigo.
 *
 * Nada de acesso depende disto: os gates leem a assinatura. O que se
 * conserta aqui é o destaque na busca, que é ordenação e por isso lê a
 * coluna.
 */
class SyncPlanColumns extends Command
{
    protected $signature = 'plans:sync';

    protected $description = 'Realinha a coluna users.plan com o plano efetivo de cada assinatura';

    public function handle(): int
    {
        $fixed = 0;

        Subscription::query()
            ->with('user')
            ->each(function (Subscription $subscription) use (&$fixed) {
                $user = $subscription->user;

                if ($user === null || $user->plan === $subscription->effectivePlan()) {
                    return;
                }

                $subscription->syncUserPlan();
                $fixed++;

                $this->line(sprintf('%s: %s', $user->email, $subscription->effectivePlan()->value));
            });

        $this->info(sprintf('%d conta(s) realinhada(s).', $fixed));

        return self::SUCCESS;
    }
}
