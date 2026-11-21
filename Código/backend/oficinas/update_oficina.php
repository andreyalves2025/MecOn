<?php
/**
 * MecOn - POST /backend/oficinas/update_oficina.php
 * Body (JSON): {
 *   "id_oficina": 1, "nome": "...", "cidade": "...", "bairro": "...",
 *   "endereco": "...", "telefone": "...", "descricao": "...",
 *   "preco_medio": 120.50, "servicos": [1, 3, 5]
 * }
 * Uso exclusivo de administrador. Campos enviados substituem os atuais;
 * "servicos", se enviado, substitui a lista completa de vínculos.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$dados     = json_decode(file_get_contents('php://input'), true);
$idOficina = $dados['id_oficina'] ?? null;
if (!is_numeric($idOficina)) {
    responderErro(400, 'id_oficina é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_oficina FROM oficinas WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
if (!$stmt->fetch()) {
    responderErro(404, 'Oficina não encontrada.');
}
$camposPermitidos = ['nome', 'cidade', 'bairro', 'endereco', 'telefone', 'descricao', 'preco_medio'];
$campos     = [];
$parametros = ['id' => $idOficina];
foreach ($camposPermitidos as $campo) {
    if (!isset($dados[$campo])) {
        continue;
    }
    if ($campo === 'preco_medio' && (!is_numeric($dados[$campo]) || (float) $dados[$campo] < 0)) {
        responderErro(400, 'preco_medio deve ser um número maior ou igual a zero.');
    }
    $campos[] = "$campo = :$campo";
    $parametros[$campo] = is_string($dados[$campo]) ? trim($dados[$campo]) : $dados[$campo];
}
$pdo->beginTransaction();
try {
    if (!empty($campos)) {
        $sql = 'UPDATE oficinas SET ' . implode(', ', $campos) . ' WHERE id_oficina = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);
    }
    if (isset($dados['servicos']) && is_array($dados['servicos'])) {
        $stmt = $pdo->prepare('DELETE FROM oficina_servico WHERE id_oficina = :id');
        $stmt->execute(['id' => $idOficina]);
        $stmtServico = $pdo->prepare(
            'INSERT INTO oficina_servico (id_oficina, id_servico) VALUES (:id_oficina, :id_servico)'
        );
        foreach ($dados['servicos'] as $idServico) {
            if (!is_numeric($idServico)) {
                continue;
            }
            $stmtServico->execute(['id_oficina' => $idOficina, 'id_servico' => $idServico]);
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao atualizar oficina.');
}
echo json_encode(['sucesso' => true]);
