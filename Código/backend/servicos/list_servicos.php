<?php
/**
 * MecOn - GET /backend/servicos/list_servicos.php[?todos=1]
 * Lista os tipos de serviço cadastrados (ex: Mecânica, Elétrica, Borracharia).
 * Por padrão retorna apenas serviços ativos (usado em dropdowns de filtro e
 * de cadastro/edição de oficina). Passe ?todos=1 para incluir os inativos
 * (usado na tela de administração do catálogo).
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}
$todos = isset($_GET['todos']) && $_GET['todos'] === '1';
$pdo = getConnection();
$sql = 'SELECT id_servico, nome, ativo, intervalo_recomendado_meses, intervalo_recomendado_km FROM servicos';
if (!$todos) {
    $sql .= ' WHERE ativo = 1';
}
$sql .= ' ORDER BY nome ASC';
$servicos = $pdo->query($sql)->fetchAll();
foreach ($servicos as &$servico) {
    $servico['ativo'] = (bool) $servico['ativo'];
    $servico['intervalo_recomendado_meses'] = $servico['intervalo_recomendado_meses'] !== null
        ? (int) $servico['intervalo_recomendado_meses']
        : null;
    $servico['intervalo_recomendado_km'] = $servico['intervalo_recomendado_km'] !== null
        ? (int) $servico['intervalo_recomendado_km']
        : null;
}
echo json_encode(['sucesso' => true, 'servicos' => $servicos]);
