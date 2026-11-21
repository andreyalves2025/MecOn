<?php
/**
 * MecOn - POST /backend/promocoes/update_promocao.php
 * Body (JSON): { "id_promocao": 1, "titulo": "...", "descricao": "...",
 *                "data_inicio": "AAAA-MM-DD", "data_fim": "AAAA-MM-DD",
 *                "id_servico": 3 | null }
 * Todos os campos exceto id_promocao são opcionais (envie só o que quiser
 * mudar). Requer login de oficina; só edita promoção da própria oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$dados       = json_decode(file_get_contents('php://input'), true);
$idPromocao  = $dados['id_promocao'] ?? null;
if (!is_numeric($idPromocao)) {
    responderErro(400, 'id_promocao é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT * FROM promocoes WHERE id_promocao = :id AND id_oficina = :id_oficina');
$stmt->execute(['id' => $idPromocao, 'id_oficina' => $oficinaSessao['id_oficina']]);
$promocaoAtual = $stmt->fetch();
if (!$promocaoAtual) {
    responderErro(404, 'Promoção não encontrada.');
}

$titulo     = array_key_exists('titulo', $dados) ? trim($dados['titulo']) : $promocaoAtual['titulo'];
$descricao  = array_key_exists('descricao', $dados) ? trim($dados['descricao']) : ($promocaoAtual['descricao'] ?? '');
$dataInicio = $dados['data_inicio'] ?? $promocaoAtual['data_inicio'];
$dataFim    = $dados['data_fim'] ?? $promocaoAtual['data_fim'];
$idServico  = array_key_exists('id_servico', $dados) ? $dados['id_servico'] : $promocaoAtual['id_servico'];

if ($titulo === '' || mb_strlen($titulo) > 150) {
    responderErro(400, 'titulo não pode ser vazio e deve ter no máximo 150 caracteres.');
}
$formatoData = 'Y-m-d';
$inicio = DateTime::createFromFormat($formatoData, $dataInicio);
$fim    = DateTime::createFromFormat($formatoData, $dataFim);
if (!$inicio || !$fim) {
    responderErro(400, 'data_inicio e data_fim devem estar no formato AAAA-MM-DD.');
}
if ($fim < $inicio) {
    responderErro(400, 'data_fim não pode ser anterior a data_inicio.');
}
if ($idServico !== null) {
    if (!is_numeric($idServico)) {
        responderErro(400, 'id_servico deve ser numérico ou null.');
    }
    $stmt = $pdo->prepare('SELECT 1 FROM oficina_servico WHERE id_oficina = :id_oficina AND id_servico = :id_servico');
    $stmt->execute(['id_oficina' => $oficinaSessao['id_oficina'], 'id_servico' => $idServico]);
    if (!$stmt->fetch()) {
        responderErro(400, 'Esta oficina não oferece o serviço informado em id_servico.');
    }
}

$stmt = $pdo->prepare('
    UPDATE promocoes
    SET titulo = :titulo, descricao = :descricao, data_inicio = :data_inicio,
        data_fim = :data_fim, id_servico = :id_servico
    WHERE id_promocao = :id
');
$stmt->execute([
    'titulo'      => $titulo,
    'descricao'   => $descricao !== '' ? $descricao : null,
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'id_servico'  => $idServico !== null ? (int) $idServico : null,
    'id'          => $idPromocao,
]);
echo json_encode(['sucesso' => true]);
