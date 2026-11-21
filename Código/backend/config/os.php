<?php
/**
 * MecOn - helpers da ordem de serviço (ver migrations/024_ordem_servico.sql)
 * Requer config/session_check.php (responderErro) e config/pecas.php
 * (os itens da OS têm o mesmo formato das peças: { nome, quantidade,
 * valor_unitario }, então a validação é a mesma — lerPecas()).
 */

const OS_ETAPAS_EDITAVEIS = ['aberta', 'em_execucao', 'aguardando_peca', 'pronta'];

/** Substitui os itens da OS pela lista informada. Chamar dentro de transação. */
function gravarItensOS(PDO $pdo, int $idOs, array $itens): void
{
    $stmt = $pdo->prepare('DELETE FROM itens_os WHERE id_os = :id');
    $stmt->execute(['id' => $idOs]);
    if (empty($itens)) {
        return;
    }
    $stmt = $pdo->prepare('
        INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario)
        VALUES (:os, :nome, :qtd, :valor)
    ');
    foreach ($itens as $i) {
        $stmt->execute(['os' => $idOs, 'nome' => $i['nome'], 'qtd' => $i['quantidade'], 'valor' => $i['valor_unitario']]);
    }
}

function listarItensOS(PDO $pdo, int $idOs): array
{
    $stmt = $pdo->prepare('SELECT id_item, nome, quantidade, valor_unitario FROM itens_os WHERE id_os = :id ORDER BY id_item');
    $stmt->execute(['id' => $idOs]);
    $itens = [];
    foreach ($stmt->fetchAll() as $l) {
        $itens[] = [
            'nome'           => $l['nome'],
            'quantidade'     => (float) $l['quantidade'],
            'valor_unitario' => $l['valor_unitario'] !== null ? (float) $l['valor_unitario'] : null,
        ];
    }
    return $itens;
}

/**
 * Totais da OS: valor_pecas (null se nenhum item tem valor unitário),
 * mão de obra, desconto e total = max(0, peças + mão de obra - desconto).
 */
function calcularTotaisOS(array $itens, ?float $maoObra, ?float $desconto): array
{
    $pecas = somaValorPecas($itens);
    $total = ($pecas ?? 0) + ($maoObra ?? 0) - ($desconto ?? 0);
    return [
        'valor_pecas'    => $pecas,
        'valor_mao_obra' => $maoObra,
        'desconto'       => $desconto,
        'valor_total'    => round(max(0, $total), 2),
    ];
}

/** Lê um valor monetário opcional de $dados[$campo] (null se ausente/vazio). */
function lerValorOpcional(array $dados, string $campo): ?float
{
    $v = $dados[$campo] ?? null;
    if ($v === null || $v === '') {
        return null;
    }
    if (!is_numeric($v) || (float) $v < 0 || (float) $v > 99999999.99) {
        responderErro(400, "$campo deve ser um número maior ou igual a zero.");
    }
    return round((float) $v, 2);
}
