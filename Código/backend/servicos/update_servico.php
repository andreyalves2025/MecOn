<?php
/**
 * MecOn - POST /backend/servicos/update_servico.php
 * Body (JSON): { "id_servico": 1, "nome": "...", "ativo": true|false }
 * "nome" e "ativo" são independentes e opcionais (envie só o que quiser mudar).
 * Desativar (ativo: false) é a forma recomendada de "remover" um serviço que
 * já está em uso por alguma oficina ou histórico — ver delete_servico.php.
 * Uso exclusivo de administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$dados = json_decode(file_get_contents('php://input'), true);
$idServico = $dados['id_servico'] ?? null;
if (!is_numeric($idServico)) {
    responderErro(400, 'id_servico é obrigatório e deve ser numérico.');
}
$campos     = [];
$parametros = ['id' => $idServico];
if (isset($dados['nome'])) {
    $nome = trim($dados['nome']);
    if ($nome === '') {
        responderErro(400, 'nome não pode ser vazio.');
    }
    if (mb_strlen($nome) > 100) {
        responderErro(400, 'nome deve ter no máximo 100 caracteres.');
    }
    $campos[] = 'nome = :nome';
    $parametros['nome'] = $nome;
}
if (isset($dados['ativo'])) {
    $campos[] = 'ativo = :ativo';
    $parametros['ativo'] = $dados['ativo'] ? 1 : 0;
}
if (array_key_exists('intervalo_recomendado_meses', $dados)) {
    $intervalo = $dados['intervalo_recomendado_meses'];
    if ($intervalo !== null && (!is_numeric($intervalo) || (int) $intervalo <= 0)) {
        responderErro(400, 'intervalo_recomendado_meses deve ser um número inteiro positivo, ou null.');
    }
    $campos[] = 'intervalo_recomendado_meses = :intervalo';
    $parametros['intervalo'] = $intervalo !== null ? (int) $intervalo : null;
}
if (array_key_exists('intervalo_recomendado_km', $dados)) {
    $intervaloKm = $dados['intervalo_recomendado_km'];
    if ($intervaloKm !== null && (!is_numeric($intervaloKm) || (int) $intervaloKm <= 0)) {
        responderErro(400, 'intervalo_recomendado_km deve ser um número inteiro positivo, ou null.');
    }
    $campos[] = 'intervalo_recomendado_km = :intervalo_km';
    $parametros['intervalo_km'] = $intervaloKm !== null ? (int) $intervaloKm : null;
}
if (empty($campos)) {
    responderErro(400, 'Informe nome e/ou ativo para atualizar.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_servico FROM servicos WHERE id_servico = :id');
$stmt->execute(['id' => $idServico]);
if (!$stmt->fetch()) {
    responderErro(404, 'Serviço não encontrado.');
}
try {
    $sql = 'UPDATE servicos SET ' . implode(', ', $campos) . ' WHERE id_servico = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        responderErro(409, 'Já existe um serviço cadastrado com esse nome.');
    }
    responderErro(500, 'Erro ao atualizar serviço.');
}
echo json_encode(['sucesso' => true]);
