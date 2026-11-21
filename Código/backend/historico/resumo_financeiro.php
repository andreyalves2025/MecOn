<?php
/**
 * MecOn - GET /backend/historico/resumo_financeiro.php[?id_veiculo=1]
 *
 * Resumo do quanto o usuário gastou com manutenção (soma de valor_total
 * dos registros de histórico que têm valor informado). Sem id_veiculo,
 * considera todos os veículos do usuário; com id_veiculo, só aquele.
 *
 * Retorna: total geral, total de peças e de mão de obra (somando só onde
 * o detalhamento foi informado), gasto por ano e por serviço.
 * Registros sem valor informado não entram nas somas, mas são contados em
 * "registros_sem_valor" para o usuário saber que o resumo é parcial.
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$idVeiculo = $_GET['id_veiculo'] ?? null;
$pdo = getConnection();

$filtroVeiculo = '';
$parametros = ['id_usuario' => $usuario['id_usuario']];
if ($idVeiculo !== null && $idVeiculo !== '') {
    if (!is_numeric($idVeiculo)) {
        responderErro(400, 'id_veiculo deve ser numérico.');
    }
    $filtroVeiculo = ' AND v.id_veiculo = :id_veiculo';
    $parametros['id_veiculo'] = $idVeiculo;
}

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(h.valor_total), 0)    AS total_geral,
        COALESCE(SUM(h.valor_pecas), 0)    AS total_pecas,
        COALESCE(SUM(h.valor_mao_obra), 0) AS total_mao_obra,
        COUNT(*)                           AS total_registros,
        SUM(h.valor_total IS NULL)         AS registros_sem_valor
    FROM historico_manutencoes h
    JOIN veiculos v ON v.id_veiculo = h.id_veiculo
    WHERE v.id_usuario = :id_usuario $filtroVeiculo
");
$stmt->execute($parametros);
$totais = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT YEAR(h.data_servico) AS ano, COALESCE(SUM(h.valor_total), 0) AS total
    FROM historico_manutencoes h
    JOIN veiculos v ON v.id_veiculo = h.id_veiculo
    WHERE v.id_usuario = :id_usuario $filtroVeiculo
    GROUP BY YEAR(h.data_servico)
    ORDER BY ano DESC
");
$stmt->execute($parametros);
$porAno = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT s.nome AS servico, COUNT(*) AS vezes, COALESCE(SUM(h.valor_total), 0) AS total
    FROM historico_manutencoes h
    JOIN veiculos v ON v.id_veiculo = h.id_veiculo
    JOIN servicos s ON s.id_servico = h.id_servico
    WHERE v.id_usuario = :id_usuario $filtroVeiculo
    GROUP BY s.id_servico, s.nome
    ORDER BY total DESC, vezes DESC
");
$stmt->execute($parametros);
$porServico = $stmt->fetchAll();

foreach ($porAno as &$a) {
    $a['ano']   = (int) $a['ano'];
    $a['total'] = (float) $a['total'];
}
foreach ($porServico as &$s) {
    $s['vezes'] = (int) $s['vezes'];
    $s['total'] = (float) $s['total'];
}

echo json_encode([
    'sucesso' => true,
    'totais'  => [
        'total_geral'         => (float) $totais['total_geral'],
        'total_pecas'         => (float) $totais['total_pecas'],
        'total_mao_obra'      => (float) $totais['total_mao_obra'],
        'total_registros'     => (int) $totais['total_registros'],
        'registros_sem_valor' => (int) $totais['registros_sem_valor'],
    ],
    'por_ano'     => $porAno,
    'por_servico' => $porServico,
]);
