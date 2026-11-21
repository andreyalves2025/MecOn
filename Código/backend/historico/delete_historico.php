<?php
/**
 * MecOn - POST /backend/historico/delete_historico.php
 * Body (JSON): { "id_historico": 1 }
 * Requer login. Só permite excluir histórico de um veículo do próprio
 * usuário (posse verificada via subquery em veiculos).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados       = json_decode(file_get_contents('php://input'), true);
$idHistorico = $dados['id_historico'] ?? null;
if (!is_numeric($idHistorico)) {
    responderErro(400, 'id_historico é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare("
    DELETE h FROM historico_manutencoes h
    JOIN veiculos v ON v.id_veiculo = h.id_veiculo
    WHERE h.id_historico = :id AND v.id_usuario = :id_usuario
");
$stmt->execute([
    'id'         => $idHistorico,
    'id_usuario' => $usuario['id_usuario'],
]);
if ($stmt->rowCount() === 0) {
    responderErro(404, 'Registro de histórico não encontrado.');
}
echo json_encode(['sucesso' => true]);
