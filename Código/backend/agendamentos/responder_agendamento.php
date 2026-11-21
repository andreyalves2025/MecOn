<?php
/**
 * MecOn - POST /backend/agendamentos/responder_agendamento.php
 * Body (JSON): { "id_agendamento": 1, "acao": "aceitar"|"recusar", "resposta": "..." }
 * Só é permitido responder agendamentos com status "pendente". Requer
 * login de oficina; só responde agendamentos da própria oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$dados         = json_decode(file_get_contents('php://input'), true);
$idAgendamento = $dados['id_agendamento'] ?? null;
$acao          = $dados['acao'] ?? null;
$resposta      = trim($dados['resposta'] ?? '');
if (!is_numeric($idAgendamento)) {
    responderErro(400, 'id_agendamento é obrigatório e deve ser numérico.');
}
if (!in_array($acao, ['aceitar', 'recusar'], true)) {
    responderErro(400, 'acao deve ser "aceitar" ou "recusar".');
}
if (mb_strlen($resposta) > 500) {
    responderErro(400, 'resposta deve ter no máximo 500 caracteres.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT status FROM agendamentos WHERE id_agendamento = :id AND id_oficina = :id_oficina');
$stmt->execute(['id' => $idAgendamento, 'id_oficina' => $oficinaSessao['id_oficina']]);
$agendamento = $stmt->fetch();
if (!$agendamento) {
    responderErro(404, 'Agendamento não encontrado.');
}
if ($agendamento['status'] !== 'pendente') {
    responderErro(409, 'Este agendamento já foi respondido e não pode ser alterado.');
}
$novoStatus = $acao === 'aceitar' ? 'aceito' : 'recusado';
$stmt = $pdo->prepare('UPDATE agendamentos SET status = :status, resposta_oficina = :resposta, respondido_em = NOW() WHERE id_agendamento = :id');
$stmt->execute([
    'status'   => $novoStatus,
    'resposta' => $resposta !== '' ? $resposta : null,
    'id'       => $idAgendamento,
]);
echo json_encode(['sucesso' => true]);
