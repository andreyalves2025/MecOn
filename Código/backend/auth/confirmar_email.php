<?php
/**
 * MecOn - GET /backend/auth/confirmar_email.php?token=...
 * Chamado pela página verificar-email.html quando o usuário clica no link
 * recebido por email. Token de uso único, expira em 24h (ver
 * migrations/009_verificacao_email.sql).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$token = $_GET['token'] ?? '';
if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    responderErro(400, 'Token inválido.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_usuario, expira_em FROM tokens_verificacao_email WHERE token = :token');
$stmt->execute(['token' => $token]);
$registro = $stmt->fetch();
if (!$registro) {
    responderErro(404, 'Link de verificação inválido ou já utilizado.');
}
if (new DateTime($registro['expira_em']) < new DateTime()) {
    // Limpa o token expirado; usuário deve pedir um novo
    $stmt = $pdo->prepare('DELETE FROM tokens_verificacao_email WHERE token = :token');
    $stmt->execute(['token' => $token]);
    responderErro(410, 'Este link de verificação expirou. Solicite um novo em /auth/reenviar_verificacao.php.');
}
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = :id');
    $stmt->execute(['id' => $registro['id_usuario']]);
    // Token de uso único: remove após confirmar (e remove qualquer outro
    // token pendente do mesmo usuário, caso tenha pedido reenvio antes)
    $stmt = $pdo->prepare('DELETE FROM tokens_verificacao_email WHERE id_usuario = :id');
    $stmt->execute(['id' => $registro['id_usuario']]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao confirmar o email.');
}
// Se o usuário confirmando é o mesmo da sessão atual, atualiza a sessão também
if (isset($_SESSION['id_usuario']) && $_SESSION['id_usuario'] === $registro['id_usuario']) {
    $_SESSION['email_verificado'] = true;
}
echo json_encode(['sucesso' => true, 'mensagem' => 'Email confirmado com sucesso!']);
