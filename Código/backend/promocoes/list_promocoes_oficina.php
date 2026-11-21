<?php
/**
 * MecOn - GET /backend/promocoes/list_promocoes_oficina.php
 * Lista TODAS as promoções da própria oficina (ativas, agendadas e
 * expiradas), para a aba "Promoções" do perfil — a oficina precisa ver e
 * editar até as que já passaram ou ainda não começaram.
 * Requer login de oficina.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$oficinaSessao = exigirOficina();
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT
        p.id_promocao, p.titulo, p.descricao, p.data_inicio, p.data_fim,
        p.id_servico, s.nome AS nome_servico,
        CASE
            WHEN CURDATE() < p.data_inicio THEN 'agendada'
            WHEN CURDATE() > p.data_fim THEN 'expirada'
            ELSE 'ativa'
        END AS status
    FROM promocoes p
    LEFT JOIN servicos s ON s.id_servico = p.id_servico
    WHERE p.id_oficina = :id_oficina
    ORDER BY p.data_fim DESC
");
$stmt->execute(['id_oficina' => $oficinaSessao['id_oficina']]);
echo json_encode(['sucesso' => true, 'promocoes' => $stmt->fetchAll()]);
