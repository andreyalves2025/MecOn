<?php
/**
 * MecOn - helper de peças utilizadas (ver migrations/019_pecas_utilizadas.sql)
 * Usado por historico/create_historico.php, historico/update_historico.php,
 * historico/list_historico.php e agendamentos/concluir_agendamento.php.
 * Requer config/session_check.php já carregado (usa responderErro).
 */

const PECAS_MAX_POR_REGISTRO = 50;

/**
 * Lê e valida a lista "pecas" de $dados. Cada item:
 *   { "nome": "Filtro de óleo", "quantidade": 1, "valor_unitario": 25.50 }
 * "quantidade" assume 1 se omitida; "valor_unitario" é opcional.
 * Retorna null se a chave "pecas" não foi enviada (sinal de "não mexer nas
 * peças existentes" em um update) ou a lista normalizada (pode ser vazia,
 * o que significa "remover todas as peças").
 */
function lerPecas(array $dados): ?array
{
    if (!array_key_exists('pecas', $dados)) {
        return null;
    }
    $lista = $dados['pecas'];
    if (!is_array($lista)) {
        responderErro(400, 'pecas deve ser uma lista.');
    }
    if (count($lista) > PECAS_MAX_POR_REGISTRO) {
        responderErro(400, 'Máximo de ' . PECAS_MAX_POR_REGISTRO . ' peças por registro.');
    }
    $pecas = [];
    foreach ($lista as $item) {
        if (!is_array($item)) {
            responderErro(400, 'Cada peça deve ser um objeto com nome, quantidade e valor_unitario.');
        }
        $nome = trim($item['nome'] ?? '');
        if ($nome === '' || mb_strlen($nome) > 150) {
            responderErro(400, 'Cada peça precisa de um nome (até 150 caracteres).');
        }
        $quantidade = $item['quantidade'] ?? 1;
        if ($quantidade === '' || $quantidade === null) {
            $quantidade = 1;
        }
        if (!is_numeric($quantidade) || (float) $quantidade <= 0 || (float) $quantidade > 999999.99) {
            responderErro(400, "Quantidade inválida para a peça \"$nome\".");
        }
        $valorUnitario = $item['valor_unitario'] ?? null;
        if ($valorUnitario === '' || $valorUnitario === null) {
            $valorUnitario = null;
        } elseif (!is_numeric($valorUnitario) || (float) $valorUnitario < 0 || (float) $valorUnitario > 99999999.99) {
            responderErro(400, "Valor unitário inválido para a peça \"$nome\".");
        } else {
            $valorUnitario = round((float) $valorUnitario, 2);
        }
        $pecas[] = [
            'nome'           => $nome,
            'quantidade'     => round((float) $quantidade, 2),
            'valor_unitario' => $valorUnitario,
        ];
    }
    return $pecas;
}

/**
 * Soma quantidade * valor_unitario das peças que têm valor unitário.
 * Retorna null se nenhuma peça tem valor (não dá para inferir o total).
 */
function somaValorPecas(array $pecas): ?float
{
    $soma = 0.0;
    $algumValor = false;
    foreach ($pecas as $p) {
        if ($p['valor_unitario'] !== null) {
            $soma += $p['quantidade'] * $p['valor_unitario'];
            $algumValor = true;
        }
    }
    return $algumValor ? round($soma, 2) : null;
}

/**
 * Substitui as peças de um registro de histórico pela lista informada
 * (apaga as anteriores e insere as novas). Chamar dentro de transação.
 */
function gravarPecas(PDO $pdo, int $idHistorico, array $pecas): void
{
    $stmt = $pdo->prepare('DELETE FROM pecas_utilizadas WHERE id_historico = :id');
    $stmt->execute(['id' => $idHistorico]);
    if (empty($pecas)) {
        return;
    }
    $stmt = $pdo->prepare('
        INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario)
        VALUES (:id_historico, :nome, :quantidade, :valor_unitario)
    ');
    foreach ($pecas as $p) {
        $stmt->execute([
            'id_historico'   => $idHistorico,
            'nome'           => $p['nome'],
            'quantidade'     => $p['quantidade'],
            'valor_unitario' => $p['valor_unitario'],
        ]);
    }
}

/**
 * Busca as peças de vários registros de uma vez (evita N+1). Retorna
 * [id_historico => [ {id_peca, nome, quantidade, valor_unitario}, ... ]].
 */
function listarPecasPorHistorico(PDO $pdo, array $idsHistorico): array
{
    if (empty($idsHistorico)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($idsHistorico), '?'));
    $stmt = $pdo->prepare("
        SELECT id_peca, id_historico, nome, quantidade, valor_unitario
        FROM pecas_utilizadas
        WHERE id_historico IN ($placeholders)
        ORDER BY id_peca ASC
    ");
    $stmt->execute(array_values($idsHistorico));
    $mapa = [];
    foreach ($stmt->fetchAll() as $linha) {
        $mapa[$linha['id_historico']][] = [
            'id_peca'        => (int) $linha['id_peca'],
            'nome'           => $linha['nome'],
            'quantidade'     => (float) $linha['quantidade'],
            'valor_unitario' => $linha['valor_unitario'] !== null ? (float) $linha['valor_unitario'] : null,
        ];
    }
    return $mapa;
}
