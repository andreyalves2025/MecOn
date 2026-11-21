<?php
/**
 * MecOn - POST /backend/promocoes/create_promocao.php
 * Body (JSON): {
 *   "titulo": "10% de desconto em revisão",
 *   "descricao": "Válido para revisões agendadas até o fim do mês",
 *   "data_inicio": "2026-09-15", "data_fim": "2026-09-30",
 *   "id_servico": 3   // opcional — vincula a promoção a um serviço específico
 * }
 * Requer login de oficina. A promoção é sempre criada para a oficina da
 * sessão (não é possível criar em nome de outra).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$dados      = json_decode(file_get_contents('php://input'), true);
$titulo     = trim($dados['titulo'] ?? '');
$descricao  = trim($dados['descricao'] ?? '');
$dataInicio = $dados['data_inicio'] ?? '';
$dataFim    = $dados['data_fim'] ?? '';
$idServico  = $dados['id_servico'] ?? null;

if ($titulo === '' || mb_strlen($titulo) > 150) {
    responderErro(400, 'titulo é obrigatório e deve ter no máximo 150 caracteres.');
}
$formatoData = 'Y-m-d';
$inicio = DateTime::createFromFormat($formatoData, $dataInicio);
$fim    = DateTime::createFromFormat($formatoData, $dataFim);
if (!$inicio || !$fim) {
    responderErro(400, 'data_inicio e data_fim são obrigatórias, no formato AAAA-MM-DD.');
}
if ($fim < $inicio) {
    responderErro(400, 'data_fim não pode ser anterior a data_inicio.');
}
$pdo = getConnection();
if ($idServico !== null) {
    if (!is_numeric($idServico)) {
        responderErro(400, 'id_servico deve ser numérico.');
    }
    // Confere se a oficina realmente oferece esse serviço (evita promoção
    // vinculada a algo que ela nem presta)
    $stmt = $pdo->prepare('SELECT 1 FROM oficina_servico WHERE id_oficina = :id_oficina AND id_servico = :id_servico');
    $stmt->execute(['id_oficina' => $oficinaSessao['id_oficina'], 'id_servico' => $idServico]);
    if (!$stmt->fetch()) {
        responderErro(400, 'Esta oficina não oferece o serviço informado em id_servico.');
    }
}
$stmt = $pdo->prepare('
    INSERT INTO promocoes (id_oficina, id_servico, titulo, descricao, data_inicio, data_fim)
    VALUES (:id_oficina, :id_servico, :titulo, :descricao, :data_inicio, :data_fim)
');
$stmt->execute([
    'id_oficina'  => $oficinaSessao['id_oficina'],
    'id_servico'  => $idServico !== null ? (int) $idServico : null,
    'titulo'      => $titulo,
    'descricao'   => $descricao !== '' ? $descricao : null,
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
]);
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_promocao' => (int) $pdo->lastInsertId()]);
