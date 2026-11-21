<?php
/**
 * MecOn - GET /backend/avaliacoes/list_reviews.php?id_oficina=1
 * Lista as avaliações de uma oficina específica (página pública da oficina).
 * Não requer login.
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}
$idOficina = $_GET['id_oficina'] ?? null;
if (!is_numeric($idOficina)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'id_oficina é obrigatório e deve ser numérico.']);
    exit;
}
$pdo = getConnection();
$sql = "
    SELECT
        a.id_avaliacao,
        a.id_usuario,
        a.id_agendamento IS NOT NULL AS verificada,
        a.nota,
        a.comentario,
        a.resposta_oficina,
        a.data_resposta,
        a.data_avaliacao,
        COALESCE(u.nome, 'Cliente removido') AS nome_usuario
    FROM avaliacoes a
    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
    WHERE a.id_oficina = :id_oficina
    ORDER BY a.data_avaliacao DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id_oficina' => $idOficina]);
$avaliacoes = $stmt->fetchAll();
foreach ($avaliacoes as &$a) {
    $a['verificada'] = (bool) $a['verificada'];
}
echo json_encode(['sucesso' => true, 'avaliacoes' => $avaliacoes]);
