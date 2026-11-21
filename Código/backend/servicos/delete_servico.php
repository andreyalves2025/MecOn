<?php
/**
 * MecOn - POST /backend/servicos/delete_servico.php
 * Body (JSON): { "id_servico": 1 }
 *
 * Exclusão definitiva só é permitida se o serviço NUNCA foi usado (nenhuma
 * oficina o oferece e nenhum histórico de manutenção o referencia) — isso
 * evita apagar em cascata dados reais de oficinas/históricos (ver
 * oficina_servico e historico_manutencoes, que têm ON DELETE CASCADE para
 * servicos). Se estiver em uso, retorna 409 e sugere desativar em vez de
 * excluir, via update_servico.php com { "ativo": false }.
 * Uso exclusivo de administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$dados = json_decode(file_get_contents('php://input'), true);
$idServico = $dados['id_servico'] ?? null;
if (!is_numeric($idServico)) {
    responderErro(400, 'id_servico é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_servico FROM servicos WHERE id_servico = :id');
$stmt->execute(['id' => $idServico]);
if (!$stmt->fetch()) {
    responderErro(404, 'Serviço não encontrado.');
}
$stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM oficina_servico WHERE id_servico = :id');
$stmt->execute(['id' => $idServico]);
$emUsoPorOficina = (int) $stmt->fetch()['total'] > 0;
$stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM historico_manutencoes WHERE id_servico = :id');
$stmt->execute(['id' => $idServico]);
$emUsoPorHistorico = (int) $stmt->fetch()['total'] > 0;
if ($emUsoPorOficina || $emUsoPorHistorico) {
    responderErro(
        409,
        'Este serviço está em uso (por oficinas e/ou histórico de manutenções) e não pode ser excluído. ' .
        'Desative-o em vez de excluir, para que deixe de aparecer em novos cadastros sem apagar dados existentes.'
    );
}
$stmt = $pdo->prepare('DELETE FROM servicos WHERE id_servico = :id');
$stmt->execute(['id' => $idServico]);
echo json_encode(['sucesso' => true]);
