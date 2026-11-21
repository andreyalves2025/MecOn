<?php
/**
 * MecOn - helper de upload de anexos (fotos/vídeos do pedido de orçamento)
 * Requer config/session_check.php já carregado (usa responderErro).
 *
 * Segurança:
 *  - O tipo do arquivo é detectado pelo CONTEÚDO (finfo), nunca pelo nome
 *    nem pelo Content-Type enviado pelo navegador, e só quatro tipos de
 *    imagem/vídeo são aceitos. Imagens ainda passam por getimagesize().
 *  - O arquivo em disco recebe nome aleatório com extensão derivada do tipo
 *    detectado (o nome original enviado pelo usuário nunca vira caminho).
 *  - A pasta de destino (backend/storage/anexos) bloqueia acesso direto por
 *    .htaccess; os arquivos só saem por orcamentos/baixar_anexo.php.
 *  - Todos os arquivos são validados ANTES de qualquer um ser gravado.
 */

const ANEXOS_MAX_POR_PEDIDO = 5;
const ANEXOS_MAX_IMAGEM_BYTES = 5 * 1024 * 1024;   // 5 MB
const ANEXOS_MAX_VIDEO_BYTES  = 25 * 1024 * 1024;  // 25 MB
const ANEXOS_TIPOS_PERMITIDOS = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
];

function diretorioAnexos(): string
{
    $dir = __DIR__ . '/../storage/anexos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Detecta o caso em que o corpo da requisição passou de post_max_size: o
 * PHP descarta TUDO ($_POST e $_FILES chegam vazios) sem avisar.
 */
function verificarLimitePostExcedido(): void
{
    $tamanho = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($tamanho > 0 && empty($_POST) && empty($_FILES)) {
        responderErro(
            413,
            'O envio excede o limite do servidor (post_max_size = ' . ini_get('post_max_size')
            . '). Envie menos/menores arquivos ou aumente esse valor no php.ini.'
        );
    }
}

/**
 * Lê $_FILES['anexos'] (campo múltiplo), valida tudo e devolve a lista de
 * arquivos prontos para gravar:
 *   [ ['tmp', 'nome_original', 'mime', 'extensao', 'tamanho'], ... ]
 * Lista vazia se nenhum arquivo foi enviado.
 */
function validarAnexosEnviados(string $campo = 'anexos'): array
{
    if (empty($_FILES[$campo]) || !is_array($_FILES[$campo]['name'])) {
        return [];
    }
    if (!class_exists('finfo')) {
        responderErro(500, 'A extensão fileinfo do PHP não está habilitada neste servidor (necessária para validar anexos).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $arquivos = [];
    foreach ($_FILES[$campo]['name'] as $i => $nomeOriginal) {
        $erro = $_FILES[$campo]['error'][$i];
        if ($erro === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $rotulo = '"' . mb_substr(basename((string) $nomeOriginal), 0, 60) . '"';
        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            responderErro(413, "O arquivo $rotulo excede o limite de envio do servidor (upload_max_filesize = " . ini_get('upload_max_filesize') . ').');
        }
        if ($erro !== UPLOAD_ERR_OK) {
            responderErro(500, "Falha ao receber o arquivo $rotulo (código $erro).");
        }
        $tmp = $_FILES[$campo]['tmp_name'][$i];
        if (!is_uploaded_file($tmp)) {
            responderErro(400, 'Arquivo de upload inválido.');
        }
        $mime = $finfo->file($tmp);
        if (!isset(ANEXOS_TIPOS_PERMITIDOS[$mime])) {
            responderErro(400, "O arquivo $rotulo não é um tipo aceito. Envie imagens (JPG, PNG, WEBP) ou vídeos (MP4, WEBM).");
        }
        $ehImagem = strpos($mime, 'image/') === 0;
        if ($ehImagem && @getimagesize($tmp) === false) {
            responderErro(400, "O arquivo $rotulo não é uma imagem válida.");
        }
        $tamanho = (int) filesize($tmp);
        $limite = $ehImagem ? ANEXOS_MAX_IMAGEM_BYTES : ANEXOS_MAX_VIDEO_BYTES;
        if ($tamanho > $limite) {
            responderErro(413, "O arquivo $rotulo excede o tamanho máximo de " . ($limite / 1024 / 1024) . ' MB para ' . ($ehImagem ? 'imagens' : 'vídeos') . '.');
        }
        $nomeLimpo = preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) $nomeOriginal));
        $arquivos[] = [
            'tmp'           => $tmp,
            'nome_original' => mb_substr($nomeLimpo !== '' ? $nomeLimpo : 'arquivo', 0, 255),
            'mime'          => $mime,
            'extensao'      => ANEXOS_TIPOS_PERMITIDOS[$mime],
            'tamanho'       => $tamanho,
        ];
    }
    if (count($arquivos) > ANEXOS_MAX_POR_PEDIDO) {
        responderErro(400, 'Máximo de ' . ANEXOS_MAX_POR_PEDIDO . ' arquivos por pedido.');
    }
    return $arquivos;
}

/**
 * Grava os arquivos já validados em disco e registra em anexos_pedido.
 * Devolve os caminhos gravados, para o chamador apagá-los se a transação
 * do banco falhar depois (ver removerArquivosGravados).
 */
function gravarAnexos(PDO $pdo, int $idPedido, array $arquivos): array
{
    $gravados = [];
    $dir = diretorioAnexos();
    $stmt = $pdo->prepare('
        INSERT INTO anexos_pedido (id_pedido, nome_original, nome_arquivo, tipo_mime, tamanho_bytes)
        VALUES (:pedido, :original, :arquivo, :mime, :tamanho)
    ');
    foreach ($arquivos as $a) {
        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $a['extensao'];
        $destino = $dir . '/' . $nomeArquivo;
        if (!move_uploaded_file($a['tmp'], $destino)) {
            removerArquivosGravados($gravados);
            throw new RuntimeException('Não foi possível gravar o arquivo no servidor.');
        }
        $gravados[] = $destino;
        $stmt->execute([
            'pedido'   => $idPedido,
            'original' => $a['nome_original'],
            'arquivo'  => $nomeArquivo,
            'mime'     => $a['mime'],
            'tamanho'  => $a['tamanho'],
        ]);
    }
    return $gravados;
}

function removerArquivosGravados(array $caminhos): void
{
    foreach ($caminhos as $c) {
        if (is_file($c)) {
            @unlink($c);
        }
    }
}
