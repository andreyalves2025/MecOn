<?php
/**
 * MecOn - GET /backend/historico/lembretes_manutencao.php
 *
 * Lembretes de manutenção preventiva a partir do histórico do próprio
 * usuário, combinando TEMPO e QUILOMETRAGEM. Para cada combinação
 * veículo + serviço, usa o registro mais recente do histórico e compara
 * com os intervalos recomendados do catálogo (ver migrações 010 e 021):
 *
 *   progresso_tempo = meses desde o último serviço / intervalo_recomendado_meses
 *   progresso_km    = (km_atual do veículo - km no último serviço) / intervalo_recomendado_km
 *
 * O lembrete segue o critério que estiver MAIS adiantado ("o que vier
 * primeiro", como em "troca de óleo a cada 10.000 km ou 6 meses"):
 *   "atrasado" -> algum progresso >= 100%
 *   "proximo"  -> algum progresso >= 80%
 * Dentro do prazo não entra na resposta — só o que precisa de atenção.
 *
 * Cada critério só é avaliado se houver dados para ele: o de tempo exige
 * intervalo em meses no serviço; o de km exige intervalo em km, km_atual
 * no veículo E km registrada no último serviço. Sem dados de km o
 * lembrete continua funcionando só por tempo. Limitação: a km_atual é
 * informada pelo usuário e pode estar desatualizada, o que faz o critério
 * de km subestimar o desgaste real.
 *
 * Requer login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session_check.php';
require_once __DIR__ . '/../config/lembretes.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderErro(405, 'Método não permitido.');
}
$usuario = exigirLogin();
$pdo = getConnection();

echo json_encode([
    'sucesso'   => true,
    'lembretes' => calcularLembretesUsuario($pdo, (int) $usuario['id_usuario']),
]);
