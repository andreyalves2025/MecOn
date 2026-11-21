<?php
/**
 * MecOn - POST /backend/oficinas/delete_oficina.php
 * Body (JSON): { "id_oficina": 1 }
 * Uso exclusivo de administrador.
 *
 * EXCLUSÃO LÓGICA: a oficina é DESATIVADA (ativa = 0), não apagada. Ela some
 * das buscas, promoções e novos pedidos e não consegue mais entrar, mas o
 * histórico de manutenção, os agendamentos e as ordens de serviço dos
 * clientes continuam intactos (o histórico pertence ao veículo e ao dono
 * dele). Para desfazer, use /oficinas/reativar_oficina.php.
 * Ver migrations/025_preservar_historico.sql.
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
$stmt = $pdo->prepare('SELECT id_oficina, nome, ativa FROM oficinas WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
$oficina = $stmt->fetch();
if (!$oficina) {
    responderErro(404, 'Oficina não encontrada.');
}
if (!(int) $oficina['ativa']) {
    responderErro(409, 'Esta oficina já está desativada.');
}
$stmt = $pdo->prepare('UPDATE oficinas SET ativa = 0, desativada_em = NOW() WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
registrarLogAuditoria(
    $pdo, $admin['id_usuario'], 'desativar', 'oficina', (int) $idOficina,
    'Oficina desativada (exclusão lógica); histórico dos clientes preservado.',
    ['ativa' => ['anterior' => 1, 'novo' => 0]]
);
echo json_encode(['sucesso' => true, 'ativa' => false]);
