<?php
/**
 * MecOn - POST /backend/ordens_servico/concluir_os.php
 * Body (JSON): { "id_os": 1 }
 *
 * Fecha a ordem de serviço. Só é possível com a etapa "pronta" (a oficina
 * confirma que o serviço terminou) e com o agendamento ainda "aceito".
 * Em uma única transação:
 *   - a OS vira "entregue" (concluida_em = agora);
 *   - o agendamento vira "concluido", com valor_total = total da OS
 *     (é este valor que alimenta o relatório financeiro da oficina);
 *   - o histórico do cliente ganha o registro do serviço, já com km
 *     (km_entrada), valores (peças, mão de obra, total) e as peças usadas,
 *     e a km_atual do veículo sobe se a da OS for maior.
 * Total da OS = peças (quantidade x valor unitário) + mão de obra - desconto.
 * Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/pecas.php';
require_once __DIR__ . '/../config/os.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficina = exigirOficina();
$dados = json_decode(file_get_contents('php://input'), true);
$idOs = $dados['id_os'] ?? null;
if (!is_numeric($idOs)) {
    responderErro(400, 'id_os é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT os.id_os, os.etapa, os.km_entrada, os.valor_mao_obra, os.desconto,
           ag.id_agendamento, ag.id_veiculo, ag.id_servico, ag.status AS status_agendamento,
           v.km_atual
    FROM ordens_servico os
    JOIN agendamentos ag ON ag.id_agendamento = os.id_agendamento
    LEFT JOIN veiculos v ON v.id_veiculo = ag.id_veiculo
    WHERE os.id_os = :id AND os.id_oficina = :o
");
$stmt->execute(['id' => $idOs, 'o' => $oficina['id_oficina']]);
$os = $stmt->fetch();
if (!$os) {
    responderErro(404, 'Ordem de serviço não encontrada.');
}
if ($os['etapa'] === 'entregue') {
    responderErro(409, 'Esta ordem de serviço já foi concluída.');
}
if ($os['etapa'] !== 'pronta') {
    responderErro(409, 'Marque a etapa como "pronta" antes de concluir a ordem de serviço.');
}
if ($os['status_agendamento'] !== 'aceito') {
    responderErro(409, 'O agendamento desta ordem de serviço não está mais aceito.');
}

$itens = listarItensOS($pdo, (int) $idOs);
$totais = calcularTotaisOS(
    $itens,
    $os['valor_mao_obra'] !== null ? (float) $os['valor_mao_obra'] : null,
    $os['desconto'] !== null ? (float) $os['desconto'] : null
);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE ordens_servico SET etapa = 'entregue', concluida_em = NOW() WHERE id_os = :id");
    $stmt->execute(['id' => $idOs]);

    $stmt = $pdo->prepare("UPDATE agendamentos SET status = 'concluido', valor_total = :total WHERE id_agendamento = :id");
    $stmt->execute(['total' => $totais['valor_total'], 'id' => $os['id_agendamento']]);

    // Se o cliente já excluiu a conta/veículo, a OS é fechada normalmente para a
    // oficina, mas não há histórico de veículo para alimentar.
    if ($os['id_veiculo'] !== null) {
    $stmt = $pdo->prepare('
        INSERT INTO historico_manutencoes
            (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra)
        VALUES (:v, :o, :s, CURDATE(), :km, :total, :pecas, :mao)
    ');
    $stmt->execute([
        'v'     => $os['id_veiculo'],
        'o'     => $oficina['id_oficina'],
        's'     => $os['id_servico'],
        'km'    => $os['km_entrada'],
        'total' => $totais['valor_total'],
        'pecas' => $totais['valor_pecas'],
        'mao'   => $totais['valor_mao_obra'],
    ]);
    $idHistorico = (int) $pdo->lastInsertId();
    if (!empty($itens)) {
        gravarPecas($pdo, $idHistorico, $itens);
    }
    }
    if ($os['id_veiculo'] !== null && $os['km_entrada'] !== null && ($os['km_atual'] === null || (int) $os['km_entrada'] > (int) $os['km_atual'])) {
        $stmt = $pdo->prepare('UPDATE veiculos SET km_atual = :km WHERE id_veiculo = :id');
        $stmt->execute(['km' => $os['km_entrada'], 'id' => $os['id_veiculo']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao concluir a ordem de serviço.');
}
echo json_encode(['sucesso' => true, 'valor_total' => $totais['valor_total']]);
