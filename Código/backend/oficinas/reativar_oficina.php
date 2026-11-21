<?php
/**
 * MecOn - POST /backend/oficinas/reativar_oficina.php
 * Body (JSON): { "id_oficina": 1 }
 * Uso exclusivo de administrador. Desfaz a desativação feita por
 * /oficinas/delete_oficina.php.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/auditoria.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$admin = exigirAdmin();
$dados     = json_decode(file_get_contents('php://input'), true);
$idOficina = $dados['id_oficina'] ?? null;
if (!is_numeric($idOficina)) {
    responderErro(400, 'id_oficina é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_oficina, ativa FROM oficinas WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
$oficina = $stmt->fetch();
if (!$oficina) {
    responderErro(404, 'Oficina não encontrada.');
}
if ((int) $oficina['ativa']) {
    responderErro(409, 'Esta oficina já está ativa.');
}
$stmt = $pdo->prepare('UPDATE oficinas SET ativa = 1, desativada_em = NULL WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
registrarLogAuditoria(
    $pdo, $admin['id_usuario'], 'reativar', 'oficina', (int) $idOficina,
    'Oficina reativada.',
    ['ativa' => ['anterior' => 0, 'novo' => 1]]
);
echo json_encode(['sucesso' => true, 'ativa' => true]);
