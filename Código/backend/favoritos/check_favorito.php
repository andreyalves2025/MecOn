<?php
/**
 * MecOn - GET /backend/favoritos/check_favorito.php?id_oficina=1
 * Verifica se a oficina informada está nos favoritos do usuário logado.
 * Usado para decidir o estado inicial do ícone de coração na página da oficina.
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$idOficina = $_GET['id_oficina'] ?? null;
if (!is_numeric($idOficina)) {
    responderErro(400, 'id_oficina é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare(
    'SELECT 1 FROM favoritos WHERE id_usuario = :id_usuario AND id_oficina = :id_oficina'
);
$stmt->execute([
    'id_usuario' => $usuario['id_usuario'],
    'id_oficina' => $idOficina,
]);
echo json_encode([
    'sucesso'    => true,
    'favoritado' => (bool) $stmt->fetch(),
]);
