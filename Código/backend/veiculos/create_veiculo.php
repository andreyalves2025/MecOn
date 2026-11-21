<?php
/**
 * MecOn - POST /backend/veiculos/create_veiculo.php
 * Body (JSON): { "placa": "ABC1D23", "km_atual": 45000 }
 * "km_atual" é opcional — alimenta os lembretes de manutenção que levam
 * em conta quilometragem, além de tempo (ver historico/lembretes_manutencao.php).
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados = json_decode(file_get_contents('php://input'), true);
$placa = trim($dados['placa'] ?? '');
$kmAtual = $dados['km_atual'] ?? null;
if ($placa === '') {
    responderErro(400, 'A placa é obrigatória.');
}
if (strlen($placa) > 10) {
    responderErro(400, 'Placa inválida.');
}
if ($kmAtual !== null && (!is_numeric($kmAtual) || (int) $kmAtual < 0)) {
    responderErro(400, 'km_atual deve ser um número maior ou igual a zero.');
}
$pdo = getConnection();
$stmt = $pdo->prepare(
    'INSERT INTO veiculos (id_usuario, placa, km_atual) VALUES (:id_usuario, :placa, :km_atual)'
);
$stmt->execute([
    'id_usuario' => $usuario['id_usuario'],
    'placa'      => placaCifrar($placa),
    'km_atual'   => $kmAtual !== null ? (int) $kmAtual : null,
]);
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_veiculo' => (int) $pdo->lastInsertId()]);
