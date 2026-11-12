<?php
/**
 * Diagnóstico gratuito (anônimo).
 *  POST {respostas: ["ok","no","ns",null,...]}  → registra e devolve o id
 *  POST {id, canal: "whatsapp"}                 → marca que o resultado foi enviado pelo WhatsApp
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$in = public_guard('diag', 30, 600);

if (isset($in['id'], $in['canal'])) {
    if ($in['canal'] === 'whatsapp') {
        db_q('UPDATE diagnosticos SET enviou_whatsapp = 1 WHERE id = ? AND criado_em > NOW() - INTERVAL 1 DAY', [(int) $in['id']]);
    }
    json_out(['ok' => true]);
}

$r = $in['respostas'] ?? null;
$n = count(JC_DIAG);
if (!is_array($r) || count($r) !== $n) {
    json_fail('Respostas inválidas.');
}
$norm = [];
$ok = $pend = 0;
foreach (array_values($r) as $v) {
    $v = one_of($v, ['ok', 'no', 'ns'], '-');
    $norm[] = $v;
    if ($v === 'ok') {
        $ok++;
    } elseif ($v === 'no' || $v === 'ns') {
        $pend++;
    }
}
db_q('INSERT INTO diagnosticos (respostas, em_dia, pendentes) VALUES (?, ?, ?)', [implode(',', $norm), $ok, $pend]);

json_out(['ok' => true, 'id' => (int) db()->lastInsertId()]);
