<?php
/**
 * MecOn - Conexão com o banco de dados (PDO/MySQL)
 *
 * IMPORTANTE: edite DB_PASS abaixo com a senha real do seu usuário
 * "root" no MySQL local antes de rodar o projeto no WampServer.
 */
// Os valores abaixo podem vir de variáveis de ambiente (MECON_DB_HOST, MECON_DB_PORT,
// MECON_DB_NAME, MECON_DB_USER, MECON_DB_PASS) — útil em hospedagem, para não deixar
// senha no código versionado. Sem variáveis, valem os padrões do WampServer local.
define('DB_HOST', getenv('MECON_DB_HOST') ?: 'localhost');
define('DB_PORT', (int) (getenv('MECON_DB_PORT') ?: 3306));   // confira a porta do MySQL no WampServer
define('DB_NAME', getenv('MECON_DB_NAME') ?: 'mecon');
define('DB_USER', getenv('MECON_DB_USER') ?: 'root');
define('DB_PASS', getenv('MECON_DB_PASS') !== false ? getenv('MECON_DB_PASS') : ''); // <-- senha do MySQL local
define('DB_CHARSET', 'utf8mb4');
/**
 * Enquanto estiver desenvolvendo, deixe true: a mensagem real do erro de conexão
 * aparece no JSON (campo "detalhe") e é gravada em logs/erro_conexao.log.
 * Em produção, troque para false.
 */
define('APP_DEBUG', getenv('MECON_DEBUG') !== false ? filter_var(getenv('MECON_DEBUG'), FILTER_VALIDATE_BOOLEAN) : true);
/**
 * Fuso horário da aplicação. O PHP e o MySQL usam relógios/fusos
 * independentes: sem alinhá-los, cálculos que misturam os dois (bloqueio
 * de login, expiração de tokens e de orçamentos, "hoje" nos relatórios)
 * podem errar por horas. Aqui os dois passam a usar o mesmo fuso.
 * O Brasil não tem horário de verão desde 2019, então o deslocamento
 * enviado ao MySQL ('-03:00') é estável; ele é calculado a partir do
 * fuso abaixo para continuar correto se APP_TIMEZONE for trocado.
 */
define('APP_TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set(APP_TIMEZONE);
/**
 * Retorna uma instância PDO conectada ao banco MecOn.
 * Lança PDOException em caso de falha de conexão.
 */
function getConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // alinha o fuso da sessão do MySQL ao do PHP (ver APP_TIMEZONE acima)
            $deslocamento = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
            $pdo->exec("SET time_zone = '" . $deslocamento . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            @file_put_contents(__DIR__ . '/../../logs/erro_conexao.log',
                '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            $resposta = [
                'sucesso' => false,
                'erro'    => 'Falha na conexão com o banco de dados.'
            ];
            if (APP_DEBUG) {
                $resposta['detalhe'] = $e->getMessage();
            }
            echo json_encode($resposta);
            exit;
        }
    }
    return $pdo;
}
