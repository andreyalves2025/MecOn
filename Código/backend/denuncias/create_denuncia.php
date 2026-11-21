<?php
/**
 * MecOn - POST /backend/denuncias/create_denuncia.php
 * Body (JSON): { "id_avaliacao": 1, "motivo": "Comentário ofensivo" }
 * "motivo" é opcional (texto livre curto).
 * Requer login. Um usuário só pode denunciar a mesma avaliação uma vez
 * (constraint UNIQUE em denuncias) e não pode denunciar a própria avaliação.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados       = json_decode(file_get_contents('php://input'), true);
$idAvaliacao = $dados['id_avaliacao'] ?? null;
$motivo      = trim($dados['motivo'] ?? '');
if (!is_numeric($idAvaliacao)) {
    responderErro(400, 'id_avaliacao é obrigatório e deve ser numérico.');
}
if (mb_strlen($motivo) > 255) {
    responderErro(400, 'motivo deve ter no máximo 255 caracteres.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_usuario FROM avaliacoes WHERE id_avaliacao = :id');
$stmt->execute(['id' => $idAvaliacao]);
$avaliacao = $stmt->fetch();
if (!$avaliacao) {
    responderErro(404, 'Avaliação não encontrada.');
}
if ((int) $avaliacao['id_usuario'] === (int) $usuario['id_usuario']) {
    responderErro(400, 'Você não pode denunciar sua própria avaliação.');
}
try {
    $stmt = $pdo->prepare('
        INSERT INTO denuncias (id_avaliacao, id_usuario, motivo)
        VALUES (:id_avaliacao, :id_usuario, :motivo)
    ');
    $stmt->execute([
        'id_avaliacao' => $idAvaliacao,
        'id_usuario'   => $usuario['id_usuario'],
        'motivo'       => $motivo !== '' ? $motivo : null,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        responderErro(409, 'Você já denunciou esta avaliação.');
    }
    responderErro(500, 'Erro ao registrar denúncia.');
}
http_response_code(201);
echo json_encode(['sucesso' => true]);
