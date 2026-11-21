<?php
require_once __DIR__ . '/placa.php';
/**
 * MecOn - helpers do mini-CRM da oficina
 * Usado por oficina_auth/list_clientes.php e oficina_auth/get_cliente.php.
 *
 * Privacidade: o CRM é construído SÓ a partir de agendamentos, ou seja, de
 * clientes que escolheram contatar aquela oficina pela plataforma. O
 * histórico de manutenção que o usuário digita por conta própria (onde ele
 * pode ter citado a oficina sem nunca ter falado com ela) NÃO entra aqui —
 * senão a oficina passaria a ver dados de pessoas que não tiveram contato.
 */

const CRM_LIMIAR_RETORNO = 0.8;

/**
 * Sugestões de retorno: para cada combinação cliente + veículo + serviço
 * que a oficina concluiu, compara o tempo desde o último serviço com o
 * intervalo recomendado em meses do catálogo. Só tempo (a oficina não
 * conhece a km atual do veículo). Ignora combinações em que o cliente já
 * tem um novo agendamento em aberto para o mesmo veículo e serviço.
 *
 * $idsUsuario null = todos os clientes da oficina.
 * Retorna [id_usuario => [ {servico, placa, meses_desde, intervalo, status}, ... ]]
 * ("proximo" a partir de 80% do intervalo, "atrasado" a partir de 100%).
 */
function obterRetornosSugeridos(PDO $pdo, int $idOficina, ?array $idsUsuario = null): array
{
    $filtroUsuarios = '';
    $parametros = [$idOficina];
    if ($idsUsuario !== null) {
        if (empty($idsUsuario)) {
            return [];
        }
        $filtroUsuarios = ' AND ag.id_usuario IN (' . implode(',', array_fill(0, count($idsUsuario), '?')) . ')';
        $parametros = array_merge($parametros, array_values($idsUsuario));
    }
    $stmt = $pdo->prepare("
        SELECT ag.id_usuario, ag.placa_snapshot AS placa, s.id_servico, s.nome AS nome_servico,
               s.intervalo_recomendado_meses AS intervalo,
               TIMESTAMPDIFF(MONTH, ag.data_hora_solicitada, NOW()) AS meses_desde
        FROM agendamentos ag
        JOIN servicos s ON s.id_servico = ag.id_servico
        WHERE ag.id_oficina = ?
          AND ag.status = 'concluido'
          AND s.intervalo_recomendado_meses IS NOT NULL
          $filtroUsuarios
          AND ag.id_agendamento = (
              SELECT a2.id_agendamento
              FROM agendamentos a2
              WHERE a2.id_oficina = ag.id_oficina AND a2.id_usuario = ag.id_usuario
                AND a2.id_veiculo = ag.id_veiculo AND a2.id_servico = ag.id_servico
                AND a2.status = 'concluido'
              ORDER BY a2.data_hora_solicitada DESC, a2.id_agendamento DESC
              LIMIT 1
          )
          AND NOT EXISTS (
              SELECT 1 FROM agendamentos a3
              WHERE a3.id_oficina = ag.id_oficina AND a3.id_usuario = ag.id_usuario
                AND a3.id_veiculo = ag.id_veiculo AND a3.id_servico = ag.id_servico
                AND a3.status IN ('pendente', 'aceito')
          )
    ");
    $stmt->execute($parametros);

    $retornos = [];
    foreach ($stmt->fetchAll() as $linha) {
        $intervalo = (int) $linha['intervalo'];
        $meses     = (int) $linha['meses_desde'];
        $progresso = $meses / $intervalo;
        if ($progresso < CRM_LIMIAR_RETORNO) {
            continue;
        }
        $retornos[(int) $linha['id_usuario']][] = [
            'servico'     => $linha['nome_servico'],
            'placa'       => placaParaOficina($linha['placa'], 'concluido'),
            'meses_desde' => $meses,
            'intervalo'   => $intervalo,
            'status'      => $progresso >= 1 ? 'atrasado' : 'proximo',
        ];
    }
    return $retornos;
}

/**
 * Veículos atendidos/agendados e serviços concluídos (com contagem) de um
 * conjunto de clientes, numa consulta só. Retorna
 * [id_usuario => ['veiculos' => [placas], 'servicos' => [['nome','vezes'], ...]]].
 */
function obterVeiculosEServicos(PDO $pdo, int $idOficina, array $idsUsuario): array
{
    if (empty($idsUsuario)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($idsUsuario), '?'));
    $stmt = $pdo->prepare("
        SELECT ag.id_usuario, ag.placa_snapshot AS placa, s.nome AS nome_servico, ag.status
        FROM agendamentos ag
        JOIN servicos s ON s.id_servico = ag.id_servico
        WHERE ag.id_oficina = ? AND ag.id_usuario IN ($placeholders)
    ");
    $stmt->execute(array_merge([$idOficina], array_values($idsUsuario)));

    $resultado = [];
    foreach ($stmt->fetchAll() as $linha) {
        $u = (int) $linha['id_usuario'];
        if (!isset($resultado[$u])) {
            $resultado[$u] = ['veiculos' => [], 'servicos' => []];
        }
        $resultado[$u]['veiculos'][placaParaOficina($linha['placa'], $linha['status'])] = true;
        if ($linha['status'] === 'concluido') {
            $nome = $linha['nome_servico'];
            $resultado[$u]['servicos'][$nome] = ($resultado[$u]['servicos'][$nome] ?? 0) + 1;
        }
    }
    foreach ($resultado as &$r) {
        $r['veiculos'] = array_keys($r['veiculos']);
        arsort($r['servicos']);
        $lista = [];
        foreach ($r['servicos'] as $nome => $vezes) {
            $lista[] = ['nome' => $nome, 'vezes' => $vezes];
        }
        $r['servicos'] = $lista;
    }
    unset($r);
    return $resultado;
}
