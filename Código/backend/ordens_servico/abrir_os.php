<?php
/**
 * MecOn - POST /backend/ordens_servico/abrir_os.php
 * Body (JSON): { "id_agendamento": 1, "responsavel": "Carlos", "km_entrada": 45200,
 *                "diagnostico": "..." }
 * A oficina abre a ordem de serviço de um agendamento ACEITO (uma OS por
 * agendamento). Se o agendamento veio de um orçamento aprovado, a mão de
 * obra do orçamento já entra como ponto de partida. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/os.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficina = exigirOficina();
$dados = json_decode(file_get_contents('php://input'), true);
$idAgendamento = $dados['id_agendamento'] ?? null;
if (!is_numeric($idAgendamento)) {
    responderErro(400, 'id_agendamento é obrigatório e deve ser numérico.');
}
$responsavel = trim($dados['responsavel'] ?? '');
if (mb_strlen($responsavel) > 100) {
    responderErro(400, 'responsavel deve ter no máximo 100 caracteres.');
}
$kmEntrada = $dados['km_entrada'] ?? null;
if ($kmEntrada === '' || $kmEntrada === null) {
    $kmEntrada = null;
} elseif (!is_numeric($kmEntrada) || (int) $kmEntrada < 0) {
    responderErro(400, 'km_entrada deve ser um número maior ou igual a zero.');
}
$diagnostico = trim($dados['diagnostico'] ?? '');
if (mb_strlen($diagnostico) > 5000) {
    responderErro(400, 'diagnostico deve ter no máximo 5000 caracteres.');
}

$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT ag.status, orc.valor_mao_obra AS mao_orcamento
    FROM agendamentos ag
    LEFT JOIN orcamentos orc ON orc.id_orcamento = ag.id_orcamento
    WHERE ag.id_agendamento = :id AND ag.id_oficina = :o
");
$stmt->execute(['id' => $idAgendamento, 'o' => $oficina['id_oficina']]);
$ag = $stmt->fetch();
if (!$ag) {
    responderErro(404, 'Agendamento não encontrado.');
}
if ($ag['status'] !== 'aceito') {
    responderErro(409, 'A ordem de serviço só pode ser aberta para agendamentos aceitos.');
}
try {
    $stmt = $pdo->prepare('
        INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, diagnostico, valor_mao_obra)
        VALUES (:ag, :o, :resp, :km, :diag, :mao)
    ');
    $stmt->execute([
        'ag'   => $idAgendamento,
        'o'    => $oficina['id_oficina'],
        'resp' => $responsavel !== '' ? $responsavel : null,
        'km'   => $kmEntrada !== null ? (int) $kmEntrada : null,
        'diag' => $diagnostico !== '' ? $diagnostico : null,
        'mao'  => $ag['mao_orcamento'],
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        responderErro(409, 'Este agendamento já tem uma ordem de serviço.');
    }
    responderErro(500, 'Erro ao abrir a ordem de serviço.');
}
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_os' => (int) $pdo->lastInsertId()]);
