<?php
/**
 * MecOn - POST /backend/avaliacoes/responder_review.php
 * Body (JSON): { "id_avaliacao": 1, "resposta": "Obrigado pelo retorno!" }
 *
 * A oficina responde publicamente a uma avaliação que recebeu. Enviar de
 * novo sobre a mesma avaliação substitui a resposta anterior (edição).
 * Enviar "resposta" vazia remove a resposta.
 *
 * Requer login de oficina. Só é possível responder avaliações da própria
 * oficina (id vem da sessão, não do body — evita responder em nome de
 * outra oficina).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$dados       = json_decode(file_get_contents('php://input'), true);
$idAvaliacao = $dados['id_avaliacao'] ?? null;
$resposta    = trim($dados['resposta'] ?? '');
if (!is_numeric($idAvaliacao)) {
    responderErro(400, 'id_avaliacao é obrigatório e deve ser numérico.');
}
if (mb_strlen($resposta) > 2000) {
    responderErro(400, 'A resposta deve ter no máximo 2000 caracteres.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_oficina FROM avaliacoes WHERE id_avaliacao = :id');
$stmt->execute(['id' => $idAvaliacao]);
$avaliacao = $stmt->fetch();
if (!$avaliacao) {
    responderErro(404, 'Avaliação não encontrada.');
}
if ((int) $avaliacao['id_oficina'] !== (int) $oficinaSessao['id_oficina']) {
    // Não é a mesma oficina: responde com 404 (não 403) para não revelar
    // a quem pertence a avaliação a quem está tentando adivinhar ids.
    responderErro(404, 'Avaliação não encontrada.');
}
$stmt = $pdo->prepare("
    UPDATE avaliacoes
    SET resposta_oficina = :resposta, data_resposta = :data_resposta
    WHERE id_avaliacao = :id
");
$stmt->execute([
    'resposta'      => $resposta !== '' ? $resposta : null,
    'data_resposta' => $resposta !== '' ? date('Y-m-d H:i:s') : null,
    'id'            => $idAvaliacao,
]);
echo json_encode(['sucesso' => true]);
