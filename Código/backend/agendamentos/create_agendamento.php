<?php
/**
 * MecOn - POST /backend/agendamentos/create_agendamento.php
 * Body (JSON): {
 *   "id_oficina": 1, "id_servico": 3, "id_veiculo": 2,
 *   "data_hora_solicitada": "2026-09-20 14:00:00",
 *   "mensagem_usuario": "Prefiro no período da tarde"
 * }
 * Requer login e email confirmado (mesma exigência de avaliar oficinas —
 * reduz solicitações falsas/spam para as oficinas).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$pdo = getConnection();
$usuario = exigirEmailVerificado($pdo);
$dados       = json_decode(file_get_contents('php://input'), true);
$idOficina   = $dados['id_oficina'] ?? null;
$idServico   = $dados['id_servico'] ?? null;
$idVeiculo   = $dados['id_veiculo'] ?? null;
$dataHora    = $dados['data_hora_solicitada'] ?? '';
$mensagem    = trim($dados['mensagem_usuario'] ?? '');
if (!is_numeric($idOficina) || !is_numeric($idServico) || !is_numeric($idVeiculo)) {
    responderErro(400, 'id_oficina, id_servico e id_veiculo são obrigatórios e devem ser numéricos.');
}
$dataHoraObj = DateTime::createFromFormat('Y-m-d H:i:s', $dataHora);
if (!$dataHoraObj) {
    responderErro(400, 'data_hora_solicitada é obrigatória, no formato AAAA-MM-DD HH:MM:SS.');
}
if ($dataHoraObj < new DateTime()) {
    responderErro(400, 'A data e hora solicitadas precisam ser no futuro.');
}
if (mb_strlen($mensagem) > 500) {
    responderErro(400, 'mensagem_usuario deve ter no máximo 500 caracteres.');
}
// Confere que o veículo é do próprio usuário
$stmt = $pdo->prepare('SELECT id_veiculo FROM veiculos WHERE id_veiculo = :id AND id_usuario = :id_usuario');
$stmt->execute(['id' => $idVeiculo, 'id_usuario' => $usuario['id_usuario']]);
if (!$stmt->fetch()) {
    responderErro(404, 'Veículo não encontrado.');
}
// Confere que a oficina realmente oferece o serviço solicitado
$stmt = $pdo->prepare('SELECT 1 FROM oficina_servico os JOIN oficinas o ON o.id_oficina = os.id_oficina WHERE os.id_oficina = :id_oficina AND os.id_servico = :id_servico AND o.ativa = 1');
$stmt->execute(['id_oficina' => $idOficina, 'id_servico' => $idServico]);
if (!$stmt->fetch()) {
    responderErro(400, 'Esta oficina não oferece o serviço informado.');
}
$stmt = $pdo->prepare('
    INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, placa_snapshot, data_hora_solicitada, mensagem_usuario)
    VALUES (:id_usuario, :id_oficina, :id_servico, :id_veiculo,
            (SELECT placa FROM veiculos WHERE id_veiculo = :id_veiculo_snap), :data_hora, :mensagem)
');
$stmt->execute([
    'id_usuario' => $usuario['id_usuario'],
    'id_oficina' => $idOficina,
    'id_servico' => $idServico,
    'id_veiculo' => $idVeiculo,
    'id_veiculo_snap' => $idVeiculo,
    'data_hora'  => $dataHoraObj->format('Y-m-d H:i:s'),
    'mensagem'   => $mensagem !== '' ? $mensagem : null,
]);
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_agendamento' => (int) $pdo->lastInsertId()]);
