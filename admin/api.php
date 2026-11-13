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
    log_evento('api_sem_sessao', (string) ($_GET['acao'] ?? ''));
    json_fail('Sessão expirada. Entre novamente.', 401);
}
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET' && $metodo !== 'POST') {
    json_fail('Método não permitido.', 405);
}
$acao = (string) ($_GET['acao'] ?? '');
if ($metodo === 'POST' && !csrf_ok($_SERVER['HTTP_X_CSRF'] ?? null)) {
    log_evento('csrf_invalido', 'Ação: ' . $acao);
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

/**
 * Ações sensíveis (convidar, redefinir acesso, remover) pedem de novo a senha E o código do aplicativo:
 * um cookie de sessão roubado não basta para tomar contas. Retorna mensagem de erro ou null.
 */
function reautenticar(array $user, array $in): ?string
{
    if (!totp_limite_ok((int) $user['id'])) {
        return 'Muitas tentativas. Aguarde alguns minutos.';
    }
    $eu = db_one('SELECT id, senha_hash, totp_segredo, totp_passo FROM usuarios WHERE id = ?', [$user['id']]);
    if (!$eu || !password_verify((string) ($in['senha'] ?? ''), $eu['senha_hash'])) {
        log_evento('2fa_falha', 'Confirmação de ação: senha incorreta');
        return 'Senha incorreta.';
    }
    if (!usuario_totp_ok($eu, (string) ($in['codigo'] ?? ''))) {
        log_evento('2fa_falha', 'Confirmação de ação: código incorreto ou já usado');
        return 'Código do aplicativo incorreto ou já usado. Aguarde o próximo código (muda a cada 30 s).';
    }
    totp_limite_limpar((int) $user['id']);
    return null;
}

/** Garante que sobra pelo menos um usuário ativo além do alvo (trava as linhas para evitar corrida entre dois masters). */
function sobra_outro_ativo(int $alvoId): bool
{
    db_all('SELECT id FROM usuarios WHERE ativo = 1 FOR UPDATE');
    return (int) db_val('SELECT COUNT(*) FROM usuarios WHERE ativo = 1 AND id <> ?', [$alvoId]) >= 1;
}

/** Filtros da tela de registros: categoria, só alertas/avisos, período e busca. @return array [" WHERE ...", params] */
function logs_filtro(array $in): array
{
    $w = [];
    $p = [];
    $cat = one_of($in['cat'] ?? '', ['acesso', 'seguranca', 'painel', 'site'], null);
    if ($cat) {
        $w[] = 'categoria = ?';
        $p[] = $cat;
    }
    $niv = one_of($in['nivel'] ?? '', ['aviso', 'alerta'], null);
    if ($niv === 'alerta') {
        $w[] = "nivel = 'alerta'";
    } elseif ($niv === 'aviso') {
        $w[] = "nivel IN ('aviso','alerta')";
    }
    $dias = (int) ($in['dias'] ?? 0);
    if (in_array($dias, [1, 7, 30, 90], true)) {
        $w[] = 'criado_em >= NOW() - INTERVAL ' . $dias . ' DAY';
    }
    $q = clean_line($in['q'] ?? '', 80);
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $w[] = '(detalhe LIKE ? OR email LIKE ? OR ip LIKE ? OR usuario_nome LIKE ?)';
        array_push($p, $like, $like, $like, $like);
    }
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
}

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
        'alertas24' => (int) db_val("SELECT COUNT(*) FROM logs WHERE nivel = 'alerta' AND criado_em >= NOW() - INTERVAL 1 DAY"),
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
    $nCsv = 0;
    foreach (db_all('SELECT * FROM leads ORDER BY id DESC') as $l) {
        $nCsv++;
        $linha = [fmt_data($l['criado_em'], true), $l['origem'], $l['nome'], fmt_phone($l['whatsapp']), $l['papel'], $l['condominio'], $l['unidades'], $l['status'], $l['mensagem'], $l['notas']];
        // evita que o Excel interprete conteúdo como fórmula
        $linha = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $linha);
        fputcsv($out, $linha, ';');
    }
    fclose($out);
    log_evento('leads_exportados', $nCsv . ' contatos');
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
    log_evento('lead_status', 'Contato #' . $id . ($st ? ' → ' . $st : '') . (array_key_exists('notas', $in) ? ' (anotações)' : ''));
    json_out(['ok' => true]);

case 'lead_excluir':
    if ($GET) break;
    db_q('DELETE FROM leads WHERE id = ?', [$id]);
    log_evento('lead_excluido', 'Contato #' . $id);
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
    $cfg = agenda_config();
    $porDia = [];
    foreach ($cfg['dias'] as $d => $j) {
        $h = [];
        foreach (['manha', 'tarde'] as $per) {
            if ($j[$per]['ativo']) $h = array_merge($h, agenda_slots_janela($j[$per]['de'], $j[$per]['ate'], $cfg['duracao']));
        }
        $porDia[$d] = $h;
    }
    json_out(['ok' => true, 'agendamentos' => $ags, 'bloqueios' => $bl,
        'config' => $cfg, 'slots_dow' => $porDia,
        'opcoes' => ['duracoes' => JC_AGENDA_DURACOES, 'antecedencias' => JC_AGENDA_ANTECEDENCIAS, 'janelas' => JC_AGENDA_JANELAS],
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
    log_evento('agendamento_status', 'Conversa #' . $id . ' → ' . $st);
    json_out(['ok' => true]);

case 'agenda_config_salvar':
    if ($GET) break;
    $cfg = agenda_config_validar($in);
    if (is_string($cfg)) json_fail($cfg);
    db_q('REPLACE INTO configuracoes (chave, valor) VALUES (?, ?)', ['agenda_config', json_encode($cfg)]);
    settings(true);
    log_evento('agenda_config', 'Conversas de ' . $cfg['duracao'] . ' min · antecedência ' . $cfg['antecedencia'] . ' dia(s) · ' . $cfg['janela'] . ' dias à frente');
    json_out(['ok' => true, 'dias' => agenda_dias(5)]);

case 'bloqueio_add':
    if ($GET) break;
    $data = (string) ($in['data'] ?? '');
    $hora = (string) ($in['hora'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $data < date('Y-m-d')) json_fail('Escolha uma data a partir de hoje.');
    if ($hora !== '' && !preg_match('/^\d{2}:\d{2}$/', $hora)) json_fail('Horário inválido.');
    db_q('INSERT INTO bloqueios (data, hora, motivo) VALUES (?, ?, ?)', [$data, $hora === '' ? null : $hora . ':00', clean_line($in['motivo'] ?? '', 120) ?: null]);
    log_evento('bloqueio_add', $data . ($hora !== '' ? ' ' . $hora : ' (dia inteiro)'));
    json_out(['ok' => true]);

case 'bloqueio_del':
    if ($GET) break;
    db_q('DELETE FROM bloqueios WHERE id = ?', [$id]);
    log_evento('bloqueio_del', 'Bloqueio #' . $id);
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
        if (is_string($img)) { log_evento('upload_recusado', 'Foto de condomínio: ' . $img); json_fail($img); }
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
    log_evento('condo_salvo', $nome . ($atual ? ' (editado)' : ' (novo)'));
    json_out(['ok' => true]);

case 'condo_exibir':
    if ($GET) break;
    db_q('UPDATE condominios SET exibir = ? WHERE id = ?', [empty($in['exibir']) ? 0 : 1, $id]);
    log_evento('condo_exibir', 'Condomínio #' . $id . (empty($in['exibir']) ? ' oculto' : ' exibido'));
    json_out(['ok' => true]);

case 'condo_excluir':
    if ($GET) break;
    $c = db_one('SELECT foto, foto_linha FROM condominios WHERE id = ?', [$id]);
    if ($c) {
        imagem_apagar($c['foto']);
        imagem_apagar($c['foto_linha']);
        db_q('DELETE FROM condominios WHERE id = ?', [$id]);
        log_evento('condo_excluido', 'Condomínio #' . $id);
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
    log_evento('depo_status', 'Depoimento #' . $id . ' → ' . $st);
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
    log_evento('depo_salvo', $id ? 'Depoimento #' . $id . ' (editado)' : 'Novo depoimento cadastrado no painel');
    json_out(['ok' => true]);

case 'depo_excluir':
    if ($GET) break;
    db_q('DELETE FROM depoimentos WHERE id = ?', [$id]);
    log_evento('depo_excluido', 'Depoimento #' . $id);
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
    $capa = (string) ($in['capa'] ?? '');
    $capa = ($capa !== '' && midia_valida($capa, ['webp', 'jpg', 'png'])) ? $capa : null; // só arquivo enviado pelo painel
    if (mb_strlen($titulo) < 5) json_fail('O título está muito curto.');
    if ($slug === '') json_fail('Endereço inválido.');
    if ($status === 'publicado' && mb_strlen($corpo) < 200) json_fail('Para publicar, escreva pelo menos alguns parágrafos.');
    if (db_val('SELECT id FROM artigos WHERE slug = ? AND id <> ?', [$slug, $id])) json_fail('Já existe um artigo com esse endereço. Mude o endereço.');
    $vals = [$slug, $titulo, mb_strtoupper(clean_line($in['categoria'] ?? 'GESTÃO', 40)) ?: 'GESTÃO', minutos_leitura($corpo), clean_line($in['resumo'] ?? '', 300), $capa, $corpo, $status];
    if ($id) {
        $atual = db_one('SELECT publicado_em, capa FROM artigos WHERE id = ?', [$id]);
        if (!$atual) json_fail('Artigo não encontrado.', 404);
        db_q('UPDATE artigos SET slug=?, titulo=?, categoria=?, minutos=?, resumo=?, capa=?, corpo=?, status=?, publicado_em=? WHERE id=?',
            array_merge($vals, [$status === 'publicado' ? ($atual['publicado_em'] ?: date('Y-m-d H:i:s')) : $atual['publicado_em'], $id]));
        if ($atual['capa'] && $atual['capa'] !== $capa) imagem_apagar($atual['capa']); // capa trocada ou removida
    } else {
        db_q('INSERT INTO artigos (slug, titulo, categoria, minutos, resumo, capa, corpo, status, publicado_em) VALUES (?,?,?,?,?,?,?,?,?)',
            array_merge($vals, [$status === 'publicado' ? date('Y-m-d H:i:s') : null]));
        $id = (int) db()->lastInsertId();
    }
    log_evento('artigo_salvo', '"' . mb_substr($titulo, 0, 80) . '" (' . $status . ')');
    json_out(['ok' => true, 'id' => $id, 'slug' => $slug]);

case 'artigo_excluir':
    if ($GET) break;
    $a = db_one('SELECT capa, corpo, titulo FROM artigos WHERE id = ?', [$id]);
    db_q('DELETE FROM artigos WHERE id = ?', [$id]);
    log_evento('artigo_excluido', '"' . mb_substr((string) ($a['titulo'] ?? '#' . $id), 0, 80) . '"');
    if ($a) { // apaga do disco a capa e as mídias que só esse artigo usava
        imagem_apagar($a['capa']);
        foreach (artigo_midias((string) $a['corpo']) as $m) {
            if (!db_val('SELECT id FROM artigos WHERE corpo LIKE ? LIMIT 1', ['%' . $m . '%'])) imagem_apagar($m);
        }
    }
    json_out(['ok' => true]);

/* Envio de mídia do artigo: tipo = capa | foto | video (multipart, campo "arquivo") */
case 'midia_enviar':
    if ($GET) break;
    $tipo = one_of($_POST['tipo'] ?? '', ['capa', 'foto', 'video'], null);
    if ($tipo === null || !isset($_FILES['arquivo'])) json_fail('Envio inválido.');
    if (!rate_ok('midia', 60, 600, true, (string) $user['id'])) json_fail('Muitos envios seguidos. Aguarde alguns minutos.', 429);
    if ($tipo === 'video') {
        $r = upload_video_salvar($_FILES['arquivo'], 'artigos');
        if ($r[0] === '!') { log_evento('upload_recusado', 'Vídeo: ' . substr($r, 1)); json_fail(substr($r, 1)); }
        log_evento('midia_enviada', 'Vídeo (' . round(($_FILES['arquivo']['size'] ?? 0) / 1048576, 1) . ' MB)');
        json_out(['ok' => true, 'caminho' => $r, 'tipo' => 'video']);
    }
    $img = upload_imagem($_FILES['arquivo']);
    if (is_string($img)) { log_evento('upload_recusado', ucfirst($tipo) . ': ' . $img); json_fail($img); }
    log_evento('midia_enviada', ucfirst($tipo));
    $img = $tipo === 'capa' ? imagem_recorte($img, 1200, 630) : imagem_reduzir($img, 1600);
    json_out(['ok' => true, 'caminho' => imagem_salvar($img, 'artigos'), 'tipo' => $tipo]);

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
    log_evento('config_salva', implode(', ', array_intersect(array_keys($campos), array_keys($in))));
    json_out(['ok' => true]);

/* ================= minha conta e usuários ================= */
case 'senha':
    if ($GET) break;
    $eu = db_one('SELECT id, nome, email, senha_hash, totp_segredo, totp_passo FROM usuarios WHERE id = ?', [$user['id']]);
    $nova = (string) ($in['nova'] ?? '');
    if (($m = senha_forte($nova)) !== null) json_fail($m);
    if (!totp_limite_ok((int) $user['id'])) json_fail('Muitas tentativas. Aguarde alguns minutos.', 429);
    if (!$eu || !password_verify((string) ($in['atual'] ?? ''), $eu['senha_hash'])) json_fail('A senha atual está incorreta.');
    if (!usuario_totp_ok($eu, (string) ($in['codigo'] ?? ''))) { log_evento('2fa_falha', 'Troca de senha'); json_fail('Código do aplicativo incorreto ou já usado.'); }
    totp_limite_limpar((int) $user['id']);
    db_q('UPDATE usuarios SET senha_hash = ?, convite_hash = NULL, convite_expira = NULL WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $user['id']]);
    session_regenerate_id(true);
    admin_carimbo_atualizar((int) $user['id']); // esta sessão continua; as outras sessões deste usuário caem
    log_evento('senha_alterada');
    json_out(['ok' => true]);

case 'usuarios':
    $rows = db_all('SELECT id, nome, email, ativo, totp_ativo, codigos_recuperacao, convite_hash, convite_expira, ultimo_login, criado_em FROM usuarios ORDER BY id');
    $lista = [];
    foreach ($rows as $r) {
        if (!(int) $r['ativo']) $sit = 'desativado';
        elseif ($r['convite_hash'] && !$r['ultimo_login']) $sit = $r['convite_expira'] < date('Y-m-d H:i:s') ? 'convite_expirado' : 'convite';
        elseif ($r['convite_hash']) $sit = $r['convite_expira'] < date('Y-m-d H:i:s') ? 'ativo' : 'redefinindo';
        else $sit = 'ativo';
        $lista[] = [
            'id' => (int) $r['id'], 'nome' => $r['nome'], 'email' => $r['email'], 'situacao' => $sit,
            'totp_ativo' => (int) $r['totp_ativo'], 'eu' => (int) $r['id'] === (int) $user['id'],
            'ultimo_login_fmt' => $r['ultimo_login'] ? fmt_data($r['ultimo_login'], true) : null,
            'convite_expira_fmt' => $r['convite_expira'] ? fmt_data($r['convite_expira'], true) : null,
            'recuperacao' => (int) $r['id'] === (int) $user['id'] ? codigos_recuperacao_restantes($r['codigos_recuperacao']) : null,
        ];
    }
    json_out(['ok' => true, 'usuarios' => $lista]);

case 'usuario_convidar':
    if ($GET) break;
    if (!rate_ok('convidar', 10, 3600, true, (string) $user['id'])) json_fail('Muitos convites seguidos. Tente mais tarde.', 429);
    if (($rr = reautenticar($user, $in)) !== null) json_fail($rr);
    $nome = clean_line($in['nome'] ?? '', 80);
    $email = mb_strtolower(clean_line($in['email'] ?? '', 160));
    if (mb_strlen($nome) < 2) json_fail('Informe o nome da pessoa.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_fail('E-mail inválido.');
    if (db_val('SELECT id FROM usuarios WHERE email = ?', [$email])) json_fail('Já existe um usuário com esse e-mail.');
    db_q('INSERT INTO usuarios (nome, email, senha_hash, criado_por) VALUES (?, ?, ?, ?)', [$nome, $email, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $user['id']]);
    $novoId = (int) db()->lastInsertId();
    $link = abs_url('admin/') . '?convite=' . convite_criar($novoId);
    $enviado = email_enviar($email, 'Convite para o painel do site JC Síndico', "Olá, $nome!\n\n{$user['nome']} convidou você para o painel do site JC Síndico Profissional.\n\nAbra o link abaixo para criar sua senha e ativar a verificação em 2 etapas (válido por 48 horas):\n\n$link\n\nSe você não esperava este convite, ignore esta mensagem.\n");
    log_evento('usuario_convidado', $nome . ' <' . $email . '>' . ($enviado ? ' (e-mail enviado)' : ' (link gerado)'));
    json_out(['ok' => true, 'link' => $link, 'email_enviado' => $enviado, 'nome' => $nome, 'email' => $email]);

case 'usuario_convite_novo':
    if ($GET) break;
    $alvo = db_one('SELECT id, nome, email, ativo FROM usuarios WHERE id = ?', [$id]);
    if (!$alvo || !(int) $alvo['ativo']) json_fail('Usuário não encontrado ou desativado.', 404);
    if ((int) $alvo['id'] === (int) $user['id']) json_fail('Para trocar a sua própria senha, use "Minha conta".');
    if (($rr = reautenticar($user, $in)) !== null) json_fail($rr);
    $link = abs_url('admin/') . '?convite=' . convite_criar((int) $alvo['id']);
    $enviado = email_enviar($alvo['email'], 'Novo link de acesso ao painel do site JC Síndico', "Olá, {$alvo['nome']}!\n\n{$user['nome']} gerou um novo link para você criar uma senha no painel (válido por 48 horas):\n\n$link\n\nSe você não esperava esta mensagem, ignore-a.\n");
    log_evento('usuario_convite_novo', $alvo['nome'] . ' <' . $alvo['email'] . '>');
    json_out(['ok' => true, 'link' => $link, 'email_enviado' => $enviado, 'nome' => $alvo['nome'], 'email' => $alvo['email']]);

case 'usuario_status':
    if ($GET) break;
    $alvo = db_one('SELECT id, nome, email FROM usuarios WHERE id = ?', [$id]);
    if (!$alvo) json_fail('Usuário não encontrado.', 404);
    if ((int) $alvo['id'] === (int) $user['id']) json_fail('Você não pode desativar o seu próprio acesso.');
    $ativo = empty($in['ativo']) ? 0 : 1;
    $pdo = db();
    $pdo->beginTransaction();
    if (!$ativo && !sobra_outro_ativo((int) $alvo['id'])) { $pdo->rollBack(); json_fail('Precisa sobrar pelo menos um usuário ativo.'); }
    db_q('UPDATE usuarios SET ativo = ? WHERE id = ?', [$ativo, $id]);
    $pdo->commit();
    log_evento('usuario_status', $alvo['nome'] . ' <' . $alvo['email'] . '> ' . ($ativo ? 'reativado' : 'desativado'));
    json_out(['ok' => true]);

case 'usuario_excluir':
    if ($GET) break;
    $alvo = db_one('SELECT id, nome, email FROM usuarios WHERE id = ?', [$id]);
    if (!$alvo) json_fail('Usuário não encontrado.', 404);
    if ((int) $alvo['id'] === (int) $user['id']) json_fail('Você não pode remover o seu próprio acesso.');
    if (($rr = reautenticar($user, $in)) !== null) json_fail($rr);
    $pdo = db();
    $pdo->beginTransaction();
    if (!sobra_outro_ativo((int) $alvo['id'])) { $pdo->rollBack(); json_fail('Precisa sobrar pelo menos um usuário ativo.'); }
    db_q('DELETE FROM usuarios WHERE id = ?', [$id]);
    $pdo->commit();
    log_evento('usuario_excluido', $alvo['nome'] . ' <' . $alvo['email'] . '>');
    json_out(['ok' => true]);

/* Outro usuário perdeu o celular: o master zera a 2ª etapa e a pessoa configura de novo no próximo login.
   Para o próprio usuário (trocar de celular) exige senha e código atuais e encerra a sessão. */
case 'usuario_2fa_reset':
    if ($GET) break;
    $alvo = db_one('SELECT id, nome, email, senha_hash, totp_segredo, totp_passo FROM usuarios WHERE id = ?', [$id]);
    if (!$alvo) json_fail('Usuário não encontrado.', 404);
    $proprio = (int) $alvo['id'] === (int) $user['id'];
    if ($proprio) {
        if (!totp_limite_ok((int) $user['id'])) json_fail('Muitas tentativas. Aguarde alguns minutos.', 429);
        if (!password_verify((string) ($in['senha'] ?? ''), $alvo['senha_hash']) || !usuario_totp_ok($alvo, (string) ($in['codigo'] ?? ''))) {
            log_evento('2fa_falha', 'Reconfiguração do próprio 2FA');
            json_fail('Senha ou código incorretos.');
        }
        totp_limite_limpar((int) $user['id']);
        db_q('UPDATE usuarios SET totp_segredo = NULL, totp_ativo = 0, totp_passo = NULL, codigos_recuperacao = NULL, convite_hash = NULL, convite_expira = NULL WHERE id = ?', [$id]);
        log_evento('usuario_2fa_reset', $alvo['nome'] . ' <' . $alvo['email'] . '> (próprio usuário)');
        admin_logout();
        json_out(['ok' => true, 'sair' => true]);
    }
    // Outra pessoa: o master confirma com senha + código; a senha antiga deixa de valer e a pessoa entra por um novo link
    // (senão quem soubesse só a senha antiga poderia cadastrar o próprio aplicativo). Sessões abertas dela caem.
    if (($rr = reautenticar($user, $in)) !== null) json_fail($rr);
    db_q('UPDATE usuarios SET totp_segredo = NULL, totp_ativo = 0, totp_passo = NULL, codigos_recuperacao = NULL WHERE id = ?', [$id]);
    senha_invalidar((int) $alvo['id']);
    $link = abs_url('admin/') . '?convite=' . convite_criar((int) $alvo['id']);
    $enviado = email_enviar($alvo['email'], 'Novo acesso ao painel do site JC Síndico', "Olá, {$alvo['nome']}!\n\n{$user['nome']} redefiniu a verificação em 2 etapas da sua conta. Abra o link abaixo para criar uma nova senha e configurar o aplicativo (válido por 48 horas):\n\n$link\n\nSe você não esperava esta mensagem, ignore-a e avise quem administra o site.\n");
    log_evento('usuario_2fa_reset', $alvo['nome'] . ' <' . $alvo['email'] . '>');
    json_out(['ok' => true, 'link' => $link, 'email_enviado' => $enviado, 'nome' => $alvo['nome'], 'email' => $alvo['email']]);

case 'codigos_novos':
    if ($GET) break;
    $eu = db_one('SELECT id, nome, email, senha_hash, totp_segredo, totp_passo FROM usuarios WHERE id = ?', [$user['id']]);
    if (!totp_limite_ok((int) $user['id'])) json_fail('Muitas tentativas. Aguarde alguns minutos.', 429);
    if (!$eu || !password_verify((string) ($in['senha'] ?? ''), $eu['senha_hash']) || !usuario_totp_ok($eu, (string) ($in['codigo'] ?? ''))) {
        log_evento('2fa_falha', 'Novos códigos de recuperação');
        json_fail('Senha ou código incorretos.');
    }
    totp_limite_limpar((int) $user['id']);
    $cods = codigos_recuperacao_gerar();
    codigos_recuperacao_guardar((int) $user['id'], $cods);
    log_evento('codigos_novos');
    json_out(['ok' => true, 'codigos' => $cods]);

/* ================= registros de atividade ================= */
case 'logs':
case 'logs_csv':
    [$onde, $par] = logs_filtro($in);
    if ($acao === 'logs_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="registros-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Data', 'Categoria', 'Evento', 'Nível', 'Usuário', 'E-mail', 'IP', 'Navegador', 'Detalhe'], ';');
        foreach (db_all('SELECT * FROM logs' . $onde . ' ORDER BY id DESC LIMIT 5000', $par) as $l) {
            $linha = [fmt_data($l['criado_em'], true), $l['categoria'], JC_LOG_EVENTOS[$l['evento']][2] ?? $l['evento'], $l['nivel'], $l['usuario_nome'], $l['email'], $l['ip'], $l['agente'], $l['detalhe']];
            fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $linha), ';');
        }
        fclose($out);
        log_evento('logs_exportados');
        exit;
    }
    $antes = (int) ($in['antes'] ?? 0);
    $sql = 'SELECT * FROM logs' . $onde . ($antes ? ($onde ? ' AND' : ' WHERE') . ' id < ?' : '') . ' ORDER BY id DESC LIMIT 51';
    $rows = db_all($sql, $antes ? array_merge($par, [$antes]) : $par);
    $mais = count($rows) > 50;
    $rows = array_slice($rows, 0, 50);
    foreach ($rows as &$l) {
        $l['criado_fmt'] = fmt_data($l['criado_em'], true) . ':' . date('s', strtotime($l['criado_em']));
        $l['rotulo'] = JC_LOG_EVENTOS[$l['evento']][2] ?? $l['evento'];
    }
    unset($l);
    $out = ['ok' => true, 'logs' => $rows, 'mais' => $mais];
    if (!$antes) {
        $r24 = db_one("SELECT SUM(evento = 'login_ok') ok, SUM(evento IN ('login_falha','2fa_falha')) falhas, SUM(evento IN ('login_bloqueado','2fa_bloqueado')) bloqueios, SUM(nivel = 'alerta') alertas FROM logs WHERE criado_em >= NOW() - INTERVAL 1 DAY");
        $out['resumo'] = ['logins' => (int) $r24['ok'], 'falhas' => (int) $r24['falhas'], 'bloqueios' => (int) $r24['bloqueios'], 'alertas' => (int) $r24['alertas']];
        $out['suspeitos'] = db_all("SELECT ip, COUNT(*) n, COUNT(DISTINCT email) emails, MAX(criado_em) ultimo FROM logs WHERE evento IN ('login_falha','2fa_falha','login_bloqueado','2fa_bloqueado','api_sem_sessao','csrf_invalido') AND criado_em >= NOW() - INTERVAL 1 DAY GROUP BY ip HAVING n >= 3 ORDER BY n DESC LIMIT 5");
        foreach ($out['suspeitos'] as &$sp) {
            $sp['ultimo_fmt'] = fmt_data($sp['ultimo'], true);
        }
        unset($sp);
    }
    json_out($out);

}

json_fail('Ação desconhecida.', 404);
