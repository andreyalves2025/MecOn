<?php
/**
 * MecOn - POST /backend/auth/login.php
 * Body (JSON): { "email": "...", "senha": "..." }
 *
 * Login unificado: tenta autenticar primeiro contra a tabela "usuarios"
 * (clientes e admins); se não encontrar correspondência, tenta contra a
 * tabela "oficinas" (login próprio das oficinas cadastradas pelo admin).
 *
 * A resposta inclui "tipo_conta": "usuario" ou "oficina", para o frontend
 * saber para qual página de perfil redirecionar.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/rate_limit.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$dados = json_decode(file_get_contents('php://input'), true);
$email = trim($dados['email'] ?? '');
$senha = $dados['senha'] ?? '';
if ($email === '' || $senha === '') {
    responderErro(400, 'Informe email e senha.');
}
$email = mb_strtolower($email);
$pdo = getConnection();
$segundosBloqueado = verificarBloqueioLogin($pdo, $email);
if ($segundosBloqueado !== null) {
    $minutosRestantes = (int) ceil($segundosBloqueado / 60);
    responderErro(
        429,
        "Muitas tentativas de login para este email. Tente novamente em {$minutosRestantes} minuto(s)."
    );
}
// 1ª tentativa: usuário (cliente ou admin)
$stmt = $pdo->prepare(
    'SELECT id_usuario, nome, email, senha_hash, tipo, email_verificado, termos_aceitos_em FROM usuarios WHERE email = :email'
);
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();
if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
    limparTentativasLogin($pdo, $email);
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['nome']       = $usuario['nome'];
    $_SESSION['email']      = $usuario['email'];
    $_SESSION['tipo']       = $usuario['tipo'];
    echo json_encode([
        'sucesso'    => true,
        'tipo_conta' => 'usuario',
        'usuario'    => [
            'id_usuario'       => $usuario['id_usuario'],
            'nome'             => $usuario['nome'],
            'email'            => $usuario['email'],
            'tipo'             => $usuario['tipo'],
            'email_verificado' => (bool) $usuario['email_verificado'],
            'termos_pendentes' => $usuario['termos_aceitos_em'] === null && $usuario['tipo'] === 'cliente',
        ],
    ]);
    exit;
}
// 2ª tentativa: oficina
$stmt = $pdo->prepare(
    'SELECT id_oficina, nome, email, senha_hash, ativa FROM oficinas WHERE email = :email'
);
$stmt->execute(['email' => $email]);
$oficina = $stmt->fetch();
if ($oficina && password_verify($senha, $oficina['senha_hash'])) {
    if (!(int) $oficina['ativa']) {
        responderErro(403, 'Esta oficina foi desativada. Entre em contato com o administrador do MecOn.');
    }
    limparTentativasLogin($pdo, $email);
    session_regenerate_id(true);
    $_SESSION['id_oficina']    = $oficina['id_oficina'];
    $_SESSION['oficina_nome']  = $oficina['nome'];
    $_SESSION['oficina_email'] = $oficina['email'];
    echo json_encode([
        'sucesso'    => true,
        'tipo_conta' => 'oficina',
        'oficina'    => [
            'id_oficina' => $oficina['id_oficina'],
            'nome'       => $oficina['nome'],
            'email'      => $oficina['email'],
        ],
    ]);
    exit;
}
registrarFalhaLogin($pdo, $email);
responderErro(401, 'Email ou senha inválidos.');
