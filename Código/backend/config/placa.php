<?php
/**
 * MecOn - proteção da placa do veículo (dado pessoal, LGPD).
 *
 * 1) CRIPTOGRAFIA EM REPOUSO: a placa é gravada cifrada (AES-256-GCM) em
 *    veiculos.placa e em agendamentos.placa_snapshot, no formato
 *    "enc:v1:" + base64(iv[12] + tag[16] + texto cifrado). Quem lê o banco
 *    (ou um backup) vê só o texto cifrado. Valores sem o prefixo "enc:v1:"
 *    (dados antigos/seed) são tratados como texto puro até rodar
 *    backend/tools/cifrar_placas.php.
 *    A chave vem de MECON_CHAVE_PLACA (base64 de 32 bytes) ou, se a variável
 *    não existir, do arquivo backend/storage/chave_placa.key (gerado na
 *    primeira utilização, fora do versionamento). GUARDE A CHAVE SEPARADA DO
 *    BACKUP DO BANCO: sem ela as placas cifradas não podem ser recuperadas.
 *
 * 2) MINIMIZAÇÃO PARA A OFICINA: a oficina só enxerga a placa completa quando
 *    o cliente já a escolheu (agendamento aceito/concluído ou orçamento
 *    aprovado). Antes disso vê a placa mascarada ("ABC****"), o suficiente
 *    para saber que há um veículo, mas não para identificá-lo.
 */
const PLACA_PREFIXO = 'enc:v1:';

function chavePlaca(): string
{
    static $chave = null;
    if ($chave !== null) {
        return $chave;
    }
    $env = getenv('MECON_CHAVE_PLACA');
    if ($env !== false && $env !== '') {
        $bruta = base64_decode($env, true);
        if ($bruta === false || strlen($bruta) !== 32) {
            throw new RuntimeException('MECON_CHAVE_PLACA deve ser base64 de 32 bytes.');
        }
        return $chave = $bruta;
    }
    $arquivo = __DIR__ . '/../storage/chave_placa.key';
    if (!is_file($arquivo)) {
        file_put_contents($arquivo, base64_encode(random_bytes(32)));
        @chmod($arquivo, 0600);
    }
    $bruta = base64_decode(trim((string) file_get_contents($arquivo)), true);
    if ($bruta === false || strlen($bruta) !== 32) {
        throw new RuntimeException('Arquivo de chave da placa inválido.');
    }
    return $chave = $bruta;
}

function placaCifrar(string $placa): string
{
    $placa = strtoupper(trim($placa));
    $iv = random_bytes(12);
    $tag = '';
    $cifrado = openssl_encrypt($placa, 'aes-256-gcm', chavePlaca(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cifrado === false) {
        throw new RuntimeException('Falha ao cifrar a placa.');
    }
    return PLACA_PREFIXO . base64_encode($iv . $tag . $cifrado);
}

function placaEstaCifrada(?string $valor): bool
{
    return $valor !== null && strncmp($valor, PLACA_PREFIXO, strlen(PLACA_PREFIXO)) === 0;
}

/** Devolve a placa em texto puro (ou o valor original, se ainda não estiver cifrado). */
function placaDecifrar(?string $valor): ?string
{
    if ($valor === null || !placaEstaCifrada($valor)) {
        return $valor;
    }
    $bin = base64_decode(substr($valor, strlen(PLACA_PREFIXO)), true);
    if ($bin === false || strlen($bin) < 29) {
        return null;
    }
    $texto = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', chavePlaca(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
    return $texto === false ? null : $texto;
}

/** "ABC1D23" -> "ABC****" */
function placaMascarar(?string $placaPura): ?string
{
    if ($placaPura === null || $placaPura === '') {
        return $placaPura;
    }
    return mb_substr($placaPura, 0, 3) . '****';
}

/**
 * Placa como a OFICINA deve ver. $statusRelacao é o status do agendamento
 * ('aceito'/'concluido') ou do orçamento ('aprovado'): só esses liberam a
 * placa completa.
 */
function placaParaOficina(?string $valorGravado, string $statusRelacao): ?string
{
    $pura = placaDecifrar($valorGravado);
    return in_array($statusRelacao, ['aceito', 'concluido', 'aprovado'], true) ? $pura : placaMascarar($pura);
}
