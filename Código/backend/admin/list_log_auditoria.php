<?php
/**
 * MecOn - GET /backend/admin/list_log_auditoria.php?pagina=1&por_pagina=20
 * Lista o log de auditoria, mais recente primeiro. Resposta paginada — ver
 * backend/config/paginacao.php. Requer login como administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/paginacao.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$pdo = getConnection();
$total = (int) $pdo->query('SELECT COUNT(*) AS total FROM log_auditoria')->fetch()['total'];
['pagina' => $pagina, 'por_pagina' => $porPagina, 'offset' => $offset] = obterParametrosPaginacao();
$sql = "
    SELECT l.id_log, l.tipo_ator, l.id_ator, l.acao, l.entidade, l.id_entidade, l.detalhes, l.criado_em,
           u.nome AS nome_ator
    FROM log_auditoria l
    LEFT JOIN usuarios u ON u.id_usuario = l.id_ator AND l.tipo_ator = 'admin'
    ORDER BY l.criado_em DESC
    LIMIT :limite OFFSET :offset
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Busca os detalhes campo a campo de todos os logs desta página numa só
// consulta (evita N+1 — uma query por log seria desnecessariamente caro)
if (!empty($logs)) {
    $idsLog = array_column($logs, 'id_log');
    $placeholders = implode(',', array_fill(0, count($idsLog), '?'));
    $stmtDetalhes = $pdo->prepare("
        SELECT id_log, campo, valor_anterior, valor_novo
        FROM log_auditoria_detalhe
        WHERE id_log IN ($placeholders)
        ORDER BY id_detalhe ASC
    ");
    $stmtDetalhes->execute($idsLog);
    $detalhesPorLog = [];
    foreach ($stmtDetalhes->fetchAll() as $detalhe) {
        $detalhesPorLog[$detalhe['id_log']][] = [
            'campo'          => $detalhe['campo'],
            'valor_anterior' => $detalhe['valor_anterior'],
            'valor_novo'     => $detalhe['valor_novo'],
        ];
    }
    foreach ($logs as &$log) {
        $log['detalhes_campos'] = $detalhesPorLog[$log['id_log']] ?? [];
    }
}

echo json_encode([
    'sucesso'   => true,
    'logs'      => $logs,
    'paginacao' => metadadosPaginacao($pagina, $porPagina, $total),
]);
