<?php
defined('JC') || exit;

/* ---------- configurações editáveis no painel ---------- */
const JC_SETTINGS_DEFAULT = [
    'whatsapp' => '(85) 99984-0270',
    'email_contato' => 'contato@juniorsindico.pro',
    'horario' => 'Seg a sex 8h–18h · sáb 8h–12h',
    'endereco' => 'Av. Washington Soares, 55, sala 516',
    'endereco2' => 'Edson Queiroz, Fortaleza-CE · 60811-341',
    'cnpj' => '42.903.182/0001-50',
    'instagram' => 'https://www.instagram.com/jc.sindicoprofissional/',
    'linkedin' => 'https://www.linkedin.com/in/junior-chagas-13ba17188/',
    'inicio_ano' => '2014',
    'num_anos_modo' => 'auto', 'num_anos_valor' => '', 'num_anos_mais' => '0', // números de destaque: auto | manual | oculto
    'num_cond_modo' => 'auto', 'num_cond_valor' => '', 'num_cond_mais' => '0',
    'num_un_modo' => 'auto', 'num_un_valor' => '', 'num_un_mais' => '0',
    'hero_estilo' => 'escura', // primeira tela do site: 'escura' (original) ou 'clara' (prédio); vale para as duas telas enquanto não houver escolha própria
    'hero_estilo_web' => '',    // escolha só para computador/tablet grande ('' = usa hero_estilo)
    'hero_estilo_mobile' => '', // escolha só para celular ('' = usa hero_estilo)
];

function settings(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = JC_SETTINGS_DEFAULT;
        foreach (db_all('SELECT chave, valor FROM configuracoes') as $r) {
            $cache[$r['chave']] = $r['valor'];
        }
    }
    return $cache;
}

function setting(string $k): string
{
    return (string) (settings()[$k] ?? '');
}

function anos_gestao(): int
{
    $ini = (int) setting('inicio_ano') ?: 2014;
    // Primeiro contrato começou em junho
    return (int) date('Y') - $ini - ((int) date('n') < 6 ? 1 : 0);
}

/* ---------- números de destaque (primeira tela e textos) ----------
 * Cada número tem modo 'auto' (calculado), 'manual' (valor digitado no painel) ou 'oculto',
 * e pode levar o sinal "+" (ex.: +12 condomínios). */
const JC_NUMEROS = ['anos' => ['ano', 'anos', 'de gestão'], 'cond' => ['condomínio', 'condomínios', ''], 'un' => ['unidade', 'unidades', '']];

function numeros_destaque(array $condos): array
{
    $auto = ['anos' => anos_gestao(), 'cond' => count($condos), 'un' => array_sum(array_map(fn($c) => (int) $c['unidades'], $condos))];
    $out = [];
    foreach (JC_NUMEROS as $k => [$sing, $plur, $suf]) {
        $modo = setting("num_{$k}_modo");
        if ($modo === 'oculto') {
            continue;
        }
        $n = $auto[$k];
        if ($modo === 'manual' && preg_match('/^\d{1,6}$/', setting("num_{$k}_valor"))) {
            $n = (int) setting("num_{$k}_valor");
        }
        $mais = setting("num_{$k}_mais") === '1';
        $out[$k] = [
            'n' => $n,
            'mais' => $mais,
            'valor' => ($mais ? '+' : '') . number_format($n, 0, ',', '.'),
            'rotulo' => trim(($n === 1 ? $sing : $plur) . ' ' . $suf),
            'frase' => ($mais ? 'mais de ' : '') . number_format($n, 0, ',', '.') . ' ' . ($n === 1 ? $sing : $plur),
        ];
    }
    return $out;
}

/** "12 condomínios, 322 unidades" conforme o que o painel deixou visível. */
function numeros_frase(array $nums, array $chaves = ['cond', 'un']): string
{
    $p = [];
    foreach ($chaves as $k) {
        if (isset($nums[$k])) {
            $p[] = $nums[$k]['frase'];
        }
    }
    return implode(', ', $p);
}

/* ---------- condomínios ---------- */
function condominios_site(): array
{
    return db_all('SELECT * FROM condominios WHERE exibir = 1 ORDER BY desde ASC, id ASC');
}

/* ---------- depoimentos ---------- */
function depoimentos_site(): array
{
    return db_all("SELECT * FROM depoimentos WHERE status = 'publicado' AND autorizado = 1 ORDER BY ordem ASC, id DESC LIMIT 12");
}

/* ---------- artigos do guia ---------- */
const JC_POR_PAGINA = 9;

function artigos_publicados(?int $limite = null, int $offset = 0, ?string $categoria = null): array
{
    $sql = "SELECT id, slug, titulo, categoria, minutos, resumo, capa, publicado_em, atualizado_em
            FROM artigos WHERE status = 'publicado'";
    $par = [];
    if ($categoria !== null && $categoria !== '') {
        $sql .= ' AND categoria = ?';
        $par[] = $categoria;
    }
    $sql .= ' ORDER BY publicado_em DESC, id DESC';
    if ($limite) {
        $sql .= ' LIMIT ' . (int) $limite . ' OFFSET ' . max(0, $offset);
    }
    return db_all($sql, $par);
}
function artigos_total(?string $categoria = null): int
{
    if ($categoria !== null && $categoria !== '') {
        return (int) db_val("SELECT COUNT(*) FROM artigos WHERE status = 'publicado' AND categoria = ?", [$categoria]);
    }
    return (int) db_val("SELECT COUNT(*) FROM artigos WHERE status = 'publicado'");
}
function artigos_categorias(): array
{
    return array_column(db_all("SELECT categoria, COUNT(*) n FROM artigos WHERE status = 'publicado' GROUP BY categoria ORDER BY n DESC, categoria"), 'categoria');
}
/** Relacionados: primeiro os da mesma categoria, depois os mais recentes. */
function artigos_relacionados(int $id, string $categoria, int $n = 3): array
{
    return db_all(
        "SELECT slug, titulo, categoria, minutos, resumo, capa FROM artigos
         WHERE status = 'publicado' AND id <> ? ORDER BY (categoria = ?) DESC, publicado_em DESC, id DESC LIMIT " . (int) $n,
        [$id, $categoria]
    );
}

/** Nomes aceitos para mídia enviada pelo painel (o nome aleatório é gerado pelo servidor). */
const JC_RE_MIDIA = '#^uploads/artigos/[a-f0-9]{20}\.(webp|jpg|png|mp4)$#';

function midia_valida(?string $rel, array $exts = ['webp', 'jpg', 'png', 'mp4']): bool
{
    return $rel !== null && preg_match(JC_RE_MIDIA, $rel) === 1
        && in_array(pathinfo($rel, PATHINFO_EXTENSION), $exts, true) && is_file(JC_ROOT . '/' . $rel);
}

/** Endereço público da capa do artigo (ou null). */
function capa_url(?string $capa): ?string
{
    return midia_valida($capa, ['webp', 'jpg', 'png']) ? url($capa) : null;
}

/**
 * Capa genérica por categoria, para artigo sem capa própria: assets/img/capas/<categoria>.webp
 * (manutencao, financas, eleicao...) ou assets/img/capas/padrao.webp. Sem os arquivos, o cartão mostra o degradê.
 */
function capa_padrao(string $categoria): ?string
{
    $k = strtr(mb_strtolower(trim($categoria)), ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    $k = trim((string) preg_replace('/[^a-z0-9]+/', '-', $k), '-');
    foreach ([$k, 'padrao'] as $n) {
        $rel = 'assets/img/capas/' . $n . '.webp';
        if ($n !== '' && is_file(JC_ROOT . '/' . $rel)) {
            return $rel;
        }
    }
    return null;
}

/** Arquivos citados no corpo do artigo ([[foto:...]] e [[video:...]]). */
function artigo_midias(string $corpo): array
{
    preg_match_all('/\[\[(?:foto|video):(uploads\/artigos\/[a-f0-9]{20}\.[a-z0-9]{3,4})(?:\||\]\])/', $corpo, $m);
    return array_values(array_unique($m[1] ?? []));
}

/** Extrai o ID de um link do YouTube (watch, youtu.be, shorts, embed). */
function youtube_id(string $u): ?string
{
    if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^\s\#]*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&\#/].*)?$#', trim($u), $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Converte o texto do artigo (formato simples) em HTML seguro. TUDO é escapado antes de virar tag.
 * Formatos: "## Subtítulo", "### Subtítulo menor", "- item", "1. item", "> citação", "---",
 * **negrito**, *itálico*, [texto](https://link) e blocos [[foto:arquivo|legenda]] / [[video:link-ou-arquivo|legenda]].
 */
function artigo_html(string $txt): string
{
    $blocos = preg_split("/\n\s*\n/", str_replace("\r", '', trim($txt))) ?: [];
    $out = '';
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<![*\w])\*(?![\s*])(.+?)(?<![\s*])\*(?![*\w])/s', '<em>$1</em>', $s) ?? $s;
        $s = preg_replace_callback('/\[([^\]\n]{1,200})\]\(((?:https?:\/\/|\/(?!\/))[^\s)]{1,500})\)/', function ($m) {
            $ext = str_starts_with($m[2], 'http');
            return '<a href="' . $m[2] . '"' . ($ext ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $m[1] . '</a>';
        }, $s) ?? $s;
        return nl2br($s, false);
    };
    $midia = function (string $tipo, string $alvo, string $legenda) use ($inline): string {
        $leg = $legenda !== '' ? '<figcaption>' . $inline($legenda) . '</figcaption>' : '';
        if ($tipo === 'foto') {
            if (!midia_valida($alvo, ['webp', 'jpg', 'png'])) {
                return '';
            }
            $d = @getimagesize(JC_ROOT . '/' . $alvo);
            $wh = $d ? ' width="' . (int) $d[0] . '" height="' . (int) $d[1] . '"' : '';
            return '<figure class="art-fig"><img src="' . e(url($alvo)) . '" alt="' . e($legenda) . '"' . $wh . ' loading="lazy" decoding="async">' . $leg . "</figure>\n";
        }
        if (($yt = youtube_id($alvo)) !== null) {
            return '<figure class="art-fig art-video"><div class="yt"><iframe src="https://www.youtube-nocookie.com/embed/' . e($yt)
                . '" title="' . e($legenda !== '' ? $legenda : 'Vídeo') . '" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>' . $leg . "</figure>\n";
        }
        if (midia_valida($alvo, ['mp4'])) {
            return '<figure class="art-fig art-video"><video controls playsinline preload="metadata" src="' . e(url($alvo)) . '"></video>' . $leg . "</figure>\n";
        }
        return '';
    };
    foreach ($blocos as $b) {
        $b = trim($b);
        if ($b === '') {
            continue;
        }
        if (preg_match('/^\[\[(foto|video):([^\]|]+)(?:\|([^\]]*))?\]\]$/u', $b, $m)) {
            $out .= $midia($m[1], trim($m[2]), clean_line($m[3] ?? '', 200));
            continue;
        }
        if (preg_match('/^-{3,}$/', $b)) {
            $out .= "<hr>\n";
            continue;
        }
        if (str_starts_with($b, '### ')) {
            $out .= '<h3>' . $inline(substr($b, 4)) . "</h3>\n";
            continue;
        }
        if (str_starts_with($b, '## ')) {
            $out .= '<h2>' . $inline(substr($b, 3)) . "</h2>\n";
            continue;
        }
        $linhas = explode("\n", $b);
        $todas = fn(string $re) => count(array_filter($linhas, fn($l) => preg_match($re, $l))) === count($linhas);
        if ($todas('/^\s*>/')) {
            $q = array_map(fn($l) => preg_replace('/^\s*>\s?/', '', $l), $linhas);
            $out .= '<blockquote><p>' . $inline(implode("\n", $q)) . "</p></blockquote>\n";
            continue;
        }
        if ($todas('/^\s*[-•]\s+/')) {
            $out .= "<ul>\n";
            foreach ($linhas as $l) {
                $out .= '<li>' . $inline(preg_replace('/^\s*[-•]\s+/', '', $l) ?? '') . "</li>\n";
            }
            $out .= "</ul>\n";
            continue;
        }
        if ($todas('/^\s*\d{1,2}[.)]\s+/')) {
            $out .= "<ol>\n";
            foreach ($linhas as $l) {
                $out .= '<li>' . $inline(preg_replace('/^\s*\d{1,2}[.)]\s+/', '', $l) ?? '') . "</li>\n";
            }
            $out .= "</ol>\n";
            continue;
        }
        $out .= '<p>' . $inline($b) . "</p>\n";
    }
    return $out;
}
function minutos_leitura(string $txt): int
{
    $palavras = count(preg_split('/\s+/u', trim($txt)) ?: []);
    return max(1, (int) round($palavras / 200));
}

/* ---------- agenda: horários de atendimento configuráveis ---------- */
const JC_AGENDA_DURACOES = [15, 20, 30, 45, 60, 90];
const JC_AGENDA_ANTECEDENCIAS = [1, 2, 3, 5, 7];
const JC_AGENDA_JANELAS = [14, 30, 60, 90];

/** Padrão de fábrica: seg a sex, manhã 9h–11h30 e tarde 14h–16h30; sábado e domingo desligados. */
function agenda_config_padrao(): array
{
    $util = ['manha' => ['ativo' => true, 'de' => '09:00', 'ate' => '11:30'], 'tarde' => ['ativo' => true, 'de' => '14:00', 'ate' => '16:30']];
    $off = ['manha' => ['ativo' => false, 'de' => '09:00', 'ate' => '11:00'], 'tarde' => ['ativo' => false, 'de' => '14:00', 'ate' => '16:00']];
    return [
        'duracao' => 30,
        'antecedencia' => 1,
        'janela' => 30,
        'dias' => [0 => $off, 1 => $util, 2 => $util, 3 => $util, 4 => $util, 5 => $util, 6 => $off],
    ];
}

function hm_min(string $hm): int
{
    return (int) substr($hm, 0, 2) * 60 + (int) substr($hm, 3, 2);
}

function min_hm(int $m): string
{
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/** Horários de início possíveis de uma janela (ex.: 09:00–11:30 com 30 min → 09:00, 09:30, 10:00, 10:30, 11:00). */
function agenda_slots_janela(string $de, string $ate, int $dur): array
{
    $out = [];
    for ($t = hm_min($de); $t + $dur <= hm_min($ate); $t += $dur) {
        $out[] = min_hm($t);
    }
    return $out;
}

/**
 * Valida e normaliza a configuração enviada pelo painel.
 * @return array|string configuração limpa, ou mensagem de erro
 */
function agenda_config_validar(array $in)
{
    $cfg = agenda_config_padrao();
    $dur = (int) ($in['duracao'] ?? 0);
    $ant = (int) ($in['antecedencia'] ?? 0);
    $jan = (int) ($in['janela'] ?? 0);
    if (!in_array($dur, JC_AGENDA_DURACOES, true)) {
        return 'Escolha a duração de cada conversa.';
    }
    if (!in_array($ant, JC_AGENDA_ANTECEDENCIAS, true) || !in_array($jan, JC_AGENDA_JANELAS, true)) {
        return 'Antecedência ou período de exibição inválido.';
    }
    $cfg['duracao'] = $dur;
    $cfg['antecedencia'] = $ant;
    $cfg['janela'] = $jan;
    $nomesDia = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    foreach ($cfg['dias'] as $d => $_) {
        $linha = $in['dias'][$d] ?? $in['dias'][(string) $d] ?? null;
        if (!is_array($linha)) {
            return 'Horários incompletos (' . $nomesDia[$d] . ').';
        }
        foreach (['manha', 'tarde'] as $per) {
            $j = $linha[$per] ?? null;
            if (!is_array($j)) {
                return 'Horários incompletos (' . $nomesDia[$d] . ').';
            }
            $ativo = !empty($j['ativo']);
            $de = (string) ($j['de'] ?? '');
            $ate = (string) ($j['ate'] ?? '');
            $rotulo = ($per === 'manha' ? 'manhã' : 'tarde') . ' de ' . $nomesDia[$d];
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $de) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $ate)) {
                return 'Horário inválido na ' . $rotulo . '.';
            }
            if ($ativo) {
                if (hm_min($ate) <= hm_min($de)) {
                    return 'Na ' . $rotulo . ', o horário final precisa ser depois do inicial.';
                }
                if (hm_min($ate) - hm_min($de) < $dur) {
                    return 'Na ' . $rotulo . ' cabe menos de uma conversa de ' . $dur . ' minutos.';
                }
                if (count(agenda_slots_janela($de, $ate, $dur)) > 40) {
                    return 'Na ' . $rotulo . ' há horários demais. Reduza o período.';
                }
            }
            $cfg['dias'][$d][$per] = ['ativo' => $ativo, 'de' => $de, 'ate' => $ate];
        }
        $m = $cfg['dias'][$d]['manha'];
        $t = $cfg['dias'][$d]['tarde'];
        if ($m['ativo'] && $t['ativo'] && hm_min($m['ate']) > hm_min($t['de'])) {
            return 'Na ' . $nomesDia[$d] . ', a manhã termina depois do início da tarde.';
        }
    }
    return $cfg;
}

/** Configuração em uso (a salva no painel, completada com o padrão). */
function agenda_config(): array
{
    $json = settings()['agenda_config'] ?? '';
    $salvo = $json !== '' ? json_decode($json, true) : null;
    if (is_array($salvo)) {
        $v = agenda_config_validar($salvo);
        if (is_array($v)) {
            return $v;
        }
    }
    return agenda_config_padrao();
}

function agenda_horarios_do_dia(string $data): array
{
    $c = agenda_config();
    $d = $c['dias'][(int) date('w', strtotime($data))];
    $h = [];
    foreach (['manha', 'tarde'] as $per) {
        if ($d[$per]['ativo']) {
            $h = array_merge($h, agenda_slots_janela($d[$per]['de'], $d[$per]['ate'], $c['duracao']));
        }
    }
    $h = array_values(array_unique($h));
    sort($h);
    return $h;
}

/** Primeiro e último dia oferecidos, conforme antecedência e período configurados. */
function agenda_intervalo(): array
{
    $c = agenda_config();
    $ini = strtotime('today +' . $c['antecedencia'] . ' days');
    return [$ini, strtotime('+' . $c['janela'] . ' days', $ini)];
}

/** Próximos dias com horários livres/ocupados para o site. */
function agenda_dias(int $quantos = 5): array
{
    $dias = [];
    [$t, $fim] = agenda_intervalo();
    $ini = date('Y-m-d', $t);
    $ate = date('Y-m-d', $fim);

    $ocupado = [];
    foreach (db_all("SELECT data, DATE_FORMAT(hora, '%H:%i') h FROM agendamentos WHERE ativo = 1 AND data BETWEEN ? AND ?", [$ini, $ate]) as $r) {
        $ocupado[$r['data']][$r['h']] = true;
    }
    $bloq = [];
    foreach (db_all("SELECT data, DATE_FORMAT(hora, '%H:%i') h FROM bloqueios WHERE data BETWEEN ? AND ?", [$ini, $ate]) as $r) {
        $bloq[$r['data']][$r['h'] ?? '*'] = true;
    }

    for (; $t <= $fim && count($dias) < $quantos; $t = strtotime('+1 day', $t)) {
        $d = date('Y-m-d', $t);
        if (isset($bloq[$d]['*'])) {
            continue;
        }
        $hs = agenda_horarios_do_dia($d);
        if (!$hs) {
            continue;
        }
        $slots = [];
        foreach ($hs as $h) {
            $slots[] = ['h' => $h, 'livre' => !isset($ocupado[$d][$h]) && !isset($bloq[$d][$h])];
        }
        $dias[] = [
            'data' => $d,
            'dia' => JC_DIAS[(int) date('w', $t)],
            'rotulo' => date('d/m', $t),
            'slots' => $slots,
        ];
    }
    return $dias;
}

/** O horário pedido existe na agenda, está dentro do período oferecido e continua livre? */
function agenda_horario_valido(string $data, string $hora): bool
{
    [$ini, $fim] = agenda_intervalo();
    $ts = strtotime($data);
    if ($ts === false || $ts < $ini || $ts > $fim || !in_array($hora, agenda_horarios_do_dia($data), true)) {
        return false;
    }
    if (db_val("SELECT id FROM bloqueios WHERE data = ? AND (hora IS NULL OR hora = ?) LIMIT 1", [$data, $hora . ':00'])) {
        return false;
    }
    return !db_val('SELECT id FROM agendamentos WHERE ativo = 1 AND data = ? AND hora = ? LIMIT 1', [$data, $hora . ':00']);
}

/* ---------- diagnóstico: perguntas (fonte única para site e painel) ---------- */
const JC_DIAG = [
    ['Seguro', 'O seguro do prédio contra incêndio está contratado e em dia?', 'Contratar ou renovar a apólice. O seguro da edificação contra incêndio ou destruição é obrigatório (Código Civil, art. 1.346).'],
    ['Segurança', 'O certificado do Corpo de Bombeiros (CBMCE) está válido?', 'Levantar as exigências do Corpo de Bombeiros e programar a renovação do certificado.'],
    ['Segurança', 'Extintores e mangueiras foram inspecionados no último ano?', 'Agendar recarga e inspeção com empresa credenciada.'],
    ['Manutenção', "A caixa d'água foi limpa nos últimos 6 meses?", 'Programar a limpeza semestral, com laudo da empresa.'],
    ['Manutenção', 'Os elevadores têm manutenção mensal por empresa habilitada?', 'Revisar o contrato de manutenção e o registro das visitas.'],
    ['Manutenção', 'O para-raios (SPDA) foi inspecionado e tem laudo válido?', 'Solicitar a inspeção e o laudo técnico do para-raios.'],
    ['Manutenção', 'Existe um plano de manutenção preventiva por escrito?', 'Montar o cronograma anual de manutenção preventiva (NBR 5674).'],
    ['Gestão', 'A assembleia ordinária deste ano já aconteceu?', 'Convocar a assembleia ordinária para aprovar contas e orçamento.'],
    ['Finanças', 'Os moradores recebem a prestação de contas todo mês?', 'Implantar um relatório mensal de receitas, despesas e saldo.'],
    ['Finanças', 'O fundo de reserva tem saldo e regra de uso definida?', 'Definir em assembleia a regra de uso e a recomposição do fundo.'],
    ['Contratos', 'Os contratos de fornecedores foram revisados nos últimos 12 meses?', 'Revisar valores, prazos e multas dos contratos de fornecedores.'],
];
