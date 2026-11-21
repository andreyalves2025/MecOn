<?php
/**
 * MecOn - GET /backend/orcamentos/get_orcamento_oficina.php?id_orcamento=1
 * Detalhe de um orçamento recebido: o problema descrito pelo cliente, o
 * veículo, os anexos (fotos/vídeos) e a resposta já dada, se houver.
 * Só a oficina destinatária enxerga; para as demais, 404. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficina = exigirOficina();
$idOrcamento = $_GET['id_orcamento'] ?? null;
if (!is_numeric($idOrcamento)) {
    responderErro(400, 'id_orcamento é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT o.id_orcamento, o.status, o.valor_total, o.valor_pecas, o.valor_mao_obra,
           o.prazo_dias, o.validade_ate, o.detalhes, o.respondido_em,
           p.id_pedido, p.descricao, p.status AS status_pedido, p.criado_em,
           u.nome AS nome_cliente, v.placa, v.km_atual, s.id_servico, s.nome AS nome_servico
    FROM orcamentos o
    JOIN pedidos_orcamento p ON p.id_pedido = o.id_pedido
    JOIN usuarios u ON u.id_usuario = p.id_usuario
    JOIN veiculos v ON v.id_veiculo = p.id_veiculo
    LEFT JOIN servicos s ON s.id_servico = p.id_servico
    WHERE o.id_orcamento = :id AND o.id_oficina = :o
");
$stmt->execute(['id' => $idOrcamento, 'o' => $oficina['id_oficina']]);
$o = $stmt->fetch();
if (!$o) {
    responderErro(404, 'Orçamento não encontrado.');
}
foreach (['valor_total', 'valor_pecas', 'valor_mao_obra'] as $c) {
    $o[$c] = $o[$c] !== null ? (float) $o[$c] : null;
}
$o['placa'] = placaParaOficina($o['placa'], $o['status']);
$o['id_orcamento'] = (int) $o['id_orcamento'];
$o['id_pedido']    = (int) $o['id_pedido'];
$o['prazo_dias']   = $o['prazo_dias'] !== null ? (int) $o['prazo_dias'] : null;
$o['km_atual']     = $o['km_atual'] !== null ? (int) $o['km_atual'] : null;

$stmt = $pdo->prepare('SELECT id_anexo, nome_original, tipo_mime, tamanho_bytes FROM anexos_pedido WHERE id_pedido = :p ORDER BY id_anexo');
$stmt->execute(['p' => $o['id_pedido']]);
$anexos = $stmt->fetchAll();
foreach ($anexos as &$a) {
    $a['id_anexo'] = (int) $a['id_anexo'];
    $a['tamanho_bytes'] = (int) $a['tamanho_bytes'];
}
unset($a);
echo json_encode(['sucesso' => true, 'orcamento' => $o, 'anexos' => $anexos]);
