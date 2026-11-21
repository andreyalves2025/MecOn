<?php
/**
 * MecOn - GET /backend/oficina_auth/get_perfil_oficina.php
 * Retorna os dados completos da oficina logada, incluindo serviços vinculados
 * e avaliações recebidas. Requer login de oficina.
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
        o.id_oficina, o.nome, o.email, o.cidade, o.bairro, o.endereco,
        o.telefone, o.descricao, o.verificada, o.latitude, o.longitude, o.data_cadastro,
        ROUND(AVG(a.nota), 1) AS media_avaliacao,
        COUNT(a.id_avaliacao) AS total_avaliacoes
    FROM oficinas o
    LEFT JOIN avaliacoes a ON a.id_oficina = o.id_oficina
    WHERE o.id_oficina = :id
    GROUP BY o.id_oficina
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $oficinaSessao['id_oficina']]);
$oficina = $stmt->fetch();
if (!$oficina) {
    responderErro(404, 'Oficina não encontrada.');
}
$oficina['verificada'] = (bool) $oficina['verificada'];
$oficina['latitude'] = $oficina['latitude'] !== null ? (float) $oficina['latitude'] : null;
$oficina['longitude'] = $oficina['longitude'] !== null ? (float) $oficina['longitude'] : null;
$oficina['media_avaliacao'] = $oficina['media_avaliacao'] !== null
    ? (float) $oficina['media_avaliacao']
    : null;
$stmt = $pdo->prepare("
    SELECT s.id_servico, s.nome, os.preco
    FROM oficina_servico os
    JOIN servicos s ON s.id_servico = os.id_servico
    WHERE os.id_oficina = :id
    ORDER BY s.nome ASC
");
$stmt->execute(['id' => $oficinaSessao['id_oficina']]);
$servicos = $stmt->fetchAll();
foreach ($servicos as &$servico) {
    $servico['preco'] = (float) $servico['preco'];
}
$oficina['servicos'] = $servicos;
echo json_encode(['sucesso' => true, 'oficina' => $oficina]);
