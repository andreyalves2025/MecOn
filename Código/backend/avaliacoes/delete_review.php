<?php
/**
 * MecOn - POST /backend/avaliacoes/delete_review.php
 * Body (JSON): { "id_avaliacao": 1 }
 * Uso exclusivo de administrador (moderação de conteúdo ofensivo/indevido).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/auditoria.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$admin = exigirAdmin();
$dados       = json_decode(file_get_contents('php://input'), true);
$idAvaliacao = $dados['id_avaliacao'] ?? null;
if (!is_numeric($idAvaliacao)) {
    responderErro(400, 'id_avaliacao é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT * FROM avaliacoes WHERE id_avaliacao = :id');
$stmt->execute(['id' => $idAvaliacao]);
$registroAnterior = $stmt->fetch();
if (!$registroAnterior) {
    responderErro(404, 'Avaliação não encontrada.');
}
$stmt = $pdo->prepare('DELETE FROM avaliacoes WHERE id_avaliacao = :id');
$stmt->execute(['id' => $idAvaliacao]);
registrarLogAuditoria(
    $pdo, $admin['id_usuario'], 'excluir', 'avaliacao', (int) $idAvaliacao,
    null, montarDetalhesExclusao($registroAnterior)
);
echo json_encode(['sucesso' => true]);
