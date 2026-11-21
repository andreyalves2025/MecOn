<?php
/**
 * MecOn - GET /backend/chat/list_conversas.php?como=usuario|oficina
 * Lista as conversas do ator (mais recentes primeiro), com o nome da outra
 * parte, prévia da última mensagem e quantas mensagens não lidas há.
 * Requer login na identidade pedida.
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

if ($ator['tipo'] === 'usuario') {
    $juncao = 'JOIN oficinas o ON o.id_oficina = c.id_oficina';
    $nomeOutro = 'o.nome';
    $meu = 'c.id_usuario';
    $remetenteOutro = 'oficina';
} else {
    $juncao = 'JOIN usuarios o ON o.id_usuario = c.id_usuario';
    $nomeOutro = 'o.nome';
    $meu = 'c.id_oficina';
    $remetenteOutro = 'usuario';
}
$stmt = $pdo->prepare("
    SELECT c.id_conversa, c.id_oficina, c.id_usuario, $nomeOutro AS nome_outro, c.ultima_mensagem_em,
           (SELECT m.texto FROM mensagens m WHERE m.id_conversa = c.id_conversa ORDER BY m.id_mensagem DESC LIMIT 1) AS ultima_mensagem,
           (SELECT COUNT(*) FROM mensagens m
             WHERE m.id_conversa = c.id_conversa AND m.remetente = :remetente_outro AND m.lida_em IS NULL) AS nao_lidas
    FROM conversas c
    $juncao
    WHERE $meu = :ator
    ORDER BY COALESCE(c.ultima_mensagem_em, c.criado_em) DESC
");
$stmt->execute(['remetente_outro' => $remetenteOutro, 'ator' => $ator['id']]);
$conversas = $stmt->fetchAll();
foreach ($conversas as &$c) {
    $c['id_conversa'] = (int) $c['id_conversa'];
    $c['id_oficina']  = (int) $c['id_oficina'];
    $c['id_usuario']  = (int) $c['id_usuario'];
    $c['nao_lidas']   = (int) $c['nao_lidas'];
}
unset($c);
echo json_encode(['sucesso' => true, 'conversas' => $conversas]);
