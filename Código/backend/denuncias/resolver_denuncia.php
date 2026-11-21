<?php
/**
 * MecOn - POST /backend/denuncias/resolver_denuncia.php
 * Body (JSON): { "id_denuncia": 1, "acao": "remover_avaliacao" | "descartar" }
 *
 * "remover_avaliacao" exclui a avaliação denunciada (e, em cascata, TODAS
 * as denúncias feitas sobre ela, já que deixam de fazer sentido).
 * "descartar" marca apenas esta denúncia como analisada e sem mérito,
 * sem afetar a avaliação nem outras denúncias sobre o mesmo comentário.
 *
 * Uso exclusivo de administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/auditoria.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$admin = exigirAdmin();
$dados       = json_decode(file_get_contents('php://input'), true);
$idDenuncia  = $dados['id_denuncia'] ?? null;
$acao        = $dados['acao'] ?? null;
if (!is_numeric($idDenuncia)) {
    responderErro(400, 'id_denuncia é obrigatório e deve ser numérico.');
}
if (!in_array($acao, ['remover_avaliacao', 'descartar'], true)) {
    responderErro(400, 'acao deve ser "remover_avaliacao" ou "descartar".');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_avaliacao, status FROM denuncias WHERE id_denuncia = :id');
$stmt->execute(['id' => $idDenuncia]);
$denuncia = $stmt->fetch();
if (!$denuncia) {
    responderErro(404, 'Denúncia não encontrada.');
}
if ($acao === 'remover_avaliacao') {
    $stmt = $pdo->prepare('SELECT * FROM avaliacoes WHERE id_avaliacao = :id');
    $stmt->execute(['id' => $denuncia['id_avaliacao']]);
    $avaliacaoAnterior = $stmt->fetch();
    // ON DELETE CASCADE em denuncias.id_avaliacao já limpa todas as
    // denúncias feitas sobre esta avaliação, não só a atual.
    $stmt = $pdo->prepare('DELETE FROM avaliacoes WHERE id_avaliacao = :id');
    $stmt->execute(['id' => $denuncia['id_avaliacao']]);
    registrarLogAuditoria(
        $pdo, $admin['id_usuario'], 'remover_avaliacao_denunciada', 'avaliacao', (int) $denuncia['id_avaliacao'],
        "via denúncia #$idDenuncia", $avaliacaoAnterior ? montarDetalhesExclusao($avaliacaoAnterior) : null
    );
} else {
    $stmt = $pdo->prepare("UPDATE denuncias SET status = 'descartada' WHERE id_denuncia = :id");
    $stmt->execute(['id' => $idDenuncia]);
    registrarLogAuditoria(
        $pdo, $admin['id_usuario'], 'descartar_denuncia', 'denuncia', (int) $idDenuncia,
        null, ['status' => ['anterior' => $denuncia['status'], 'novo' => 'descartada']]
    );
}
echo json_encode(['sucesso' => true]);
