<?php
/**
 * MecOn - POST /backend/favoritos/remove_favorito.php
 * Body (JSON): { "id_oficina": 1 }
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados = json_decode(file_get_contents('php://input'), true);
$idOficina = $dados['id_oficina'] ?? null;
if (!is_numeric($idOficina)) {
    responderErro(400, 'id_oficina é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare(
    'DELETE FROM favoritos WHERE id_usuario = :id_usuario AND id_oficina = :id_oficina'
);
$stmt->execute([
    'id_usuario' => $usuario['id_usuario'],
    'id_oficina' => $idOficina,
]);
echo json_encode(['sucesso' => true]);
