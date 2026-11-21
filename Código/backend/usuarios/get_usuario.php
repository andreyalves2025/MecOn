<?php
/**
 * MecOn - GET /backend/usuarios/get_usuario.php?id_usuario=1
 * Retorna detalhes de um usuário, incluindo suas avaliações (admin-usuarios.html).
 * Requer login como administrador.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
exigirAdmin();
$idUsuario = $_GET['id_usuario'] ?? null;
if (!is_numeric($idUsuario)) {
    responderErro(400, 'id_usuario é obrigatório e deve ser numérico.');
}
$pdo = getConnection();
$stmt = $pdo->prepare(
    'SELECT id_usuario, nome, email, tipo, data_cadastro FROM usuarios WHERE id_usuario = :id'
);
$stmt->execute(['id' => $idUsuario]);
$usuario = $stmt->fetch();
if (!$usuario) {
    responderErro(404, 'Usuário não encontrado.');
}
$stmt = $pdo->prepare("
    SELECT a.id_avaliacao, a.nota, a.comentario, a.data_avaliacao, o.nome AS nome_oficina
    FROM avaliacoes a
    JOIN oficinas o ON o.id_oficina = a.id_oficina
    WHERE a.id_usuario = :id
    ORDER BY a.data_avaliacao DESC
");
$stmt->execute(['id' => $idUsuario]);
$usuario['avaliacoes'] = $stmt->fetchAll();
echo json_encode(['sucesso' => true, 'usuario' => $usuario]);
