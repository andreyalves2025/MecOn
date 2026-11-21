<?php
/**
 * MecOn - POST /backend/avaliacoes/update_review.php
 * Body (JSON): { "id_avaliacao": 1, "nota": 4, "comentario": "texto moderado" }
 * Uso exclusivo de administrador (moderação de conteúdo).
 * Usuários comuns não podem editar suas próprias avaliações.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$dados       = json_decode(file_get_contents('php://input'), true);
$idAvaliacao = $dados['id_avaliacao'] ?? null;
$nota        = $dados['nota'] ?? null;
$comentario  = trim($dados['comentario'] ?? '');
if (!is_numeric($idAvaliacao) || !is_numeric($nota)) {
    responderErro(400, 'id_avaliacao e nota são obrigatórios.');
}
$nota = (int) $nota;
if ($nota < 1 || $nota > 5) {
    responderErro(400, 'nota deve estar entre 1 e 5.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_avaliacao FROM avaliacoes WHERE id_avaliacao = :id');
$stmt->execute(['id' => $idAvaliacao]);
if (!$stmt->fetch()) {
    responderErro(404, 'Avaliação não encontrada.');
}
$stmt = $pdo->prepare("
    UPDATE avaliacoes
    SET nota = :nota, comentario = :comentario
    WHERE id_avaliacao = :id_avaliacao
");
$stmt->execute([
    'nota'         => $nota,
    'comentario'   => $comentario !== '' ? $comentario : null,
    'id_avaliacao' => $idAvaliacao,
]);
echo json_encode(['sucesso' => true]);
