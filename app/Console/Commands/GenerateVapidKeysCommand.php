<?php

namespace App\Console\Commands;

use App\Services\WebPush\Vapid;
use Illuminate\Console\Command;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Gera um par de chaves VAPID para as notificações push';

    public function handle(): int
    {
        $keys = Vapid::generateKeys();

        $this->components->info('Chaves VAPID geradas. Copie as linhas abaixo para o seu .env:');
        $this->newLine();
        $this->line('VAPID_SUBJECT="mailto:contato@'.parse_url((string) config('app.url'), PHP_URL_HOST).'"');
        $this->line('VAPID_PUBLIC_KEY='.$keys['public']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['private']);
        $this->newLine();
        $this->components->warn('Guarde a chave privada em segredo. Trocar as chaves invalida todas as inscrições existentes.');

        return self::SUCCESS;
    }
}
