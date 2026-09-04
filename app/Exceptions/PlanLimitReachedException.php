<?php

namespace App\Exceptions;

use App\Enums\Feature;
use App\Enums\Plan;
use RuntimeException;

/**
 * Levantada quando o plano do usuário não cobre o que ele acabou de tentar
 * fazer — o teto do mês estourou, ou o recurso é de um plano acima.
 *
 * Bater no limite não é erro: é o produto funcionando. Por isso a exceção
 * carrega o recurso e o plano sugerido, e os controllers a transformam em
 * um convite para assinar, nunca em uma tela de erro.
 */
class PlanLimitReachedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly Feature $feature,
        public readonly ?Plan $suggestedPlan = null,
        public readonly ?int $limit = null,
    ) {
        parent::__construct($message);
    }

    /**
     * O teto mensal do recurso acabou.
     */
    public static function quotaExhausted(Feature $feature, int $limit, ?Plan $suggested = null): self
    {
        $message = $feature->exhaustedMessage($limit);

        if ($suggested !== null) {
            $message .= ' '.__('No plano :plan são :limit.', [
                'plan' => $suggested->label(),
                'limit' => mb_strtolower($feature->describeLimit($suggested->limit($feature))),
            ]);
        }

        return new self($message, $feature, $suggested, $limit);
    }

    /**
     * O recurso inteiro é de um plano acima.
     */
    public static function featureUnavailable(Feature $feature, ?Plan $suggested = null): self
    {
        $message = $suggested !== null
            ? __(':feature faz parte do plano :plan.', ['feature' => $feature->label(), 'plan' => $suggested->label()])
            : __('Este recurso não está disponível no seu plano.');

        return new self($message, $feature, $suggested);
    }
}
