<?php
/**
 * MecOn - POST /backend/historico/create_historico.php
 * Body (JSON): { "id_veiculo": 1, "id_oficina": 1, "id_servico": 2, "data_servico": "2026-05-10", "km_servico": 45200 }
 * "km_servico" é opcional. Quando informado e maior que a km_atual
 * registrada no veículo (ou o veículo ainda não tinha km registrada), o
 * veículo é atualizado automaticamente — evita o usuário ter que lembrar
 * de atualizar os dois lugares.
 * Valores financeiros opcionais (ver migrations/018_historico_financeiro.sql):
 * "valor_total" (o que foi pago), "valor_pecas" e "valor_mao_obra"
 * (detalhamento). Se só as partes forem informadas, o total é a soma.
 * "pecas" (opcional): lista de { nome, quantidade, valor_unitario } — ver
 * config/pecas.php. Se valor_pecas não for informado e as peças tiverem
 * valor unitário, valor_pecas é calculado pela soma.
 * Requer login. O veículo informado deve pertencer ao usuário logado.
 * O histórico só pode referenciar oficinas/serviços já cadastrados.
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
$idVeiculo   = $dados['id_veiculo'] ?? null;
$idOficina   = $dados['id_oficina'] ?? null;
$idServico   = $dados['id_servico'] ?? null;
$dataServico = $dados['data_servico'] ?? null;
$kmServico   = $dados['km_servico'] ?? null;
if (!is_numeric($idVeiculo) || !is_numeric($idOficina) || !is_numeric($idServico) || empty($dataServico)) {
    responderErro(400, 'id_veiculo, id_oficina, id_servico e data_servico são obrigatórios.');
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
// Garante que o veículo pertence ao usuário logado
$stmt = $pdo->prepare('SELECT id_veiculo, km_atual FROM veiculos WHERE id_veiculo = :id AND id_usuario = :id_usuario');
$stmt->execute(['id' => $idVeiculo, 'id_usuario' => $usuario['id_usuario']]);
$veiculo = $stmt->fetch();
if (!$veiculo) {
    responderErro(404, 'Veículo não encontrado.');
}
// Valida existência de oficina e serviço antes de inserir
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
    INSERT INTO historico_manutencoes
        (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra)
    VALUES
        (:id_veiculo, :id_oficina, :id_servico, :data_servico, :km_servico, :valor_total, :valor_pecas, :valor_mao_obra)
");
$stmt->execute([
    'id_veiculo'     => $idVeiculo,
    'id_oficina'     => $idOficina,
    'id_servico'     => $idServico,
    'data_servico'   => $dataServico,
    'km_servico'     => $kmServico !== null ? (int) $kmServico : null,
    'valor_total'    => $valores['valor_total'],
    'valor_pecas'    => $valores['valor_pecas'],
    'valor_mao_obra' => $valores['valor_mao_obra'],
]);
$idHistorico = (int) $pdo->lastInsertId();
if (!empty($pecas)) {
    gravarPecas($pdo, $idHistorico, $pecas);
}
if ($kmServico !== null && ($veiculo['km_atual'] === null || (int) $kmServico > (int) $veiculo['km_atual'])) {
    $stmt = $pdo->prepare('UPDATE veiculos SET km_atual = :km WHERE id_veiculo = :id');
    $stmt->execute(['km' => (int) $kmServico, 'id' => $idVeiculo]);
}
$pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao registrar manutenção.');
}
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_historico' => $idHistorico]);
