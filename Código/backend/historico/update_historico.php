<?php
/**
 * MecOn - POST /backend/historico/update_historico.php
 * Body (JSON): { "id_historico": 1, "id_oficina": 1, "id_servico": 2, "data_servico": "2026-05-10", "km_servico": 45200 }
 * "km_servico" é opcional, com a mesma sincronização automática de
 * create_historico.php: se maior que a km_atual do veículo, atualiza o
 * veículo também.
 * Aceita também os valores financeiros opcionais valor_total, valor_pecas e
 * valor_mao_obra (ver create_historico.php e config/valores.php).
 * "pecas" (opcional): se enviada, SUBSTITUI a lista de peças do registro; se
 * omitida, as peças existentes são mantidas (ver config/pecas.php).
 * Requer login. Só permite editar histórico de um veículo do próprio usuário
 * (posse verificada via JOIN com veiculos, já que o histórico pertence ao
 * veículo, não diretamente ao usuário).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/valores.php';
require_once __DIR__ . '/../config/pecas.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados       = json_decode(file_get_contents('php://input'), true);
$idHistorico = $dados['id_historico'] ?? null;
$idOficina   = $dados['id_oficina'] ?? null;
$idServico   = $dados['id_servico'] ?? null;
$dataServico = $dados['data_servico'] ?? null;
$kmServico   = $dados['km_servico'] ?? null;
if (!is_numeric($idHistorico) || !is_numeric($idOficina) || !is_numeric($idServico) || empty($dataServico)) {
    responderErro(400, 'id_historico, id_oficina, id_servico e data_servico são obrigatórios.');
}
$data = DateTime::createFromFormat('Y-m-d', $dataServico);
if (!$data || $data->format('Y-m-d') !== $dataServico) {
    responderErro(400, 'data_servico deve estar no formato AAAA-MM-DD.');
}
if ($kmServico !== null && (!is_numeric($kmServico) || (int) $kmServico < 0)) {
    responderErro(400, 'km_servico deve ser um número maior ou igual a zero.');
}
$pecas = lerPecas($dados);
if ($pecas !== null && !isset($dados['valor_pecas'])) {
    $somaPecas = somaValorPecas($pecas);
    if ($somaPecas !== null) {
        $dados['valor_pecas'] = $somaPecas;
    }
}
$valores = lerValoresFinanceiros($dados);
$pdo = getConnection();
// Confirma que o registro existe e o veículo pertence ao usuário logado
$stmt = $pdo->prepare("
    SELECT h.id_historico, h.id_veiculo, v.km_atual
    FROM historico_manutencoes h
    JOIN veiculos v ON v.id_veiculo = h.id_veiculo
    WHERE h.id_historico = :id AND v.id_usuario = :id_usuario
");
$stmt->execute(['id' => $idHistorico, 'id_usuario' => $usuario['id_usuario']]);
$registro = $stmt->fetch();
if (!$registro) {
    responderErro(404, 'Registro de histórico não encontrado.');
}
// Valida existência de oficina e serviço
$stmt = $pdo->prepare('SELECT id_oficina FROM oficinas WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
if (!$stmt->fetch()) {
    responderErro(404, 'Oficina não encontrada.');
}
$stmt = $pdo->prepare('SELECT id_servico FROM servicos WHERE id_servico = :id');
$stmt->execute(['id' => $idServico]);
if (!$stmt->fetch()) {
    responderErro(404, 'Serviço não encontrado.');
}
$pdo->beginTransaction();
try {
$stmt = $pdo->prepare("
    UPDATE historico_manutencoes
    SET id_oficina = :id_oficina, id_servico = :id_servico, data_servico = :data_servico, km_servico = :km_servico,
        valor_total = :valor_total, valor_pecas = :valor_pecas, valor_mao_obra = :valor_mao_obra
    WHERE id_historico = :id_historico
");
$stmt->execute([
    'id_oficina'     => $idOficina,
    'id_servico'     => $idServico,
    'data_servico'   => $dataServico,
    'km_servico'     => $kmServico !== null ? (int) $kmServico : null,
    'valor_total'    => $valores['valor_total'],
    'valor_pecas'    => $valores['valor_pecas'],
    'valor_mao_obra' => $valores['valor_mao_obra'],
    'id_historico'   => $idHistorico,
]);
if ($pecas !== null) {
    gravarPecas($pdo, (int) $idHistorico, $pecas);
}
if ($kmServico !== null && ($registro['km_atual'] === null || (int) $kmServico > (int) $registro['km_atual'])) {
    $stmt = $pdo->prepare('UPDATE veiculos SET km_atual = :km WHERE id_veiculo = :id');
    $stmt->execute(['km' => (int) $kmServico, 'id' => $registro['id_veiculo']]);
}
$pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao atualizar manutenção.');
}
echo json_encode(['sucesso' => true]);
