<?php
/**
 * MecOn - GET /backend/oficina_auth/get_desempenho_oficina.php
 * Retorna métricas de desempenho da oficina logada:
 *  - Percentil de avaliação frente a todas as oficinas já avaliadas
 *  - Quantidade de usuários que favoritaram a oficina
 *  - Distribuição de notas (quantas avaliações em cada nota de 1 a 5)
 *  - Evolução da média de avaliação nos últimos 6 meses
 *  - Reputação (taxas de conclusão/aceite, tempo de resposta — ver config/reputacao.php)
 * Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/reputacao.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$idOficina = $oficinaSessao['id_oficina'];
$pdo = getConnection();
// 1. Média e total de avaliações da própria oficina
$stmt = $pdo->prepare("
    SELECT ROUND(AVG(nota), 2) AS media, COUNT(*) AS total
    FROM avaliacoes
    WHERE id_oficina = :id
");
$stmt->execute(['id' => $idOficina]);
$propria = $stmt->fetch();
$mediaPropria = $propria['media'] !== null ? (float) $propria['media'] : null;
$totalAvaliacoesPropria = (int) $propria['total'];
// 2. Percentil: % de oficinas avaliadas com média MENOR OU IGUAL à desta oficina,
// considerando apenas oficinas que já têm pelo menos 1 avaliação.
$percentil = null;
if ($mediaPropria !== null) {
    $stmt = $pdo->prepare("
        SELECT o.id_oficina, AVG(a.nota) AS media
        FROM oficinas o
        JOIN avaliacoes a ON a.id_oficina = o.id_oficina
        GROUP BY o.id_oficina
    ");
    $stmt->execute();
    $medias = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    $totalOficinasAvaliadas = count($medias);
    if ($totalOficinasAvaliadas > 0) {
        $comMediaMenorOuIgual = 0;
        foreach ($medias as $media) {
            if ((float) $media <= $mediaPropria) {
                $comMediaMenorOuIgual++;
            }
        }
        $percentil = round(($comMediaMenorOuIgual / $totalOficinasAvaliadas) * 100);
    }
}
// 3. Quantidade de favoritos
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM favoritos WHERE id_oficina = :id");
$stmt->execute(['id' => $idOficina]);
$totalFavoritos = (int) $stmt->fetch()['total'];
// 4. Distribuição de notas (1 a 5), preenchendo com 0 as notas sem avaliação
$stmt = $pdo->prepare("
    SELECT nota, COUNT(*) AS total
    FROM avaliacoes
    WHERE id_oficina = :id
    GROUP BY nota
");
$stmt->execute(['id' => $idOficina]);
$contagemPorNota = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$distribuicaoNotas = [];
for ($nota = 1; $nota <= 5; $nota++) {
    $distribuicaoNotas[] = [
        'nota'  => $nota,
        'total' => isset($contagemPorNota[$nota]) ? (int) $contagemPorNota[$nota] : 0,
    ];
}
// 5. Evolução da média de avaliação nos últimos 6 meses (incluindo o atual)
$stmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(data_avaliacao, '%Y-%m') AS mes,
        ROUND(AVG(nota), 2) AS media
    FROM avaliacoes
    WHERE id_oficina = :id
      AND data_avaliacao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mes
    ORDER BY mes ASC
");
$stmt->execute(['id' => $idOficina]);
$evolucaoMedia = $stmt->fetchAll();
foreach ($evolucaoMedia as &$ponto) {
    $ponto['media'] = (float) $ponto['media'];
}
echo json_encode([
    'sucesso' => true,
    'desempenho' => [
        'media_avaliacao'    => $mediaPropria,
        'total_avaliacoes'   => $totalAvaliacoesPropria,
        'percentil'          => $percentil,
        'total_favoritos'    => $totalFavoritos,
        'distribuicao_notas' => $distribuicaoNotas,
        'evolucao_media'     => $evolucaoMedia,
        'reputacao'          => calcularReputacaoOficina($pdo, (int) $idOficina),
    ],
]);
