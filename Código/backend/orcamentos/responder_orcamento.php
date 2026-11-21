<?php
/**
 * MecOn - POST /backend/orcamentos/responder_orcamento.php
 * Body (JSON):
 *   enviar: { "id_orcamento": 1, "acao": "enviar", "valor_total": 450.00,
 *             "valor_pecas": 300.00, "valor_mao_obra": 150.00, "prazo_dias": 2,
 *             "validade_ate": "2026-11-30", "detalhes": "Inclui alinhamento" }
 *   recusar: { "id_orcamento": 1, "acao": "recusar" }
 *
 * "enviar" exige valor_total (ou as partes, que o somam — ver
 * config/valores.php). Sem validade_ate, vale 7 dias. É possível reenviar
 * (corrigir valores) enquanto o cliente não tiver aprovado nem fechado o
 * pedido. Só a oficina destinatária responde. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/valores.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficina = exigirOficina();
$dados = json_decode(file_get_contents('php://input'), true);
$idOrcamento = $dados['id_orcamento'] ?? null;
$acao = $dados['acao'] ?? null;
if (!is_numeric($idOrcamento)) {
    responderErro(400, 'id_orcamento é obrigatório e deve ser numérico.');
}
if (!in_array($acao, ['enviar', 'recusar'], true)) {
    responderErro(400, 'acao deve ser "enviar" ou "recusar".');
}
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT o.status, p.status AS status_pedido
    FROM orcamentos o JOIN pedidos_orcamento p ON p.id_pedido = o.id_pedido
    WHERE o.id_orcamento = :id AND o.id_oficina = :o
");
$stmt->execute(['id' => $idOrcamento, 'o' => $oficina['id_oficina']]);
$atual = $stmt->fetch();
if (!$atual) {
    responderErro(404, 'Orçamento não encontrado.');
}
if ($atual['status_pedido'] !== 'aberto' || !in_array($atual['status'], ['pendente', 'respondido'], true)) {
    responderErro(409, 'Este orçamento não pode mais ser alterado (o cliente já decidiu ou cancelou o pedido).');
}

if ($acao === 'recusar') {
    $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'recusado', respondido_em = NOW() WHERE id_orcamento = :id");
    $stmt->execute(['id' => $idOrcamento]);
    echo json_encode(['sucesso' => true]);
    exit;
}

$valores = lerValoresFinanceiros($dados);
if ($valores['valor_total'] === null) {
    responderErro(400, 'Informe o valor total do orçamento.');
}
$prazo = $dados['prazo_dias'] ?? null;
if ($prazo === '' || $prazo === null) {
    $prazo = null;
} elseif (!is_numeric($prazo) || (int) $prazo < 0 || (int) $prazo > 365) {
    responderErro(400, 'prazo_dias deve ser um número de 0 a 365.');
}
$validade = $dados['validade_ate'] ?? null;
if ($validade === '' || $validade === null) {
    $validade = null; // o banco aplica o padrão de 7 dias
} else {
    $d = DateTime::createFromFormat('Y-m-d', $validade);
    if (!$d || $d->format('Y-m-d') !== $validade) {
        responderErro(400, 'validade_ate deve estar no formato AAAA-MM-DD.');
    }
    $stmt = $pdo->query('SELECT CURDATE() AS hoje');
    if ($validade < $stmt->fetch()['hoje']) {
        responderErro(400, 'validade_ate não pode estar no passado.');
    }
}
$detalhes = trim($dados['detalhes'] ?? '');
if (mb_strlen($detalhes) > 1000) {
    responderErro(400, 'detalhes deve ter no máximo 1000 caracteres.');
}

$stmt = $pdo->prepare("
    UPDATE orcamentos
    SET status = 'respondido', valor_total = :total, valor_pecas = :pecas, valor_mao_obra = :mao,
        prazo_dias = :prazo, validade_ate = IFNULL(:validade, DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
        detalhes = :detalhes, respondido_em = NOW()
    WHERE id_orcamento = :id
");
$stmt->execute([
    'total'    => $valores['valor_total'],
    'pecas'    => $valores['valor_pecas'],
    'mao'      => $valores['valor_mao_obra'],
    'prazo'    => $prazo !== null ? (int) $prazo : null,
    'validade' => $validade,
    'detalhes' => $detalhes !== '' ? $detalhes : null,
    'id'       => $idOrcamento,
]);
echo json_encode(['sucesso' => true]);
