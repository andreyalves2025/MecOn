<?php
/**
 * MecOn - GET /backend/avaliacoes/list_all_reviews.php?busca=termo&pagina=1&por_pagina=20
 * Lista todas as avaliações da plataforma, para moderação (admin-avaliacoes.html).
 * "busca" filtra por nome do usuário ou da oficina (opcional).
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
$busca = trim($_GET['busca'] ?? '');
$pdo = getConnection();

$condicao   = '';
$parametros = [];
if ($busca !== '') {
    $condicao = ' WHERE u.nome LIKE :busca_usuario OR o.nome LIKE :busca_oficina';
    $parametros['busca_usuario'] = '%' . $busca . '%';
    $parametros['busca_oficina'] = '%' . $busca . '%';
}

$stmtTotal = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM avaliacoes a
    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
    JOIN oficinas o ON o.id_oficina = a.id_oficina
    $condicao
");
$stmtTotal->execute($parametros);
$total = (int) $stmtTotal->fetch()['total'];

['pagina' => $pagina, 'por_pagina' => $porPagina, 'offset' => $offset] = obterParametrosPaginacao();

$sql = "
    SELECT
        a.id_avaliacao,
        a.id_agendamento IS NOT NULL AS verificada,
        a.nota,
        a.comentario,
        a.data_avaliacao,
        u.id_usuario,
        COALESCE(u.nome, 'Cliente removido') AS nome_usuario,
        o.id_oficina,
        o.nome AS nome_oficina
    FROM avaliacoes a
    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
    JOIN oficinas o ON o.id_oficina = a.id_oficina
    $condicao
    ORDER BY a.data_avaliacao DESC
    LIMIT :limite OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($parametros as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$avaliacoes = $stmt->fetchAll();
foreach ($avaliacoes as &$a) {
    $a['verificada'] = (bool) $a['verificada'];
}

echo json_encode([
    'sucesso'    => true,
    'avaliacoes' => $avaliacoes,
    'paginacao'  => metadadosPaginacao($pagina, $porPagina, $total),
]);
