<?php
/**
 * MecOn - GET /backend/agendamentos/list_agendamentos_usuario.php
 * Lista os agendamentos do próprio usuário, mais recentes primeiro.
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
$stmt = $pdo->prepare("
    SELECT
        ag.id_agendamento, ag.data_hora_solicitada, ag.mensagem_usuario,
        ag.status, ag.resposta_oficina, ag.valor_total, ag.criado_em,
        o.id_oficina, o.nome AS nome_oficina, o.telefone AS telefone_oficina,
        s.nome AS nome_servico,
        COALESCE(v.placa, ag.placa_snapshot) AS placa,
        (SELECT os.etapa FROM ordens_servico os WHERE os.id_agendamento = ag.id_agendamento) AS etapa_os,
        (SELECT 1 FROM avaliacoes a WHERE a.id_agendamento = ag.id_agendamento) IS NOT NULL AS ja_avaliado
    FROM agendamentos ag
    JOIN oficinas o ON o.id_oficina = ag.id_oficina
    JOIN servicos s ON s.id_servico = ag.id_servico
    LEFT JOIN veiculos v ON v.id_veiculo = ag.id_veiculo
    WHERE ag.id_usuario = :id_usuario
    ORDER BY ag.data_hora_solicitada DESC
");
$stmt->execute(['id_usuario' => $usuario['id_usuario']]);
$agendamentos = $stmt->fetchAll();
foreach ($agendamentos as &$ag) {
    $ag['placa'] = placaDecifrar($ag['placa']);
    $ag['ja_avaliado'] = (bool) $ag['ja_avaliado'];
    $ag['valor_total'] = $ag['valor_total'] !== null ? (float) $ag['valor_total'] : null;
}
echo json_encode(['sucesso' => true, 'agendamentos' => $agendamentos]);
