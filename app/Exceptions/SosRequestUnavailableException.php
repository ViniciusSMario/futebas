<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an SOS can no longer be acted on — it was already filled,
 * cancelled, or its deadline passed while the user had the page open.
 *
 * SOS is a race by design (many goalkeepers, one slot), so losing it is an
 * expected outcome, not a bug: controllers turn this into a message rather
 * than an error page.
 */
class SosRequestUnavailableException extends RuntimeException
{
    public static function notOpen(): self
    {
        return new self(__('Esta solicitação de SOS não está mais aberta.'));
    }

    public static function applicationNotPending(): self
    {
        return new self(__('Esta candidatura já foi respondida.'));
    }

    public static function notAGoalkeeper(): self
    {
        return new self(__('O SOS é só para goleiros. Adicione a posição Goleiro ao seu perfil para se candidatar.'));
    }

    public static function alreadyInGame(): self
    {
        return new self(__('Você já está nessa partida — não é possível se candidatar ao SOS dela.'));
    }
}
