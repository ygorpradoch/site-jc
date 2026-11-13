<?php
/** Depoimento enviado por cliente. Só vai ao site depois de aprovado no painel. */
require dirname(__DIR__) . '/app/bootstrap.php';

$in = public_guard('depo', 3, 3600);

$texto = clean_text($in['texto'] ?? '', 600);
$nome = clean_line($in['nome'] ?? '', 100);
$papel = clean_line($in['papel'] ?? '', 80);
$cond = clean_line($in['condominio'] ?? '', 120);
$aut = !empty($in['autorizado']);

if (mb_strlen($texto) < 20) {
    json_fail('Escreva um pouco mais sobre a sua experiência (mínimo de 20 caracteres).', 422, ['campo' => 'texto']);
}
if (mb_strlen($nome) < 2) {
    json_fail('Informe seu nome.', 422, ['campo' => 'nome']);
}
if (!$aut) {
    json_fail('Para publicar, precisamos da sua autorização.', 422, ['campo' => 'autorizado']);
}

db_q(
    "INSERT INTO depoimentos (texto, nome, papel, condominio, autorizado, origem) VALUES (?, ?, ?, ?, 1, 'site')",
    [$texto, $nome, $papel ?: null, $cond ?: null]
);

log_evento('depoimento_enviado', 'Aguardando aprovação');
notify('Novo depoimento para aprovar: ' . $nome, ['Nome' => $nome, 'Condomínio' => $cond ?: '-', 'Texto' => $texto]);

json_out(['ok' => true]);
