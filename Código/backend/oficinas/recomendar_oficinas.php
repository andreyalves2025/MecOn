<?php
/**
 * MecOn - GET /backend/oficinas/recomendar_oficinas.php
 *   ?servico=ID  &lat=..&lng=..  &raio_km=15  &limite=5
 *
 * "Melhor opção para o seu perfil": ranqueia oficinas por um score
 * ponderado (SQL + PHP, sem IA) em vez de só nota ou distância. Funciona
 * sem login (ranking geral); com login de cliente, personaliza.
 *
 * Fatores (cada um de 0 a 1) e pesos:
 *   avaliacao   30  média bayesiana: puxa para a média geral quando há
 *                   poucas avaliações (uma única nota 5 não vence 40
 *                   avaliações com média 4,8)
 *   preco       20  relativo às demais candidatas: menor preço = 1. Com
 *                   "servico", usa o preço daquele serviço; sem, a média
 *                   dos serviços da oficina. Sem preço = neutro (0,5)
 *   distancia   20  só com lat/lng: 1 - distância/raio. Com localização,
 *                   oficinas sem coordenadas ou fora do raio ficam de fora
 *   confianca   15  50% selo de verificada + 50% serviços concluídos pela
 *                   plataforma (satura em 10)
 *   afinidade   15  só com login de cliente: oficina favorita (+0,5) e/ou
 *                   onde já foi atendido (+0,5)
 * Fatores que não se aplicam (sem localização, sem login) saem da conta e
 * os pesos restantes são reescalados — o score final é sempre de 0 a 100.
 *
 * Com login, oficinas que o próprio usuário avaliou com nota 1 ou 2 não
 * são recomendadas (ele já disse que não gostou).
 *
 * Cada resultado traz "motivos" legíveis. Máximo de 10 resultados.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}

const REC_PESOS = ['avaliacao' => 30, 'preco' => 20, 'distancia' => 20, 'confianca' => 15, 'afinidade' => 15];
const REC_PRIOR_AVALIACOES = 3; // "peso" da média geral na média bayesiana

$idServico = $_GET['servico'] ?? null;
if ($idServico !== null && $idServico !== '' && !is_numeric($idServico)) {
    responderErro(400, 'servico deve ser numérico.');
}
$temServico = $idServico !== null && $idServico !== '';
$lat = $_GET['lat'] ?? null;
$lng = $_GET['lng'] ?? null;
$temLocal = is_numeric($lat) && is_numeric($lng)
    && abs((float) $lat) <= 90 && abs((float) $lng) <= 180;
$raioKm = isset($_GET['raio_km']) && is_numeric($_GET['raio_km']) && (float) $_GET['raio_km'] > 0
    ? min((float) $_GET['raio_km'], 200) : 15.0;
$limite = isset($_GET['limite']) && is_numeric($_GET['limite'])
    ? max(1, min((int) $_GET['limite'], 10)) : 5;

$usuario = usuarioAtual();
$personalizar = $usuario !== null && $usuario['tipo'] === 'cliente';
$idUsuario = $personalizar ? (int) $usuario['id_usuario'] : null;

$pdo = getConnection();

// ---- Candidatas ----
$colunasPreco = $temServico
    ? 'os.preco AS preco_ref'
    : '(SELECT AVG(os2.preco) FROM oficina_servico os2 WHERE os2.id_oficina = o.id_oficina) AS preco_ref';
$joinServico = $temServico
    ? 'JOIN oficina_servico os ON os.id_oficina = o.id_oficina AND os.id_servico = :id_servico'
    : '';
$condicoes = ['o.ativa = 1'];
$parametros = [];
if ($temServico) {
    $parametros['id_servico'] = (int) $idServico;
}
if ($temLocal) {
    // pré-filtro por "caixa" em volta do ponto (barato, usa as colunas direto);
    // a distância exata é calculada depois, em PHP
    $dLat = $raioKm / 111.0;
    $dLng = $raioKm / (111.0 * max(cos(deg2rad((float) $lat)), 0.01));
    $condicoes[] = 'o.latitude BETWEEN :lat_min AND :lat_max AND o.longitude BETWEEN :lng_min AND :lng_max';
    $parametros['lat_min'] = (float) $lat - $dLat;
    $parametros['lat_max'] = (float) $lat + $dLat;
    $parametros['lng_min'] = (float) $lng - $dLng;
    $parametros['lng_max'] = (float) $lng + $dLng;
}
$sql = "
    SELECT o.id_oficina, o.nome, o.cidade, o.bairro, o.verificada, o.latitude, o.longitude,
           $colunasPreco,
           (SELECT AVG(a.nota) FROM avaliacoes a WHERE a.id_oficina = o.id_oficina) AS media,
           (SELECT COUNT(*) FROM avaliacoes a WHERE a.id_oficina = o.id_oficina) AS total_avaliacoes,
           (SELECT COUNT(*) FROM agendamentos ag WHERE ag.id_oficina = o.id_oficina AND ag.status = 'concluido') AS concluidos
    FROM oficinas o
    $joinServico
    WHERE " . implode(' AND ', $condicoes) . "
    LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$candidatas = $stmt->fetchAll();

// ---- Contexto pessoal ----
$favoritas = $atendidas = $rejeitadas = [];
if ($personalizar) {
    $stmt = $pdo->prepare('SELECT id_oficina FROM favoritos WHERE id_usuario = :id');
    $stmt->execute(['id' => $idUsuario]);
    foreach ($stmt->fetchAll() as $l) { $favoritas[(int) $l['id_oficina']] = true; }

    $stmt = $pdo->prepare("
        SELECT id_oficina FROM agendamentos WHERE id_usuario = :id1 AND status = 'concluido'
        UNION
        SELECT h.id_oficina FROM historico_manutencoes h
        JOIN veiculos v ON v.id_veiculo = h.id_veiculo WHERE v.id_usuario = :id2
    ");
    $stmt->execute(['id1' => $idUsuario, 'id2' => $idUsuario]);
    foreach ($stmt->fetchAll() as $l) { $atendidas[(int) $l['id_oficina']] = true; }

    $stmt = $pdo->prepare('SELECT id_oficina FROM avaliacoes WHERE id_usuario = :id AND nota <= 2');
    $stmt->execute(['id' => $idUsuario]);
    foreach ($stmt->fetchAll() as $l) { $rejeitadas[(int) $l['id_oficina']] = true; }
}

// ---- Normalizações globais ----
$mediaGeral = (float) ($pdo->query('SELECT AVG(nota) AS m FROM avaliacoes')->fetch()['m'] ?? 0);
if ($mediaGeral <= 0) {
    $mediaGeral = 3.5; // sem nenhuma avaliação na plataforma: referência neutra
}

function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$itens = [];
foreach ($candidatas as $c) {
    $id = (int) $c['id_oficina'];
    if (isset($rejeitadas[$id])) {
        continue;
    }
    $distancia = null;
    if ($temLocal) {
        if ($c['latitude'] === null || $c['longitude'] === null) {
            continue;
        }
        $distancia = distanciaKm((float) $lat, (float) $lng, (float) $c['latitude'], (float) $c['longitude']);
        if ($distancia > $raioKm) {
            continue;
        }
    }
    $c['_id'] = $id;
    $c['_distancia'] = $distancia;
    $itens[] = $c;
}

$precos = [];
foreach ($itens as $c) {
    if ($c['preco_ref'] !== null) {
        $precos[] = (float) $c['preco_ref'];
    }
}
$precoMin = !empty($precos) ? min($precos) : null;
$precoMax = !empty($precos) ? max($precos) : null;

$resultado = [];
foreach ($itens as $c) {
    $id = $c['_id'];
    $n = (int) $c['total_avaliacoes'];
    $soma = $c['media'] !== null ? (float) $c['media'] * $n : 0.0;
    $bayes = ($mediaGeral * REC_PRIOR_AVALIACOES + $soma) / (REC_PRIOR_AVALIACOES + $n);
    $fatores = ['avaliacao' => max(0.0, min(1.0, ($bayes - 1) / 4))];
    $motivos = [];
    if ($n > 0) {
        $motivos[] = 'Nota ' . number_format((float) $c['media'], 1, ',', '') . " ($n avaliação" . ($n > 1 ? 'ões' : '') . ')';
    }

    // preço
    if ($c['preco_ref'] !== null && $precoMin !== null) {
        $p = (float) $c['preco_ref'];
        $fatores['preco'] = $precoMax > $precoMin ? 1 - ($p - $precoMin) / ($precoMax - $precoMin) : 1.0;
        if ($precoMax > $precoMin && $p == $precoMin) {
            $motivos[] = 'Menor preço entre as opções (R$ ' . number_format($p, 2, ',', '.') . ')';
        }
    } else {
        $fatores['preco'] = 0.5;
    }

    // distância
    if ($temLocal) {
        $fatores['distancia'] = max(0.0, 1 - $c['_distancia'] / $raioKm);
        $motivos[] = 'A ' . number_format($c['_distancia'], 1, ',', '') . ' km de você';
    }

    // confiança
    $verificada = (bool) $c['verificada'];
    $concluidos = (int) $c['concluidos'];
    $fatores['confianca'] = 0.5 * ($verificada ? 1 : 0) + 0.5 * min($concluidos / 10, 1);
    if ($verificada) {
        $motivos[] = 'Oficina verificada';
    }
    if ($concluidos > 0) {
        $motivos[] = "$concluidos serviço" . ($concluidos > 1 ? 's' : '') . ' concluído' . ($concluidos > 1 ? 's' : '') . ' pelo MecOn';
    }

    // afinidade
    if ($personalizar) {
        $af = 0.0;
        if (isset($favoritas[$id])) { $af += 0.5; $motivos[] = 'Nos seus favoritos'; }
        if (isset($atendidas[$id])) { $af += 0.5; $motivos[] = 'Você já foi atendido aqui'; }
        $fatores['afinidade'] = $af;
    }

    $somaPesos = 0;
    $somaPonderada = 0.0;
    foreach ($fatores as $nome => $valor) {
        $somaPesos += REC_PESOS[$nome];
        $somaPonderada += REC_PESOS[$nome] * $valor;
    }
    $score = $somaPesos > 0 ? round($somaPonderada / $somaPesos * 100) : 0;

    $resultado[] = [
        'id_oficina'   => $id,
        'nome'         => $c['nome'],
        'cidade'       => $c['cidade'],
        'bairro'       => $c['bairro'],
        'verificada'   => $verificada,
        'media_avaliacao'  => $n > 0 ? round((float) $c['media'], 1) : null,
        'total_avaliacoes' => $n,
        'preco_referencia' => $c['preco_ref'] !== null ? round((float) $c['preco_ref'], 2) : null,
        'distancia_km' => $c['_distancia'] !== null ? round($c['_distancia'], 1) : null,
        'score'        => (int) $score,
        'motivos'      => $motivos,
    ];
}

usort($resultado, function ($a, $b) {
    if ($a['score'] !== $b['score']) {
        return $b['score'] <=> $a['score'];
    }
    return $b['total_avaliacoes'] <=> $a['total_avaliacoes'];
});
$resultado = array_slice($resultado, 0, $limite);

echo json_encode([
    'sucesso'        => true,
    'personalizada'  => $personalizar,
    'recomendacoes'  => $resultado,
]);
