<?php
/**
 * MecOn - POST /backend/oficinas/create_oficina.php
 * Body (JSON): {
 *   "nome": "...", "email": "...", "senha": "...",
 *   "cidade": "...", "bairro": "...", "endereco": "...",
 *   "telefone": "...", "descricao": "...",
 *   "latitude": -25.4284, "longitude": -49.2733,
 *   "servicos": [{ "id_servico": 1, "preco": 80.00 }, { "id_servico": 3, "preco": 150.00 }]
 * }
 * "latitude"/"longitude" são opcionais (sem elas, a oficina não aparece em
 * buscas por proximidade, só nas por texto/filtro — ver
 * migrations/011_geolocalizacao.sql).
 * Cada serviço oferecido tem seu próprio preço (não existe mais um único
 * "preço médio" por oficina — ver migrations/003_preco_por_servico.sql).
 * Uso exclusivo de administrador. A oficina criada recebe login próprio
 * (email/senha) para depois acessar e editar seus próprios dados via
 * /backend/oficina_auth/. O admin NÃO pode editar a oficina após a criação
 * (ver oficina_auth/update_perfil_oficina.php, de uso exclusivo da oficina).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$dados = json_decode(file_get_contents('php://input'), true);
$nome       = trim($dados['nome'] ?? '');
$email      = trim($dados['email'] ?? '');
$senha      = $dados['senha'] ?? '';
$cidade     = trim($dados['cidade'] ?? '');
$bairro     = trim($dados['bairro'] ?? '');
$endereco   = trim($dados['endereco'] ?? '');
$telefone   = trim($dados['telefone'] ?? '');
$descricao  = trim($dados['descricao'] ?? '');
$latitude   = $dados['latitude'] ?? null;
$longitude  = $dados['longitude'] ?? null;
$servicos   = $dados['servicos'] ?? [];
if ($nome === '' || $email === '' || $senha === '' || $cidade === '' || $bairro === '' || $endereco === '' || $telefone === '') {
    responderErro(400, 'nome, email, senha, cidade, bairro, endereco e telefone são obrigatórios.');
}
if ($latitude !== null && (!is_numeric($latitude) || $latitude < -90 || $latitude > 90)) {
    responderErro(400, 'latitude deve estar entre -90 e 90.');
}
if ($longitude !== null && (!is_numeric($longitude) || $longitude < -180 || $longitude > 180)) {
    responderErro(400, 'longitude deve estar entre -180 e 180.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderErro(400, 'Email inválido.');
}
if (strlen($senha) < 6) {
    responderErro(400, 'A senha deve ter pelo menos 6 caracteres.');
}
if (!is_array($servicos) || empty($servicos)) {
    responderErro(400, 'Informe ao menos um serviço com seu respectivo preço.');
}
foreach ($servicos as $servico) {
    if (!is_array($servico) || !isset($servico['id_servico'], $servico['preco'])
        || !is_numeric($servico['id_servico']) || !is_numeric($servico['preco']) || (float) $servico['preco'] < 0) {
        responderErro(400, 'Cada serviço deve ter id_servico e preco (numérico, maior ou igual a zero).');
    }
}
$pdo = getConnection();
$stmt = $pdo->prepare('SELECT id_oficina FROM oficinas WHERE email = :email');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    responderErro(409, 'Este email já está cadastrado para outra oficina.');
}
$senhaHash = password_hash($senha, PASSWORD_BCRYPT);
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO oficinas (nome, email, senha_hash, cidade, bairro, endereco, latitude, longitude, telefone, descricao)
        VALUES (:nome, :email, :senha_hash, :cidade, :bairro, :endereco, :latitude, :longitude, :telefone, :descricao)
    ");
    $stmt->execute([
        'nome'        => $nome,
        'email'       => $email,
        'senha_hash'  => $senhaHash,
        'cidade'      => $cidade,
        'bairro'      => $bairro,
        'endereco'    => $endereco,
        'latitude'    => $latitude !== null ? (float) $latitude : null,
        'longitude'   => $longitude !== null ? (float) $longitude : null,
        'telefone'    => $telefone,
        'descricao'   => $descricao !== '' ? $descricao : null,
    ]);
    $idOficina = (int) $pdo->lastInsertId();
    $stmtServico = $pdo->prepare(
        'INSERT INTO oficina_servico (id_oficina, id_servico, preco) VALUES (:id_oficina, :id_servico, :preco)'
    );
    foreach ($servicos as $servico) {
        $stmtServico->execute([
            'id_oficina' => $idOficina,
            'id_servico' => (int) $servico['id_servico'],
            'preco'      => (float) $servico['preco'],
        ]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    // SQLSTATE 23000 = violação de constraint (aqui, a UNIQUE de oficinas.email).
    // Cobre a condição de corrida: duas requisições passam na checagem manual
    // acima antes de qualquer uma commitar, e só o banco pega a duplicidade.
    if ($e->getCode() === '23000') {
        responderErro(409, 'Este email já está cadastrado para outra oficina.');
    }
    responderErro(500, 'Erro ao cadastrar oficina.');
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao cadastrar oficina.');
}
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_oficina' => $idOficina]);
