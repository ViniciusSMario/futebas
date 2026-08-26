#  Futebas

**Organize sua pelada sem depender do grupo do WhatsApp.**

Futebas resolve o ciclo inteiro de uma partida de futebol amador: criar o jogo, achar
jogadores, confirmar presença, sortear os times, controlar quem pagou e avaliar quem
jogou. Feito para o futebol de bairro brasileiro — society, futsal e campo.

---

## O que o app faz

### Para quem organiza

| | |
|---|---|
| **Partidas** | Cria o jogo, define vagas, valor e se aprova cada entrada. Link público para compartilhar no WhatsApp — quem recebe entra até sem ter conta. |
| **Peladas semanais** | Cadastra a pelada de toda quinta uma vez. As partidas das próximas semanas passam a ser criadas sozinhas, com os mensalistas já confirmados. |
| **Elenco** | Busca jogadores por posição, nível, cidade, preço e disponibilidade — e ordena por reputação ou por taxa de presença. |
| **Presença** | No dia do jogo vê quem confirmou, quem não confirmou, e marca quem faltou. |
| **Financeiro** | Acompanha o previsto, o recebido e o pendente de cada partida. |
| **SOS Goleiro** | Goleiro furou? Publica uma chamada paga; os goleiros da região se candidatam com o preço deles e você escolhe comparando valor, cidade e avaliações. |
| **Avaliações** | Depois do jogo, avalia pontualidade, comportamento e desempenho de cada um. |

### Para quem joga

| | |
|---|---|
| **Procurar partidas** | Encontra peladas abertas por cidade, modalidade, data, valor e vagas. |
| **Convites** | Recebe, aceita ou recusa convites de organizadores. |
| **Check-in** | No dia do jogo, confirma presença com um toque. |
| **Reputação** | Acumula avaliações, histórico de partidas e taxa de presença — o que faz organizador chamar de novo. |
| **SOS Goleiro** | Se é goleiro, recebe as chamadas de última hora da região e responde com o próprio preço. |

O app é um **PWA instalável** e manda **notificações push** no celular.

---

## Stack

- **Laravel 13** · PHP 8.4
- **Blade + Tailwind + Alpine.js** (server-rendered, sem SPA) via **Vite**
- **MySQL** em desenvolvimento · **SQLite em memória** nos testes
- **Web Push próprio** — RFC 8291 e RFC 8292 implementados em `app/Services/WebPush/`,
  sem dependência externa
- **Pest/PHPUnit** — 376 testes

---

## Rodando o projeto

**Pré-requisitos:** PHP 8.4, Composer, Node 20+, MySQL.

```bash
# 1. Instala dependências, copia o .env, gera a APP_KEY, migra e builda os assets
composer run setup

# 2. Popula o banco com a base de demonstração (ver seção abaixo)
php artisan migrate:fresh --seed

# 3. Sobe tudo: servidor, fila, logs e Vite, num terminal só
composer run dev
```

O app fica em **http://localhost:8000**.

> **Notificações push** são opcionais. Sem as chaves VAPID no `.env` o app funciona
> normalmente — as notificações ficam só na caixa de entrada, sem o alerta no celular.
> Para ligar: `php artisan webpush:vapid` e cole o par no `.env`.

---

## Base de demonstração

`php artisan migrate:fresh --seed` monta uma Teresina povoada: 16 jogadores com
avaliações e histórico de presença reais, seis semanas de partidas já jogadas, uma
partida encerrada esperando avaliação, um jogo hoje com o check-in aberto, peladas
futuras (uma lotada, para ver a lista de espera), uma pelada semanal com mensalistas e
um SOS com três goleiros disputando a vaga.

**Contas** — senha `password` em todas:

| E-mail | Papel |
|---|---|
| `organizador@futebas.test` | Organizador (dono da Pelada de Quinta) |
| `jogador@futebas.test` | Jogador de linha, com check-in pendente hoje |
| `goleiro@futebas.test` | Goleiro, com uma chamada de SOS para responder |

---

## Comandos

```bash
composer test                  # roda a suíte inteira
php artisan test --filter=Nome # um arquivo ou método

vendor/bin/pint                # formata o código
vendor/bin/pint --test         # só confere

npm run dev                    # Vite em watch
npm run build                  # build de produção

php artisan migrate            # aplica migrations
php artisan series:generate    # gera as próximas partidas das peladas semanais
php artisan webpush:vapid      # gera um par de chaves VAPID
```

O banco de desenvolvimento é MySQL (`foot_interior_db`); os testes rodam em SQLite na
memória, então **nunca tocam no banco de desenvolvimento**.

---

## Como o código está organizado

O detalhamento das decisões de arquitetura está em [`CLAUDE.md`](CLAUDE.md). Em resumo:

- **Dois papéis** — `player` e `organizer` — separados por middleware nas rotas, não por
  policies.
- **Entrar numa partida passa sempre por `GamePlayerService`**, seja por convite, link
  público, pelada semanal, SOS ou adição manual. É o que mantém capacidade, lista de
  espera e aprovação consistentes. Liberar vaga chama `promoteFromWaitingList()`.
- **Participante pode ser um usuário ou um convidado sem conta** (`GuestPlayer`) — metade
  de uma pelada real não tem app instalado.
- **Notificações saem por dois canais**: o registro no banco é o confiável, o push é a
  interrupção e pode falhar em silêncio.
- **Reputação é denormalizada e recalculada**, nunca escrita à mão: médias de avaliação e
  histórico de presença vivem em `PlayerProfile`.

Os testes ficam em `tests/Feature/`, organizados por capacidade do usuário
(`GameSearchTest`, `GameCheckInTest`, `SosApplicationTest`…), não por controller.
