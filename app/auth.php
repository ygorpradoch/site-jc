<?php
defined('JC') || exit;

/** Sessão do painel: cookie seguro, só HTTP, mesmo site, restrito a /admin. */
function admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('jc_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => url('admin/'),
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    // Expira após 2 horas sem uso
    $now = time();
    if (isset($_SESSION['uid'], $_SESSION['last']) && $now - (int) $_SESSION['last'] > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last'] = $now;
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function admin_user(): ?array
{
    admin_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $u = null;
    if ($u === null) {
        $u = db_one('SELECT id, nome, email FROM usuarios WHERE id = ?', [(int) $_SESSION['uid']]);
    }
    return $u;
}

function csrf_token(): string
{
    admin_session();
    return (string) $_SESSION['csrf'];
}

function csrf_ok(?string $t): bool
{
    admin_session();
    return is_string($t) && $t !== '' && hash_equals((string) $_SESSION['csrf'], $t);
}

/** Tenta entrar. Retorna mensagem de erro ou null em caso de sucesso. */
function admin_login(string $email, string $senha): ?string
{
    $email = mb_strtolower(trim($email));
    if (!rate_ok('login', 5, 900, false, $email) || !rate_ok('login-ip', 20, 900, false)) {
        return 'Muitas tentativas. O acesso fica bloqueado por 15 minutos.';
    }
    $u = db_one('SELECT id, senha_hash FROM usuarios WHERE email = ?', [$email]);
    // Verifica mesmo sem usuário para não revelar quais e-mails existem
    $hash = $u['senha_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    if (!password_verify($senha, $hash) || !$u) {
        rate_ok('login', 5, 900, true, $email);
        rate_ok('login-ip', 20, 900, true);
        return 'E-mail ou senha incorretos.';
    }
    rate_clear('login', $email);
    admin_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db_q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    }
    db_q('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$u['id']]);
    return null;
}

function admin_logout(): void
{
    admin_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

function senha_forte(string $s): ?string
{
    if (mb_strlen($s) < 10) {
        return 'A senha precisa ter pelo menos 10 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $s) || !preg_match('/\d/', $s)) {
        return 'Use letras e números na senha.';
    }
    return null;
}
