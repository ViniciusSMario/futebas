# Planos e assinatura

O app tem três planos — **Free**, **Pro** e **Clube**. O Free é o padrão de
quem nunca assinou e de quem deixou a assinatura vencer: nele o Futebas
funciona inteiro. Os pagos ampliam limites e liberam recursos; nenhum deles
destrava o básico.

## O catálogo

Tudo o que os planos prometem está em [`config/plans.php`](../config/plans.php),
e é de lá que sai tanto a regra aplicada quanto o texto da página de preços.
Não existe uma lista de vantagens escrita à mão em outro lugar para
desencontrar da regra.

| | Free | Pro | Clube |
|---|---|---|---|
| Preço/mês | Grátis | R$ 19,90 | R$ 79,90 |
| SOS Goleiro publicados | 1 | 10 | ilimitado |
| Candidaturas a SOS | 2 | ilimitado | ilimitado |
| Destaque na busca e no ranking | — | ✓ | ✓ |
| Filtro de cidades próximas | — | ✓ | ✓ |
| Múltiplos organizadores | — | — | ✓ |
| Relatórios do time | — | — | ✓ |
| Suporte prioritário | — | — | ✓ |

Os três últimos recursos do Clube existem hoje como **gate pronto**
(`Feature::MULTIPLE_ORGANIZERS`, `TEAM_REPORTS`, `PRIORITY_SUPPORT`) e
aparecem na comparação de planos, mas ainda não têm tela: quando forem
construídos, basta pendurar a rota no middleware `plan:{recurso}`.

### Mudar preços ou limites

Mexa só na config. Um limite `null` significa ilimitado, e um limite igual
ao do plano de baixo some da lista de vantagens em vez de ser repetido.
Depois rode `php artisan test --filter=PlanCatalog` — esse teste é o que
garante que a config e os enums continuam falando a mesma língua.

## Como o limite é contado

Não existe contador guardado. `PlanService::used()` conta as linhas de
verdade (SOS publicados por aquele organizador, candidaturas enviadas por
aquele goleiro) desde o começo do ciclo, que é:

- o **período da fatura**, para quem assina — quem paga no dia 10 zera no dia 10;
- o **mês do calendário**, para quem está no Free.

Detalhes que valem lembrar:

- Cancelar um SOS não devolve o limite: ele já avisou a região inteira.
- Revisar o valor pedido em uma candidatura **não** gasta uma segunda vaga.

## Ligar o Stripe

Sem `STRIPE_SECRET` a cobrança fica desligada: a página de planos vira
vitrine ("Em breve"), todo mundo continua no Free e nada quebra. Em
ambiente `local` e sem chaves, a mesma página deixa trocar de plano na mão
para dar para testar os limites.

Quando for ligar de verdade:

1. **Crie os produtos e preços** no painel do Stripe (recorrência mensal),
   um para o Pro e um para o Clube.
2. **Preencha o `.env`**:

   ```dotenv
   STRIPE_SECRET=sk_live_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   STRIPE_PRICE_PRO=price_...
   STRIPE_PRICE_CLUBE=price_...
   ```

3. **Cadastre o webhook** apontando para `https://SEU_DOMINIO/webhooks/stripe`
   e assine os eventos:

   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`

4. **Ative o portal de cobrança** (Settings → Billing → Customer portal), que
   é onde o usuário troca o cartão, vê faturas e cancela.
5. **Teste local** com o CLI do Stripe:

   ```bash
   stripe listen --forward-to localhost:8000/webhooks/stripe
   stripe trigger customer.subscription.created
   ```

Não há SDK a instalar: as três chamadas que o app faz (criar cliente, abrir
checkout, abrir portal) vão pela API REST em
[`StripeBillingGateway`](../app/Services/Billing/StripeBillingGateway.php).

## O que libera o plano

Sempre o **webhook** — nunca o navegador voltando do checkout, que qualquer
pessoa consegue simular digitando a URL. Por isso `/planos/obrigado` só diz
"estamos confirmando": o plano aparece quando o Stripe confirma.

O webhook é a única rota que muda plano sem ninguém logado, então ele
confere a assinatura HMAC do corpo (com janela de tempo) antes de olhar o
conteúdo. Evento sem assinatura válida é descartado com 403; evento que o
app não usa recebe 200 e é ignorado, para o Stripe não ficar reenviando.

## Quem responde "qual é o meu plano?"

`User::currentPlan()`, que pergunta à assinatura — a única que conhece as
datas. A coluna `users.plan` é **cópia**, existe porque a busca de jogadores
ordena por ela (o destaque do Pro é um `order by`) e é refeita a cada
gravação de assinatura. `php artisan plans:sync` conserta o único caso que
nada grava: uma assinatura que venceu pela passagem do tempo.
