<?php
/**
 * MecOn - POST /backend/orcamentos/cancelar_pedido.php
 * Body (JSON): { "id_pedido": 1 }
 * O cliente cancela um pedido ainda aberto: o pedido e todos os seus
 * orçamentos em andamento (pendentes ou respondidos) viram "cancelado".
 * Requer login. Pedido de outro usuário responde 404.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$dados = json_decode(file_get_contents('php://input'), true);
$idPedido = $dados['id_pedido'] ?? null;
if (!is_numeric($idPedido)) {
    responderErro(400, 'id_pedido é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT status FROM pedidos_orcamento WHERE id_pedido = :p AND id_usuario = :u');
$stmt->execute(['p' => $idPedido, 'u' => $usuario['id_usuario']]);
$pedido = $stmt->fetch();
if (!$pedido) {
    responderErro(404, 'Pedido não encontrado.');
}
if ($pedido['status'] !== 'aberto') {
    responderErro(409, 'Só é possível cancelar um pedido ainda aberto.');
}
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE pedidos_orcamento SET status = 'cancelado' WHERE id_pedido = :p");
    $stmt->execute(['p' => $idPedido]);
    $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'cancelado' WHERE id_pedido = :p AND status IN ('pendente', 'respondido')");
    $stmt->execute(['p' => $idPedido]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao cancelar o pedido.');
}
echo json_encode(['sucesso' => true]);
