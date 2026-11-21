<?php
/**
 * MecOn - POST /backend/usuarios/excluir_minha_conta.php
 * Body (JSON): { "senha": "..." }
 *
 * Eliminação dos dados pelo próprio titular (LGPD, art. 18, VI). Exige a
 * senha atual como confirmação. Apaga a conta e, em cascata (ON DELETE
 * CASCADE a partir de usuarios/veiculos): veículos, histórico de manutenção
 * e peças, pedidos de orçamento, favoritos, denúncias, conversas
 * e mensagens. AVALIAÇÕES são mantidas de forma anônima (id_usuario NULL,
 * "Cliente removido"), pois compõem a reputação pública da oficina. AGENDAMENTOS E ORDENS DE SERVIÇO NÃO SÃO APAGADOS: pertencem
 * também à oficina (registro de serviço/financeiro). Eles perdem o vínculo com
 * o titular (id_usuario/id_veiculo = NULL, mensagem do cliente removida) e
 * a oficina os vê como "Cliente removido", com a placa cifrada da época. Os arquivos de anexos gravados
 * em disco também são removidos. Contas de administrador não podem ser
 * excluídas por aqui. O log de auditoria guarda apenas o id e a data
 * (nenhum dado pessoal).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/anexos.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$sessao = exigirLogin();
if (($sessao['tipo'] ?? '') !== 'cliente') {
    responderErro(403, 'Contas de administrador não podem ser excluídas por aqui.');
}
$dados = json_decode(file_get_contents('php://input'), true);
$senha = $dados['senha'] ?? '';
if ($senha === '') {
    responderErro(400, 'Informe sua senha para confirmar a exclusão da conta.');
}
$id  = (int) $sessao['id_usuario'];
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT email, senha_hash FROM usuarios WHERE id_usuario = :id');
$stmt->execute(['id' => $id]);
$usuario = $stmt->fetch();
if (!$usuario) {
    responderErro(404, 'Usuário não encontrado.');
}
if (!password_verify($senha, $usuario['senha_hash'])) {
    responderErro(403, 'Senha incorreta.');
}
// Arquivos de anexo do usuário (apagados do disco depois do COMMIT)
$stmt = $pdo->prepare('
    SELECT a.nome_arquivo FROM anexos_pedido a
    JOIN pedidos_orcamento p ON p.id_pedido = a.id_pedido WHERE p.id_usuario = :id
');
$stmt->execute(['id' => $id]);
$arquivos = array_column($stmt->fetchAll(), 'nome_arquivo');

$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO log_auditoria (tipo_ator, id_ator, acao, entidade, id_entidade, detalhes)
        VALUES ('usuario', :id, 'excluir_propria_conta', 'usuario', :id2, 'Conta excluída pelo titular (LGPD, art. 18, VI).')
    ")->execute(['id' => $id, 'id2' => $id]);
    // Os agendamentos e ordens de serviço PERMANECEM para a oficina (FK ON DELETE SET NULL):
    // sai o vínculo com a pessoa (nome/e-mail via usuarios) e o texto livre escrito pelo cliente.
    $pdo->prepare('UPDATE agendamentos SET mensagem_usuario = NULL WHERE id_usuario = :id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM tentativas_login WHERE email = :e')->execute(['e' => $usuario['email']]);
    $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = :id')->execute(['id' => $id]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Falha ao excluir conta: ' . $e->getMessage());
    responderErro(500, 'Não foi possível excluir a conta. Tente novamente.');
}
$dir = diretorioAnexos();
foreach ($arquivos as $nome) {
    $caminho = $dir . '/' . basename($nome);
    if (is_file($caminho)) {
        @unlink($caminho);
    }
}
$_SESSION = [];
session_destroy();
echo json_encode(['sucesso' => true]);
