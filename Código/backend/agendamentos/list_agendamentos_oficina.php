<?php
/**
 * MecOn - GET /backend/agendamentos/list_agendamentos_oficina.php
 * Lista os agendamentos recebidos pela própria oficina, mais recentes
 * primeiro. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT
        ag.id_agendamento, ag.data_hora_solicitada, ag.mensagem_usuario,
        ag.status, ag.resposta_oficina, ag.valor_total, ag.id_orcamento, orc.valor_total AS valor_orcamento, os.id_os, os.etapa AS etapa_os, ag.criado_em,
        COALESCE(u.nome, 'Cliente removido') AS nome_usuario, u.email AS email_usuario,
        s.nome AS nome_servico,
        ag.placa_snapshot AS placa
    FROM agendamentos ag
    LEFT JOIN usuarios u ON u.id_usuario = ag.id_usuario
    JOIN servicos s ON s.id_servico = ag.id_servico
    LEFT JOIN orcamentos orc ON orc.id_orcamento = ag.id_orcamento
    LEFT JOIN ordens_servico os ON os.id_agendamento = ag.id_agendamento
    WHERE ag.id_oficina = :id_oficina
    ORDER BY ag.data_hora_solicitada DESC
");
$stmt->execute(['id_oficina' => $oficinaSessao['id_oficina']]);
$agendamentos = $stmt->fetchAll();
foreach ($agendamentos as &$ag) {
    $ag['placa'] = placaParaOficina($ag['placa'], $ag['status']);
    $ag['cliente_removido'] = $ag['email_usuario'] === null;
    $ag['valor_total'] = $ag['valor_total'] !== null ? (float) $ag['valor_total'] : null;
    $ag['valor_orcamento'] = $ag['valor_orcamento'] !== null ? (float) $ag['valor_orcamento'] : null;
}
echo json_encode(['sucesso' => true, 'agendamentos' => $agendamentos]);
