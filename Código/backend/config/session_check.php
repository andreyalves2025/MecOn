<?php
/**
 * MecOn - Middleware de sessão e autorização
 *
 * Como usar em qualquer endpoint protegido:
 *
 *   require_once __DIR__ . '/../config/session_check.php';
 *   $usuario = exigirLogin();          // bloqueia se não estiver logado como usuário (cliente/admin)
 *   $usuario = exigirAdmin();          // bloqueia se não for admin
 *   $oficina = exigirOficina();        // bloqueia se a oficina não estiver logada
 *
 * As sessões de usuário (cliente/admin) e de oficina são independentes,
 * guardadas em chaves de sessão distintas ($_SESSION['id_usuario'] vs.
 * $_SESSION['id_oficina']), permitindo que coexistam sem conflito.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Envia uma resposta JSON de erro e termina a execução.
 */
function responderErro(int $codigoHttp, string $mensagem): void {
    http_response_code($codigoHttp);
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'erro' => $mensagem]);
    exit;
}
/**
 * Retorna true se há um usuário (cliente/admin) autenticado na sessão atual.
 */
function estaLogado(): bool {
    return isset($_SESSION['id_usuario']);
}
/**
 * Retorna os dados do usuário logado, ou null se não houver sessão.
 */
function usuarioAtual(): ?array {
    if (!estaLogado()) {
        return null;
    }
    return [
        'id_usuario' => $_SESSION['id_usuario'],
        'nome'       => $_SESSION['nome'],
        'email'      => $_SESSION['email'],
        'tipo'       => $_SESSION['tipo'],
    ];
}
/**
 * Bloqueia o acesso (HTTP 401) se não houver usuário logado.
 * Caso contrário, retorna os dados do usuário.
 */
function exigirLogin(): array {
    $usuario = usuarioAtual();
    if ($usuario === null) {
        responderErro(401, 'É necessário estar logado para acessar este recurso.');
    }
    return $usuario;
}
/**
 * Bloqueia o acesso (HTTP 403) se o usuário logado não for administrador.
 * Caso contrário, retorna os dados do usuário.
 */
function exigirAdmin(): array {
    $usuario = exigirLogin();
    if ($usuario['tipo'] !== 'admin') {
        responderErro(403, 'Acesso restrito a administradores.');
    }
    return $usuario;
}
/**
 * Bloqueia o acesso (HTTP 403) se o usuário logado ainda não confirmou o
 * email (ver migrations/009_verificacao_email.sql). Precisa do $pdo porque
 * a sessão não guarda esse dado (poderia ficar desatualizado se o usuário
 * verificar o email em outra aba sem relogar). Usado em ações sensíveis a
 * contas falsas, como avaliar uma oficina.
 */
function exigirEmailVerificado(PDO $pdo): array {
    $usuario = exigirLogin();
    $stmt = $pdo->prepare('SELECT email_verificado FROM usuarios WHERE id_usuario = :id');
    $stmt->execute(['id' => $usuario['id_usuario']]);
    $registro = $stmt->fetch();
    if (!$registro || !$registro['email_verificado']) {
        responderErro(
            403,
            'Confirme seu email antes de avaliar oficinas. Verifique sua caixa de entrada ou peça um novo link em /auth/reenviar_verificacao.php.'
        );
    }
    return $usuario;
}
/**
 * Retorna true se há uma oficina autenticada na sessão atual.
 */
function oficinaEstaLogada(): bool {
    return isset($_SESSION['id_oficina']);
}
/**
 * Retorna os dados da oficina logada, ou null se não houver sessão de oficina.
 */
function oficinaAtual(): ?array {
    if (!oficinaEstaLogada()) {
        return null;
    }
    return [
        'id_oficina' => $_SESSION['id_oficina'],
        'nome'       => $_SESSION['oficina_nome'],
        'email'      => $_SESSION['oficina_email'],
    ];
}
/**
 * Bloqueia o acesso (HTTP 401) se a oficina não estiver logada.
 * Caso contrário, retorna os dados da oficina.
 */
function exigirOficina(): array {
    $oficina = oficinaAtual();
    if ($oficina === null) {
        responderErro(401, 'É necessário estar logado como oficina para acessar este recurso.');
    }
    // Oficina desativada pelo administrador perde o acesso mesmo com a sessão aberta.
    if (function_exists('getConnection')) {
        $stmt = getConnection()->prepare('SELECT ativa FROM oficinas WHERE id_oficina = :id');
        $stmt->execute(['id' => $oficina['id_oficina']]);
        $linha = $stmt->fetch();
        if (!$linha || !(int) $linha['ativa']) {
            unset($_SESSION['id_oficina'], $_SESSION['oficina_nome'], $_SESSION['oficina_email']);
            responderErro(403, 'Esta oficina foi desativada. Entre em contato com o administrador do MecOn.');
        }
    }
    return $oficina;
}
