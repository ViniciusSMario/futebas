<?php

namespace App\Console\Commands;

use App\Models\SosApplication;
use App\Models\SosRequest;
use App\Notifications\SosApplicationExpired;
use App\Notifications\SosRequestExpired;
use Illuminate\Console\Command;

/**
 * Fecha o silêncio das chamadas de goleiro que venceram.
 *
 * Todo outro fim de um SOS avisa alguém: quem escolhe avisa o escolhido e
 * os preteridos, quem cancela avisa quem estava esperando. Só o prazo
 * passando não avisava ninguém — o organizador seguia achando que ainda
 * podia aparecer alguém, e o goleiro ficava com a candidatura "aguardando"
 * para sempre.
 *
 * O comando **não** mexe no status da chamada: `sos_requests.status` só
 * muda por decisão de alguém, e "expirado" continua sendo derivado de
 * `expires_at` como sempre foi. O que ele registra, em
 * `expiry_notified_at`, é apenas que o aviso já saiu.
 */
class NotifyExpiredSosRequests extends Command
{
    protected $signature = 'sos:notify-expired';

    protected $description = 'Avisa organizador e candidatos quando o prazo de um SOS vence sem escolha';

    public function handle(): int
    {
        $notified = 0;

        SosRequest::query()
            ->where('status', SosRequest::STATUS_OPEN)
            ->whereNull('expiry_notified_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['game', 'organizer', 'applications.user'])
            ->each(function (SosRequest $sosRequest) use (&$notified) {
                $pending = $sosRequest->applications
                    ->where('status', SosApplication::STATUS_PENDING);

                foreach ($pending as $application) {
                    $application->user?->notify(new SosApplicationExpired($application));
                }

                // Se a partida foi cancelada, o organizador tomou essa
                // decisão e já sabe que não tem goleiro — repetir seria
                // ruído. Os candidatos, esses, continuam precisando saber.
                if ($sosRequest->game?->isOpen()) {
                    $sosRequest->organizer?->notify(new SosRequestExpired($sosRequest, $pending->count()));
                }

                $sosRequest->forceFill(['expiry_notified_at' => now()])->save();
                $notified++;

                $this->line(sprintf(
                    '#%d — %s, %d candidatura(s) sem resposta',
                    $sosRequest->id,
                    $sosRequest->game?->team_name ?? __('partida removida'),
                    $pending->count(),
                ));
            });

        $this->info(sprintf('%d chamada(s) avisada(s).', $notified));

        return self::SUCCESS;
    }
}
