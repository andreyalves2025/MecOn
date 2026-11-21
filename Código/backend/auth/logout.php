<?php
/**
 * MecOn - POST /backend/auth/logout.php
 * Destroi a sessão atual.
 */
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();
echo json_encode(['sucesso' => true]);
