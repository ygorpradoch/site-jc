<?php
/** Pedido de proposta (formulário de contato). */
require dirname(__DIR__) . '/app/bootstrap.php';

$in = public_guard('lead', 8, 600);

$nome = clean_line($in['nome'] ?? '', 120);
$wa = clean_phone($in['whatsapp'] ?? '');
$ok = !empty($in['consentimento']);

if (mb_strlen($nome) < 2) {
    json_fail('Informe seu nome.', 422, ['campo' => 'nome']);
}
if ($wa === null) {
    json_fail('Informe o WhatsApp com DDD.', 422, ['campo' => 'whatsapp']);
}
if (!$ok) {
    json_fail('Para enviar, marque a concordância com o uso dos dados.', 422, ['campo' => 'consentimento']);
}

$papel = one_of($in['papel'] ?? '', ['sindico', 'conselho', 'morador', 'administradora'], null);
$unid = one_of($in['unidades'] ?? '', ['Até 30', '31 a 80', '81 a 150', 'Mais de 150'], null);
$cond = clean_line($in['condominio'] ?? '', 160);
$msg = clean_text($in['mensagem'] ?? '', 2000);
$diagId = (int) ($in['diagnostico_id'] ?? 0);

db_q(
    'INSERT INTO leads (origem, nome, whatsapp, papel, condominio, unidades, mensagem, consentimento, ip_hash)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
    [$diagId > 0 ? 'diagnostico' : 'proposta', $nome, $wa, $papel, $cond ?: null, $unid, $msg ?: null, ip_hash()]
);

if ($diagId > 0) {
    db_q('UPDATE diagnosticos SET enviou_formulario = 1 WHERE id = ? AND criado_em > NOW() - INTERVAL 1 DAY', [$diagId]);
}

notify('Novo pedido de proposta: ' . $nome, [
    'Nome' => $nome,
    'WhatsApp' => fmt_phone($wa),
    'Você é' => $papel ?? '-',
    'Condomínio' => $cond ?: '-',
    'Unidades' => $unid ?? '-',
    'Mensagem' => $msg ?: '-',
]);

json_out(['ok' => true]);
