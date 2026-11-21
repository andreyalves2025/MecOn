<?php
/**
 * MecOn - GET /backend/denuncias/list_denuncias.php?status=pendente&pagina=1&por_pagina=20
 * Lista denúncias para a fila de moderação do admin, com o conteúdo da
 * avaliação denunciada e quem denunciou. "status" filtra por
 * pendente/descartada (padrão: pendente — é o que o admin precisa agir).
 * Resposta paginada — ver backend/config/paginacao.php.
 * Requer login como administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/paginacao.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$status = $_GET['status'] ?? 'pendente';
if (!in_array($status, ['pendente', 'descartada', 'todas'], true)) {
    responderErro(400, 'status deve ser "pendente", "descartada" ou "todas".');
}
$pdo = getConnection();
$condicao   = $status !== 'todas' ? ' WHERE d.status = :status' : '';
$parametros = $status !== 'todas' ? ['status' => $status] : [];

$stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM denuncias d $condicao");
$stmtTotal->execute($parametros);
$total = (int) $stmtTotal->fetch()['total'];

['pagina' => $pagina, 'por_pagina' => $porPagina, 'offset' => $offset] = obterParametrosPaginacao();

$sql = "
    SELECT
        d.id_denuncia,
        d.motivo,
        d.status,
        d.data_denuncia,
        a.id_avaliacao,
        a.nota,
        a.comentario,
        COALESCE(au.nome, 'Cliente removido') AS nome_autor_avaliacao,
        o.id_oficina,
        o.nome AS nome_oficina,
        ud.nome AS nome_denunciante
    FROM denuncias d
    JOIN avaliacoes a ON a.id_avaliacao = d.id_avaliacao
    LEFT JOIN usuarios au ON au.id_usuario = a.id_usuario
    JOIN oficinas o ON o.id_oficina = a.id_oficina
    JOIN usuarios ud ON ud.id_usuario = d.id_usuario
    $condicao
    ORDER BY d.data_denuncia ASC
    LIMIT :limite OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($parametros as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();

echo json_encode([
    'sucesso'   => true,
    'denuncias' => $stmt->fetchAll(),
    'paginacao' => metadadosPaginacao($pagina, $porPagina, $total),
]);
