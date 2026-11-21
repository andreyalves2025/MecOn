<?php
/**
 * MecOn - POST /backend/avaliacoes/create_review.php
 * Body (JSON): { "id_oficina": 1, "nota": 5, "comentario": "Excelente serviço", "id_agendamento": 7 }
 * "id_agendamento" é opcional. Quando informado, a avaliação recebe o selo
 * de "serviço confirmado pelo MecOn" — mas só se esse agendamento for do
 * próprio usuário, dessa mesma oficina, estiver concluído, e ainda não
 * tiver sido usado em outra avaliação (ver migrations/014_avaliacao_verificada.sql).
 * Requer login. Avaliação é imutável pelo usuário após criada
 * (apenas administradores podem editar/excluir — ver update_review.php / delete_review.php).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErro(405, 'Método não permitido.');
}
$pdo = getConnection();
$usuario = exigirEmailVerificado($pdo);
$dados         = json_decode(file_get_contents('php://input'), true);
$idOficina     = $dados['id_oficina'] ?? null;
$nota          = $dados['nota'] ?? null;
$comentario    = trim($dados['comentario'] ?? '');
$idAgendamento = $dados['id_agendamento'] ?? null;
if (!is_numeric($idOficina) || !is_numeric($nota)) {
    responderErro(400, 'id_oficina e nota são obrigatórios.');
}
$nota = (int) $nota;
if ($nota < 1 || $nota > 5) {
    responderErro(400, 'nota deve estar entre 1 e 5.');
}
$stmt = $pdo->prepare('SELECT id_oficina FROM oficinas WHERE id_oficina = :id');
$stmt->execute(['id' => $idOficina]);
if (!$stmt->fetch()) {
    responderErro(404, 'Oficina não encontrada.');
}
if ($idAgendamento !== null) {
    if (!is_numeric($idAgendamento)) {
        responderErro(400, 'id_agendamento deve ser numérico.');
    }
    $stmt = $pdo->prepare('
        SELECT ag.id_agendamento, ag.status,
               (SELECT 1 FROM avaliacoes a WHERE a.id_agendamento = ag.id_agendamento) AS ja_avaliado
        FROM agendamentos ag
        WHERE ag.id_agendamento = :id AND ag.id_usuario = :id_usuario AND ag.id_oficina = :id_oficina
    ');
    $stmt->execute(['id' => $idAgendamento, 'id_usuario' => $usuario['id_usuario'], 'id_oficina' => $idOficina]);
    $agendamento = $stmt->fetch();
    if (!$agendamento) {
        responderErro(404, 'Agendamento não encontrado para este usuário e oficina.');
    }
    if ($agendamento['status'] !== 'concluido') {
        responderErro(400, 'Só é possível vincular a avaliação a um agendamento já concluído.');
    }
    if ($agendamento['ja_avaliado']) {
        responderErro(409, 'Este agendamento já foi avaliado.');
    }
}
$stmt = $pdo->prepare("
    INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario)
    VALUES (:id_usuario, :id_oficina, :id_agendamento, :nota, :comentario)
");
$stmt->execute([
    'id_usuario'     => $usuario['id_usuario'],
    'id_oficina'     => $idOficina,
    'id_agendamento' => $idAgendamento !== null ? (int) $idAgendamento : null,
    'nota'           => $nota,
    'comentario'     => $comentario !== '' ? $comentario : null,
]);
http_response_code(201);
echo json_encode(['sucesso' => true, 'id_avaliacao' => (int) $pdo->lastInsertId()]);
