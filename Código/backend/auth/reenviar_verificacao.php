<?php
/**
 * MecOn - POST /backend/auth/reenviar_verificacao.php
 * Reenvia o email de confirmação para o usuário logado, caso ele não
 * tenha recebido ou o link anterior tenha expirado. Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/email.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT email_verificado FROM usuarios WHERE id_usuario = :id');
$stmt->execute(['id' => $usuario['id_usuario']]);
$registro = $stmt->fetch();
if ($registro && $registro['email_verificado']) {
    responderErro(400, 'Este email já foi confirmado.');
}
// Remove tokens antigos pendentes antes de gerar um novo, evitando que
// vários links antigos continuem válidos ao mesmo tempo
$stmt = $pdo->prepare('DELETE FROM tokens_verificacao_email WHERE id_usuario = :id');
$stmt->execute(['id' => $usuario['id_usuario']]);
$tokenVerificacao = gerarEEnviarTokenVerificacao($pdo, $usuario['id_usuario'], $usuario['email'], $usuario['nome']);
$resposta = ['sucesso' => true, 'mensagem' => 'Email de verificação reenviado.'];
if (EMAIL_MODO_DEV) {
    $resposta['debug_link_verificacao'] = "http://localhost/mecon/frontend/pages/verificar-email.html?token={$tokenVerificacao}";
}
echo json_encode($resposta);
