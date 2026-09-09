<?php

namespace Tests\Feature;

use App\Console\Commands\HealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * O comando que existe para o silêncio virar alarme.
 *
 * Cada teste aqui pinta uma situação em que o app responde 200 em toda
 * página e mesmo assim não está entregando o que promete — e cobra que o
 * comando saia com código de erro, porque é esse código que um monitorador
 * enxerga de fora.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /** Testes rodam com QUEUE_CONNECTION=sync; aqui interessa a de verdade. */
    private function usingDatabaseQueue(): void
    {
        config()->set('queue.default', 'database');
    }

    private function schedulerJustRan(): void
    {
        Cache::forever(HealthCheck::HEARTBEAT, now()->toIso8601String());
    }

    private function queueJob(int $minutesAgo): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->subMinutes($minutesAgo)->getTimestamp(),
            'created_at' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    public function test_everything_running_passes(): void
    {
        $this->usingDatabaseQueue();
        $this->schedulerJustRan();

        $this->artisan('app:health')->assertSuccessful();
    }

    public function test_a_scheduler_that_never_ran_fails(): void
    {
        $this->usingDatabaseQueue();

        // Nada carimbou o batimento: ninguém está chamando schedule:run, e
        // partida nenhuma vai encerrar sozinha.
        $this->artisan('app:health')->assertFailed();
    }

    public function test_a_scheduler_that_stopped_hours_ago_fails(): void
    {
        $this->usingDatabaseQueue();
        Cache::forever(HealthCheck::HEARTBEAT, now()->subHours(3)->toIso8601String());

        $this->artisan('app:health')->assertFailed();
    }

    public function test_a_queue_nobody_is_consuming_fails(): void
    {
        $this->usingDatabaseQueue();
        $this->schedulerJustRan();

        // Um convite esperando há meia hora: o worker caiu.
        $this->queueJob(minutesAgo: 30);

        $this->artisan('app:health')->assertFailed();
    }

    public function test_a_brief_backlog_is_not_treated_as_a_dead_worker(): void
    {
        $this->usingDatabaseQueue();
        $this->schedulerJustRan();

        // Fila com trabalho recente é fila viva num pico, não fila parada.
        $this->queueJob(minutesAgo: 1);

        $this->artisan('app:health')->assertSuccessful();
    }

    public function test_a_job_scheduled_for_later_is_not_a_backlog(): void
    {
        $this->usingDatabaseQueue();
        $this->schedulerJustRan();

        // Trabalho adiado ainda não podia ter saído: cobrar por ele seria
        // alarme falso toda vez que algo fosse agendado para o futuro.
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->addHour()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $this->artisan('app:health')->assertSuccessful();
    }

    public function test_failed_jobs_are_reported(): void
    {
        $this->usingDatabaseQueue();
        $this->schedulerJustRan();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'boom',
            'failed_at' => now(),
        ]);

        $this->artisan('app:health')->assertFailed();
    }

    public function test_the_sync_queue_needs_no_worker(): void
    {
        config()->set('queue.default', 'sync');
        $this->schedulerJustRan();

        // Sem fila de verdade não há worker a cobrar: tudo roda no request.
        $this->artisan('app:health')->assertSuccessful();
    }
}
