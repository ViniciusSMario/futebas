<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nothing runs a scheduler in this project yet, and weekly peladas don't
// need one — the organizer opening their series tops the calendar up. This
// only makes the same work happen unattended once `schedule:work` exists.
Schedule::command('series:generate')->dailyAt('04:00');

// Só conserta a cópia de `users.plan` quando uma assinatura vence sem
// nenhum evento para gravá-la. Não é caminho de acesso: os limites leem a
// assinatura, não a coluna.
Schedule::command('plans:sync')->dailyAt('04:10');

// De hora em hora porque partida acaba a qualquer hora. Esta é a única
// tarefa agendada que o app não consegue fazer sob demanda: uma partida
// que ninguém finaliza não some só de um calendário — ela some da ficha de
// presença de todo mundo que jogou.
Schedule::command('games:finish')->hourly();

// Lembretes da partida (véspera e última hora). De hora em hora porque é a
// granularidade do aviso curto; cada partida guarda o que já foi enviado.
//
// Só em horário de gente acordada: este é o aviso que vai para todo mundo
// de uma vez, e push às 3h da manhã não lembra ninguém de nada — ensina a
// desligar a notificação. O que vence de madrugada sai na primeira rodada
// da manhã, porque o disparo é decidido por "já passou do prazo e ainda
// não avisei", não por bater o minuto exato.
Schedule::command('games:remind')->hourly()->between('07:00', '22:00');

// O único fim de SOS que não avisava ninguém: o prazo passando.
Schedule::command('sos:notify-expired')->hourly();

// Uma vez por dia, e uma vez por dívida. Pagamento de pelada é combinado
// entre pessoas — o app lembra, não cobra.
Schedule::command('payments:remind')->dailyAt('10:00');
