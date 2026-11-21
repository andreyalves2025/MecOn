<?php
/**
 * MecOn - GET /backend/historico/list_historico.php?id_veiculo=1
 * Lista o histórico de manutenção de um veículo específico do usuário
 * logado, mais recente primeiro. O veículo deve pertencer ao usuário.
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/pecas.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$idVeiculo = $_GET['id_veiculo'] ?? null;
if (!is_numeric($idVeiculo)) {
    responderErro(400, 'id_veiculo é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
// Garante que o veículo pertence ao usuário logado
$stmt = $pdo->prepare('SELECT id_veiculo FROM veiculos WHERE id_veiculo = :id AND id_usuario = :id_usuario');
$stmt->execute(['id' => $idVeiculo, 'id_usuario' => $usuario['id_usuario']]);
if (!$stmt->fetch()) {
    responderErro(404, 'Veículo não encontrado.');
}
$sql = "
    SELECT
        h.id_historico,
        h.data_servico,
        h.km_servico,
        h.valor_total,
        h.valor_pecas,
        h.valor_mao_obra,
        o.id_oficina,
        o.nome AS nome_oficina,
        s.id_servico,
        s.nome AS nome_servico
    FROM historico_manutencoes h
    JOIN oficinas o ON o.id_oficina = h.id_oficina
    JOIN servicos s ON s.id_servico = h.id_servico
    WHERE h.id_veiculo = :id_veiculo
    ORDER BY h.data_servico DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id_veiculo' => $idVeiculo]);
$historico = $stmt->fetchAll();
// DECIMAL chega do PDO como string; converte para número no JSON
foreach ($historico as &$h) {
    foreach (['valor_total', 'valor_pecas', 'valor_mao_obra'] as $campo) {
        $h[$campo] = $h[$campo] !== null ? (float) $h[$campo] : null;
    }
}
$pecasPorHistorico = listarPecasPorHistorico($pdo, array_column($historico, 'id_historico'));
foreach ($historico as &$h) {
    $h['pecas'] = $pecasPorHistorico[$h['id_historico']] ?? [];
}
$totalGasto = array_sum(array_map(function ($h) { return $h['valor_total'] ?? 0; }, $historico));
echo json_encode([
    'sucesso'     => true,
    'historico'   => $historico,
    'total_gasto' => round($totalGasto, 2),
]);
