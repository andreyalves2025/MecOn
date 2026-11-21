<?php
/**
 * MecOn - POST /backend/agendamentos/cancelar_agendamento.php
 * Body (JSON): { "id_agendamento": 1 }
 * Usuário cancela um agendamento próprio, desde que ainda não tenha sido
 * concluído nem já esteja cancelado/recusado. Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados         = json_decode(file_get_contents('php://input'), true);
$idAgendamento = $dados['id_agendamento'] ?? null;
if (!is_numeric($idAgendamento)) {
    responderErro(400, 'id_agendamento é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT status FROM agendamentos WHERE id_agendamento = :id AND id_usuario = :id_usuario');
$stmt->execute(['id' => $idAgendamento, 'id_usuario' => $usuario['id_usuario']]);
$agendamento = $stmt->fetch();
if (!$agendamento) {
    responderErro(404, 'Agendamento não encontrado.');
}
if (!in_array($agendamento['status'], ['pendente', 'aceito'], true)) {
    responderErro(409, 'Este agendamento não pode mais ser cancelado.');
}
$stmt = $pdo->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id_agendamento = :id");
$stmt->execute(['id' => $idAgendamento]);
echo json_encode(['sucesso' => true]);
