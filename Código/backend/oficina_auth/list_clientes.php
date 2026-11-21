<?php
/**
 * MecOn - GET /backend/oficina_auth/list_clientes.php?busca=&recontatar=1&pagina=1&por_pagina=20
 *
 * Mini-CRM: lista os clientes que agendaram serviço com a oficina logada,
 * com métricas (agendamentos, concluídos, total gasto, última visita,
 * próximo agendamento), veículos, serviços realizados e sugestões de
 * retorno (cliente que está na hora de refazer um serviço).
 *   busca       -> filtra por nome ou email do cliente
 *   recontatar  -> "1" mostra só clientes com retorno sugerido
 * Só considera agendamentos (ver a nota de privacidade em config/crm.php).
 * Resposta paginada — ver config/paginacao.php. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/paginacao.php';
require_once __DIR__ . '/../config/crm.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$idOficina  = (int) $oficinaSessao['id_oficina'];
$busca      = trim($_GET['busca'] ?? '');
$recontatar = ($_GET['recontatar'] ?? '') === '1';
$pdo = getConnection();

$condicoes  = ['ag.id_oficina = :id_oficina'];
$parametros = ['id_oficina' => $idOficina];
if ($busca !== '') {
    $condicoes[] = '(u.nome LIKE :busca_nome OR u.email LIKE :busca_email)';
    $parametros['busca_nome']  = '%' . $busca . '%';
    $parametros['busca_email'] = '%' . $busca . '%';
}

$retornosTodos = null;
if ($recontatar) {
    $retornosTodos = obterRetornosSugeridos($pdo, $idOficina, null);
    $idsComRetorno = array_map('intval', array_keys($retornosTodos));
    if (empty($idsComRetorno)) {
        echo json_encode([
            'sucesso'   => true,
            'clientes'  => [],
            'paginacao' => metadadosPaginacao(1, obterParametrosPaginacao()['por_pagina'], 0),
        ]);
        exit;
    }
    // ids já são inteiros (array_map intval acima): seguro para interpolar
    $condicoes[] = 'ag.id_usuario IN (' . implode(',', $idsComRetorno) . ')';
}
$where = implode(' AND ', $condicoes);

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ag.id_usuario) AS total
    FROM agendamentos ag
    JOIN usuarios u ON u.id_usuario = ag.id_usuario
    WHERE $where
");
$stmt->execute($parametros);
$total = (int) $stmt->fetch()['total'];

['pagina' => $pagina, 'por_pagina' => $porPagina, 'offset' => $offset] = obterParametrosPaginacao();

$sql = "
    SELECT
        u.id_usuario, u.nome, u.email,
        COUNT(*)                                        AS total_agendamentos,
        SUM(ag.status = 'concluido')                    AS concluidos,
        SUM(ag.status = 'cancelado')                    AS cancelados,
        SUM(ag.status = 'recusado')                     AS recusados,
        SUM(ag.status IN ('pendente', 'aceito'))        AS em_aberto,
        MAX(CASE WHEN ag.status = 'concluido' THEN ag.data_hora_solicitada END) AS ultima_visita,
        COALESCE(SUM(CASE WHEN ag.status = 'concluido' THEN ag.valor_total END), 0) AS total_gasto,
        MIN(CASE WHEN ag.status IN ('pendente', 'aceito') AND ag.data_hora_solicitada >= NOW()
                 THEN ag.data_hora_solicitada END)      AS proximo_agendamento
    FROM agendamentos ag
    JOIN usuarios u ON u.id_usuario = ag.id_usuario
    WHERE $where
    GROUP BY u.id_usuario, u.nome, u.email
    ORDER BY ultima_visita IS NULL, ultima_visita DESC, u.nome ASC
    LIMIT :limite OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($parametros as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}
$stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$clientes = $stmt->fetchAll();

$idsPagina = array_map('intval', array_column($clientes, 'id_usuario'));
$retornos = $retornosTodos ?? obterRetornosSugeridos($pdo, $idOficina, $idsPagina);
$extras   = obterVeiculosEServicos($pdo, $idOficina, $idsPagina);

foreach ($clientes as &$c) {
    $id = (int) $c['id_usuario'];
    $c['id_usuario']          = $id;
    $c['total_agendamentos']  = (int) $c['total_agendamentos'];
    $c['concluidos']          = (int) $c['concluidos'];
    $c['cancelados']          = (int) $c['cancelados'];
    $c['recusados']           = (int) $c['recusados'];
    $c['em_aberto']           = (int) $c['em_aberto'];
    $c['total_gasto']         = round((float) $c['total_gasto'], 2);
    $c['veiculos']            = $extras[$id]['veiculos'] ?? [];
    $c['servicos_realizados'] = $extras[$id]['servicos'] ?? [];
    $c['retornos_sugeridos']  = $retornos[$id] ?? [];
}
unset($c);

echo json_encode([
    'sucesso'   => true,
    'clientes'  => $clientes,
    'paginacao' => metadadosPaginacao($pagina, $porPagina, $total),
]);
