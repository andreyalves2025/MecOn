<?php
/**
 * MecOn - POST /backend/usuarios/update_usuario.php
 * Body (JSON): { "id_usuario": 1, "nome": "...", "email": "...", "senha": "...", "tipo": "cliente" }
 *
 * Regras de autorização:
 *   - Um usuário logado pode editar nome/email/senha do PRÓPRIO cadastro.
 *   - Apenas administradores podem editar o campo "tipo" (promover/rebaixar)
 *     ou editar o cadastro de OUTRO usuário.
 *
 * Todos os campos são opcionais, exceto id_usuario; envie apenas o que deseja alterar.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/auditoria.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuarioLogado = exigirLogin();
$dados     = json_decode(file_get_contents('php://input'), true);
$idUsuario = $dados['id_usuario'] ?? null;
if (!is_numeric($idUsuario)) {
    responderErro(400, 'id_usuario é obrigatório e deve ser numérico.');
}
$ehDono  = ((int) $idUsuario === (int) $usuarioLogado['id_usuario']);
$ehAdmin = ($usuarioLogado['tipo'] === 'admin');
if (!$ehDono && !$ehAdmin) {
    responderErro(403, 'Você só pode editar o seu próprio cadastro.');
}
if (isset($dados['tipo']) && !$ehAdmin) {
    responderErro(403, 'Apenas administradores podem alterar o tipo de usuário.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_usuario, nome, email, tipo FROM usuarios WHERE id_usuario = :id');
$stmt->execute(['id' => $idUsuario]);
$registroAnterior = $stmt->fetch();
if (!$registroAnterior) {
    responderErro(404, 'Usuário não encontrado.');
}
$campos     = [];
$parametros = ['id' => $idUsuario];
if (isset($dados['nome']) && trim($dados['nome']) !== '') {
    $campos[] = 'nome = :nome';
    $parametros['nome'] = trim($dados['nome']);
}
if (isset($dados['email']) && trim($dados['email']) !== '') {
    $email = trim($dados['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderErro(400, 'Email inválido.');
    }
    $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email AND id_usuario != :id');
    $stmt->execute(['email' => $email, 'id' => $idUsuario]);
    if ($stmt->fetch()) {
        responderErro(409, 'Este email já está em uso por outro usuário.');
    }
    $campos[] = 'email = :email';
    $parametros['email'] = $email;
}
if (isset($dados['senha']) && $dados['senha'] !== '') {
    if (strlen($dados['senha']) < 6) {
        responderErro(400, 'A senha deve ter pelo menos 6 caracteres.');
    }
    $campos[] = 'senha_hash = :senha_hash';
    $parametros['senha_hash'] = password_hash($dados['senha'], PASSWORD_BCRYPT);
}
if (isset($dados['tipo'])) {
    if (!in_array($dados['tipo'], ['cliente', 'admin'], true)) {
        responderErro(400, "tipo deve ser 'cliente' ou 'admin'.");
    }
    $campos[] = 'tipo = :tipo';
    $parametros['tipo'] = $dados['tipo'];
}
if (empty($campos)) {
    responderErro(400, 'Nenhum campo válido informado para atualização.');
}
$sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id_usuario = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
// Histórico de alterações: só registra quando é o ADMIN editando o
// cadastro de OUTRA pessoa (autoedição não é uma ação administrativa a
// auditar). Senha nunca entra no histórico (só o hash mudaria de valor,
// sem nenhum significado legível).
if ($ehAdmin && !$ehDono) {
    $detalhesCampos = [];
    foreach (['nome', 'email', 'tipo'] as $campo) {
        if (isset($parametros[$campo])) {
            $detalhesCampos[$campo] = ['anterior' => $registroAnterior[$campo], 'novo' => $parametros[$campo]];
        }
    }
    if (isset($parametros['senha_hash'])) {
        // valores propositalmente diferentes (não são o hash real) só para o
        // helper não descartar a linha por "não mudou" — o objetivo aqui é
        // só documentar QUE a senha foi trocada, nunca expor hash algum
        $detalhesCampos['senha'] = ['anterior' => '(oculto)', 'novo' => '(alterada pelo admin)'];
    }
    registrarLogAuditoria($pdo, $usuarioLogado['id_usuario'], 'editar', 'usuario', (int) $idUsuario, null, $detalhesCampos);
}
// Mantém a sessão sincronizada se o próprio usuário alterou nome/email
if ($ehDono) {
    if (isset($parametros['nome'])) {
        $_SESSION['nome'] = $parametros['nome'];
    }
    if (isset($parametros['email'])) {
        $_SESSION['email'] = $parametros['email'];
    }
}
echo json_encode(['sucesso' => true]);
