<?php
/**
 * MecOn - GET /backend/veiculos/list_veiculos.php
 * Lista os veículos cadastrados pelo usuário logado.
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$pdo = getConnection();
$stmt = $pdo->prepare(
    'SELECT id_veiculo, placa, km_atual, data_cadastro FROM veiculos WHERE id_usuario = :id_usuario ORDER BY data_cadastro DESC'
);
$stmt->execute(['id_usuario' => $usuario['id_usuario']]);
$veiculos = $stmt->fetchAll();
foreach ($veiculos as &$v) {
    $v['placa'] = placaDecifrar($v['placa']);
}
unset($v);
echo json_encode(['sucesso' => true, 'veiculos' => $veiculos]);
