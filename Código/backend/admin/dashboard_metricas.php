<?php
/**
 * MecOn - GET /backend/admin/dashboard_metricas.php
 * Métricas gerais da plataforma para o painel administrativo.
 * Requer login como administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$pdo = getConnection();

$totais = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente') AS total_usuarios,
        (SELECT COUNT(*) FROM oficinas WHERE ativa = 1) AS total_oficinas,
        (SELECT COUNT(*) FROM oficinas WHERE verificada = 1 AND ativa = 1) AS total_oficinas_verificadas,
        (SELECT COUNT(*) FROM avaliacoes) AS total_avaliacoes,
        (SELECT ROUND(AVG(nota), 2) FROM avaliacoes) AS media_geral_avaliacoes,
        (SELECT COUNT(*) FROM denuncias WHERE status = 'pendente') AS denuncias_pendentes,
        (SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente') AS agendamentos_pendentes,
        (SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente' AND data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS novos_usuarios_30_dias,
        (SELECT COUNT(*) FROM oficinas WHERE ativa = 1 AND data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS novas_oficinas_30_dias
")->fetch();
foreach ($totais as $chave => &$valor) {
    if ($valor !== null && strpos($chave, 'media_') === 0) {
        $valor = (float) $valor;
    } elseif ($valor !== null) {
        $valor = (int) $valor;
    }
}

$topOficinas = $pdo->query("
    SELECT o.id_oficina, o.nome, COUNT(a.id_avaliacao) AS total_avaliacoes, ROUND(AVG(a.nota), 1) AS media
    FROM oficinas o
    JOIN avaliacoes a ON a.id_oficina = o.id_oficina
    GROUP BY o.id_oficina
    ORDER BY total_avaliacoes DESC, media DESC
    LIMIT 5
")->fetchAll();
foreach ($topOficinas as &$o) {
    $o['media'] = (float) $o['media'];
}

$distribuicaoNotas = $pdo->query('
    SELECT nota, COUNT(*) AS total FROM avaliacoes GROUP BY nota ORDER BY nota DESC
')->fetchAll();

echo json_encode([
    'sucesso'             => true,
    'totais'              => $totais,
    'top_oficinas'        => $topOficinas,
    'distribuicao_notas'  => $distribuicaoNotas,
]);
