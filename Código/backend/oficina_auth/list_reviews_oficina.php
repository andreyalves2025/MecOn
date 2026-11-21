<?php
/**
 * MecOn - GET /backend/oficina_auth/list_reviews_oficina.php
 * Lista as avaliações recebidas pela oficina logada (sem precisar informar
 * id_oficina manualmente — usa o id da sessão atual).
 * Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$pdo = getConnection();
$sql = "
    SELECT
        a.id_avaliacao,
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
$stmt->execute(['id_oficina' => $oficinaSessao['id_oficina']]);
$avaliacoes = $stmt->fetchAll();
foreach ($avaliacoes as &$a) {
    $a['verificada'] = (bool) $a['verificada'];
}
echo json_encode(['sucesso' => true, 'avaliacoes' => $avaliacoes]);
