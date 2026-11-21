<?php
/**
 * MecOn - GET /backend/ordens_servico/get_os.php?como=usuario|oficina&id_agendamento=1
 *
 * Ordem de serviço de um agendamento, para o cliente (somente leitura) ou
 * para a oficina (que também a edita). "como" escolhe a sessão a usar
 * (usuário e oficina podem estar logados no mesmo navegador) e é sempre
 * conferido contra a sessão e a posse do agendamento: quem não é parte do
 * agendamento recebe 404. Se o agendamento existe mas ainda não tem OS,
 * "os" vem null. Traz também os dados para impressão (oficina, cliente,
 * veículo, serviço) e os totais calculados.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/pecas.php';
require_once __DIR__ . '/../config/os.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$como = $_GET['como'] ?? null;
if ($como === 'oficina') {
    $idParte = (int) exigirOficina()['id_oficina'];
    $filtroParte = 'ag.id_oficina = :parte';
} elseif ($como === 'usuario') {
    $idParte = (int) exigirLogin()['id_usuario'];
    $filtroParte = 'ag.id_usuario = :parte';
} else {
    responderErro(400, 'O parâmetro "como" deve ser "usuario" ou "oficina".');
}
$idAgendamento = $_GET['id_agendamento'] ?? null;
if (!is_numeric($idAgendamento)) {
    responderErro(400, 'id_agendamento é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT ag.id_agendamento, ag.data_hora_solicitada, ag.status AS status_agendamento,
           s.nome AS nome_servico, ag.placa_snapshot AS placa, v.km_atual,
           COALESCE(u.nome, 'Cliente removido') AS nome_cliente,
           o.nome AS nome_oficina, o.telefone AS telefone_oficina, o.endereco, o.bairro, o.cidade,
           orc.valor_total AS valor_orcamento
    FROM agendamentos ag
    JOIN servicos s ON s.id_servico = ag.id_servico
    LEFT JOIN veiculos v ON v.id_veiculo = ag.id_veiculo
    LEFT JOIN usuarios u ON u.id_usuario = ag.id_usuario
    JOIN oficinas o ON o.id_oficina = ag.id_oficina
    LEFT JOIN orcamentos orc ON orc.id_orcamento = ag.id_orcamento
    WHERE ag.id_agendamento = :id AND $filtroParte
");
$stmt->execute(['id' => $idAgendamento, 'parte' => $idParte]);
$ag = $stmt->fetch();
if (!$ag) {
    responderErro(404, 'Agendamento não encontrado.');
}
$ag['id_agendamento'] = (int) $ag['id_agendamento'];
$ag['placa'] = $como === 'oficina'
    ? placaParaOficina($ag['placa'], $ag['status_agendamento'])
    : placaDecifrar($ag['placa']);
$ag['km_atual'] = $ag['km_atual'] !== null ? (int) $ag['km_atual'] : null;
$ag['valor_orcamento'] = $ag['valor_orcamento'] !== null ? (float) $ag['valor_orcamento'] : null;

$stmt = $pdo->prepare('
    SELECT id_os, responsavel, km_entrada, etapa, diagnostico, observacoes,
           valor_mao_obra, desconto, criado_em, concluida_em
    FROM ordens_servico WHERE id_agendamento = :id
');
$stmt->execute(['id' => $idAgendamento]);
$os = $stmt->fetch();
if ($os) {
    $os['id_os'] = (int) $os['id_os'];
    $os['km_entrada'] = $os['km_entrada'] !== null ? (int) $os['km_entrada'] : null;
    $mao = $os['valor_mao_obra'] !== null ? (float) $os['valor_mao_obra'] : null;
    $desc = $os['desconto'] !== null ? (float) $os['desconto'] : null;
    $os['itens'] = listarItensOS($pdo, $os['id_os']);
    $os['totais'] = calcularTotaisOS($os['itens'], $mao, $desc);
}
echo json_encode(['sucesso' => true, 'agendamento' => $ag, 'os' => $os ?: null]);
