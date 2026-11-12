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
    'agenda_manha' => '1',
    'agenda_tarde' => '1',
    'agenda_sabado' => '0',
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
function artigos_publicados(?int $limite = null): array
{
    $sql = "SELECT id, slug, titulo, categoria, minutos, resumo, publicado_em, atualizado_em
            FROM artigos WHERE status = 'publicado' ORDER BY publicado_em DESC, id DESC";
    if ($limite) {
        $sql .= ' LIMIT ' . (int) $limite;
    }
    return db_all($sql);
}

/**
 * Converte o texto do artigo (formato simples) em HTML seguro.
 *   ## Título      → subtítulo
 *   - item         → lista
 *   **negrito**    → negrito
 *   linha em branco separa parágrafos
 */
function artigo_html(string $txt): string
{
    $blocos = preg_split("/\n\s*\n/", str_replace("\r", '', trim($txt))) ?: [];
    $out = '';
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s) ?? $s;
        return nl2br($s, false);
    };
    foreach ($blocos as $b) {
        $b = trim($b);
        if ($b === '') {
            continue;
        }
        if (str_starts_with($b, '## ')) {
            $out .= '<h2>' . $inline(substr($b, 3)) . "</h2>\n";
            continue;
        }
        $linhas = explode("\n", $b);
        if (count(array_filter($linhas, fn($l) => preg_match('/^\s*[-•]\s+/', $l))) === count($linhas)) {
            $out .= "<ul>\n";
            foreach ($linhas as $l) {
                $out .= '<li>' . $inline(preg_replace('/^\s*[-•]\s+/', '', $l) ?? '') . "</li>\n";
            }
            $out .= "</ul>\n";
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

/* ---------- agenda pública ---------- */
function agenda_horarios_do_dia(string $data): array
{
    $dow = (int) date('w', strtotime($data));
    $s = settings();
    $h = [];
    if ($dow === 0) {
        return [];
    }
    if ($dow === 6) {
        return $s['agenda_sabado'] === '1' ? ['09:00', '09:30', '10:00', '10:30'] : [];
    }
    if ($s['agenda_manha'] === '1') {
        array_push($h, '09:00', '09:30', '10:00', '10:30', '11:00');
    }
    if ($s['agenda_tarde'] === '1') {
        array_push($h, '14:00', '14:30', '15:00', '15:30', '16:00');
    }
    return $h;
}

/** Próximos dias com horários (a partir de amanhã). */
function agenda_dias(int $quantos = 5): array
{
    $dias = [];
    $t = strtotime('tomorrow');
    $fim = strtotime('+30 days', $t);
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

function agenda_horario_valido(string $data, string $hora): bool
{
    foreach (agenda_dias(10) as $d) {
        if ($d['data'] === $data) {
            foreach ($d['slots'] as $s) {
                if ($s['h'] === $hora) {
                    return $s['livre'];
                }
            }
        }
    }
    return false;
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
