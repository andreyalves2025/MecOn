<?php
/**
 * MecOn - cálculo da reputação da oficina (indicadores além das estrelas)
 * Usado por oficinas/get_oficina.php (página pública) e
 * oficina_auth/get_desempenho_oficina.php (painel da própria oficina).
 *
 * Definições (todas baseadas só em dados reais de agendamentos):
 *  - servicos_concluidos: agendamentos com status "concluido".
 *  - taxa_conclusao: concluídos / (concluídos + cancelados APÓS o aceite).
 *    Mede, dos serviços que a oficina aceitou e que já tiveram desfecho,
 *    quantos de fato foram realizados. Aceitos ainda em andamento ficam
 *    de fora (ainda não têm desfecho).
 *  - taxa_cancelamento: o complemento da anterior (cancelados após aceite
 *    / aceitos com desfecho). Um "cancelado após aceite" é um cancelamento
 *    com respondido_em preenchido (cancelar só é possível enquanto o
 *    agendamento está pendente ou aceito; se a oficina já respondeu, só
 *    pode ter sido aceito). Cancelamentos antes da resposta da oficina NÃO
 *    contam contra ela.
 *  - taxa_aceite: aceitos / (aceitos + recusados), entre solicitações
 *    que a oficina respondeu.
 *  - tempo_medio_resposta_horas: média de (respondido_em - criado_em).
 *    Agendamentos anteriores à migração 020 não têm respondido_em e ficam
 *    fora desta média.
 *  - clientes_recorrentes_pct: % dos clientes com serviço concluído que
 *    voltaram e concluíram 2+ serviços. É um comportamento observado, não
 *    uma intenção declarada ("voltaria?") — não há essa pergunta no sistema.
 *
 * Com amostra pequena, percentuais e médias viram null (o frontend mostra
 * "dados insuficientes") em vez de exibir, por exemplo, "100%" baseado em
 * um único serviço.
 */

const REPUTACAO_AMOSTRA_MINIMA = 3;

function calcularReputacaoOficina(PDO $pdo, int $idOficina): array
{
    $stmt = $pdo->prepare("
        SELECT
            SUM(status = 'concluido')                                          AS concluidos,
            SUM(status = 'cancelado' AND respondido_em IS NOT NULL)            AS cancelados_apos_aceite,
            SUM(status = 'recusado')                                           AS recusados,
            SUM(status IN ('aceito', 'concluido')
                OR (status = 'cancelado' AND respondido_em IS NOT NULL))       AS aceitos,
            COUNT(respondido_em)                                               AS respondidos,
            AVG(CASE WHEN respondido_em IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, criado_em, respondido_em) END) AS media_minutos
        FROM agendamentos
        WHERE id_oficina = :id
    ");
    $stmt->execute(['id' => $idOficina]);
    $r = $stmt->fetch();

    $concluidos   = (int) $r['concluidos'];
    $canceladosAA = (int) $r['cancelados_apos_aceite'];
    $recusados    = (int) $r['recusados'];
    $aceitos      = (int) $r['aceitos'];
    $respondidos  = (int) $r['respondidos'];

    $comDesfecho      = $concluidos + $canceladosAA;
    $totalRespondidas = $aceitos + $recusados;

    $taxaConclusao = $comDesfecho >= REPUTACAO_AMOSTRA_MINIMA
        ? round($concluidos / $comDesfecho * 100, 1) : null;
    $taxaCancelamento = $comDesfecho >= REPUTACAO_AMOSTRA_MINIMA
        ? round($canceladosAA / $comDesfecho * 100, 1) : null;
    $taxaAceite = $totalRespondidas >= REPUTACAO_AMOSTRA_MINIMA
        ? round($aceitos / $totalRespondidas * 100, 1) : null;
    $tempoMedio = ($respondidos >= REPUTACAO_AMOSTRA_MINIMA && $r['media_minutos'] !== null)
        ? round((float) $r['media_minutos'] / 60, 1) : null;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS clientes, COALESCE(SUM(n >= 2), 0) AS recorrentes
        FROM (
            SELECT id_usuario, COUNT(*) AS n
            FROM agendamentos
            WHERE id_oficina = :id AND status = 'concluido'
            GROUP BY id_usuario
        ) por_cliente
    ");
    $stmt->execute(['id' => $idOficina]);
    $c = $stmt->fetch();
    $clientes    = (int) $c['clientes'];
    $recorrentes = (int) $c['recorrentes'];
    $recorrentesPct = $clientes >= REPUTACAO_AMOSTRA_MINIMA
        ? round($recorrentes / $clientes * 100, 1) : null;

    return [
        'servicos_concluidos'       => $concluidos,
        'taxa_conclusao'            => $taxaConclusao,
        'taxa_cancelamento'         => $taxaCancelamento,
        'taxa_aceite'               => $taxaAceite,
        'tempo_medio_resposta_horas' => $tempoMedio,
        'clientes_recorrentes_pct'  => $recorrentesPct,
        'amostra' => [
            'minima'              => REPUTACAO_AMOSTRA_MINIMA,
            'com_desfecho'        => $comDesfecho,
            'solicitacoes_respondidas' => $totalRespondidas,
            'clientes_atendidos'  => $clientes,
        ],
    ];
}
