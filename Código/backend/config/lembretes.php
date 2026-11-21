<?php
require_once __DIR__ . '/placa.php';
/**
 * MecOn - cálculo dos lembretes de manutenção preventiva do usuário
 * Usado por historico/lembretes_manutencao.php (o próprio endpoint de
 * lembretes) e por promocoes/ofertas_personalizadas.php (que cruza os
 * lembretes com as promoções ativas).
 *
 * Regras completas (tempo + quilometragem, limiares de 80% e 100%,
 * limitações): ver o comentário de historico/lembretes_manutencao.php.
 */

const LEMBRETE_LIMIAR_PROXIMO = 0.8;

/**
 * Retorna a lista de lembretes (só o que precisa de atenção), do mais
 * adiantado para o menos. Cada item tem id_veiculo, placa, id_servico,
 * nome_servico, progresso_tempo_pct, progresso_km_pct, status
 * ("proximo"|"atrasado"), motivo ("tempo"|"km"|"ambos") e os dados de
 * apoio (meses_desde, km_rodados, intervalos...).
 */
function calcularLembretesUsuario(PDO $pdo, int $idUsuario): array
{
    $sql = "
        SELECT
            v.id_veiculo,
            v.placa,
            v.km_atual,
            s.id_servico,
            s.nome AS nome_servico,
            s.intervalo_recomendado_meses,
            s.intervalo_recomendado_km,
            h.data_servico AS data_ultimo_servico,
            h.km_servico   AS km_ultimo_servico,
            TIMESTAMPDIFF(MONTH, h.data_servico, CURDATE()) AS meses_desde
        FROM historico_manutencoes h
        JOIN veiculos v ON v.id_veiculo = h.id_veiculo
        JOIN servicos s ON s.id_servico = h.id_servico
        WHERE v.id_usuario = :id_usuario
          AND (s.intervalo_recomendado_meses IS NOT NULL OR s.intervalo_recomendado_km IS NOT NULL)
          AND h.id_historico = (
              SELECT h2.id_historico
              FROM historico_manutencoes h2
              WHERE h2.id_veiculo = h.id_veiculo AND h2.id_servico = h.id_servico
              ORDER BY h2.data_servico DESC, h2.id_historico DESC
              LIMIT 1
          )
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_usuario' => $idUsuario]);

    $lembretes = [];
    foreach ($stmt->fetchAll() as $l) {
        $mesesDesde = (int) $l['meses_desde'];
        $intMeses   = $l['intervalo_recomendado_meses'] !== null ? (int) $l['intervalo_recomendado_meses'] : null;
        $intKm      = $l['intervalo_recomendado_km'] !== null ? (int) $l['intervalo_recomendado_km'] : null;
        $kmAtual    = $l['km_atual'] !== null ? (int) $l['km_atual'] : null;
        $kmUltimo   = $l['km_ultimo_servico'] !== null ? (int) $l['km_ultimo_servico'] : null;

        $progressoTempo = $intMeses !== null ? $mesesDesde / $intMeses : null;

        $kmRodados = null;
        $progressoKm = null;
        if ($intKm !== null && $kmAtual !== null && $kmUltimo !== null) {
            // max(0, ...): se o usuário digitou uma km atual menor que a do
            // serviço (dado inconsistente), não gera progresso negativo
            $kmRodados = max(0, $kmAtual - $kmUltimo);
            $progressoKm = $kmRodados / $intKm;
        }

        $maiorProgresso = max($progressoTempo ?? 0, $progressoKm ?? 0);
        if ($maiorProgresso < LEMBRETE_LIMIAR_PROXIMO) {
            continue; // dentro do prazo (ou sem dados suficientes para avaliar)
        }

        $status = $maiorProgresso >= 1 ? 'atrasado' : 'proximo';
        $tempoDisparou = $progressoTempo !== null && $progressoTempo >= LEMBRETE_LIMIAR_PROXIMO;
        $kmDisparou    = $progressoKm !== null && $progressoKm >= LEMBRETE_LIMIAR_PROXIMO;
        $motivo = $tempoDisparou && $kmDisparou ? 'ambos' : ($kmDisparou ? 'km' : 'tempo');

        $lembretes[] = [
            'id_veiculo'                  => (int) $l['id_veiculo'],
            'placa'                       => placaDecifrar($l['placa']),
            'id_servico'                  => (int) $l['id_servico'],
            'nome_servico'                => $l['nome_servico'],
            'data_ultimo_servico'         => $l['data_ultimo_servico'],
            'meses_desde'                 => $mesesDesde,
            'intervalo_recomendado_meses' => $intMeses,
            'km_ultimo_servico'           => $kmUltimo,
            'km_atual'                    => $kmAtual,
            'km_rodados'                  => $kmRodados,
            'intervalo_recomendado_km'    => $intKm,
            'progresso_tempo_pct'         => $progressoTempo !== null ? (int) round($progressoTempo * 100) : null,
            'progresso_km_pct'            => $progressoKm !== null ? (int) round($progressoKm * 100) : null,
            'status'                      => $status,
            'motivo'                      => $motivo,
            '_ordem'                      => $maiorProgresso,
        ];
    }
    // Mais adiantados primeiro
    usort($lembretes, function ($a, $b) {
        return $b['_ordem'] <=> $a['_ordem'];
    });
    foreach ($lembretes as &$l) {
        unset($l['_ordem']);
    }
    unset($l); // encerra a referência do foreach (evita efeito colateral em quem usa o retorno)
    return $lembretes;
}
