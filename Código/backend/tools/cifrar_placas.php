<?php
/**
 * MecOn - cifra as placas já gravadas em texto puro (rodar UMA vez após a migração 027).
 * Uso: php backend/tools/cifrar_placas.php
 * Idempotente: valores já cifrados são ignorados.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/placa.php';
$pdo = getConnection();
$n = 0;
foreach ([['veiculos', 'id_veiculo', 'placa'], ['agendamentos', 'id_agendamento', 'placa_snapshot']] as [$t, $pk, $c]) {
    $rows = $pdo->query("SELECT $pk AS id, $c AS valor FROM $t WHERE $c IS NOT NULL")->fetchAll();
    $up = $pdo->prepare("UPDATE $t SET $c = :v WHERE $pk = :id");
    foreach ($rows as $r) {
        if (placaEstaCifrada($r['valor'])) continue;
        $up->execute(['v' => placaCifrar($r['valor']), 'id' => $r['id']]);
        $n++;
    }
}
echo "Placas cifradas: $n\n";
