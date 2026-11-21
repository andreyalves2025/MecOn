<?php
/**
 * MecOn - helper de envio de email
 *
 * IMPORTANTE (leia antes de usar em produção):
 * A função mail() do PHP depende de um servidor SMTP configurado no
 * ambiente (WampServer/XAMPP não vêm com isso pronto, e serviços de email
 * como Gmail/Outlook bloqueiam envio direto sem autenticação). Para
 * produção de verdade, troque enviarEmail() por uma biblioteca como
 * PHPMailer + um provedor transacional (SendGrid, Mailgun, Amazon SES etc).
 *
 * Para não travar o fluxo de cadastro enquanto isso não existe, em modo
 * de desenvolvimento (EMAIL_MODO_DEV = true) o "envio" só grava o link em
 * um arquivo de log local (logs/emails_dev.log) e a rota de cadastro
 * devolve o link também na resposta da API, para dar pra testar o fluxo
 * de ponta a ponta sem um servidor de email de verdade. Isso NUNCA deve
 * ir para produção (exporia o link de verificação de qualquer usuário
 * para quem tiver acesso à resposta da própria requisição de cadastro,
 * o que é seguro só porque é a resposta pro próprio usuário que acabou
 * de se cadastrar — mas mesmo assim, é só para desenvolvimento).
 */
const EMAIL_MODO_DEV = true; // true = grava em log local; false = tenta mail() de verdade

/**
 * Gera um token de verificação de email (validade 24h), grava no banco e
 * "envia" o email com o link de confirmação. Reaproveitado por
 * register.php (no cadastro) e reenviar_verificacao.php (reenvio manual).
 */
function gerarEEnviarTokenVerificacao(PDO $pdo, int $idUsuario, string $email, string $nome): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('
        INSERT INTO tokens_verificacao_email (token, id_usuario, expira_em)
        VALUES (:token, :id_usuario, DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ');
    $stmt->execute(['token' => $token, 'id_usuario' => $idUsuario]);

    // Em produção, trocar pela URL pública real do frontend.
    $linkVerificacao = "http://localhost/mecon/frontend/pages/verificar-email.html?token={$token}";
    $corpoHtml = "
        <p>Olá, " . htmlspecialchars($nome) . "!</p>
        <p>Confirme seu email no MecOn clicando no link abaixo (válido por 24 horas):</p>
        <p><a href=\"{$linkVerificacao}\">{$linkVerificacao}</a></p>
        <p>Se você não se cadastrou no MecOn, ignore este email.</p>
    ";
    enviarEmail($email, 'Confirme seu email - MecOn', $corpoHtml);
    return $token;
}

/**
 * Tenta enviar um email. Retorna true em caso de sucesso (ou, em modo dev,
 * sempre true após gravar o log).
 */
function enviarEmail(string $destinatario, string $assunto, string $corpoHtml): bool
{
    if (EMAIL_MODO_DEV) {
        $linhaLog = sprintf(
            "[%s] Para: %s | Assunto: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $destinatario,
            $assunto,
            strip_tags($corpoHtml)
        );
        $diretorioLogs = __DIR__ . '/../../logs';
        if (!is_dir($diretorioLogs)) {
            mkdir($diretorioLogs, 0755, true);
        }
        file_put_contents($diretorioLogs . '/emails_dev.log', $linhaLog, FILE_APPEND);
        return true;
    }
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: MecOn <naoresponda@mecon.com.br>' . "\r\n";
    return mail($destinatario, $assunto, $corpoHtml, $headers);
}
