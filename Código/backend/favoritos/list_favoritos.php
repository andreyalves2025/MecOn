<?php
/**
 * MecOn - GET /backend/favoritos/list_favoritos.php
 * Lista todas as oficinas favoritadas pelo usuário logado.
 * Usado na aba "Oficinas favoritas" da página de perfil.
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
        o.id_oficina,
        o.nome,
        o.cidade,
        o.bairro,
        o.telefone,
        ROUND(AVG(a.nota), 1) AS media_avaliacao
    FROM favoritos f
    JOIN oficinas o ON o.id_oficina = f.id_oficina
    LEFT JOIN avaliacoes a ON a.id_oficina = o.id_oficina
    WHERE f.id_usuario = :id_usuario
    GROUP BY o.id_oficina
    ORDER BY o.nome ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id_usuario' => $usuario['id_usuario']]);
$favoritos = $stmt->fetchAll();
foreach ($favoritos as &$oficina) {
    $oficina['media_avaliacao'] = $oficina['media_avaliacao'] !== null
        ? (float) $oficina['media_avaliacao']
        : null;
}
echo json_encode(['sucesso' => true, 'favoritos' => $favoritos]);
