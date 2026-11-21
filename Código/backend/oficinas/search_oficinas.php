<?php
/**
 * MecOn - GET /backend/oficinas/search_oficinas.php
 *
 * Busca textual livre por localização (cidade e/ou bairro) ou nome da oficina.
 * Usada na home (campo único de busca) e na barra de busca da página de
 * oficinas filtradas. "preco_medio" no retorno é calculado a partir dos
 * preços dos serviços oferecidos por cada oficina.
 *
 * Query params:
 *   q          -> termo de busca livre (ex: "vila amelia, pinhais, pr" ou "Jaiminho")
 *   pagina     -> página desejada (padrão 1)
 *   por_pagina -> itens por página (padrão 20, máximo 100)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paginacao.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}
$termo = trim($_GET['q'] ?? '');
$pdo = getConnection();
$sqlBase = "
    SELECT
        o.id_oficina,
        o.nome,
        o.cidade,
        o.bairro,
        o.endereco,
        o.telefone,
        o.descricao,
        o.verificada,
        o.latitude,
        o.longitude,
        ROUND(AVG(a.nota), 1) AS media_avaliacao,
        COUNT(a.id_avaliacao) AS total_avaliacoes
    FROM oficinas o
    LEFT JOIN avaliacoes a ON a.id_oficina = o.id_oficina
";
$parametros = [];
$sqlBase .= ' WHERE o.ativa = 1 ';
if ($termo !== '') {
    $sqlBase .= " AND (o.nome LIKE :termo_nome OR o.cidade LIKE :termo_cidade OR o.bairro LIKE :termo_bairro) ";
    $parametros['termo_nome']   = '%' . $termo . '%';
    $parametros['termo_cidade'] = '%' . $termo . '%';
    $parametros['termo_bairro'] = '%' . $termo . '%';
}
$sqlBase .= ' GROUP BY o.id_oficina';

// Conta quantas oficinas atendem o filtro, envolvendo a consulta agrupada
// numa subconsulta (não dá pra usar COUNT(*) direto com GROUP BY no meio).
$stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM ($sqlBase) AS sub");
$stmtTotal->execute($parametros);
$total = (int) $stmtTotal->fetch()['total'];

['pagina' => $pagina, 'por_pagina' => $porPagina, 'offset' => $offset] = obterParametrosPaginacao();

$sql = $sqlBase . ' ORDER BY o.nome ASC LIMIT :limite OFFSET :offset';
$stmt = $pdo->prepare($sql);
foreach ($parametros as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$oficinas = $stmt->fetchAll();
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
    $oficina['latitude'] = $oficina['latitude'] !== null ? (float) $oficina['latitude'] : null;
    $oficina['longitude'] = $oficina['longitude'] !== null ? (float) $oficina['longitude'] : null;
    $oficina['media_avaliacao'] = $oficina['media_avaliacao'] !== null
        ? (float) $oficina['media_avaliacao']
        : null;
}
echo json_encode([
    'sucesso'   => true,
    'oficinas'  => $oficinas,
    'paginacao' => metadadosPaginacao($pagina, $porPagina, $total),
]);
