<?php
/**
 * MecOn - GET /backend/oficinas/list_oficinas.php
 * Retorna todas as oficinas cadastradas, com média de avaliação e serviços
 * (cada um com seu próprio preço). "preco_medio" no retorno é calculado a
 * partir dos preços dos serviços oferecidos, não é mais um valor armazenado.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}
$pdo = getConnection();
// Por padrão só oficinas ATIVAS. O administrador pode pedir também as desativadas
// (?incluir_inativas=1) para reativá-las; para os demais o parâmetro é ignorado.
$incluirInativas = !empty($_GET['incluir_inativas']) && estaLogado() && (usuarioAtual()['tipo'] ?? '') === 'admin';
$filtroAtiva = $incluirInativas ? '' : 'WHERE o.ativa = 1';
$sql = "
    SELECT
        o.id_oficina,
        o.nome,
        o.cidade,
        o.bairro,
        o.endereco,
        o.telefone,
        o.descricao,
        o.verificada,
        o.ativa,
        o.latitude,
        o.longitude,
        ROUND(AVG(a.nota), 1) AS media_avaliacao,
        COUNT(a.id_avaliacao) AS total_avaliacoes
    FROM oficinas o
    LEFT JOIN avaliacoes a ON a.id_oficina = o.id_oficina
    $filtroAtiva
    GROUP BY o.id_oficina
    ORDER BY o.nome ASC
";
$oficinas = $pdo->query($sql)->fetchAll();
// Busca os serviços (com preço) de cada oficina (evita N+1 com uma query por oficina, agrupada)
$stmtServicos = $pdo->prepare("
    SELECT s.id_servico, s.nome, os.preco
    FROM oficina_servico os
    JOIN servicos s ON s.id_servico = os.id_servico
    WHERE os.id_oficina = :id_oficina
    ORDER BY s.nome ASC
");
foreach ($oficinas as &$oficina) {
    $stmtServicos->execute(['id_oficina' => $oficina['id_oficina']]);
    $servicos = $stmtServicos->fetchAll();
    foreach ($servicos as &$servico) {
        $servico['preco'] = (float) $servico['preco'];
    }
    $oficina['servicos'] = $servicos;
    $oficina['preco_medio'] = count($servicos) > 0
        ? round(array_sum(array_column($servicos, 'preco')) / count($servicos), 2)
        : null;
    $oficina['verificada'] = (bool) $oficina['verificada'];
    $oficina['ativa'] = (bool) $oficina['ativa'];
    $oficina['latitude'] = $oficina['latitude'] !== null ? (float) $oficina['latitude'] : null;
    $oficina['longitude'] = $oficina['longitude'] !== null ? (float) $oficina['longitude'] : null;
    $oficina['media_avaliacao'] = $oficina['media_avaliacao'] !== null
        ? (float) $oficina['media_avaliacao']
        : null;
}
echo json_encode(['sucesso' => true, 'oficinas' => $oficinas]);
