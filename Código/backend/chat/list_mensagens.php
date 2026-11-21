<?php
/**
 * MecOn - GET /backend/chat/list_mensagens.php?como=usuario|oficina&id_conversa=7&depois_de=0
 *
 * Mensagens de uma conversa, em ordem cronológica. "depois_de" (id da
 * última mensagem que a tela já tem) devolve só as novas — é o que o
 * polling usa. Abrir a conversa marca como lidas as mensagens da OUTRA
 * parte. Resposta também traz "lidas_ate": o maior id entre as mensagens
 * DO PRÓPRIO ator que a outra parte já leu (para exibir "visualizada").
 * Só o dono da conversa acessa; para os demais, 404.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/chat.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$ator = resolverAtorChat($_GET['como'] ?? null);
$pdo  = getConnection();
$conversa = carregarConversaDoAtor($pdo, $ator, $_GET['id_conversa'] ?? null);
$depoisDe = isset($_GET['depois_de']) && is_numeric($_GET['depois_de']) ? (int) $_GET['depois_de'] : 0;
$outro = $ator['tipo'] === 'usuario' ? 'oficina' : 'usuario';

$stmt = $pdo->prepare("
    SELECT id_mensagem, remetente, texto, criado_em
    FROM mensagens
    WHERE id_conversa = :c AND id_mensagem > :depois
    ORDER BY id_mensagem ASC
    LIMIT 200
");
$stmt->execute(['c' => $conversa['id_conversa'], 'depois' => $depoisDe]);
$mensagens = $stmt->fetchAll();
foreach ($mensagens as &$m) {
    $m['id_mensagem'] = (int) $m['id_mensagem'];
}
unset($m);

$stmt = $pdo->prepare("
    UPDATE mensagens SET lida_em = NOW()
    WHERE id_conversa = :c AND remetente = :outro AND lida_em IS NULL
");
$stmt->execute(['c' => $conversa['id_conversa'], 'outro' => $outro]);

$stmt = $pdo->prepare("
    SELECT MAX(id_mensagem) AS ate FROM mensagens
    WHERE id_conversa = :c AND remetente = :eu AND lida_em IS NOT NULL
");
$stmt->execute(['c' => $conversa['id_conversa'], 'eu' => $ator['tipo']]);
$lidasAte = $stmt->fetch()['ate'];

echo json_encode([
    'sucesso'   => true,
    'mensagens' => $mensagens,
    'lidas_ate' => $lidasAte !== null ? (int) $lidasAte : 0,
]);
