<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fecha uma rota inteira atrás de um recurso de plano — o irmão do
 * `role:`, para o eixo "quanto você assinou" em vez de "o que você é".
 *
 * A diferença de tratamento é proposital: um papel errado é um endereço
 * que não era para você (403), mas um plano insuficiente é um convite —
 * então aqui a pessoa vai parar na página de planos, com a explicação do
 * que faltou, e não em uma tela de erro.
 *
 * Vale para recursos booleanos. Os que têm teto mensal são conferidos no
 * serviço que executa a ação, onde ainda dá para dizer quanto sobrou.
 */
class EnsurePlanAllows
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $required = Feature::from($feature);

        if ($request->user()?->planAllows($required)) {
            return $next($request);
        }

        return redirect()
            ->route('subscription.index')
            ->with('error', __(':feature faz parte de um plano acima do seu.', ['feature' => $required->label()]));
    }
}
