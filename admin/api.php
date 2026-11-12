<?php
/**
 * API do painel. Exige sessão de administrador.
 * Leituras: GET ?acao=...   Gravações: POST ?acao=... com cabeçalho X-CSRF.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require JC_APP . '/auth.php';
require JC_APP . '/imagem.php';

header('X-Robots-Tag: noindex, nofollow');

$user = admin_user();
if (!$user) {
    json_fail('Sessão expirada. Entre novamente.', 401);
}
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET' && $metodo !== 'POST') {
    json_fail('Método não permitido.', 405);
}
$acao = (string) ($_GET['acao'] ?? '');
if ($metodo === 'POST' && !csrf_ok($_SERVER['HTTP_X_CSRF'] ?? null)) {
    json_fail('A página expirou. Recarregue o painel.', 403);
}
$in = $metodo === 'POST' ? input() : $_GET;
$id = (int) ($in['id'] ?? 0);

const LEAD_STATUS = ['novo', 'conversa', 'proposta', 'fechado', 'perdido'];

function lead_publico(array $l): array
{
    $l['whatsapp_fmt'] = fmt_phone($l['whatsapp']);
    $l['criado_fmt'] = fmt_data($l['criado_em'], true);
    unset($l['ip_hash']);
    return $l;
}

$GET = $metodo === 'GET';

switch ($acao) {

/* ================= visão geral ================= */
case 'resumo':
    $p30 = (int) db_val("SELECT COUNT(*) FROM leads WHERE origem IN ('proposta','diagnostico') AND criado_em >= NOW() - INTERVAL 30 DAY");
    $p60 = (int) db_val("SELECT COUNT(*) FROM leads WHERE origem IN ('proposta','diagnostico') AND criado_em >= NOW() - INTERVAL 60 DAY AND criado_em < NOW() - INTERVAL 30 DAY");
    $d = db_one('SELECT COUNT(*) n, COALESCE(SUM(enviou_whatsapp OR enviou_formulario),0) env FROM diagnosticos WHERE criado_em >= NOW() - INTERVAL 30 DAY');
    $prox = db_one("SELECT a.data, DATE_FORMAT(a.hora,'%H:%i') hora, l.nome FROM agendamentos a LEFT JOIN leads l ON l.id = a.lead_id WHERE a.ativo = 1 AND a.data >= CURDATE() ORDER BY a.data, a.hora LIMIT 1");
    $nAg = (int) db_val('SELECT COUNT(*) FROM agendamentos WHERE ativo = 1 AND data >= CURDATE()');
    $tot30 = (int) db_val('SELECT COUNT(*) FROM leads WHERE criado_em >= NOW() - INTERVAL 30 DAY');
    $resp30 = (int) db_val("SELECT COUNT(*) FROM leads WHERE criado_em >= NOW() - INTERVAL 30 DAY AND status <> 'novo'");
    // contatos por semana nas últimas 8 semanas (para o minigráfico)
    $sem = array_fill(0, 8, 0);
    foreach (db_all('SELECT FLOOR(DATEDIFF(CURDATE(), DATE(criado_em)) / 7) w, COUNT(*) n FROM leads WHERE criado_em >= CURDATE() - INTERVAL 55 DAY GROUP BY w') as $r) {
        $w = (int) $r['w'];
        if ($w >= 0 && $w < 8) {
            $sem[7 - $w] = (int) $r['n'];
        }
    }
    json_out(['ok' => true,
        'nome' => $user['nome'],
        'propostas' => $p30, 'propostas_antes' => $p60,
        'diagnosticos' => (int) $d['n'], 'diag_enviados' => (int) $d['env'],
        'agendados' => $nAg,
        'proximo' => $prox ? JC_DIAS[(int) date('w', strtotime($prox['data']))] . ' ' . date('d/m', strtotime($prox['data'])) . ', ' . $prox['hora'] . ($prox['nome'] ? ' · ' . $prox['nome'] : '') : null,
        'taxa_resposta' => $tot30 ? (int) round($resp30 * 100 / $tot30) : null,
        'semanas' => $sem,
        'recentes' => array_map('lead_publico', db_all('SELECT * FROM leads ORDER BY id DESC LIMIT 5')),
        'pend' => [
            'novos' => (int) db_val("SELECT COUNT(*) FROM leads WHERE status = 'novo'"),
            'agenda' => (int) db_val("SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente' AND ativo = 1 AND data >= CURDATE()"),
            'depo' => (int) db_val("SELECT COUNT(*) FROM depoimentos WHERE status = 'pendente'"),
            'rascunhos' => (int) db_val("SELECT COUNT(*) FROM artigos WHERE status = 'rascunho'"),
            'sem_foto' => db_all("SELECT id, nome FROM condominios WHERE exibir = 1 AND (foto IS NULL OR foto = '')"),
        ],
    ]);

/* ================= contatos ================= */
case 'leads':
    $st = one_of($in['status'] ?? '', LEAD_STATUS, null);
    $rows = $st
        ? db_all('SELECT * FROM leads WHERE status = ? ORDER BY id DESC LIMIT 500', [$st])
        : db_all('SELECT * FROM leads ORDER BY id DESC LIMIT 500');
    json_out(['ok' => true, 'leads' => array_map('lead_publico', $rows)]);

case 'leads_csv':
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contatos-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // Excel reconhece acentos
    fputcsv($out, ['Data', 'Origem', 'Nome', 'WhatsApp', 'Papel', 'Condomínio', 'Unidades', 'Status', 'Mensagem', 'Notas'], ';');
    foreach (db_all('SELECT * FROM leads ORDER BY id DESC') as $l) {
        $linha = [fmt_data($l['criado_em'], true), $l['origem'], $l['nome'], fmt_phone($l['whatsapp']), $l['papel'], $l['condominio'], $l['unidades'], $l['status'], $l['mensagem'], $l['notas']];
        // evita que o Excel interprete conteúdo como fórmula
        $linha = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $linha);
        fputcsv($out, $linha, ';');
    }
    fclose($out);
    exit;

case 'lead_salvar':
    if ($GET) break;
    $st = one_of($in['status'] ?? '', LEAD_STATUS, null);
    if ($st) {
        db_q('UPDATE leads SET status = ? WHERE id = ?', [$st, $id]);
    }
    if (array_key_exists('notas', $in)) {
        db_q('UPDATE leads SET notas = ? WHERE id = ?', [clean_text($in['notas'], 4000) ?: null, $id]);
    }
    json_out(['ok' => true]);

case 'lead_excluir':
    if ($GET) break;
    db_q('DELETE FROM leads WHERE id = ?', [$id]);
    json_out(['ok' => true]);

/* ================= agenda ================= */
case 'agenda':
    $ags = db_all("SELECT a.id, a.data, DATE_FORMAT(a.hora,'%H:%i') hora, a.modo, a.status, l.nome, l.whatsapp
                   FROM agendamentos a LEFT JOIN leads l ON l.id = a.lead_id
                   WHERE a.data >= CURDATE() - INTERVAL 7 DAY AND a.status <> 'cancelado'
                   ORDER BY a.data, a.hora LIMIT 200");
    foreach ($ags as &$a) {
        $a['dia'] = JC_DIAS[(int) date('w', strtotime($a['data']))] . ' ' . date('d/m', strtotime($a['data']));
        $a['whatsapp_fmt'] = $a['whatsapp'] ? fmt_phone($a['whatsapp']) : '';
        $a['passado'] = $a['data'] < date('Y-m-d');
    }
    unset($a);
    $bl = db_all("SELECT id, data, DATE_FORMAT(hora,'%H:%i') hora, motivo FROM bloqueios WHERE data >= CURDATE() ORDER BY data, hora");
    foreach ($bl as &$b) {
        $b['dia'] = JC_DIAS[(int) date('w', strtotime($b['data']))] . ' ' . date('d/m/Y', strtotime($b['data']));
    }
    unset($b);
    $s = settings();
    json_out(['ok' => true, 'agendamentos' => $ags, 'bloqueios' => $bl,
        'disp' => ['manha' => $s['agenda_manha'] === '1', 'tarde' => $s['agenda_tarde'] === '1', 'sabado' => $s['agenda_sabado'] === '1'],
        'dias' => agenda_dias(5)]);

case 'ag_status':
    if ($GET) break;
    $st = one_of($in['status'] ?? '', ['confirmado', 'cancelado'], null);
    if (!$st) json_fail('Status inválido.');
    try {
        db_q('UPDATE agendamentos SET status = ?, ativo = ? WHERE id = ?', [$st, $st === 'cancelado' ? null : 1, $id]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') json_fail('Esse horário já foi ocupado por outra conversa.', 409);
        throw $e;
    }
    json_out(['ok' => true]);

case 'disponibilidade':
    if ($GET) break;
    foreach (['manha', 'tarde', 'sabado'] as $k) {
        if (array_key_exists($k, $in)) {
            db_q('REPLACE INTO configuracoes (chave, valor) VALUES (?, ?)', ['agenda_' . $k, empty($in[$k]) ? '0' : '1']);
        }
    }
    json_out(['ok' => true]);

case 'bloqueio_add':
    if ($GET) break;
    $data = (string) ($in['data'] ?? '');
    $hora = (string) ($in['hora'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data < date('Y-m-d')) json_fail('Escolha uma data a partir de hoje.');
    if ($hora !== '' && !preg_match('/^\d{2}:\d{2}$/', $hora)) json_fail('Horário inválido.');
    db_q('INSERT INTO bloqueios (data, hora, motivo) VALUES (?, ?, ?)', [$data, $hora === '' ? null : $hora . ':00', clean_line($in['motivo'] ?? '', 120) ?: null]);
    json_out(['ok' => true]);

case 'bloqueio_del':
    if ($GET) break;
    db_q('DELETE FROM bloqueios WHERE id = ?', [$id]);
    json_out(['ok' => true]);

/* ================= condomínios ================= */
case 'condominios':
    json_out(['ok' => true, 'condominios' => db_all('SELECT * FROM condominios ORDER BY desde, id')]);

case 'condo_salvar':
    if ($GET) break;
    $nome = clean_line($_POST['nome'] ?? '', 120);
    $bairro = clean_line($_POST['bairro'] ?? '', 80);
    $un = (int) ($_POST['unidades'] ?? 0);
    $desde = (string) ($_POST['desde'] ?? '');
    if (mb_strlen($nome) < 2) json_fail('Informe o nome do condomínio.');
    if ($un < 1 || $un > 5000) json_fail('Informe o número de unidades.');
    if (!preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', $desde)) json_fail('Informe o mês de início (mm/aaaa).');
    $atual = $id ? db_one('SELECT * FROM condominios WHERE id = ?', [$id]) : null;
    if ($id && !$atual) json_fail('Condomínio não encontrado.', 404);

    $foto = $atual['foto'] ?? null;
    $linha = $atual['foto_linha'] ?? null;
    if (!empty($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $img = upload_imagem($_FILES['foto']);
        if (is_string($img)) json_fail($img);
        $novaFoto = imagem_salvar(imagem_recorte($img, 920, 832), 'condominios');
        $novaLinha = null;
        if (!empty($_FILES['linha']) && ($_FILES['linha']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ln = upload_imagem($_FILES['linha']);
            if (!is_string($ln)) {
                $novaLinha = imagem_salvar(imagem_recorte($ln, 460, 416, true), 'condominios', true);
            }
        }
        imagem_apagar($foto);
        imagem_apagar($linha);
        $foto = $novaFoto;
        $linha = $novaLinha;
    } elseif (!empty($_POST['remover_foto'])) {
        imagem_apagar($foto);
        imagem_apagar($linha);
        $foto = $linha = null;
    }
    $exibir = isset($_POST['exibir']) ? (int) !empty($_POST['exibir']) : (int) ($atual['exibir'] ?? 1);
    $vals = [$nome, $bairro ?: null, $un, $desde . '-01', $foto, $linha, $exibir];
    if ($atual) {
        db_q('UPDATE condominios SET nome=?, bairro=?, unidades=?, desde=?, foto=?, foto_linha=?, exibir=? WHERE id=?', array_merge($vals, [$id]));
    } else {
        db_q('INSERT INTO condominios (nome, bairro, unidades, desde, foto, foto_linha, exibir) VALUES (?,?,?,?,?,?,?)', $vals);
    }
    json_out(['ok' => true]);

case 'condo_exibir':
    if ($GET) break;
    db_q('UPDATE condominios SET exibir = ? WHERE id = ?', [empty($in['exibir']) ? 0 : 1, $id]);
    json_out(['ok' => true]);

case 'condo_excluir':
    if ($GET) break;
    $c = db_one('SELECT foto, foto_linha FROM condominios WHERE id = ?', [$id]);
    if ($c) {
        imagem_apagar($c['foto']);
        imagem_apagar($c['foto_linha']);
        db_q('DELETE FROM condominios WHERE id = ?', [$id]);
    }
    json_out(['ok' => true]);

/* ================= depoimentos ================= */
case 'depoimentos':
    json_out(['ok' => true, 'depoimentos' => array_map(function ($d) {
        $d['criado_fmt'] = fmt_data($d['criado_em']);
        return $d;
    }, db_all("SELECT * FROM depoimentos ORDER BY FIELD(status,'pendente','publicado','recusado'), ordem, id DESC"))]);

case 'depo_status':
    if ($GET) break;
    $st = one_of($in['status'] ?? '', ['pendente', 'publicado', 'recusado'], null);
    if (!$st) json_fail('Status inválido.');
    db_q('UPDATE depoimentos SET status = ? WHERE id = ?', [$st, $id]);
    json_out(['ok' => true]);

case 'depo_salvar':
    if ($GET) break;
    $texto = clean_text($in['texto'] ?? '', 600);
    $nome = clean_line($in['nome'] ?? '', 100);
    if (mb_strlen($texto) < 10 || mb_strlen($nome) < 2) json_fail('Preencha o texto e o nome.');
    if (empty($in['autorizado'])) json_fail('Confirme que a pessoa autorizou a publicação.');
    $vals = [$texto, $nome, clean_line($in['papel'] ?? '', 80) ?: null, clean_line($in['condominio'] ?? '', 120) ?: null];
    if ($id) {
        db_q('UPDATE depoimentos SET texto=?, nome=?, papel=?, condominio=?, autorizado=1 WHERE id=?', array_merge($vals, [$id]));
    } else {
        db_q("INSERT INTO depoimentos (texto, nome, papel, condominio, autorizado, origem, status) VALUES (?,?,?,?,1,'painel','publicado')", $vals);
    }
    json_out(['ok' => true]);

case 'depo_excluir':
    if ($GET) break;
    db_q('DELETE FROM depoimentos WHERE id = ?', [$id]);
    json_out(['ok' => true]);

/* ================= artigos ================= */
case 'artigos':
    json_out(['ok' => true, 'artigos' => array_map(function ($a) {
        $a['atualizado_fmt'] = fmt_data($a['atualizado_em']);
        return $a;
    }, db_all('SELECT id, slug, titulo, categoria, minutos, status, leituras, publicado_em, atualizado_em FROM artigos ORDER BY status, COALESCE(publicado_em, atualizado_em) DESC'))]);

case 'artigo':
    $a = db_one('SELECT * FROM artigos WHERE id = ?', [$id]);
    if (!$a) json_fail('Artigo não encontrado.', 404);
    json_out(['ok' => true, 'artigo' => $a]);

case 'artigo_salvar':
    if ($GET) break;
    $titulo = clean_line($in['titulo'] ?? '', 160);
    $corpo = clean_text($in['corpo'] ?? '', 60000);
    $slug = slugify((string) (($in['slug'] ?? '') !== '' ? $in['slug'] : $titulo));
    $status = one_of($in['status'] ?? '', ['rascunho', 'publicado'], 'rascunho');
    if (mb_strlen($titulo) < 5) json_fail('O título está muito curto.');
    if ($slug === '') json_fail('Endereço inválido.');
    if ($status === 'publicado' && mb_strlen($corpo) < 200) json_fail('Para publicar, escreva pelo menos alguns parágrafos.');
    if (db_val('SELECT id FROM artigos WHERE slug = ? AND id <> ?', [$slug, $id])) json_fail('Já existe um artigo com esse endereço. Mude o endereço.');
    $vals = [$slug, $titulo, mb_strtoupper(clean_line($in['categoria'] ?? 'GESTÃO', 40)) ?: 'GESTÃO', minutos_leitura($corpo), clean_line($in['resumo'] ?? '', 300), $corpo, $status];
    if ($id) {
        $pub = db_val('SELECT publicado_em FROM artigos WHERE id = ?', [$id]);
        db_q('UPDATE artigos SET slug=?, titulo=?, categoria=?, minutos=?, resumo=?, corpo=?, status=?, publicado_em=? WHERE id=?',
            array_merge($vals, [$status === 'publicado' ? ($pub ?: date('Y-m-d H:i:s')) : $pub, $id]));
    } else {
        db_q('INSERT INTO artigos (slug, titulo, categoria, minutos, resumo, corpo, status, publicado_em) VALUES (?,?,?,?,?,?,?,?)',
            array_merge($vals, [$status === 'publicado' ? date('Y-m-d H:i:s') : null]));
        $id = (int) db()->lastInsertId();
    }
    json_out(['ok' => true, 'id' => $id, 'slug' => $slug]);

case 'artigo_excluir':
    if ($GET) break;
    db_q('DELETE FROM artigos WHERE id = ?', [$id]);
    json_out(['ok' => true]);

/* ================= configurações ================= */
case 'config':
    json_out(['ok' => true, 'config' => settings(), 'email' => $user['email']]);

case 'config_salvar':
    if ($GET) break;
    $campos = ['whatsapp' => 30, 'email_contato' => 160, 'horario' => 80, 'endereco' => 120, 'endereco2' => 120, 'cnpj' => 20, 'instagram' => 200, 'linkedin' => 200];
    if (isset($in['whatsapp']) && clean_phone($in['whatsapp']) === null) json_fail('WhatsApp inválido (use DDD + número).');
    if (isset($in['email_contato']) && !filter_var($in['email_contato'], FILTER_VALIDATE_EMAIL)) json_fail('E-mail inválido.');
    foreach (['instagram', 'linkedin'] as $u) {
        if (!empty($in[$u]) && !preg_match('#^https://#i', (string) $in[$u])) json_fail('Os links das redes devem começar com https://');
    }
    foreach ($campos as $k => $max) {
        if (array_key_exists($k, $in)) {
            $v = clean_line($in[$k], $max);
            if ($k === 'whatsapp') {
                $v = fmt_phone((string) clean_phone($v));
            }
            db_q('REPLACE INTO configuracoes (chave, valor) VALUES (?, ?)', [$k, $v]);
        }
    }
    json_out(['ok' => true]);

case 'senha':
    if ($GET) break;
    $row = db_one('SELECT senha_hash FROM usuarios WHERE id = ?', [$user['id']]);
    if (!$row || !password_verify((string) ($in['atual'] ?? ''), $row['senha_hash'])) json_fail('A senha atual está incorreta.');
    $nova = (string) ($in['nova'] ?? '');
    if (($m = senha_forte($nova)) !== null) json_fail($m);
    db_q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $user['id']]);
    session_regenerate_id(true);
    json_out(['ok' => true]);
}

json_fail('Ação desconhecida.', 404);
