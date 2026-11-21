<?php
/**
 * MecOn - POST /backend/promocoes/delete_promocao.php
 * Body (JSON): { "id_promocao": 1 }
 * Requer login de oficina; só exclui promoção da própria oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$dados      = json_decode(file_get_contents('php://input'), true);
$idPromocao = $dados['id_promocao'] ?? null;
if (!is_numeric($idPromocao)) {
    responderErro(400, 'id_promocao é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('DELETE FROM promocoes WHERE id_promocao = :id AND id_oficina = :id_oficina');
$stmt->execute(['id' => $idPromocao, 'id_oficina' => $oficinaSessao['id_oficina']]);
if ($stmt->rowCount() === 0) {
    responderErro(404, 'Promoção não encontrada.');
}
echo json_encode(['sucesso' => true]);
