<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Diz se o que roda fora do request está mesmo rodando.
 *
 * Metade do domínio deste app não acontece durante uma requisição: as
 * notificações são `ShouldQueue` e esperam um `queue:work`, e finalizar
 * partida, lembrar da pelada e avisar SOS vencido esperam um `schedule:run`.
 * O problema nunca foi esses processos caírem — é que, quando caem, **nada
 * dá erro**. O convite some numa fila que ninguém consome, a partida fica
 * aberta para sempre, e a presença de todo mundo simplesmente para de
 * acumular. O app continua respondendo 200 em toda página.
 *
 * Por isso este comando existe e por isso ele devolve código de saída: é o
 * que um monitorador consegue perguntar de fora, e é o que transforma um
 * silêncio em alarme.
 */
class HealthCheck extends Command
{
    protected $signature = 'app:health {--json : Devolve o relatório em JSON, para monitoramento}';

    protected $description = 'Verifica se a fila e o agendador estão rodando';

    /** Onde o agendador carimba que passou por aqui. Ver routes/console.php. */
    public const HEARTBEAT = 'scheduler:last-run';

    /**
     * Tolerância antes de chamar de parado.
     *
     * O agendador roda de minuto em minuto e a fila é consumida em segundos,
     * então cinco minutos é folga larga para um pico de carga ou um deploy —
     * e curta o bastante para o aviso chegar no mesmo dia em que quebrou.
     */
    private const STALE_MINUTES = 5;

    public function handle(): int
    {
        $checks = [
            $this->scheduler(),
            $this->queue(),
            $this->failedJobs(),
        ];

        $failing = array_filter($checks, fn (array $check) => $check['status'] === 'falha');

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => $failing === [],
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $failing === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['', 'O quê', 'Situação'],
            array_map(fn (array $check) => [
                match ($check['status']) {
                    'ok' => '<fg=green>OK</>',
                    'falha' => '<fg=red>FALHA</>',
                    default => '<fg=gray>--</>',
                },
                $check['name'],
                $check['detail'],
            ], $checks)
        );

        if ($failing !== []) {
            $this->newLine();
            $this->error('Algo que deveria estar rodando não está. Veja docs/deploy.md.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * O agendador carimba a hora a cada minuto. Carimbo velho — ou nenhum
     * carimbo — quer dizer que ninguém está chamando `schedule:run`.
     *
     * @return array{name: string, status: string, detail: string}
     */
    private function scheduler(): array
    {
        $last = Cache::get(self::HEARTBEAT);

        if (! $last) {
            return $this->result('Agendador (schedule:run)', 'falha', 'nunca rodou neste ambiente');
        }

        $at = Carbon::parse($last);
        $minutes = $at->diffInMinutes(now());

        return $this->result(
            'Agendador (schedule:run)',
            $minutes > self::STALE_MINUTES ? 'falha' : 'ok',
            'último sinal '.$at->diffForHumans(),
        );
    }

    /**
     * Fila parada é convite que não chega. O que importa não é o tamanho da
     * fila — um pico é normal — mas a idade do trabalho mais antigo que já
     * poderia ter saído: se ninguém o pegou em cinco minutos, ninguém está
     * pegando nada.
     *
     * @return array{name: string, status: string, detail: string}
     */
    private function queue(): array
    {
        if (config('queue.default') === 'sync') {
            return $this->result('Fila (queue:work)', 'n/a', 'QUEUE_CONNECTION=sync: tudo roda no próprio request');
        }

        if (config('queue.default') !== 'database') {
            return $this->result('Fila (queue:work)', 'n/a', 'conexão "'.config('queue.default').'": verifique pelo painel dela');
        }

        $pending = DB::table('jobs')->where('available_at', '<=', now()->getTimestamp());
        $count = (clone $pending)->count();

        if ($count === 0) {
            return $this->result('Fila (queue:work)', 'ok', 'nada esperando');
        }

        $oldest = Carbon::createFromTimestamp((int) (clone $pending)->min('available_at'));
        $minutes = $oldest->diffInMinutes(now());

        return $this->result(
            'Fila (queue:work)',
            $minutes > self::STALE_MINUTES ? 'falha' : 'ok',
            $count.' na fila, mais antigo de '.$oldest->diffForHumans(),
        );
    }

    /**
     * Trabalho que falhou não some sozinho, e cada um é uma notificação que
     * alguém deveria ter recebido.
     *
     * @return array{name: string, status: string, detail: string}
     */
    private function failedJobs(): array
    {
        $count = DB::table('failed_jobs')->count();

        return $this->result(
            'Trabalhos com falha',
            $count === 0 ? 'ok' : 'falha',
            $count === 0 ? 'nenhum' : $count.' acumulados (php artisan queue:failed)',
        );
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function result(string $name, string $status, string $detail): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }
}
