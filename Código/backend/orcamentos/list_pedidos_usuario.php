<?php
/**
 * MecOn - GET /backend/orcamentos/list_pedidos_usuario.php
 * Pedidos de orçamento do próprio usuário, mais recentes primeiro, com
 * contagem de orçamentos recebidos e anexos. Requer login.
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
    SELECT p.id_pedido, p.descricao, p.status, p.criado_em, v.placa, s.nome AS nome_servico,
           (SELECT COUNT(*) FROM orcamentos o WHERE o.id_pedido = p.id_pedido) AS total_orcamentos,
           (SELECT COUNT(*) FROM orcamentos o WHERE o.id_pedido = p.id_pedido AND o.status IN ('respondido', 'aprovado')) AS respondidos,
           (SELECT COUNT(*) FROM anexos_pedido a WHERE a.id_pedido = p.id_pedido) AS total_anexos
    FROM pedidos_orcamento p
    JOIN veiculos v ON v.id_veiculo = p.id_veiculo
    LEFT JOIN servicos s ON s.id_servico = p.id_servico
    WHERE p.id_usuario = :u
    ORDER BY p.criado_em DESC
");
$stmt->execute(['u' => $usuario['id_usuario']]);
$pedidos = $stmt->fetchAll();
foreach ($pedidos as &$p) {
    $p['placa'] = placaDecifrar($p['placa']);
    $p['id_pedido']        = (int) $p['id_pedido'];
    $p['total_orcamentos'] = (int) $p['total_orcamentos'];
    $p['respondidos']      = (int) $p['respondidos'];
    $p['total_anexos']     = (int) $p['total_anexos'];
}
unset($p);
echo json_encode(['sucesso' => true, 'pedidos' => $pedidos]);
