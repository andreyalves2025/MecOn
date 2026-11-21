<?php
/**
 * MecOn - GET /backend/orcamentos/get_pedido.php?id_pedido=1
 * Detalhe de um pedido do próprio usuário: o problema, os anexos e os
 * orçamentos recebidos (um por oficina) para comparar lado a lado.
 * Pedido de outro usuário responde 404. Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$idPedido = $_GET['id_pedido'] ?? null;
if (!is_numeric($idPedido)) {
    responderErro(400, 'id_pedido é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT p.id_pedido, p.id_servico, p.descricao, p.status, p.criado_em, v.placa, s.nome AS nome_servico
    FROM pedidos_orcamento p
    JOIN veiculos v ON v.id_veiculo = p.id_veiculo
    LEFT JOIN servicos s ON s.id_servico = p.id_servico
    WHERE p.id_pedido = :p AND p.id_usuario = :u
");
$stmt->execute(['p' => $idPedido, 'u' => $usuario['id_usuario']]);
$pedido = $stmt->fetch();
if (!$pedido) {
    responderErro(404, 'Pedido não encontrado.');
}
$pedido['placa'] = placaDecifrar($pedido['placa']);

$stmt = $pdo->prepare('SELECT id_anexo, nome_original, tipo_mime, tamanho_bytes FROM anexos_pedido WHERE id_pedido = :p ORDER BY id_anexo');
$stmt->execute(['p' => $idPedido]);
$anexos = $stmt->fetchAll();
foreach ($anexos as &$a) {
    $a['id_anexo'] = (int) $a['id_anexo'];
    $a['tamanho_bytes'] = (int) $a['tamanho_bytes'];
}
unset($a);

$stmt = $pdo->prepare("
    SELECT o.id_orcamento, o.status, o.valor_total, o.valor_pecas, o.valor_mao_obra, o.prazo_dias,
           o.validade_ate, o.detalhes, o.respondido_em,
           (o.validade_ate IS NOT NULL AND o.validade_ate < CURDATE()) AS expirado,
           ofi.id_oficina, ofi.nome AS nome_oficina, ofi.verificada,
           (SELECT ROUND(AVG(a.nota), 1) FROM avaliacoes a WHERE a.id_oficina = ofi.id_oficina) AS media_avaliacao
    FROM orcamentos o
    JOIN oficinas ofi ON ofi.id_oficina = o.id_oficina
    WHERE o.id_pedido = :p
    ORDER BY (o.valor_total IS NULL), o.valor_total ASC
");
$stmt->execute(['p' => $idPedido]);
$orcamentos = $stmt->fetchAll();
foreach ($orcamentos as &$o) {
    $o['id_orcamento'] = (int) $o['id_orcamento'];
    $o['id_oficina']   = (int) $o['id_oficina'];
    $o['verificada']   = (bool) $o['verificada'];
    $o['expirado']     = (bool) $o['expirado'];
    foreach (['valor_total', 'valor_pecas', 'valor_mao_obra', 'media_avaliacao'] as $c) {
        $o[$c] = $o[$c] !== null ? (float) $o[$c] : null;
    }
    $o['prazo_dias'] = $o['prazo_dias'] !== null ? (int) $o['prazo_dias'] : null;
}
unset($o);

$pedido['id_pedido'] = (int) $pedido['id_pedido'];
$pedido['id_servico'] = $pedido['id_servico'] !== null ? (int) $pedido['id_servico'] : null;
echo json_encode(['sucesso' => true, 'pedido' => $pedido, 'anexos' => $anexos, 'orcamentos' => $orcamentos]);
