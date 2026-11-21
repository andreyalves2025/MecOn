<?php
/**
 * MecOn - POST /backend/servicos/create_servico.php
 * Body (JSON): { "nome": "Troca de óleo", "intervalo_recomendado_meses": 6, "intervalo_recomendado_km": 10000 }
 * Os dois intervalos são opcionais e independentes; o lembrete dispara pelo que vier primeiro.
 * "intervalo_recomendado_meses" é opcional — quando informado, alimenta os
 * lembretes de manutenção preventiva calculados a partir do histórico do
 * usuário (ver backend/historico/lembretes_manutencao.php).
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
$nome = trim($dados['nome'] ?? '');
$intervalo = $dados['intervalo_recomendado_meses'] ?? null;
$intervaloKm = $dados['intervalo_recomendado_km'] ?? null;
if ($nome === '') {
    responderErro(400, 'nome é obrigatório.');
}
if (mb_strlen($nome) > 100) {
    responderErro(400, 'nome deve ter no máximo 100 caracteres.');
}
if ($intervalo !== null && (!is_numeric($intervalo) || (int) $intervalo <= 0)) {
    responderErro(400, 'intervalo_recomendado_meses deve ser um número inteiro positivo, ou omitido.');
}
if ($intervaloKm !== null && (!is_numeric($intervaloKm) || (int) $intervaloKm <= 0)) {
    responderErro(400, 'intervalo_recomendado_km deve ser um número inteiro positivo, ou omitido.');
}
$pdo = getConnection();
try {
    $stmt = $pdo->prepare('INSERT INTO servicos (nome, ativo, intervalo_recomendado_meses, intervalo_recomendado_km) VALUES (:nome, 1, :intervalo, :intervalo_km)');
    $stmt->execute([
        'nome'         => $nome,
        'intervalo'    => $intervalo !== null ? (int) $intervalo : null,
        'intervalo_km' => $intervaloKm !== null ? (int) $intervaloKm : null,
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        responderErro(409, 'Já existe um serviço cadastrado com esse nome.');
    }
    responderErro(500, 'Erro ao cadastrar serviço.');
}
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_servico' => (int) $pdo->lastInsertId()]);
