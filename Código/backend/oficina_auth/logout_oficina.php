<?php
/**
 * MecOn - POST /backend/oficina_auth/logout_oficina.php
 * Encerra apenas a sessão de oficina, sem afetar sessão de usuário (cliente/admin)
 * que porventura coexista no mesmo navegador.
 */
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
unset($_SESSION['id_oficina'], $_SESSION['oficina_nome'], $_SESSION['oficina_email']);
echo json_encode(['sucesso' => true]);
