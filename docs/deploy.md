# Colocando o Futebas no ar

Este documento existe por um motivo específico: **metade do domínio deste app
não acontece durante uma requisição**, e quando a outra metade não está
rodando, nada dá erro. O site responde 200 em toda página, o organizador cria
a partida, o jogador entra — e o convite nunca chega, a partida nunca encerra,
a presença de ninguém acumula.

Servir o PHP não é colocar o app no ar. São **três** processos.

---

## O que precisa estar rodando

| Processo | Como | O que quebra sem ele |
|---|---|---|
| **PHP/web** | nginx + php-fpm, Apache, o que preferir | tudo, e isso é visível |
| **Worker da fila** | `queue:work` supervisionado | 18 das 19 notificações são `ShouldQueue`. Convite, aviso de SOS, lembrete de partida e cobrança param na tabela `jobs` e **não saem nunca** |
| **Agendador** | `schedule:run` a cada minuto | partida que ninguém encerrou fica aberta para sempre — e some da ficha de presença de todo mundo que jogou, que é a mesma presença pela qual a busca de jogadores ordena |

As duas últimas falham em silêncio. É por isso que existe o `app:health`, na
última seção.

---

## Opção A — systemd (VPS próprio)

Os arquivos estão em `deploy/systemd/`. Ajuste `/var/www/futebas`, o usuário e
o caminho do `php` antes de copiar.

```bash
sudo mkdir -p /var/log/futebas && sudo chown www-data:www-data /var/log/futebas

sudo cp deploy/systemd/futebas-queue.service /etc/systemd/system/
sudo cp deploy/systemd/futebas-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/futebas-scheduler.timer /etc/systemd/system/

sudo systemctl daemon-reload
sudo systemctl enable --now futebas-queue
sudo systemctl enable --now futebas-scheduler.timer
```

Conferindo:

```bash
systemctl status futebas-queue
systemctl list-timers futebas-scheduler.timer
```

## Opção B — supervisor + cron (hospedagem gerenciada)

```bash
sudo cp deploy/supervisor/futebas-queue.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
```

E a linha de cron do agendador, no crontab do usuário que roda a aplicação
(`crontab -e`):

```cron
* * * * * cd /var/www/futebas && php artisan schedule:run >> /dev/null 2>&1
```

De minuto em minuto, sempre. Quem decide o que está na hora de rodar é o
próprio `schedule:run` — o cron só bate o ponto.

---

## Depois de todo deploy

```bash
php artisan migrate --force
php artisan queue:restart      # <- este é o que se esquece
```

`queue:work` carrega a aplicação **uma vez** e fica com ela na memória. Sem o
`queue:restart`, o worker continua executando o código antigo depois do
deploy — inclusive corrigindo bugs que você acabou de corrigir. O comando não
mata nada na força: ele avisa os workers para saírem ao terminar o trabalho
atual, e o supervisor/systemd os traz de volta já com o código novo.

---

## Saber que parou

```bash
php artisan app:health
```

```
+-------+--------------------------+----------------------------+
|       | O quê                    | Situação                   |
+-------+--------------------------+----------------------------+
| OK    | Agendador (schedule:run) | último sinal há 32 segundos|
| OK    | Fila (queue:work)        | nada esperando             |
| OK    | Trabalhos com falha      | nenhum                     |
+-------+--------------------------+----------------------------+
```

Sai com código **1** quando algo está parado, então serve direto para
monitoramento. Uma linha de cron que só fala quando há problema:

```cron
*/15 * * * * cd /var/www/futebas && php artisan app:health || mail -s "Futebas: algo parou" voce@exemplo.com
```

Há também `--json`, para quem tem monitorador de verdade.

O que ele olha:

- **Agendador** — `routes/console.php` carimba a hora a cada minuto. Carimbo
  velho, ou nenhum carimbo, quer dizer que ninguém está chamando
  `schedule:run`.
- **Fila** — não o tamanho, que oscila, mas a **idade do trabalho mais antigo
  que já poderia ter saído**. Se ninguém o pegou em cinco minutos, ninguém
  está pegando nada.
- **Trabalhos com falha** — cada linha em `failed_jobs` é uma notificação que
  alguém deveria ter recebido. `php artisan queue:failed` lista, e
  `queue:retry all` tenta de novo.

---

## Configuração que desliga recurso em silêncio

Estas são decisões do projeto, não bugs — o app funciona inteiro sem elas —,
mas vale saber o que está desligado:

| Ausente no `.env` | Efeito |
|---|---|
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | push desligado; a notificação ainda chega em `/notificacoes` |
| `STRIPE_SECRET` | cobrança desligada, a página de planos diz "em breve" e todo mundo fica no Free |

Gerar o par VAPID: `php artisan webpush:vapid`.

---

## Checklist da primeira subida

1. `.env` com `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` e banco
2. `php artisan key:generate` (se ainda não houver `APP_KEY`)
3. `php artisan migrate --force`
4. `npm ci && npm run build`
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
   — e `php artisan optimize:clear` antes de qualquer deploy seguinte
6. Worker e agendador no ar (opção A ou B acima)
7. `php artisan app:health` — tem de sair verde
8. `php artisan storage:link`, se as fotos de perfil forem usadas
