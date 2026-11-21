<?php
/**
 * MecOn - POST /backend/veiculos/delete_veiculo.php
 * Body (JSON): { "id_veiculo": 1 }
 * Requer login. Só permite excluir veículo do próprio usuário.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados = json_decode(file_get_contents('php://input'), true);
$idVeiculo = $dados['id_veiculo'] ?? null;
if (!is_numeric($idVeiculo)) {
    responderErro(400, 'id_veiculo é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare(
    'DELETE FROM veiculos WHERE id_veiculo = :id AND id_usuario = :id_usuario'
);
$stmt->execute([
    'id'         => $idVeiculo,
    'id_usuario' => $usuario['id_usuario'],
]);
if ($stmt->rowCount() === 0) {
    responderErro(404, 'Veículo não encontrado.');
}
echo json_encode(['sucesso' => true]);
