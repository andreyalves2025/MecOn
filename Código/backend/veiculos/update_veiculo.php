<?php
/**
 * MecOn - POST /backend/veiculos/update_veiculo.php
 * Body (JSON): { "id_veiculo": 1, "placa": "ABC1D23", "km_atual": 46200 }
 * Ambos os campos são opcionais (envie só o que quiser mudar). km_atual
 * não pode retroceder (não é possível "diminuir" a km de um veículo —
 * evita erro de digitação silenciosamente apagar o valor real).
 * Requer login. Só edita veículo do próprio usuário.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados     = json_decode(file_get_contents('php://input'), true);
$idVeiculo = $dados['id_veiculo'] ?? null;
if (!is_numeric($idVeiculo)) {
    responderErro(400, 'id_veiculo é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT km_atual FROM veiculos WHERE id_veiculo = :id AND id_usuario = :id_usuario');
$stmt->execute(['id' => $idVeiculo, 'id_usuario' => $usuario['id_usuario']]);
$veiculoAtual = $stmt->fetch();
if (!$veiculoAtual) {
    responderErro(404, 'Veículo não encontrado.');
}
$campos     = [];
$parametros = ['id' => $idVeiculo];
if (isset($dados['placa'])) {
    $placa = trim($dados['placa']);
    if ($placa === '' || strlen($placa) > 10) {
        responderErro(400, 'Placa inválida.');
    }
    $campos[] = 'placa = :placa';
    $parametros['placa'] = placaCifrar($placa);
    $placaNova = $parametros['placa'];
}
if (array_key_exists('km_atual', $dados) && $dados['km_atual'] !== null) {
    $kmAtual = $dados['km_atual'];
    if (!is_numeric($kmAtual) || (int) $kmAtual < 0) {
        responderErro(400, 'km_atual deve ser um número maior ou igual a zero.');
    }
    if ($veiculoAtual['km_atual'] !== null && (int) $kmAtual < (int) $veiculoAtual['km_atual']) {
        responderErro(400, 'km_atual não pode ser menor que a quilometragem já registrada (' . $veiculoAtual['km_atual'] . ' km).');
    }
    $campos[] = 'km_atual = :km_atual';
    $parametros['km_atual'] = (int) $kmAtual;
}
if (empty($campos)) {
    responderErro(400, 'Informe placa e/ou km_atual para atualizar.');
}
$sql = 'UPDATE veiculos SET ' . implode(', ', $campos) . ' WHERE id_veiculo = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
if (isset($placaNova)) {
    // Correção de placa: atualiza a cópia dos agendamentos ainda em aberto (os concluídos mantêm a placa da época).
    $pdo->prepare("UPDATE agendamentos SET placa_snapshot = :p WHERE id_veiculo = :id AND status IN ('pendente', 'aceito')")
        ->execute(['p' => $placaNova, 'id' => $idVeiculo]);
}
echo json_encode(['sucesso' => true]);
