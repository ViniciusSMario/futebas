<?php

namespace App\Support;

/**
 * O que cada `->with('status', ...)` diz na tela.
 *
 * Antes isto era uma escada de `@if (session('status') === '...')` repetida
 * em dezessete views — quarenta e uma comparações de string à mão, cada
 * mensagem nova exigindo mexer em dois arquivos que não se conhecem. Duas
 * coisas já tinham escorregado por essa fresta: `notifications-read`, que
 * o controller emitia e nenhuma view mostrava, e `sos-invite-sent`, um
 * ramo que nenhuma rota alcançava mais.
 *
 * Junto veio o problema que se via na tela: a mensagem era um `<p>` no meio
 * do conteúdo. Quem marcava um pagamento no fim de uma lista longa voltava
 * com "Pagamento atualizado" escrito lá em cima, fora da tela.
 *
 * Aqui é só o texto e o tom; quem desenha é `<x-flash>`, e quem decide
 * *quando* continua sendo o controller.
 */
class Flash
{
    public const SUCCESS = 'success';

    public const INFO = 'info';

    public const WARNING = 'warning';

    /**
     * Status que existem e deliberadamente **não** viram toast.
     *
     * `verification-link-sent` é aviso de campo, não de página: ele aparece
     * colado ao endereço de e-mail em "Minha Conta", que é onde a pessoa
     * acabou de pedir o reenvio, e a mesma chave serve à tela de verificação,
     * que nem passa por este layout. Vira toast e ele aparece duas vezes.
     *
     * Está escrito aqui, e não no teste, porque é decisão de produto e não
     * detalhe de teste — e porque a guarda de `FlashMessageTest` precisa
     * saber a diferença entre "de propósito" e "esquecido".
     *
     * @var array<int, string>
     */
    public const HANDLED_IN_PLACE = ['verification-link-sent'];

    /**
     * Mensagens de página: o retorno de uma ação que acabou de acontecer.
     *
     * Fora daqui ficam também as frases do fluxo de senha, que o Laravel
     * entrega já traduzidas por extenso — não são chaves, são frases, e
     * continuam com `<x-auth-session-status>` nas telas de autenticação.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MESSAGES = [
        // Partidas
        'game-created' => ['Partida criada. Agora é chamar o pessoal.', self::SUCCESS],
        'game-updated' => ['Partida atualizada. Quem já estava dentro foi avisado.', self::SUCCESS],
        'game-cancelled' => ['Partida cancelada.', self::INFO],
        'game-finished' => ['Partida finalizada. Agora dá para avaliar quem jogou.', self::SUCCESS],
        'teams-drawn' => ['Times sorteados.', self::SUCCESS],

        // Participantes
        'player-added' => ['Jogador adicionado à partida.', self::SUCCESS],
        'player-confirmed' => ['Jogador confirmado.', self::SUCCESS],
        'player-removed' => ['Jogador removido da partida.', self::INFO],
        'payment-updated' => ['Pagamento atualizado.', self::SUCCESS],
        'no-show-updated' => ['Presença do jogador atualizada.', self::SUCCESS],
        'checked-in' => ['Presença confirmada. Bom jogo!', self::SUCCESS],
        'check-in-undone' => ['Presença desfeita.', self::INFO],
        'left-game' => ['Sua participação foi cancelada.', self::INFO],
        'already-requested' => ['Você já pediu para entrar nessa partida. Acompanhe aqui embaixo.', self::WARNING],

        // Entrada pelo link público
        'joined-game' => ['Pronto, você está na partida!', self::SUCCESS],
        'joined-confirmed' => ['Pronto, você está na partida!', self::SUCCESS],
        'joined-pending' => ['Pedido enviado. O organizador precisa aprovar sua entrada.', self::INFO],
        'joined-waiting-list' => ['Partida lotada — você entrou na lista de espera.', self::INFO],

        // Convites
        'invitation-sent' => ['Convite enviado.', self::SUCCESS],
        'invitation-accepted' => ['Convite aceito. Sua vaga está garantida.', self::SUCCESS],
        'invitation-declined' => ['Convite recusado.', self::INFO],

        // Peladas semanais
        'series-created' => ['Pelada semanal criada. As próximas partidas já estão no calendário.', self::SUCCESS],
        'series-ended' => ['Pelada semanal encerrada. As partidas já marcadas continuam valendo.', self::INFO],
        'member-added' => ['Mensalista adicionado.', self::SUCCESS],
        'member-removed' => ['Mensalista removido.', self::INFO],

        // SOS Goleiro
        'sos-published' => ['SOS publicado. Avisamos os goleiros da região.', self::SUCCESS],
        'sos-accepted' => ['Goleiro confirmado na partida. Os outros candidatos foram avisados.', self::SUCCESS],
        'sos-rejected' => ['Candidatura recusada.', self::INFO],
        'sos-cancelled' => ['Chamada cancelada.', self::INFO],
        'sos-applied' => ['Candidatura enviada. Avisamos você da decisão do organizador.', self::SUCCESS],
        'sos-withdrawn' => ['Você saiu da disputa.', self::INFO],

        // Avaliações
        'rating-saved' => ['Avaliação salva.', self::SUCCESS],
        'already-rated' => ['Você já avaliou esse jogador nessa partida.', self::WARNING],

        // Conta e plano
        'profile-updated' => ['Perfil atualizado.', self::SUCCESS],
        'password-updated' => ['Senha alterada.', self::SUCCESS],
        'player-profile-updated' => ['Perfil de jogador atualizado.', self::SUCCESS],
        'availability-updated' => ['Disponibilidade atualizada.', self::SUCCESS],
        'notifications-read' => ['Notificações marcadas como lidas.', self::SUCCESS],
        'subscription-processing' => ['Estamos confirmando seu pagamento. Seu plano é liberado assim que ele cair.', self::INFO],
        'subscription-simulated' => ['Plano trocado no modo de teste.', self::INFO],
    ];

    /**
     * O texto e o tom de um status, ou null quando a chave não é nossa —
     * caso das frases já traduzidas do fluxo de senha, que passam pela
     * mesma sessão e não devem virar toast.
     *
     * @return array{text: string, tone: string}|null
     */
    public static function resolve(?string $status): ?array
    {
        if ($status === null || ! isset(self::MESSAGES[$status])) {
            return null;
        }

        [$text, $tone] = self::MESSAGES[$status];

        return ['text' => __($text), 'tone' => $tone];
    }

    /**
     * As chaves conhecidas. Existe para o teste poder cobrar que todo
     * status emitido por um controller tenha texto aqui.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::MESSAGES);
    }
}
