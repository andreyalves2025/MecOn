<?php
/**
 * MecOn - POST /backend/chat/enviar_mensagem.php
 * Body (JSON):
 *   como "usuario": { "como": "usuario", "id_oficina": 3, "texto": "..." }
 *                   (cria a conversa com a oficina se ainda não existir;
 *                    também aceita "id_conversa" no lugar de "id_oficina")
 *   como "oficina": { "como": "oficina", "id_conversa": 7, "texto": "..." }
 *                   (a oficina só responde conversas que o cliente iniciou)
 *
 * Quem inicia ou envia como usuário precisa ter o email confirmado (mesma
 * exigência de avaliar e agendar — reduz spam para as oficinas).
 * Limite: 15 mensagens por minuto por remetente em cada conversa (429).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/chat.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$dados = json_decode(file_get_contents('php://input'), true);
$pdo   = getConnection();
$ator  = resolverAtorChat($dados['como'] ?? null, $pdo, true);
$texto = trim($dados['texto'] ?? '');
if ($texto === '') {
    responderErro(400, 'A mensagem não pode estar vazia.');
}
if (mb_strlen($texto) > CHAT_TAMANHO_MAXIMO) {
    responderErro(400, 'A mensagem deve ter no máximo ' . CHAT_TAMANHO_MAXIMO . ' caracteres.');
}

if (!empty($dados['id_conversa'])) {
    $conversa = carregarConversaDoAtor($pdo, $ator, $dados['id_conversa']);
} elseif ($ator['tipo'] === 'usuario') {
    $idOficina = $dados['id_oficina'] ?? null;
    if (!is_numeric($idOficina)) {
        responderErro(400, 'Informe id_oficina ou id_conversa.');
    }
    $stmt = $pdo->prepare('SELECT 1 FROM oficinas WHERE id_oficina = :id AND ativa = 1');
    $stmt->execute(['id' => $idOficina]);
    if (!$stmt->fetch()) {
        responderErro(404, 'Oficina não encontrada.');
    }
    $buscar = $pdo->prepare('SELECT * FROM conversas WHERE id_usuario = :u AND id_oficina = :o');
    $buscar->execute(['u' => $ator['id'], 'o' => $idOficina]);
    $conversa = $buscar->fetch();
    if (!$conversa) {
        try {
            $stmt = $pdo->prepare('INSERT INTO conversas (id_usuario, id_oficina) VALUES (:u, :o)');
            $stmt->execute(['u' => $ator['id'], 'o' => $idOficina]);
        } catch (PDOException $e) {
            // 23000 = outra requisição criou a mesma conversa no mesmo instante
            if ($e->getCode() !== '23000') {
                responderErro(500, 'Erro ao iniciar a conversa.');
            }
        }
        $buscar->execute(['u' => $ator['id'], 'o' => $idOficina]);
        $conversa = $buscar->fetch();
    }
} else {
    responderErro(400, 'A oficina só pode responder conversas existentes (informe id_conversa).');
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total FROM mensagens
    WHERE id_conversa = :c AND remetente = :r AND criado_em >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
");
$stmt->execute(['c' => $conversa['id_conversa'], 'r' => $ator['tipo']]);
if ((int) $stmt->fetch()['total'] >= CHAT_LIMITE_MENSAGENS_POR_MINUTO) {
    responderErro(429, 'Você está enviando mensagens rápido demais. Aguarde um instante.');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO mensagens (id_conversa, remetente, texto) VALUES (:c, :r, :t)');
    $stmt->execute(['c' => $conversa['id_conversa'], 'r' => $ator['tipo'], 't' => $texto]);
    $idMensagem = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('UPDATE conversas SET ultima_mensagem_em = NOW() WHERE id_conversa = :c');
    $stmt->execute(['c' => $conversa['id_conversa']]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao enviar a mensagem.');
}

$stmt = $pdo->prepare('SELECT id_mensagem, remetente, texto, criado_em FROM mensagens WHERE id_mensagem = :id');
$stmt->execute(['id' => $idMensagem]);
http_response_code(201);
echo json_encode([
    'sucesso'     => true,
    'id_conversa' => (int) $conversa['id_conversa'],
    'mensagem'    => $stmt->fetch(),
]);
