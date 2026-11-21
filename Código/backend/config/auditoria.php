<?php
/**
 * MecOn - helper de log de auditoria
 * Ver migrations/013_log_auditoria.sql.
 */

/**
 * Registra uma ação administrativa no log de auditoria. Chamado a partir
 * de endpoints que já exigiram exigirAdmin() antes (assume que $idAdmin
 * é confiável). Falhas ao gravar o log não devem derrubar a ação
 * principal — por isso o try/catch silencioso (loga um erro de PHP, mas
 * não interrompe a resposta ao usuário).
 *
 * $detalhesCampos (opcional) registra o histórico de alterações campo a
 * campo — ver migrations/016_log_auditoria_detalhe.sql. Formato:
 *   ['nome_do_campo' => ['anterior' => valorAntigo, 'novo' => valorNovo], ...]
 * Em uma exclusão, passe o registro inteiro com 'novo' => null em cada
 * campo (ver registrarSnapshotExclusao() logo abaixo, que já monta isso).
 * Retorna o id_log gerado (ou null em caso de falha), caso o chamador
 * precise reaproveitá-lo.
 */
function registrarLogAuditoria(PDO $pdo, int $idAdmin, string $acao, string $entidade, ?int $idEntidade = null, ?string $detalhes = null, ?array $detalhesCampos = null): ?int
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO log_auditoria (tipo_ator, id_ator, acao, entidade, id_entidade, detalhes)
            VALUES (\'admin\', :id_ator, :acao, :entidade, :id_entidade, :detalhes)
        ');
        $stmt->execute([
            'id_ator'     => $idAdmin,
            'acao'        => $acao,
            'entidade'    => $entidade,
            'id_entidade' => $idEntidade,
            'detalhes'    => $detalhes,
        ]);
        $idLog = (int) $pdo->lastInsertId();
        if (!empty($detalhesCampos)) {
            $stmtDetalhe = $pdo->prepare('
                INSERT INTO log_auditoria_detalhe (id_log, campo, valor_anterior, valor_novo)
                VALUES (:id_log, :campo, :valor_anterior, :valor_novo)
            ');
            foreach ($detalhesCampos as $campo => $valores) {
                $anterior = $valores['anterior'] ?? null;
                $novo     = $valores['novo'] ?? null;
                // Ignora campos que não mudaram (evita ruído no histórico)
                if ($anterior === $novo) {
                    continue;
                }
                $stmtDetalhe->execute([
                    'id_log'         => $idLog,
                    'campo'          => $campo,
                    'valor_anterior' => $anterior !== null ? (string) $anterior : null,
                    'valor_novo'     => $novo !== null ? (string) $novo : null,
                ]);
            }
        }
        return $idLog;
    } catch (Exception $e) {
        error_log('Falha ao registrar log de auditoria: ' . $e->getMessage());
        return null;
    }
}

/**
 * Monta o array de $detalhesCampos para uma exclusão: cada coluna do
 * registro apagado vira uma linha com valor_novo = null. $registro é o
 * array associativo retornado por um fetch() antes do DELETE.
 * Colunas em $ignorar (ex.: senha_hash) não entram no histórico.
 */
function montarDetalhesExclusao(array $registro, array $ignorar = ['senha_hash']): array
{
    $detalhes = [];
    foreach ($registro as $campo => $valor) {
        if (in_array($campo, $ignorar, true)) {
            continue;
        }
        $detalhes[$campo] = ['anterior' => $valor, 'novo' => null];
    }
    return $detalhes;
}
