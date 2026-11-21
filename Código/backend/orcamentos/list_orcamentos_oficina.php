<?php
/**
 * MecOn - GET /backend/orcamentos/list_orcamentos_oficina.php
 * Pedidos de orçamento recebidos pela oficina logada (um item por
 * orçamento), pendentes primeiro e depois mais recentes. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficina = exigirOficina();
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT o.id_orcamento, o.status, o.valor_total, o.prazo_dias, o.validade_ate, o.criado_em,
           p.id_pedido, p.descricao, p.status AS status_pedido,
           u.nome AS nome_cliente, v.placa, s.nome AS nome_servico,
           (SELECT COUNT(*) FROM anexos_pedido a WHERE a.id_pedido = p.id_pedido) AS total_anexos
    FROM orcamentos o
    JOIN pedidos_orcamento p ON p.id_pedido = o.id_pedido
    JOIN usuarios u ON u.id_usuario = p.id_usuario
    JOIN veiculos v ON v.id_veiculo = p.id_veiculo
    LEFT JOIN servicos s ON s.id_servico = p.id_servico
    WHERE o.id_oficina = :o AND o.status <> 'cancelado'
    ORDER BY (o.status = 'pendente') DESC, o.criado_em DESC
");
$stmt->execute(['o' => $oficina['id_oficina']]);
$lista = $stmt->fetchAll();
foreach ($lista as &$l) {
    $l['placa'] = placaParaOficina($l['placa'], $l['status']);
    $l['id_orcamento'] = (int) $l['id_orcamento'];
    $l['id_pedido']    = (int) $l['id_pedido'];
    $l['total_anexos'] = (int) $l['total_anexos'];
    $l['valor_total']  = $l['valor_total'] !== null ? (float) $l['valor_total'] : null;
    $l['prazo_dias']   = $l['prazo_dias'] !== null ? (int) $l['prazo_dias'] : null;
}
unset($l);
echo json_encode(['sucesso' => true, 'orcamentos' => $lista]);
