<?php
/**
 * MecOn - POST /backend/usuarios/delete_usuario.php
 * Body (JSON): { "id_usuario": 1 }
 * Uso exclusivo de administrador. Exclui em cascata favoritos, avaliações
 * e histórico do usuário (ON DELETE CASCADE definido no banco).
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
$idUsuario = $dados['id_usuario'] ?? null;
if (!is_numeric($idUsuario)) {
    responderErro(400, 'id_usuario é obrigatório e deve ser numérico.');
}
if ((int) $idUsuario === (int) $admin['id_usuario']) {
    responderErro(400, 'Não é possível excluir o próprio usuário administrador logado.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario = :id');
$stmt->execute(['id' => $idUsuario]);
$registroAnterior = $stmt->fetch();
if (!$registroAnterior) {
    responderErro(404, 'Usuário não encontrado.');
}
$stmt = $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = :id');
$stmt->execute(['id' => $idUsuario]);
registrarLogAuditoria(
    $pdo, $admin['id_usuario'], 'excluir', 'usuario', (int) $idUsuario,
    null, montarDetalhesExclusao($registroAnterior)
);
echo json_encode(['sucesso' => true]);
