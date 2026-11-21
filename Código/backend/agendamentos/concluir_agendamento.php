<?php
/**
 * MecOn - POST /backend/agendamentos/concluir_agendamento.php
 * Body (JSON): { "id_agendamento": 1, "valor_total": 280.00, "valor_pecas": 180.00, "valor_mao_obra": 100.00 }
 * "pecas" (opcional): lista de { nome, quantidade, valor_unitario } registrada
 * no histórico do cliente (ver config/pecas.php).
 * Os três valores são opcionais (ver config/valores.php). O valor informado
 * pela oficina fica em agendamentos.valor_total (base dos relatórios
 * financeiros da oficina) e é copiado para o histórico do cliente.
 *
 * Só é permitido concluir agendamentos com status "aceito". Ao concluir,
 * cria automaticamente um registro em historico_manutencoes para o
 * veículo do usuário (data do serviço = hoje) — assim o histórico dele
 * fica em dia sem precisar cadastrar manualmente (ver
 * backend/historico/create_historico.php, que faz a mesma coisa "na mão").
 *
 * Requer login de oficina; só conclui agendamentos da própria oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/valores.php';
require_once __DIR__ . '/../config/pecas.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$dados         = json_decode(file_get_contents('php://input'), true);
$idAgendamento = $dados['id_agendamento'] ?? null;
if (!is_numeric($idAgendamento)) {
    responderErro(400, 'id_agendamento é obrigatório e deve ser numérico.');
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
$stmt = $pdo->prepare('
    SELECT id_veiculo, id_servico, status
    FROM agendamentos
    WHERE id_agendamento = :id AND id_oficina = :id_oficina
');
$stmt->execute(['id' => $idAgendamento, 'id_oficina' => $oficinaSessao['id_oficina']]);
$agendamento = $stmt->fetch();
if (!$agendamento) {
    responderErro(404, 'Agendamento não encontrado.');
}
if ($agendamento['status'] !== 'aceito') {
    responderErro(409, 'Só é possível concluir agendamentos que estejam com status "aceito".');
}
// Se já existe uma ordem de serviço, o fechamento deve passar por ela: é
// ela que carrega diagnóstico, itens e valores finais (ver
// ordens_servico/concluir_os.php). Concluir por aqui ignoraria tudo isso.
$stmt = $pdo->prepare('SELECT 1 FROM ordens_servico WHERE id_agendamento = :id');
$stmt->execute(['id' => $idAgendamento]);
if ($stmt->fetch()) {
    responderErro(409, 'Este agendamento tem uma ordem de serviço. Conclua pela ordem de serviço.');
}
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE agendamentos SET status = 'concluido', valor_total = :valor_total WHERE id_agendamento = :id");
    $stmt->execute(['valor_total' => $valores['valor_total'], 'id' => $idAgendamento]);
    if ($agendamento['id_veiculo'] === null) {
        // Cliente já excluiu a conta/veículo: conclui para a oficina, sem histórico de veículo.
        $pdo->commit();
        echo json_encode(['sucesso' => true]);
        exit;
    }
    $stmt = $pdo->prepare('
        INSERT INTO historico_manutencoes
            (id_veiculo, id_oficina, id_servico, data_servico, valor_total, valor_pecas, valor_mao_obra)
        VALUES
            (:id_veiculo, :id_oficina, :id_servico, CURDATE(), :valor_total, :valor_pecas, :valor_mao_obra)
    ');
    $stmt->execute([
        'id_veiculo'     => $agendamento['id_veiculo'],
        'id_oficina'     => $oficinaSessao['id_oficina'],
        'id_servico'     => $agendamento['id_servico'],
        'valor_total'    => $valores['valor_total'],
        'valor_pecas'    => $valores['valor_pecas'],
        'valor_mao_obra' => $valores['valor_mao_obra'],
    ]);
    if (!empty($pecas)) {
        gravarPecas($pdo, (int) $pdo->lastInsertId(), $pecas);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao concluir agendamento.');
}
echo json_encode(['sucesso' => true]);
