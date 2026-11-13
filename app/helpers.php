<?php
defined('JC') || exit;

/* ---------- saída HTML ---------- */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Caminho relativo à raiz do site (permite publicar numa subpasta via base_path). */
function url(string $path = ''): string
{
    $base = rtrim((string) cfg('base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** URL absoluta (canonical, sitemap, Open Graph). */
function abs_url(string $path = ''): string
{
    return rtrim((string) cfg('site_url', ''), '/') . url($path);
}

/** Arquivo estático com versão pela data de modificação (cache longo + atualização garantida). */
function asset(string $path): string
{
    $file = JC_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $v;
}

/* ---------- JSON ---------- */
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail(string $msg, int $status = 400, array $extra = []): void
{
    json_out(['ok' => false, 'erro' => $msg] + $extra, $status);
}

/** Lê o corpo JSON (ou o formulário) da requisição. */
function input(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $raw = file_get_contents('php://input', false, null, 0, 65536);
        $j = json_decode((string) $raw, true);
        $data = is_array($j) ? $j : [];
    } else {
        $data = $_POST;
    }
    return $data;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        json_fail('Método não permitido.', 405);
    }
}

/* ---------- validação ---------- */
/** Texto de uma linha: remove controles, comprime espaços e limita tamanho. */
function clean_line($v, int $max = 120): string
{
    $s = is_scalar($v) ? (string) $v : '';
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    return mb_substr($s, 0, $max);
}

/** Texto de várias linhas: mantém quebras, remove outros controles. */
function clean_text($v, int $max = 2000): string
{
    $s = is_scalar($v) ? (string) $v : '';
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $s) ?? '';
    $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

/** Telefone brasileiro: só dígitos, 10 ou 11 com DDD (aceita +55). */
function clean_phone($v): ?string
{
    $d = preg_replace('/\D+/', '', is_scalar($v) ? (string) $v : '') ?? '';
    if (strlen($d) > 11 && str_starts_with($d, '55')) {
        $d = substr($d, 2);
    }
    return (strlen($d) === 10 || strlen($d) === 11) ? $d : null;
}

function fmt_phone(string $d): string
{
    if (strlen($d) === 11) {
        return sprintf('(%s) %s %s-%s', substr($d, 0, 2), substr($d, 2, 1), substr($d, 3, 4), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return $d;
}

function one_of($v, array $allowed, $default)
{
    return in_array($v, $allowed, true) ? $v : $default;
}

function slugify(string $s): string
{
    $s = mb_strtolower($s);
    $map = ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'î' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n'];
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim(substr($s, 0, 90), '-');
}

/* ---------- proteção dos endpoints públicos ---------- */
function client_ip(): string
{
    // Na Hostinger o IP real chega em REMOTE_ADDR; não confiar em cabeçalhos enviados pelo cliente.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** IP guardado só como hash (LGPD: não armazenamos o IP em si). */
function ip_hash(): string
{
    return hash_hmac('sha256', client_ip(), (string) cfg('app_secret', 'jc'));
}

/** Aceita apenas requisições vindas do próprio site (Origin/Referer). */
function same_origin(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $src = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($src === '' || $host === '') {
        return false;
    }
    return strcasecmp((string) parse_url($src, PHP_URL_HOST), (string) preg_replace('/:\d+$/', '', $host)) === 0;
}

/**
 * Limite de tentativas por janela de tempo.
 * Retorna true se ainda pode; registra a tentativa quando $hit = true.
 */
function rate_chave(string $acao, ?string $extra, bool $comIp): string
{
    return substr($acao . ':' . ($comIp ? ip_hash() : '*') . ($extra !== null ? ':' . hash('sha256', $extra) : ''), 0, 190);
}

function rate_ok(string $acao, int $max, int $janelaSeg, bool $hit = true, ?string $extra = null, bool $comIp = true): bool
{
    $chave = rate_chave($acao, $extra, $comIp);
    $agora = time();
    db_q('DELETE FROM rate_limits WHERE expira_em < ?', [$agora]);
    $row = db_one('SELECT tentativas FROM rate_limits WHERE chave = ?', [$chave]);
    if ($row && (int) $row['tentativas'] >= $max) {
        return false;
    }
    if ($hit) {
        db_q(
            'INSERT INTO rate_limits (chave, tentativas, expira_em) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE tentativas = tentativas + 1',
            [$chave, $agora + $janelaSeg]
        );
    }
    return true;
}

/**
 * Conta UMA tentativa e diz se ainda está dentro do limite. Incrementa ANTES de verificar (atômico no banco),
 * então requisições paralelas não conseguem "furar" o limite como no par rate_ok + rate_ok(hit).
 */
function rate_contar(string $acao, int $max, int $janelaSeg, ?string $extra = null, bool $comIp = true): bool
{
    $chave = rate_chave($acao, $extra, $comIp);
    $agora = time();
    db_q('DELETE FROM rate_limits WHERE expira_em < ?', [$agora]);
    db_q(
        'INSERT INTO rate_limits (chave, tentativas, expira_em) VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE tentativas = tentativas + 1',
        [$chave, $agora + $janelaSeg]
    );
    return (int) db_val('SELECT tentativas FROM rate_limits WHERE chave = ?', [$chave]) <= $max;
}

function rate_clear(string $acao, ?string $extra = null, bool $comIp = true): void
{
    db_q('DELETE FROM rate_limits WHERE chave = ?', [rate_chave($acao, $extra, $comIp)]);
}

/** Barreira comum dos formulários públicos: método, origem, honeypot e limite. */
function public_guard(string $acao, int $max, int $janela): array
{
    require_method('POST');
    if (!same_origin()) {
        log_evento('origem_invalida', 'Formulário: ' . $acao);
        json_fail('Requisição inválida.', 403);
    }
    $in = input();
    // Campo invisível: robôs preenchem, pessoas não.
    if (!empty($in['site_url'])) {
        log_evento('honeypot', 'Formulário: ' . $acao);
        json_out(['ok' => true]);
    }
    if (!rate_ok($acao, $max, $janela)) {
        log_evento('limite_excedido', 'Formulário: ' . $acao);
        json_fail('Muitas tentativas seguidas. Tente de novo em alguns minutos ou chame no WhatsApp.', 429);
    }
    return $in;
}

/* ---------- e-mail ---------- */
/** Envia um e-mail de texto simples pelo remetente configurado (mail_from). Retorna false se não puder enviar. */
function email_enviar(string $para, string $assunto, string $corpo): bool
{
    $from = (string) cfg('mail_from', '');
    if (!filter_var($para, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $assunto = '=?UTF-8?B?' . base64_encode(clean_line($assunto, 150)) . '?=';
    $headers = [
        'From: JC Síndico (site) <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    return (bool) @mail($para, $assunto, $corpo, implode("
", $headers), '-f' . $from);
}

/** Aviso de novo contato para o e-mail configurado no painel. */
function notify(string $assunto, array $linhas): void
{
    $to = setting('email_contato');
    if (!$to) {
        return;
    }
    $corpo = '';
    foreach ($linhas as $k => $v) {
        $corpo .= (is_int($k) ? '' : $k . ': ') . $v . "
";
    }
    $corpo .= "
Abra o painel: " . abs_url('admin/') . "
";
    email_enviar((string) $to, $assunto, $corpo);
}

/* ---------- utilidades de data ---------- */
const JC_DIAS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
const JC_MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

function fmt_data(?string $dt, bool $hora = false): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return $t ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '';
}

function fmt_mes_ano(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    return $t ? date('m/Y', $t) : '';
}

/** Link do WhatsApp com o número das configurações. */
function wa_link(string $texto = ''): string
{
    $n = preg_replace('/\D+/', '', (string) setting('whatsapp')) ?? '';
    if ($n !== '' && !str_starts_with($n, '55')) {
        $n = '55' . $n;
    }
    return 'https://wa.me/' . $n . ($texto !== '' ? '?text=' . rawurlencode($texto) : '');
}
