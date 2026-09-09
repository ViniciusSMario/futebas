<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Como o app fala de tempo.
 *
 * Ninguém marca pelada para "12/09/2026": marca para sábado, e quando é
 * hoje o que importa é justamente que é hoje. Isto começou como três views
 * mantendo cada uma a sua cópia do array de dias da semana; o SOS teria
 * sido a quarta.
 *
 * As abreviações são escritas aqui em vez de virem do locale porque o que
 * o ICU devolve em pt_BR ("sáb.", com ponto) não é a que cabe num cartão,
 * e porque estas são as três palavras que o app repete o dia inteiro.
 */
class When
{
    /** Dias da semana abreviados, na ordem do `dayOfWeek` do Carbon. */
    public const WEEKDAYS_SHORT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    /**
     * Quantas horas antes de um prazo ele deixa de ser "uma data" e passa
     * a ser uma contagem regressiva.
     *
     * Um dia: acima disso "sábado, 19:00" responde melhor do que "faltam
     * 31 horas", que ninguém consegue converter em plano.
     */
    private const COUNTDOWN_WINDOW_HOURS = 24;

    /** A abreviação do dia da semana: "Sáb". */
    public static function weekdayShort(CarbonInterface $date): string
    {
        return self::WEEKDAYS_SHORT[(int) $date->dayOfWeek];
    }

    /**
     * O dia como alguém marca pelada: "Hoje", "Ontem", "Amanhã",
     * "Sáb, 12/09".
     *
     * "Ontem" está aqui porque o passado recente também é falado assim, e
     * é onde ele mais aparece: o organizador chega na tela de avaliar
     * jogadores logo depois de encerrar a partida, e a partida que ele
     * acabou de encerrar foi ontem à noite.
     *
     * O ano só aparece quando não é o corrente — o único caso em que ele
     * diz alguma coisa, e no histórico de partidas antigas ele diz.
     */
    public static function day(CarbonInterface $date): string
    {
        if ($date->isToday()) {
            return __('Hoje');
        }

        if ($date->isYesterday()) {
            return __('Ontem');
        }

        if ($date->isTomorrow()) {
            return __('Amanhã');
        }

        $format = $date->isSameYear(Carbon::now()) ? 'd/m' : 'd/m/Y';

        return self::weekdayShort($date).', '.$date->format($format);
    }

    /** O dia e a hora numa linha só: "Sáb, 12/09 · 19:00". */
    public static function dayAndTime(CarbonInterface $moment): string
    {
        return self::day($moment).' · '.$moment->format('H:i');
    }

    /**
     * Quanto falta para um prazo, do ponto de vista de quem precisa
     * decidir: "Faltam 40 minutos", "Faltam 3 horas", "Até sáb, 12/09 ·
     * 19:00".
     *
     * A contagem regressiva é o formato certo só perto do fim. Um SOS que
     * vence daqui a dois dias não é urgente, e dizer "faltam 51 horas"
     * obriga a pessoa a fazer a conta que a data já entregava pronta.
     */
    public static function remaining(CarbonInterface $deadline): string
    {
        if ($deadline->isPast()) {
            return __('Prazo encerrado');
        }

        // Arredonda para cima e nunca chega a zero: no último segundo, "Falta
        // 1 minuto" é verdade suficiente, e "Faltam 0 minutos" não é frase.
        $minutes = max(1, (int) ceil(Carbon::now()->diffInMinutes($deadline)));

        if ($minutes < 60) {
            return trans_choice('Falta :count minuto|Faltam :count minutos', $minutes, ['count' => $minutes]);
        }

        $hours = intdiv($minutes, 60);

        if ($hours < self::COUNTDOWN_WINDOW_HOURS) {
            return trans_choice('Falta :count hora|Faltam :count horas', $hours, ['count' => $hours]);
        }

        return __('Até :quando', ['quando' => mb_strtolower(self::dayAndTime($deadline))]);
    }

    /**
     * O quanto esse prazo aperta, para a interface poder mudar de cor sem
     * refazer a conta: 'past', 'urgent' (menos de três horas), 'soon'
     * (menos de um dia) ou 'calm'.
     */
    public static function urgency(CarbonInterface $deadline): string
    {
        if ($deadline->isPast()) {
            return 'past';
        }

        $hours = Carbon::now()->diffInHours($deadline);

        return match (true) {
            $hours < 3 => 'urgent',
            $hours < self::COUNTDOWN_WINDOW_HOURS => 'soon',
            default => 'calm',
        };
    }
}
