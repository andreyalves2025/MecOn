<?php
/**
 * MecOn - POST /backend/auth/aceitar_termos.php
 * Registra o aceite dos Termos de Uso e da Política de Privacidade (versão
 * atual) pelo usuário logado. Usado por contas criadas antes da migração 026,
 * que ficaram com termos_aceitos_em = NULL.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/lgpd.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$pdo = getConnection();
$stmt = $pdo->prepare('UPDATE usuarios SET termos_aceitos_em = NOW(), termos_versao = :v WHERE id_usuario = :id');
$stmt->execute(['v' => TERMOS_VERSAO, 'id' => $usuario['id_usuario']]);
echo json_encode(['sucesso' => true, 'termos_versao' => TERMOS_VERSAO]);
