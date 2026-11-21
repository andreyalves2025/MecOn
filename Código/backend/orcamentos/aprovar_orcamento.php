<?php
/**
 * MecOn - POST /backend/orcamentos/aprovar_orcamento.php
 * Body (JSON): { "id_orcamento": 1, "data_hora_solicitada": "2026-11-20 14:00:00",
 *                "id_servico": 3, "mensagem_usuario": "..." }
 *
 * O cliente aprova UM orçamento do seu pedido e já solicita o agendamento
 * na oficina escolhida. Em uma só transação:
 *   - o orçamento vira "aprovado"; os demais do mesmo pedido ainda em aberto
 *     viram "nao_escolhido"; o pedido vira "aprovado";
 *   - é criado um agendamento "pendente" ligado ao orçamento (a oficina ainda
 *     aceita ou recusa o horário, como em qualquer agendamento).
 * "id_servico" só é necessário se o pedido foi enviado sem serviço do
 * catálogo (descrição livre): o agendamento precisa de um serviço, e a
 * oficina precisa oferecê-lo. Orçamento vencido (validade_ate passou) não
 * pode ser aprovado. Requer login com email confirmado.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$pdo = getConnection();
$usuario = exigirEmailVerificado($pdo);
$dados = json_decode(file_get_contents('php://input'), true);
$idOrcamento = $dados['id_orcamento'] ?? null;
if (!is_numeric($idOrcamento)) {
    responderErro(400, 'id_orcamento é obrigatório e deve ser numérico.');
}
$dataHora = DateTime::createFromFormat('Y-m-d H:i:s', $dados['data_hora_solicitada'] ?? '');
if (!$dataHora) {
    responderErro(400, 'data_hora_solicitada é obrigatória, no formato AAAA-MM-DD HH:MM:SS.');
}
if ($dataHora < new DateTime()) {
    responderErro(400, 'A data e hora solicitadas precisam ser no futuro.');
}
$mensagem = trim($dados['mensagem_usuario'] ?? '');
if (mb_strlen($mensagem) > 400) {
    responderErro(400, 'mensagem_usuario deve ter no máximo 400 caracteres.');
}

$stmt = $pdo->prepare("
    SELECT o.id_orcamento, o.id_oficina, o.status, o.valor_total,
           (o.validade_ate IS NOT NULL AND o.validade_ate < CURDATE()) AS expirado,
           p.id_pedido, p.id_veiculo, p.id_servico, p.status AS status_pedido
    FROM orcamentos o
    JOIN pedidos_orcamento p ON p.id_pedido = o.id_pedido
    WHERE o.id_orcamento = :id AND p.id_usuario = :u
");
$stmt->execute(['id' => $idOrcamento, 'u' => $usuario['id_usuario']]);
$orc = $stmt->fetch();
if (!$orc) {
    responderErro(404, 'Orçamento não encontrado.');
}
if ($orc['status_pedido'] !== 'aberto' || $orc['status'] !== 'respondido') {
    responderErro(409, 'Só é possível aprovar um orçamento já respondido de um pedido ainda aberto.');
}
if ($orc['expirado']) {
    responderErro(410, 'Este orçamento venceu. Peça à oficina um novo orçamento.');
}

$idServico = $orc['id_servico'] !== null ? (int) $orc['id_servico'] : ($dados['id_servico'] ?? null);
if ($idServico === null || !is_numeric($idServico)) {
    responderErro(400, 'Informe o serviço (id_servico) para agendar: o pedido foi enviado sem serviço do catálogo.');
}
$stmt = $pdo->prepare('SELECT 1 FROM oficina_servico WHERE id_oficina = :o AND id_servico = :s');
$stmt->execute(['o' => $orc['id_oficina'], 's' => $idServico]);
if (!$stmt->fetch()) {
    responderErro(400, 'A oficina deste orçamento não oferece o serviço informado.');
}
if ($mensagem === '') {
    $mensagem = 'Orçamento aprovado (R$ ' . number_format((float) $orc['valor_total'], 2, ',', '.') . ')';
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'aprovado' WHERE id_orcamento = :id");
    $stmt->execute(['id' => $idOrcamento]);
    $stmt = $pdo->prepare("
        UPDATE orcamentos SET status = 'nao_escolhido'
        WHERE id_pedido = :p AND id_orcamento <> :id AND status IN ('pendente', 'respondido')
    ");
    $stmt->execute(['p' => $orc['id_pedido'], 'id' => $idOrcamento]);
    $stmt = $pdo->prepare("UPDATE pedidos_orcamento SET status = 'aprovado' WHERE id_pedido = :p");
    $stmt->execute(['p' => $orc['id_pedido']]);
    $stmt = $pdo->prepare('
        INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, id_orcamento, data_hora_solicitada, mensagem_usuario)
        VALUES (:u, :o, :s, :v, :orc, :dh, :m)
    ');
    $stmt->execute([
        'u'   => $usuario['id_usuario'],
        'o'   => $orc['id_oficina'],
        's'   => (int) $idServico,
        'v'   => $orc['id_veiculo'],
        'orc' => $idOrcamento,
        'dh'  => $dataHora->format('Y-m-d H:i:s'),
        'm'   => $mensagem,
    ]);
    $idAgendamento = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao aprovar o orçamento.');
}
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_agendamento' => $idAgendamento]);
