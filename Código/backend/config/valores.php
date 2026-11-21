<?php
/**
 * MecOn - helper de validação de valores financeiros
 * Usado por historico/create_historico.php, historico/update_historico.php
 * e agendamentos/concluir_agendamento.php. Requer config/session_check.php
 * já carregado (usa responderErro).
 */

/**
 * Lê valor_total, valor_pecas e valor_mao_obra de $dados, valida (numérico,
 * >= 0, até 2 casas decimais úteis) e devolve os três já normalizados
 * (float ou null). Se valor_total não foi informado mas pelo menos uma das
 * partes foi, valor_total é calculado como a soma das partes informadas.
 */
function lerValoresFinanceiros(array $dados): array
{
    $resultado = [];
    foreach (['valor_total', 'valor_pecas', 'valor_mao_obra'] as $campo) {
        $valor = $dados[$campo] ?? null;
        if ($valor === '' || $valor === null) {
            $resultado[$campo] = null;
            continue;
        }
        if (!is_numeric($valor) || (float) $valor < 0) {
            responderErro(400, "$campo deve ser um número maior ou igual a zero.");
        }
        if ((float) $valor > 99999999.99) {
            responderErro(400, "$campo excede o valor máximo permitido.");
        }
        $resultado[$campo] = round((float) $valor, 2);
    }
    if ($resultado['valor_total'] === null
        && ($resultado['valor_pecas'] !== null || $resultado['valor_mao_obra'] !== null)) {
        $resultado['valor_total'] = round(
            ($resultado['valor_pecas'] ?? 0) + ($resultado['valor_mao_obra'] ?? 0),
            2
        );
    }
    return $resultado;
}
