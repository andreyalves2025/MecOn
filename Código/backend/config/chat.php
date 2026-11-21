<?php
/**
 * MecOn - helpers do chat (ver migrations/022_chat.sql)
 * Requer config/session_check.php já carregado.
 *
 * Usuário e oficina têm sessões independentes e podem estar logados no
 * mesmo navegador ao mesmo tempo, então o cliente HTTP diz explicitamente
 * com qual identidade está falando ("como": usuario | oficina) em vez de o
 * servidor adivinhar. A identidade é sempre conferida contra a sessão —
 * "como" só escolhe QUAL sessão usar, nunca substitui a autenticação.
 */

const CHAT_TAMANHO_MAXIMO = 2000;
const CHAT_LIMITE_MENSAGENS_POR_MINUTO = 15;

/**
 * Exige login na identidade pedida e devolve ['tipo' => 'usuario'|'oficina', 'id' => int].
 * Para "usuario" também exige email confirmado quando $exigirEmailVerificado
 * (necessário para INICIAR/ENVIAR; ler conversas já existentes não exige).
 */
function resolverAtorChat(?string $como, ?PDO $pdo = null, bool $exigirEmailVerificado = false): array
{
    if ($como === 'oficina') {
        $oficina = exigirOficina();
        return ['tipo' => 'oficina', 'id' => (int) $oficina['id_oficina']];
    }
    if ($como === 'usuario') {
        $usuario = $exigirEmailVerificado && $pdo !== null
            ? exigirEmailVerificado($pdo)
            : exigirLogin();
        return ['tipo' => 'usuario', 'id' => (int) $usuario['id_usuario']];
    }
    responderErro(400, 'O parâmetro "como" deve ser "usuario" ou "oficina".');
}

/**
 * Carrega uma conversa só se ela pertencer ao ator. Para qualquer outra
 * conversa responde 404 (não 403), sem revelar que ela existe.
 */
function carregarConversaDoAtor(PDO $pdo, array $ator, $idConversa): array
{
    if (!is_numeric($idConversa)) {
        responderErro(400, 'id_conversa é obrigatório e deve ser numérico.');
    }
    $coluna = $ator['tipo'] === 'usuario' ? 'id_usuario' : 'id_oficina';
    $stmt = $pdo->prepare("SELECT * FROM conversas WHERE id_conversa = :id AND $coluna = :ator");
    $stmt->execute(['id' => $idConversa, 'ator' => $ator['id']]);
    $conversa = $stmt->fetch();
    if (!$conversa) {
        responderErro(404, 'Conversa não encontrada.');
    }
    return $conversa;
}
