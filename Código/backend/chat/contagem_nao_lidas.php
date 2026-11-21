<?php
/**
 * MecOn - GET /backend/chat/contagem_nao_lidas.php?como=usuario|oficina
 * Total de mensagens não lidas do ator, somando todas as conversas.
 * Consulta barata, feita periodicamente para o selo de novas mensagens.
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
$coluna = $ator['tipo'] === 'usuario' ? 'c.id_usuario' : 'c.id_oficina';
$remetenteOutro = $ator['tipo'] === 'usuario' ? 'oficina' : 'usuario';
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM mensagens m
    JOIN conversas c ON c.id_conversa = m.id_conversa
    WHERE $coluna = :ator AND m.remetente = :outro AND m.lida_em IS NULL
");
$stmt->execute(['ator' => $ator['id'], 'outro' => $remetenteOutro]);
echo json_encode(['sucesso' => true, 'nao_lidas' => (int) $stmt->fetch()['total']]);
