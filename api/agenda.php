<?php
/** Agenda pública: GET lista os horários; POST pede um horário. */
require dirname(__DIR__) . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    json_out(['ok' => true, 'dias' => agenda_dias(5)]);
}

$in = public_guard('agenda', 8, 600);

$nome = clean_line($in['nome'] ?? '', 120);
$wa = clean_phone($in['whatsapp'] ?? '');
$modo = one_of($in['modo'] ?? '', ['video', 'presencial', 'escritorio'], 'video');
$data = (string) ($in['data'] ?? '');
$hora = (string) ($in['hora'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
    json_fail('Escolha um dia e um horário.', 422, ['campo' => 'horario']);
}
if (mb_strlen($nome) < 2) {
    json_fail('Informe seu nome.', 422, ['campo' => 'nome']);
}
if ($wa === null) {
    json_fail('Informe o WhatsApp com DDD.', 422, ['campo' => 'whatsapp']);
}
if (empty($in['consentimento'])) {
    json_fail('Para enviar, marque a concordância com o uso dos dados.', 422, ['campo' => 'consentimento']);
}
if (!agenda_horario_valido($data, $hora)) {
    json_fail('Esse horário não está mais disponível. Escolha outro.', 409, ['campo' => 'horario', 'dias' => agenda_dias(5)]);
}

$modos = ['video' => 'Videochamada', 'presencial' => 'No condomínio', 'escritorio' => 'No escritório'];
$quando = JC_DIAS[(int) date('w', strtotime($data))] . ' ' . date('d/m', strtotime($data)) . ' às ' . $hora;

$pdo = db();
try {
    $pdo->beginTransaction();
    db_q(
        "INSERT INTO leads (origem, nome, whatsapp, mensagem, consentimento, ip_hash) VALUES ('agenda', ?, ?, ?, 1, ?)",
        [$nome, $wa, 'Conversa pedida para ' . $quando . ' · ' . $modos[$modo], ip_hash()]
    );
    $leadId = (int) $pdo->lastInsertId();
    db_q('INSERT INTO agendamentos (lead_id, data, hora, modo) VALUES (?, ?, ?, ?)', [$leadId, $data, $hora . ':00', $modo]);
    $pdo->commit();
    log_evento('agenda_pedida', 'Contato #' . $leadId . ' · ' . $quando);
} catch (PDOException $e) {
    $pdo->rollBack();
    if ((string) $e->getCode() === '23000') { // outra pessoa pegou o horário no mesmo instante
        json_fail('Esse horário acabou de ser reservado. Escolha outro.', 409, ['campo' => 'horario', 'dias' => agenda_dias(5)]);
    }
    throw $e;
}

notify('Conversa agendada pelo site: ' . $quando, [
    'Nome' => $nome,
    'WhatsApp' => fmt_phone($wa),
    'Quando' => $quando,
    'Formato' => $modos[$modo],
]);

json_out(['ok' => true, 'quando' => $quando, 'dias' => agenda_dias(5)]);
