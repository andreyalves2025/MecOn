<?php
/**
 * MecOn - POST /backend/oficina_auth/update_perfil_oficina.php
 * Body (JSON): campos opcionais entre nome, email, senha, cidade, bairro,
 * endereco, telefone, descricao, servicos (lista de { id_servico, preco },
 * substitui o vínculo completo, com preços, se enviada).
 * Requer login de oficina. Edita apenas a própria oficina (id vem da sessão).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$idOficina = $oficinaSessao['id_oficina'];
$dados = json_decode(file_get_contents('php://input'), true);
$pdo = getConnection();
$camposTexto = ['nome', 'cidade', 'bairro', 'endereco', 'telefone', 'descricao'];
$campos      = [];
$parametros  = ['id' => $idOficina];
foreach ($camposTexto as $campo) {
    if (isset($dados[$campo])) {
        $campos[] = "$campo = :$campo";
        $parametros[$campo] = trim($dados[$campo]);
    }
}
if (array_key_exists('latitude', $dados)) {
    if ($dados['latitude'] !== null && (!is_numeric($dados['latitude']) || $dados['latitude'] < -90 || $dados['latitude'] > 90)) {
        responderErro(400, 'latitude deve estar entre -90 e 90.');
    }
    $campos[] = 'latitude = :latitude';
    $parametros['latitude'] = $dados['latitude'] !== null ? (float) $dados['latitude'] : null;
}
if (array_key_exists('longitude', $dados)) {
    if ($dados['longitude'] !== null && (!is_numeric($dados['longitude']) || $dados['longitude'] < -180 || $dados['longitude'] > 180)) {
        responderErro(400, 'longitude deve estar entre -180 e 180.');
    }
    $campos[] = 'longitude = :longitude';
    $parametros['longitude'] = $dados['longitude'] !== null ? (float) $dados['longitude'] : null;
}
if (isset($dados['email'])) {
    $email = trim($dados['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderErro(400, 'Email inválido.');
    }
    $stmt = $pdo->prepare('SELECT id_oficina FROM oficinas WHERE email = :email AND id_oficina != :id');
    $stmt->execute(['email' => $email, 'id' => $idOficina]);
    if ($stmt->fetch()) {
        responderErro(409, 'Este email já está em uso por outra oficina.');
    }
    $campos[] = 'email = :email';
    $parametros['email'] = $email;
}
if (isset($dados['senha']) && $dados['senha'] !== '') {
    if (strlen($dados['senha']) < 6) {
        responderErro(400, 'A senha deve ter pelo menos 6 caracteres.');
    }
    $campos[] = 'senha_hash = :senha_hash';
    $parametros['senha_hash'] = password_hash($dados['senha'], PASSWORD_BCRYPT);
}
if (empty($campos) && !isset($dados['servicos'])) {
    responderErro(400, 'Nenhum campo válido informado para atualização.');
}
if (isset($dados['servicos']) && is_array($dados['servicos'])) {
    foreach ($dados['servicos'] as $servico) {
        if (!is_array($servico) || !isset($servico['id_servico'], $servico['preco'])
            || !is_numeric($servico['id_servico']) || !is_numeric($servico['preco']) || (float) $servico['preco'] < 0) {
            responderErro(400, 'Cada serviço deve ter id_servico e preco (numérico, maior ou igual a zero).');
        }
    }
}
$pdo->beginTransaction();
try {
    if (!empty($campos)) {
        $sql = 'UPDATE oficinas SET ' . implode(', ', $campos) . ' WHERE id_oficina = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);
    }
    if (isset($dados['servicos']) && is_array($dados['servicos'])) {
        $stmt = $pdo->prepare('DELETE FROM oficina_servico WHERE id_oficina = :id');
        $stmt->execute(['id' => $idOficina]);
        $stmtServico = $pdo->prepare(
            'INSERT INTO oficina_servico (id_oficina, id_servico, preco) VALUES (:id_oficina, :id_servico, :preco)'
        );
        foreach ($dados['servicos'] as $servico) {
            $stmtServico->execute([
                'id_oficina' => $idOficina,
                'id_servico' => (int) $servico['id_servico'],
                'preco'      => (float) $servico['preco'],
            ]);
        }
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        responderErro(409, 'Este email já está em uso por outra oficina.');
    }
    responderErro(500, 'Erro ao atualizar dados da oficina.');
} catch (Exception $e) {
    $pdo->rollBack();
    responderErro(500, 'Erro ao atualizar dados da oficina.');
}
// Mantém a sessão sincronizada se nome/email foram alterados
if (isset($parametros['nome'])) {
    $_SESSION['oficina_nome'] = $parametros['nome'];
}
if (isset($parametros['email'])) {
    $_SESSION['oficina_email'] = $parametros['email'];
}
echo json_encode(['sucesso' => true]);
