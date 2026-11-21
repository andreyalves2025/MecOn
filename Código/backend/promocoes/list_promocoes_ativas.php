<?php
/**
 * MecOn - GET /backend/promocoes/list_promocoes_ativas.php[?id_oficina=1]
 *
 * Lista promoções ATIVAS neste momento (data_inicio <= hoje <= data_fim).
 * Sem "id_oficina": todas as promoções ativas da plataforma, ordenadas
 * pelas que terminam mais cedo primeiro (usado como "ofertas em destaque"
 * na home). Com "id_oficina": só as da oficina informada (usado na
 * página pública de detalhe da oficina).
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}
$idOficina = $_GET['id_oficina'] ?? null;
$pdo = getConnection();
$sql = "
    SELECT
        p.id_promocao, p.titulo, p.descricao, p.data_fim,
        p.id_oficina, o.nome AS nome_oficina,
        p.id_servico, s.nome AS nome_servico
    FROM promocoes p
    JOIN oficinas o ON o.id_oficina = p.id_oficina
    LEFT JOIN servicos s ON s.id_servico = p.id_servico
    WHERE CURDATE() BETWEEN p.data_inicio AND p.data_fim
      AND o.ativa = 1
";
$parametros = [];
if ($idOficina !== null && $idOficina !== '') {
    if (!is_numeric($idOficina)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'id_oficina deve ser numérico.']);
        exit;
    }
    $sql .= ' AND p.id_oficina = :id_oficina';
    $parametros['id_oficina'] = $idOficina;
}
$sql .= ' ORDER BY p.data_fim ASC LIMIT 20';
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
echo json_encode(['sucesso' => true, 'promocoes' => $stmt->fetchAll()]);
