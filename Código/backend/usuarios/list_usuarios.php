<?php
/**
 * MecOn - GET /backend/usuarios/list_usuarios.php?busca=termo&pagina=1&por_pagina=20
 * Lista usuários, com busca opcional por nome ou email (admin-usuarios.html).
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
    $condicao = ' WHERE nome LIKE :busca_nome OR email LIKE :busca_email';
    $parametros['busca_nome']  = '%' . $busca . '%';
    $parametros['busca_email'] = '%' . $busca . '%';
}

$stmtTotal = $pdo->prepare('SELECT COUNT(*) AS total FROM usuarios' . $condicao);
$stmtTotal->execute($parametros);
$total = (int) $stmtTotal->fetch()['total'];

['pagina' => $pagina, 'por_pagina' => $porPagina, 'offset' => $offset] = obterParametrosPaginacao();

$sql = 'SELECT id_usuario, nome, email, tipo, data_cadastro FROM usuarios' . $condicao
     . ' ORDER BY nome ASC LIMIT :limite OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($parametros as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();

echo json_encode([
    'sucesso'    => true,
    'usuarios'   => $stmt->fetchAll(),
    'paginacao'  => metadadosPaginacao($pagina, $porPagina, $total),
]);
