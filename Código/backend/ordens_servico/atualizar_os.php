<?php
/**
 * MecOn - POST /backend/ordens_servico/atualizar_os.php
 * Body (JSON): { "id_os": 1, ...campos que quiser alterar... }
 *   responsavel, km_entrada, diagnostico, observacoes,
 *   etapa (aberta | em_execucao | aguardando_peca | pronta),
 *   valor_mao_obra, desconto,
 *   pecas: [ { nome, quantidade, valor_unitario } ]  (SUBSTITUI a lista; se
 *          omitido, os itens atuais são mantidos — ver config/pecas.php)
 * Campos omitidos não são alterados; enviar "" ou null limpa o campo.
 * A etapa "entregue" não é aceita aqui (só concluir_os.php fecha a OS), e
 * uma OS já entregue não pode mais ser editada. Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/pecas.php';
require_once __DIR__ . '/../config/os.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficina = exigirOficina();
$dados = json_decode(file_get_contents('php://input'), true);
$idOs = $dados['id_os'] ?? null;
if (!is_numeric($idOs)) {
    responderErro(400, 'id_os é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT etapa FROM ordens_servico WHERE id_os = :id AND id_oficina = :o');
$stmt->execute(['id' => $idOs, 'o' => $oficina['id_oficina']]);
$os = $stmt->fetch();
if (!$os) {
    responderErro(404, 'Ordem de serviço não encontrada.');
}
if ($os['etapa'] === 'entregue') {
    responderErro(409, 'Esta ordem de serviço já foi concluída e não pode mais ser editada.');
}

$campos = [];
$parametros = ['id' => $idOs];
$textos = ['responsavel' => 100, 'diagnostico' => 5000, 'observacoes' => 1000];
foreach ($textos as $campo => $max) {
    if (array_key_exists($campo, $dados)) {
        $valor = trim((string) $dados[$campo]);
        if (mb_strlen($valor) > $max) {
            responderErro(400, "$campo deve ter no máximo $max caracteres.");
        }
        $campos[] = "$campo = :$campo";
        $parametros[$campo] = $valor !== '' ? $valor : null;
    }
}
if (array_key_exists('km_entrada', $dados)) {
    $km = $dados['km_entrada'];
    if ($km !== null && $km !== '' && (!is_numeric($km) || (int) $km < 0)) {
        responderErro(400, 'km_entrada deve ser um número maior ou igual a zero.');
    }
    $campos[] = 'km_entrada = :km';
    $parametros['km'] = ($km === null || $km === '') ? null : (int) $km;
}
if (array_key_exists('etapa', $dados)) {
    if (!in_array($dados['etapa'], OS_ETAPAS_EDITAVEIS, true)) {
        responderErro(400, 'etapa deve ser: ' . implode(', ', OS_ETAPAS_EDITAVEIS) . '.');
    }
    $campos[] = 'etapa = :etapa';
    $parametros['etapa'] = $dados['etapa'];
}
foreach (['valor_mao_obra', 'desconto'] as $campo) {
    if (array_key_exists($campo, $dados)) {
        $campos[] = "$campo = :$campo";
        $parametros[$campo] = lerValorOpcional($dados, $campo);
    }
}
$itens = lerPecas($dados); // null = não mexer nos itens

$pdo->beginTransaction();
try {
    if (!empty($campos)) {
        $stmt = $pdo->prepare('UPDATE ordens_servico SET ' . implode(', ', $campos) . ' WHERE id_os = :id');
        $stmt->execute($parametros);
    }
    if ($itens !== null) {
        gravarItensOS($pdo, (int) $idOs, $itens);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao atualizar a ordem de serviço.');
}
echo json_encode(['sucesso' => true]);
