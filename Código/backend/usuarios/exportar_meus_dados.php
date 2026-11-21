<?php
/**
 * MecOn - GET /backend/usuarios/exportar_meus_dados.php[?download=1]
 * Portabilidade/acesso do titular (LGPD, art. 18, II e V): devolve em JSON
 * TODOS os dados pessoais do usuário logado guardados pelo MecOn. Nunca
 * inclui o hash da senha. Com ?download=1 o navegador baixa o arquivo.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$sessao = exigirLogin();
$id = (int) $sessao['id_usuario'];
$pdo = getConnection();

$consultar = function (string $sql, array $params = []) use ($pdo): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$stmt = $pdo->prepare('SELECT id_usuario, nome, email, tipo, email_verificado, termos_aceitos_em, termos_versao, data_cadastro FROM usuarios WHERE id_usuario = :id');
$stmt->execute(['id' => $id]);
$perfil = $stmt->fetch();
if (!$perfil) {
    responderErro(404, 'Usuário não encontrado.');
}

$dados = [
    'gerado_em' => date('c'),
    'base_legal' => 'LGPD (Lei 13.709/2018), art. 18',
    'perfil' => $perfil,
    'veiculos' => $consultar('SELECT id_veiculo, placa, km_atual, data_cadastro FROM veiculos WHERE id_usuario = :id', ['id' => $id]),
    'historico_manutencoes' => $consultar("
        SELECT h.id_historico, v.placa, o.nome AS oficina, s.nome AS servico, h.data_servico,
               h.km_servico, h.valor_total, h.valor_pecas, h.valor_mao_obra
        FROM historico_manutencoes h
        JOIN veiculos v ON v.id_veiculo = h.id_veiculo
        JOIN oficinas o ON o.id_oficina = h.id_oficina
        JOIN servicos s ON s.id_servico = h.id_servico
        WHERE v.id_usuario = :id ORDER BY h.data_servico DESC", ['id' => $id]),
    'pecas_utilizadas' => $consultar("
        SELECT p.id_historico, p.nome, p.quantidade, p.valor_unitario
        FROM pecas_utilizadas p
        JOIN historico_manutencoes h ON h.id_historico = p.id_historico
        JOIN veiculos v ON v.id_veiculo = h.id_veiculo
        WHERE v.id_usuario = :id", ['id' => $id]),
    'pedidos_orcamento' => $consultar('SELECT * FROM pedidos_orcamento WHERE id_usuario = :id', ['id' => $id]),
    'orcamentos' => $consultar("
        SELECT q.* FROM orcamentos q
        JOIN pedidos_orcamento p ON p.id_pedido = q.id_pedido WHERE p.id_usuario = :id", ['id' => $id]),
    'anexos_enviados' => $consultar("
        SELECT a.id_anexo, a.id_pedido, a.nome_original, a.tipo_mime, a.tamanho_bytes, a.criado_em
        FROM anexos_pedido a JOIN pedidos_orcamento p ON p.id_pedido = a.id_pedido
        WHERE p.id_usuario = :id", ['id' => $id]),
    'agendamentos' => $consultar('SELECT id_agendamento, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, valor_total, criado_em FROM agendamentos WHERE id_usuario = :id', ['id' => $id]),
    'ordens_servico' => $consultar("
        SELECT os.* FROM ordens_servico os
        JOIN agendamentos a ON a.id_agendamento = os.id_agendamento WHERE a.id_usuario = :id", ['id' => $id]),
    'itens_ordens_servico' => $consultar("
        SELECT i.* FROM itens_os i
        JOIN ordens_servico os ON os.id_os = i.id_os
        JOIN agendamentos a ON a.id_agendamento = os.id_agendamento WHERE a.id_usuario = :id", ['id' => $id]),
    'avaliacoes' => $consultar('SELECT * FROM avaliacoes WHERE id_usuario = :id', ['id' => $id]),
    'denuncias_feitas' => $consultar('SELECT * FROM denuncias WHERE id_usuario = :id', ['id' => $id]),
    'favoritos' => $consultar('SELECT id_oficina FROM favoritos WHERE id_usuario = :id', ['id' => $id]),
    'conversas' => $consultar('SELECT * FROM conversas WHERE id_usuario = :id', ['id' => $id]),
    'mensagens' => $consultar("
        SELECT m.* FROM mensagens m
        JOIN conversas c ON c.id_conversa = m.id_conversa WHERE c.id_usuario = :id ORDER BY m.id_mensagem", ['id' => $id]),
];
foreach (['veiculos', 'historico_manutencoes'] as $chave) {
    foreach ($dados[$chave] as &$linha) {
        $linha['placa'] = placaDecifrar($linha['placa']);
    }
    unset($linha);
}

if (!empty($_GET['download'])) {
    header('Content-Disposition: attachment; filename="meus-dados-mecon.json"');
}
echo json_encode(['sucesso' => true, 'dados' => $dados], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
