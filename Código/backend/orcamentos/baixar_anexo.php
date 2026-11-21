<?php
/**
 * MecOn - GET /backend/orcamentos/baixar_anexo.php?id_anexo=1
 *
 * Único caminho pelo qual um anexo (foto/vídeo de pedido de orçamento) sai
 * do servidor — a pasta backend/storage/anexos bloqueia acesso direto.
 * Quem pode ver:
 *   - o cliente dono do pedido (sessão de usuário), ou
 *   - uma oficina que recebeu orçamento desse pedido (e o pedido não foi
 *     cancelado), enquanto logada como oficina.
 * Qualquer outro caso responde 404 (sem revelar que o anexo existe).
 * Suporta "Range" para que vídeos possam ser avançados/retrocedidos.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/anexos.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$idAnexo = $_GET['id_anexo'] ?? null;
if (!is_numeric($idAnexo)) {
    responderErro(400, 'id_anexo é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT a.nome_original, a.nome_arquivo, a.tipo_mime, p.id_pedido, p.id_usuario
    FROM anexos_pedido a JOIN pedidos_orcamento p ON p.id_pedido = a.id_pedido
    WHERE a.id_anexo = :id
");
$stmt->execute(['id' => $idAnexo]);
$anexo = $stmt->fetch();

$autorizado = false;
if ($anexo) {
    if (estaLogado() && (int) $_SESSION['id_usuario'] === (int) $anexo['id_usuario']) {
        $autorizado = true;
    } elseif (oficinaEstaLogada()) {
        $stmt = $pdo->prepare("SELECT 1 FROM orcamentos WHERE id_pedido = :p AND id_oficina = :o AND status <> 'cancelado'");
        $stmt->execute(['p' => $anexo['id_pedido'], 'o' => $_SESSION['id_oficina']]);
        $autorizado = (bool) $stmt->fetch();
    }
}
if (!$autorizado) {
    responderErro(404, 'Anexo não encontrado.');
}
// Defesa extra: o nome gravado só pode ter o formato que nós mesmos geramos
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|mp4|webm)$/', $anexo['nome_arquivo'])) {
    responderErro(404, 'Anexo não encontrado.');
}
$caminho = diretorioAnexos() . '/' . $anexo['nome_arquivo'];
if (!is_file($caminho)) {
    responderErro(404, 'Arquivo não encontrado no servidor.');
}

session_write_close(); // não segura a sessão durante a transferência
$tamanho = filesize($caminho);
$inicio = 0;
$fim = $tamanho - 1;
$parcial = false;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m) && ($m[1] !== '' || $m[2] !== '')) {
    if ($m[1] === '') {                       // "últimos N bytes"
        $inicio = max(0, $tamanho - (int) $m[2]);
    } else {
        $inicio = (int) $m[1];
        if ($m[2] !== '') {
            $fim = min((int) $m[2], $tamanho - 1);
        }
    }
    if ($inicio > $fim || $inicio >= $tamanho) {
        http_response_code(416);
        header("Content-Range: bytes */$tamanho");
        exit;
    }
    $parcial = true;
}
$nomeSeguro = rawurlencode($anexo['nome_original']);
http_response_code($parcial ? 206 : 200);
header('Content-Type: ' . $anexo['tipo_mime']);
header('Content-Disposition: inline; filename*=UTF-8\'\'' . $nomeSeguro);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');
header('Content-Length: ' . ($fim - $inicio + 1));
if ($parcial) {
    header("Content-Range: bytes $inicio-$fim/$tamanho");
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
$f = fopen($caminho, 'rb');
fseek($f, $inicio);
$restante = $fim - $inicio + 1;
while ($restante > 0 && !feof($f)) {
    $bloco = fread($f, min(8192, $restante));
    echo $bloco;
    $restante -= strlen($bloco);
}
fclose($f);
