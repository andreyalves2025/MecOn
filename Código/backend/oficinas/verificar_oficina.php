<?php
/**
 * MecOn - POST /backend/oficinas/verificar_oficina.php
 * Body (JSON): { "id_oficina": 1, "verificada": true }
 * Concede ou remove o selo de "oficina verificada" (dado após o admin
 * checar documentação/existência real da oficina). Uso exclusivo de
 * administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/auditoria.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$admin = exigirAdmin();
$dados      = json_decode(file_get_contents('php://input'), true);
$idOficina  = $dados['id_oficina'] ?? null;
$verificada = $dados['verificada'] ?? null;
if (!is_numeric($idOficina)) {
    responderErro(400, 'id_oficina é obrigatório e deve ser numérico.');
}
if (!is_bool($verificada)) {
    responderErro(400, 'verificada é obrigatório e deve ser true ou false.');
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_oficina, verificada FROM oficinas WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
$oficinaAnterior = $stmt->fetch();
if (!$oficinaAnterior) {
    responderErro(404, 'Oficina não encontrada.');
}
// verificada_em só é definida na transição para verificada=true (não se
// reinicia a cada vez que o admin clica de novo) e é limpa ao remover o
// selo — "verificada há X meses" deve refletir a verificação atual.
$stmt = $pdo->prepare(
    $verificada
        ? "UPDATE oficinas SET verificada = 1, verificada_em = IFNULL(verificada_em, NOW()) WHERE id_oficina = :id"
        : "UPDATE oficinas SET verificada = 0, verificada_em = NULL WHERE id_oficina = :id"
);
$stmt->execute(['id' => $idOficina]);
registrarLogAuditoria(
    $pdo, $admin['id_usuario'], $verificada ? 'verificar' : 'remover_verificacao', 'oficina', (int) $idOficina,
    null, ['verificada' => ['anterior' => (bool) $oficinaAnterior['verificada'] ? '1' : '0', 'novo' => $verificada ? '1' : '0']]
);
echo json_encode(['sucesso' => true]);
