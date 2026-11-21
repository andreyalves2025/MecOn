<?php
/**
 * MecOn - POST /backend/auth/register.php
 * Body (JSON): { "nome": "...", "email": "...", "senha": "..." }
 *
 * Sempre cria usuário com tipo "cliente". Administradores são
 * cadastrados diretamente no banco (fora do fluxo público).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../config/lgpd.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$dados = json_decode(file_get_contents('php://input'), true);
$nome  = trim($dados['nome'] ?? '');
$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';
$aceiteTermos = !empty($dados['aceite_termos']);
if ($nome === '' || $email === '' || $senha === '') {
    responderErro(400, 'Informe nome, email e senha.');
}
if (!$aceiteTermos) {
    responderErro(400, 'É necessário aceitar os Termos de Uso e a Política de Privacidade para criar a conta.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderErro(400, 'Email inválido.');
}
if (strlen($senha) < 6) {
    responderErro(400, 'A senha deve ter pelo menos 6 caracteres.');
}
$pdo = getConnection();
// Verifica duplicidade de email antes de tentar inserir
$stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    responderErro(409, 'Este email já está cadastrado.');
}
$senhaHash = password_hash($senha, PASSWORD_BCRYPT);
$stmt = $pdo->prepare(
    'INSERT INTO usuarios (nome, email, senha_hash, tipo, termos_aceitos_em, termos_versao)
     VALUES (:nome, :email, :senha_hash, "cliente", NOW(), :termos_versao)'
);
$stmt->execute([
    'nome'       => $nome,
    'email'      => $email,
    'senha_hash' => $senhaHash,
    'termos_versao' => TERMOS_VERSAO,
]);
$idUsuario = (int) $pdo->lastInsertId();
$tokenVerificacao = gerarEEnviarTokenVerificacao($pdo, $idUsuario, $email, $nome);
// Loga o usuário automaticamente após o cadastro
session_regenerate_id(true);
$_SESSION['id_usuario'] = $idUsuario;
$_SESSION['nome']       = $nome;
$_SESSION['email']      = $email;
$_SESSION['tipo']       = 'cliente';
http_response_code(201);
$resposta = [
    'sucesso' => true,
    'usuario' => [
        'id_usuario'       => $idUsuario,
        'nome'             => $nome,
        'email'            => $email,
        'tipo'             => 'cliente',
        'email_verificado' => false,
    ],
];
// Em modo dev (sem SMTP configurado), devolve o link de verificação junto
// na resposta, só para permitir testar o fluxo completo sem um servidor
// de email de verdade — ver o aviso de segurança em config/email.php.
if (EMAIL_MODO_DEV) {
    $resposta['debug_link_verificacao'] = "http://localhost/mecon/frontend/pages/verificar-email.html?token={$tokenVerificacao}";
}
echo json_encode($resposta);
