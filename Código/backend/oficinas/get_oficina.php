<?php
/**
 * MecOn - GET /backend/oficinas/get_oficina.php?id_oficina=1
 * Retorna os dados completos de uma oficina (página pública da oficina).
 * Não requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/reputacao.php';
require_once __DIR__ . '/../config/session_check.php';
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
        o.id_oficina,
        o.nome,
        o.cidade,
        o.bairro,
        o.endereco,
        o.telefone,
        o.descricao,
        o.verificada,
        o.verificada_em,
        o.ativa,
        o.latitude,
        o.longitude,
        ROUND(AVG(a.nota), 1) AS media_avaliacao,
        COUNT(a.id_avaliacao) AS total_avaliacoes
    FROM oficinas o
    LEFT JOIN avaliacoes a ON a.id_oficina = o.id_oficina
    WHERE o.id_oficina = :id
    GROUP BY o.id_oficina
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $idOficina]);
$oficina = $stmt->fetch();
$ehAdmin = estaLogado() && (usuarioAtual()['tipo'] ?? '') === 'admin';
if (!$oficina || (!(int) $oficina['ativa'] && !$ehAdmin)) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'erro' => 'Oficina não encontrada.']);
    exit;
}
$oficina['verificada'] = (bool) $oficina['verificada'];
$oficina['latitude'] = $oficina['latitude'] !== null ? (float) $oficina['latitude'] : null;
$oficina['longitude'] = $oficina['longitude'] !== null ? (float) $oficina['longitude'] : null;
$oficina['media_avaliacao'] = $oficina['media_avaliacao'] !== null
    ? (float) $oficina['media_avaliacao']
    : null;

// Histórico de confiança: dados que já existem em outras tabelas,
// agregados aqui para exibição num único bloco na página da oficina.
$stmt = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM agendamentos WHERE id_oficina = :id1 AND status = 'concluido') AS servicos_concluidos,
        (SELECT COUNT(*) FROM avaliacoes WHERE id_oficina = :id2 AND id_agendamento IS NOT NULL) AS avaliacoes_verificadas
");
$stmt->execute(['id1' => $idOficina, 'id2' => $idOficina]);
$confianca = $stmt->fetch();
$oficina['historico_confianca'] = [
    'verificada_desde'       => $oficina['verificada'] ? $oficina['verificada_em'] : null,
    'servicos_concluidos'    => (int) $confianca['servicos_concluidos'],
    'avaliacoes_verificadas' => (int) $confianca['avaliacoes_verificadas'],
];
unset($oficina['verificada_em']); // já incorporado em historico_confianca.verificada_desde
$oficina['reputacao'] = calcularReputacaoOficina($pdo, (int) $idOficina);

$stmt = $pdo->prepare("
    SELECT s.id_servico, s.nome, os.preco
    FROM oficina_servico os
    JOIN servicos s ON s.id_servico = os.id_servico
    WHERE os.id_oficina = :id
    ORDER BY s.nome ASC
");
$stmt->execute(['id' => $idOficina]);
$servicos = $stmt->fetchAll();
foreach ($servicos as &$servico) {
    $servico['preco'] = (float) $servico['preco'];
}
$oficina['servicos'] = $servicos;
$oficina['preco_medio'] = count($servicos) > 0
    ? round(array_sum(array_column($servicos, 'preco')) / count($servicos), 2)
    : null;
echo json_encode(['sucesso' => true, 'oficina' => $oficina]);
