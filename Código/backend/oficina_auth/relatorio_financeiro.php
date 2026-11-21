<?php
/**
 * MecOn - GET /backend/oficina_auth/relatorio_financeiro.php?periodo=6m
 *
 * Relatório financeiro da oficina logada. "periodo": 30d, 90d, 6m
 * (padrão), 12m ou tudo.
 *
 * Fonte dos números: agendamentos CONCLUÍDOS com o valor que a PRÓPRIA
 * oficina informou ao concluir (agendamentos.valor_total) — nunca os
 * valores que o cliente digita à mão no histórico dele (ver migração
 * 018). Serviços concluídos sem valor informado não entram no faturamento
 * nem no ticket médio, mas são contados em "concluidos_sem_valor" para o
 * relatório não parecer completo quando não é.
 *
 * A data de referência de cada serviço é a data/hora agendada
 * (data_hora_solicitada). Os picos de movimento consideram agendamentos
 * aceitos e concluídos (demanda efetivamente marcada), não só os já feitos.
 *
 * Retorna: totais, comparação com o período anterior de mesmo tamanho,
 * faturamento por mês, serviços mais realizados e picos por dia da
 * semana e por hora. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$idOficina = (int) $oficinaSessao['id_oficina'];

// Lista fechada: o valor escolhido entra no SQL, então nunca vem direto do usuário
$periodos = [
    '30d' => ['30 DAY',   '60 DAY'],
    '90d' => ['90 DAY',   '180 DAY'],
    '6m'  => ['6 MONTH',  '12 MONTH'],
    '12m' => ['12 MONTH', '24 MONTH'],
    'tudo' => null,
];
$periodo = $_GET['periodo'] ?? '6m';
if (!array_key_exists($periodo, $periodos)) {
    responderErro(400, 'periodo deve ser 30d, 90d, 6m, 12m ou tudo.');
}
$pdo = getConnection();

if ($periodos[$periodo] !== null) {
    [$intervalo, $intervaloDobro] = $periodos[$periodo];
    $limitesPeriodo = "AND ag.data_hora_solicitada >= DATE_SUB(CURDATE(), INTERVAL $intervalo)
                       AND ag.data_hora_solicitada < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    $limiteInicioPico = "AND ag.data_hora_solicitada >= DATE_SUB(CURDATE(), INTERVAL $intervalo)";
} else {
    $limitesPeriodo = "AND ag.data_hora_solicitada < DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    $limiteInicioPico = '';
}
$baseConcluidos = "ag.id_oficina = :id AND ag.status = 'concluido' $limitesPeriodo";

// --- Totais ---
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS concluidos, COUNT(ag.valor_total) AS com_valor,
           COALESCE(SUM(ag.valor_total), 0) AS faturamento,
           COUNT(DISTINCT ag.id_usuario) AS clientes
    FROM agendamentos ag
    WHERE $baseConcluidos
");
$stmt->execute(['id' => $idOficina]);
$t = $stmt->fetch();
$concluidos = (int) $t['concluidos'];
$comValor   = (int) $t['com_valor'];
$faturamento = round((float) $t['faturamento'], 2);

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS recorrentes FROM (
        SELECT ag.id_usuario FROM agendamentos ag
        WHERE $baseConcluidos
        GROUP BY ag.id_usuario HAVING COUNT(*) >= 2
    ) por_cliente
");
$stmt->execute(['id' => $idOficina]);
$recorrentes = (int) $stmt->fetch()['recorrentes'];

// --- Comparação com o período anterior (mesmo tamanho) ---
$variacao = null;
$faturamentoAnterior = null;
if ($periodos[$periodo] !== null) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ag.valor_total), 0) AS faturamento
        FROM agendamentos ag
        WHERE ag.id_oficina = :id AND ag.status = 'concluido'
          AND ag.data_hora_solicitada >= DATE_SUB(CURDATE(), INTERVAL $intervaloDobro)
          AND ag.data_hora_solicitada <  DATE_SUB(CURDATE(), INTERVAL $intervalo)
    ");
    $stmt->execute(['id' => $idOficina]);
    $faturamentoAnterior = round((float) $stmt->fetch()['faturamento'], 2);
    if ($faturamentoAnterior > 0) {
        $variacao = round(($faturamento - $faturamentoAnterior) / $faturamentoAnterior * 100, 1);
    }
}

// --- Faturamento por mês (com meses sem movimento preenchidos com zero) ---
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(ag.data_hora_solicitada, '%Y-%m') AS mes,
           COUNT(*) AS qtd, COALESCE(SUM(ag.valor_total), 0) AS total
    FROM agendamentos ag
    WHERE $baseConcluidos
    GROUP BY mes ORDER BY mes ASC
");
$stmt->execute(['id' => $idOficina]);
$mapaMeses = [];
foreach ($stmt->fetchAll() as $m) {
    $mapaMeses[$m['mes']] = ['qtd' => (int) $m['qtd'], 'total' => round((float) $m['total'], 2)];
}
$mesAtual = $pdo->query("SELECT DATE_FORMAT(CURDATE(), '%Y-%m') AS m")->fetch()['m'];
if ($periodos[$periodo] !== null) {
    $mesInicio = $pdo->query("SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL $intervalo), '%Y-%m') AS m")->fetch()['m'];
} else {
    $mesInicio = !empty($mapaMeses) ? array_keys($mapaMeses)[0] : $mesAtual;
}
$porMes = [];
$cursor = DateTime::createFromFormat('Y-m-d', $mesInicio . '-01');
$fim    = DateTime::createFromFormat('Y-m-d', $mesAtual . '-01');
while ($cursor <= $fim) {
    $chave = $cursor->format('Y-m');
    $porMes[] = ['mes' => $chave] + ($mapaMeses[$chave] ?? ['qtd' => 0, 'total' => 0.0]);
    $cursor->modify('+1 month');
}

// --- Serviços mais realizados ---
$stmt = $pdo->prepare("
    SELECT s.nome, COUNT(*) AS qtd, COUNT(ag.valor_total) AS com_valor,
           COALESCE(SUM(ag.valor_total), 0) AS total
    FROM agendamentos ag
    JOIN servicos s ON s.id_servico = ag.id_servico
    WHERE $baseConcluidos
    GROUP BY s.id_servico, s.nome
    ORDER BY qtd DESC, total DESC
    LIMIT 10
");
$stmt->execute(['id' => $idOficina]);
$porServico = [];
foreach ($stmt->fetchAll() as $s) {
    $totalServico = round((float) $s['total'], 2);
    $porServico[] = [
        'servico'      => $s['nome'],
        'qtd'          => (int) $s['qtd'],
        'faturamento'  => $totalServico,
        'ticket_medio' => (int) $s['com_valor'] > 0 ? round($totalServico / (int) $s['com_valor'], 2) : null,
    ];
}

// --- Picos de movimento (aceitos + concluídos) ---
$stmt = $pdo->prepare("
    SELECT DAYOFWEEK(ag.data_hora_solicitada) AS dia, HOUR(ag.data_hora_solicitada) AS hora, COUNT(*) AS qtd
    FROM agendamentos ag
    WHERE ag.id_oficina = :id AND ag.status IN ('aceito', 'concluido') $limiteInicioPico
    GROUP BY dia, hora
");
$stmt->execute(['id' => $idOficina]);
$porDiaSemana = array_fill(0, 7, 0); // 0 = domingo ... 6 = sábado (DAYOFWEEK vai de 1 a 7)
$porHora      = array_fill(0, 24, 0);
foreach ($stmt->fetchAll() as $p) {
    $porDiaSemana[(int) $p['dia'] - 1] += (int) $p['qtd'];
    $porHora[(int) $p['hora']] += (int) $p['qtd'];
}

echo json_encode([
    'sucesso' => true,
    'periodo' => $periodo,
    'totais'  => [
        'faturamento'          => $faturamento,
        'servicos_concluidos'  => $concluidos,
        'concluidos_sem_valor' => $concluidos - $comValor,
        'ticket_medio'         => $comValor > 0 ? round($faturamento / $comValor, 2) : null,
        'clientes_atendidos'   => (int) $t['clientes'],
        'clientes_recorrentes' => $recorrentes,
        'faturamento_periodo_anterior' => $faturamentoAnterior,
        'variacao_pct'         => $variacao,
    ],
    'por_mes'        => $porMes,
    'por_servico'    => $porServico,
    'picos' => [
        'por_dia_semana' => $porDiaSemana,
        'por_hora'       => $porHora,
    ],
]);
