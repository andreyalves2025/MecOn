<?php
/**
 * MecOn - helpers de rate limiting para login (backend/auth/login.php)
 * Ver migrations/008_rate_limit_login.sql para a tabela usada.
 */
const RATE_LIMIT_MAX_TENTATIVAS  = 5;   // tentativas inválidas seguidas permitidas
const RATE_LIMIT_JANELA_MINUTOS  = 15;  // após esse tempo sem tentativas, o contador "esfria"
const RATE_LIMIT_BLOQUEIO_MINUTOS = 15; // duração do bloqueio ao estourar o limite

/**
 * Verifica se o email está bloqueado agora. Retorna os segundos restantes
 * de bloqueio (int > 0) ou null se não há bloqueio ativo. Limpa registros
 * de bloqueio já expirados como efeito colateral (housekeeping simples,
 * sem precisar de um job separado).
 */
function verificarBloqueioLogin(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT tentativas, atualizado_em, bloqueado_ate FROM tentativas_login WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $registro = $stmt->fetch();
    if (!$registro) {
        return null;
    }
    $agora = new DateTime();
    if ($registro['bloqueado_ate'] !== null) {
        $bloqueadoAte = new DateTime($registro['bloqueado_ate']);
        if ($bloqueadoAte > $agora) {
            return $bloqueadoAte->getTimestamp() - $agora->getTimestamp();
        }
        // Bloqueio expirado: limpa para começar do zero na próxima tentativa
        $stmt = $pdo->prepare('DELETE FROM tentativas_login WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return null;
    }
    // Sem bloqueio ativo, mas se a última tentativa foi há muito tempo,
    // o contador "esfria" (evita que uma tentativa isolada há meses conte
    // junto com uma de agora)
    $atualizadoEm = new DateTime($registro['atualizado_em']);
    $minutosDesdeUltima = ($agora->getTimestamp() - $atualizadoEm->getTimestamp()) / 60;
    if ($minutosDesdeUltima > RATE_LIMIT_JANELA_MINUTOS) {
        $stmt = $pdo->prepare('DELETE FROM tentativas_login WHERE email = :email');
        $stmt->execute(['email' => $email]);
    }
    return null;
}

/**
 * Registra uma tentativa de login inválida para o email. Se atingir o
 * limite, define bloqueado_ate. Chamado só quando email e/ou senha não
 * conferem (nunca em caso de sucesso).
 */
function registrarFalhaLogin(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('
        INSERT INTO tentativas_login (email, tentativas, atualizado_em)
        VALUES (:email, 1, NOW())
        ON DUPLICATE KEY UPDATE tentativas = tentativas + 1, atualizado_em = NOW()
    ');
    $stmt->execute(['email' => $email]);

    $stmt = $pdo->prepare('SELECT tentativas FROM tentativas_login WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $tentativas = (int) $stmt->fetch()['tentativas'];

    if ($tentativas >= RATE_LIMIT_MAX_TENTATIVAS) {
        $stmt = $pdo->prepare("
            UPDATE tentativas_login
            SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL :minutos MINUTE)
            WHERE email = :email
        ");
        $stmt->execute(['minutos' => RATE_LIMIT_BLOQUEIO_MINUTOS, 'email' => $email]);
    }
}

/**
 * Limpa o histórico de tentativas de um email após um login bem-sucedido.
 */
function limparTentativasLogin(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('DELETE FROM tentativas_login WHERE email = :email');
    $stmt->execute(['email' => $email]);
}
