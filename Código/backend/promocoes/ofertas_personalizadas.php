<?php
/**
 * MecOn - GET /backend/promocoes/ofertas_personalizadas.php
 *
 * "Ofertas para o seu veículo": cruza as promoções ATIVAS com o que se
 * sabe do usuário (lembretes de manutenção, histórico e favoritos) e
 * devolve só as que têm algum motivo concreto para interessá-lo, do mais
 * relevante para o menos. É diferente de list_promocoes_ativas.php, que
 * lista todas as ofertas para qualquer visitante.
 *
 * Motivos e pesos (somados; uma oferta só entra se tiver ao menos um
 * motivo de personalização — "verificada" sozinha não basta):
 *   manutencao_atrasada  100  a promoção é de um serviço que o usuário tem atrasado
 *   manutencao_proxima    80  ... de um serviço que está chegando
 *   servico_ja_realizado  40  ... de um serviço que o usuário já fez antes
 *   oficina_favorita      20  a oficina está nos favoritos
 *   oficina_ja_utilizada  15  o usuário já foi atendido nessa oficina
 *   oficina_verificada     5  só desempata, não personaliza
 * Empates saem pela data de término (a que acaba primeiro vem antes).
 *
 * Promoções gerais (sem serviço vinculado) só aparecem se a oficina for
 * favorita ou já utilizada. Máximo de 10 ofertas. Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/lembretes.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario   = exigirLogin();
$idUsuario = (int) $usuario['id_usuario'];
$pdo       = getConnection();

// Lembretes por serviço (um serviço pode estar pendente em mais de um veículo)
$lembretesPorServico = [];
foreach (calcularLembretesUsuario($pdo, $idUsuario) as $l) {
    $lembretesPorServico[$l['id_servico']][] = $l;
}

$stmt = $pdo->prepare('
    SELECT DISTINCT h.id_servico, h.id_oficina
    FROM historico_manutencoes h
    JOIN veiculos v ON v.id_veiculo = h.id_veiculo
    WHERE v.id_usuario = :id
');
$stmt->execute(['id' => $idUsuario]);
$servicosJaFeitos = [];
$oficinasUtilizadas = [];
foreach ($stmt->fetchAll() as $linha) {
    $servicosJaFeitos[(int) $linha['id_servico']] = true;
    $oficinasUtilizadas[(int) $linha['id_oficina']] = true;
}

$stmt = $pdo->prepare('SELECT id_oficina FROM favoritos WHERE id_usuario = :id');
$stmt->execute(['id' => $idUsuario]);
$oficinasFavoritas = [];
foreach ($stmt->fetchAll() as $linha) {
    $oficinasFavoritas[(int) $linha['id_oficina']] = true;
}

$promocoes = $pdo->query("
    SELECT p.id_promocao, p.titulo, p.descricao, p.data_fim,
           p.id_oficina, o.nome AS nome_oficina, o.verificada,
           p.id_servico, s.nome AS nome_servico
    FROM promocoes p
    JOIN oficinas o ON o.id_oficina = p.id_oficina
    LEFT JOIN servicos s ON s.id_servico = p.id_servico
    WHERE CURDATE() BETWEEN p.data_inicio AND p.data_fim
      AND o.ativa = 1
")->fetchAll();

$ofertas = [];
foreach ($promocoes as $p) {
    $idOficina = (int) $p['id_oficina'];
    $idServico = $p['id_servico'] !== null ? (int) $p['id_servico'] : null;
    $score = 0;
    $motivos = [];
    $placas = [];

    if ($idServico !== null && isset($lembretesPorServico[$idServico])) {
        $atrasado = false;
        foreach ($lembretesPorServico[$idServico] as $l) {
            $placas[] = $l['placa'];
            if ($l['status'] === 'atrasado') {
                $atrasado = true;
            }
        }
        $placas = array_values(array_unique($placas));
        $listaPlacas = implode(', ', $placas);
        if ($atrasado) {
            $score += 100;
            $motivos[] = ['tipo' => 'manutencao_atrasada', 'texto' => "Sua {$p['nome_servico']} está atrasada ($listaPlacas)"];
        } else {
            $score += 80;
            $motivos[] = ['tipo' => 'manutencao_proxima', 'texto' => "Sua {$p['nome_servico']} está chegando ($listaPlacas)"];
        }
    } elseif ($idServico !== null && isset($servicosJaFeitos[$idServico])) {
        $score += 40;
        $motivos[] = ['tipo' => 'servico_ja_realizado', 'texto' => "Você já fez {$p['nome_servico']} antes"];
    }
    if (isset($oficinasFavoritas[$idOficina])) {
        $score += 20;
        $motivos[] = ['tipo' => 'oficina_favorita', 'texto' => 'Oficina nos seus favoritos'];
    }
    if (isset($oficinasUtilizadas[$idOficina])) {
        $score += 15;
        $motivos[] = ['tipo' => 'oficina_ja_utilizada', 'texto' => 'Você já foi atendido nesta oficina'];
    }
    if (empty($motivos)) {
        continue; // nada que personalize a oferta para este usuário
    }
    if ($p['verificada']) {
        $score += 5;
    }

    $ofertas[] = [
        'id_promocao'        => (int) $p['id_promocao'],
        'titulo'             => $p['titulo'],
        'descricao'          => $p['descricao'],
        'data_fim'           => $p['data_fim'],
        'id_oficina'         => $idOficina,
        'nome_oficina'       => $p['nome_oficina'],
        'oficina_verificada' => (bool) $p['verificada'],
        'id_servico'         => $idServico,
        'nome_servico'       => $p['nome_servico'],
        'placas'             => $placas,
        'motivos'            => $motivos,
        '_score'             => $score,
    ];
}

usort($ofertas, function ($a, $b) {
    if ($a['_score'] !== $b['_score']) {
        return $b['_score'] <=> $a['_score'];
    }
    return strcmp($a['data_fim'], $b['data_fim']);
});
$ofertas = array_slice($ofertas, 0, 10);
foreach ($ofertas as &$o) {
    unset($o['_score']);
}
unset($o);

echo json_encode(['sucesso' => true, 'ofertas' => $ofertas]);
