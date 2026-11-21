<?php
/**
 * MecOn - GET /backend/oficina_auth/get_cliente.php?id_usuario=1
 *
 * Ficha de um cliente da oficina: contato, linha do tempo de agendamentos
 * (serviço, veículo, data, status, valor), veículos, serviços realizados
 * e sugestões de retorno. Só funciona para clientes que têm ao menos um
 * agendamento com a oficina logada — para qualquer outro usuário a
 * resposta é 404, sem revelar se o usuário existe na plataforma.
 * Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/crm.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$idOficina = (int) $oficinaSessao['id_oficina'];
$idUsuario = $_GET['id_usuario'] ?? null;
if (!is_numeric($idUsuario)) {
    responderErro(400, 'id_usuario é obrigatório e deve ser numérico.');
}
$idUsuario = (int) $idUsuario;
$pdo = getConnection();

$stmt = $pdo->prepare("
    SELECT
        ag.id_agendamento, ag.data_hora_solicitada, ag.status,
        ag.mensagem_usuario, ag.resposta_oficina, ag.valor_total,
        s.nome AS nome_servico, ag.placa_snapshot AS placa
    FROM agendamentos ag
    JOIN servicos s ON s.id_servico = ag.id_servico
    WHERE ag.id_oficina = :id_oficina AND ag.id_usuario = :id_usuario
    ORDER BY ag.data_hora_solicitada DESC
");
$stmt->execute(['id_oficina' => $idOficina, 'id_usuario' => $idUsuario]);
$agendamentos = $stmt->fetchAll();
if (empty($agendamentos)) {
    responderErro(404, 'Cliente não encontrado.');
}

$stmt = $pdo->prepare('SELECT id_usuario, nome, email FROM usuarios WHERE id_usuario = :id');
$stmt->execute(['id' => $idUsuario]);
$cliente = $stmt->fetch();

$totalGasto = 0.0;
$concluidos = 0;
foreach ($agendamentos as &$ag) {
    $ag['placa'] = placaParaOficina($ag['placa'], $ag['status']);
    $ag['id_agendamento'] = (int) $ag['id_agendamento'];
    $ag['valor_total'] = $ag['valor_total'] !== null ? (float) $ag['valor_total'] : null;
    if ($ag['status'] === 'concluido') {
        $concluidos++;
        $totalGasto += $ag['valor_total'] ?? 0;
    }
}
unset($ag);

$extras   = obterVeiculosEServicos($pdo, $idOficina, [$idUsuario]);
$retornos = obterRetornosSugeridos($pdo, $idOficina, [$idUsuario]);

echo json_encode([
    'sucesso' => true,
    'cliente' => [
        'id_usuario'          => (int) $cliente['id_usuario'],
        'nome'                => $cliente['nome'],
        'email'               => $cliente['email'],
        'total_agendamentos'  => count($agendamentos),
        'concluidos'          => $concluidos,
        'total_gasto'         => round($totalGasto, 2),
        'veiculos'            => $extras[$idUsuario]['veiculos'] ?? [],
        'servicos_realizados' => $extras[$idUsuario]['servicos'] ?? [],
        'retornos_sugeridos'  => $retornos[$idUsuario] ?? [],
        'agendamentos'        => $agendamentos,
    ],
]);
