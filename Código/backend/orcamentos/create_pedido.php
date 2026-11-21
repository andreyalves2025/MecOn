<?php
/**
 * MecOn - POST /backend/orcamentos/create_pedido.php   (multipart/form-data)
 *
 * Campos:
 *   id_veiculo     (obrigatório) veículo do próprio usuário
 *   id_servico     (opcional)    serviço do catálogo, se o cliente souber qual é
 *   descricao      (obrigatório) o problema, 10 a 2000 caracteres
 *   ids_oficinas[] (obrigatório) de 1 a 3 oficinas que receberão o pedido
 *   anexos[]       (opcional)    até 5 fotos/vídeos (ver config/anexos.php)
 *
 * Um pedido gera um orçamento "pendente" para cada oficina escolhida; cada
 * uma responde separadamente e o cliente compara. Se id_servico for
 * informado, só oficinas que oferecem esse serviço podem ser escolhidas.
 * Requer login com email confirmado.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/anexos.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
verificarLimitePostExcedido();
$pdo = getConnection();
$usuario = exigirEmailVerificado($pdo);

$idVeiculo = $_POST['id_veiculo'] ?? null;
$idServico = $_POST['id_servico'] ?? null;
$descricao = trim($_POST['descricao'] ?? '');
$idsOficinas = $_POST['ids_oficinas'] ?? [];
if (!is_numeric($idVeiculo)) {
    responderErro(400, 'id_veiculo é obrigatório e deve ser numérico.');
}
$temServico = $idServico !== null && $idServico !== '';
if ($temServico && !is_numeric($idServico)) {
    responderErro(400, 'id_servico deve ser numérico.');
}
if (mb_strlen($descricao) < 10 || mb_strlen($descricao) > 2000) {
    responderErro(400, 'Descreva o problema com 10 a 2000 caracteres.');
}
if (!is_array($idsOficinas)) {
    $idsOficinas = [$idsOficinas];
}
$idsOficinas = array_values(array_unique(array_filter($idsOficinas, 'is_numeric')));
if (count($idsOficinas) < 1 || count($idsOficinas) > 3) {
    responderErro(400, 'Escolha de 1 a 3 oficinas para receber o pedido.');
}

$stmt = $pdo->prepare('SELECT 1 FROM veiculos WHERE id_veiculo = :v AND id_usuario = :u');
$stmt->execute(['v' => $idVeiculo, 'u' => $usuario['id_usuario']]);
if (!$stmt->fetch()) {
    responderErro(404, 'Veículo não encontrado.');
}
foreach ($idsOficinas as $idOficina) {
    if ($temServico) {
        $stmt = $pdo->prepare('SELECT 1 FROM oficina_servico os JOIN oficinas o ON o.id_oficina = os.id_oficina WHERE os.id_oficina = :o AND os.id_servico = :s AND o.ativa = 1');
        $stmt->execute(['o' => $idOficina, 's' => $idServico]);
        if (!$stmt->fetch()) {
            responderErro(400, 'Uma das oficinas escolhidas não oferece o serviço informado.');
        }
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM oficinas WHERE id_oficina = :o AND ativa = 1');
        $stmt->execute(['o' => $idOficina]);
        if (!$stmt->fetch()) {
            responderErro(404, 'Uma das oficinas escolhidas não foi encontrada.');
        }
    }
}

$arquivos = validarAnexosEnviados('anexos'); // valida tudo antes de gravar qualquer coisa

$gravados = [];
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('
        INSERT INTO pedidos_orcamento (id_usuario, id_veiculo, id_servico, descricao)
        VALUES (:u, :v, :s, :d)
    ');
    $stmt->execute([
        'u' => $usuario['id_usuario'],
        'v' => $idVeiculo,
        's' => $temServico ? (int) $idServico : null,
        'd' => $descricao,
    ]);
    $idPedido = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('INSERT INTO orcamentos (id_pedido, id_oficina) VALUES (:p, :o)');
    foreach ($idsOficinas as $idOficina) {
        $stmt->execute(['p' => $idPedido, 'o' => (int) $idOficina]);
    }
    $gravados = gravarAnexos($pdo, $idPedido, $arquivos);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    removerArquivosGravados($gravados);
    responderErro(500, 'Erro ao registrar o pedido de orçamento.');
}
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_pedido' => $idPedido, 'anexos' => count($arquivos)]);
