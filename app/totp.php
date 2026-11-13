<?php
defined('JC') || exit;

/**
 * Verificação em 2 etapas (TOTP, RFC 6238): o mesmo padrão do Google Authenticator,
 * Microsoft Authenticator, Authy etc. Sem serviço externo.
 *  - O segredo de cada usuário fica cifrado (AES-256-GCM) com chave derivada da app_secret.
 *  - Cada código só vale uma vez (guardamos o último "passo" aceito).
 *  - Códigos de recuperação: 10, de uso único, guardados só como hash.
 */

const TOTP_PERIODO = 30;

function base32_codificar(string $bin): string
{
    $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $parte) {
        $out .= $alf[bindec(str_pad($parte, 5, '0'))];
    }
    return $out;
}

function base32_decodificar(string $s): string
{
    $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) {
        $bits .= str_pad(decbin((int) strpos($alf, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_novo_segredo(): string
{
    return base32_codificar(random_bytes(20)); // 160 bits, 32 caracteres
}

function totp_codigo(string $segredoB32, int $passo): string
{
    $chave = base32_decodificar($segredoB32);
    $msg = pack('N2', $passo >> 32, $passo & 0xFFFFFFFF);
    $h = hash_hmac('sha1', $msg, $chave, true);
    $o = ord($h[19]) & 0x0F;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Confere o código de 6 dígitos (tolera 30 s de diferença de relógio para cada lado).
 * Devolve o "passo" usado ou null. Passos já usados ($ultimoPasso) são recusados.
 */
function totp_verificar(string $segredoB32, string $codigo, ?int $ultimoPasso = null): ?int
{
    $codigo = preg_replace('/\s+/', '', $codigo);
    if (!preg_match('/^\d{6}$/', (string) $codigo)) {
        return null;
    }
    $agora = intdiv(time(), TOTP_PERIODO);
    $achou = null;
    for ($d = -1; $d <= 1; $d++) {
        $p = $agora + $d;
        if ($ultimoPasso !== null && $p <= $ultimoPasso) {
            continue;
        }
        if (hash_equals(totp_codigo($segredoB32, $p), $codigo)) {
            $achou = $p;
        }
    }
    return $achou;
}

function totp_uri(string $segredoB32, string $email): string
{
    $emissor = 'JC Síndico';
    return 'otpauth://totp/' . rawurlencode($emissor . ':' . $email)
        . '?secret=' . $segredoB32 . '&issuer=' . rawurlencode($emissor) . '&algorithm=SHA1&digits=6&period=' . TOTP_PERIODO;
}

/* ---------- segredo cifrado no banco ---------- */
function segredo_chave(): string
{
    $s = (string) cfg('app_secret', '');
    if (strlen($s) < 32 || str_starts_with($s, 'TROQUE')) {
        throw new RuntimeException('app_secret precisa ter pelo menos 32 caracteres aleatórios (veja config.example.php).');
    }
    return hash('sha256', 'jc-totp|' . $s, true);
}

function segredo_cifrar(string $segredo): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($segredo, 'aes-256-gcm', segredo_chave(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) {
        throw new RuntimeException('Falha ao cifrar o segredo da verificação em 2 etapas.');
    }
    return base64_encode($iv . $tag . $ct);
}

function segredo_decifrar(?string $guardado): ?string
{
    if (!$guardado) {
        return null;
    }
    $raw = base64_decode($guardado, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $r = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', segredo_chave(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $r === false ? null : $r;
}

/* ---------- códigos de recuperação ---------- */
function codigos_recuperacao_gerar(int $n = 10): array
{
    $alf = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem I, O, 0, 1
    $lista = [];
    while (count($lista) < $n) {
        $c = '';
        for ($i = 0; $i < 10; $i++) {
            $c .= $alf[random_int(0, strlen($alf) - 1)];
        }
        $lista[$c] = substr($c, 0, 5) . '-' . substr($c, 5);
    }
    return array_values($lista);
}

function codigo_recuperacao_normalizar(string $c): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c));
}

function codigo_recuperacao_hash(string $c): string
{
    return hash_hmac('sha256', codigo_recuperacao_normalizar($c), (string) cfg('app_secret', ''));
}

/** Guarda os hashes dos códigos novos (substitui os antigos). */
function codigos_recuperacao_guardar(int $uid, array $codigos): void
{
    db_q('UPDATE usuarios SET codigos_recuperacao = ? WHERE id = ?', [json_encode(array_map('codigo_recuperacao_hash', $codigos)), $uid]);
}

function codigos_recuperacao_restantes(?string $json): int
{
    $l = $json ? json_decode($json, true) : [];
    return is_array($l) ? count($l) : 0;
}
