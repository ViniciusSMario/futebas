<?php

namespace App\Enums;

/**
 * Tudo que um plano pode ampliar, em uma lista só.
 *
 * São dois tipos de recurso, e a diferença entre eles é o que vale a pena
 * ter em mente: os **contáveis** (`isQuota()`) têm um teto mensal por plano
 * e o app conta o uso na fonte; os **booleanos** simplesmente existem ou
 * não para aquele plano.
 *
 * Os textos moram aqui, e não na config, porque a página de preços e a
 * mensagem de "acabou seu limite" precisam descrever o mesmo recurso do
 * mesmo jeito — a única diferença entre elas é o número que vem do plano.
 */
enum Feature: string
{
    /** SOS Goleiro publicados no mês (lado do organizador). */
    case SOS_REQUESTS = 'sos_requests';

    /** Candidaturas a SOS enviadas no mês (lado do goleiro). */
    case SOS_APPLICATIONS = 'sos_applications';

    /** Aparecer antes dos demais na busca de jogadores. */
    case SEARCH_HIGHLIGHT = 'search_highlight';

    /** Buscar também em cidades vizinhas, não só na cidade exata. */
    case NEARBY_CITIES = 'nearby_cities';

    /** Mais de um organizador tocando as mesmas partidas. */
    case MULTIPLE_ORGANIZERS = 'multiple_organizers';

    /** Relatórios de presença, pagamento e desempenho do time. */
    case TEAM_REPORTS = 'team_reports';

    /** Atendimento na frente da fila. */
    case PRIORITY_SUPPORT = 'priority_support';

    /**
     * Recursos com teto mensal, contados a partir das próprias tabelas de
     * origem — nunca de um contador guardado à parte, que dessincroniza.
     */
    public function isQuota(): bool
    {
        return in_array($this, [self::SOS_REQUESTS, self::SOS_APPLICATIONS], true);
    }

    /**
     * Nome curto do recurso, do jeito que o usuário o conhece.
     */
    public function label(): string
    {
        return match ($this) {
            self::SOS_REQUESTS => __('SOS Goleiro'),
            self::SOS_APPLICATIONS => __('Candidaturas a SOS'),
            self::SEARCH_HIGHLIGHT => __('Destaque na busca e no ranking'),
            self::NEARBY_CITIES => __('Filtro de cidades próximas'),
            self::MULTIPLE_ORGANIZERS => __('Múltiplos organizadores'),
            self::TEAM_REPORTS => __('Relatórios do time'),
            self::PRIORITY_SUPPORT => __('Suporte prioritário'),
        };
    }

    /**
     * A linha que a página de preços mostra para este recurso no plano que
     * tem o limite `$limit` (`null` = ilimitado).
     */
    public function describeLimit(?int $limit): string
    {
        if ($limit === null) {
            return match ($this) {
                self::SOS_REQUESTS => __('SOS Goleiro ilimitado'),
                self::SOS_APPLICATIONS => __('Candidaturas ilimitadas'),
                default => $this->label(),
            };
        }

        return match ($this) {
            self::SOS_REQUESTS => trans_choice(':count SOS Goleiro por mês|:count SOS Goleiro por mês', $limit, ['count' => $limit]),
            self::SOS_APPLICATIONS => trans_choice('Candidatura a :count vaga por mês|Candidatura a :count vagas por mês', $limit, ['count' => $limit]),
            default => $this->label(),
        };
    }

    /**
     * O que dizer a quem bateu no teto. Fala do que a pessoa estava
     * tentando fazer, não do contador.
     */
    public function exhaustedMessage(int $limit): string
    {
        return match ($this) {
            self::SOS_REQUESTS => trans_choice(
                'Você já publicou o :count SOS Goleiro do seu plano neste mês.|Você já publicou os :count SOS Goleiro do seu plano neste mês.',
                $limit,
                ['count' => $limit],
            ),
            self::SOS_APPLICATIONS => trans_choice(
                'Você já usou a :count candidatura do seu plano neste mês.|Você já usou as :count candidaturas do seu plano neste mês.',
                $limit,
                ['count' => $limit],
            ),
            default => __('Este recurso não está disponível no seu plano.'),
        };
    }
}
