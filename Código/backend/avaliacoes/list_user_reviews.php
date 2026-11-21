<?php
/**
 * MecOn - GET /backend/avaliacoes/list_user_reviews.php
 * Lista as avaliações feitas pelo usuário logado (aba "Comentários feitos" do perfil).
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$pdo = getConnection();
$sql = "
    SELECT
        a.id_avaliacao,
        a.nota,
        a.comentario,
        a.data_avaliacao,
        o.id_oficina,
        o.nome AS nome_oficina
    FROM avaliacoes a
    JOIN oficinas o ON o.id_oficina = a.id_oficina
    WHERE a.id_usuario = :id_usuario
    ORDER BY a.data_avaliacao DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id_usuario' => $usuario['id_usuario']]);
echo json_encode(['sucesso' => true, 'avaliacoes' => $stmt->fetchAll()]);
