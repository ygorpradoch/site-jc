<?php
defined('JC') || exit;
require_once JC_APP . '/totp.php';

/*
 * Acesso ao painel, em duas etapas:
 *   1) e-mail + senha  → sessão "pré-login" (pre_uid), ainda sem acesso a nada;
 *   2) código de 6 dígitos do aplicativo autenticador (ou um código de recuperação) → sessão completa (uid).
 * Quem ainda não configurou a 2ª etapa é obrigado a configurar antes de entrar.
 * Só existe um perfil de usuário: "master" (acesso total, pode convidar e gerenciar os outros).
 */

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
    $dir = JC_APP . '/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    $now = time();
    // Expira após 2 horas sem uso
    if (isset($_SESSION['uid'], $_SESSION['last']) && $now - (int) $_SESSION['last'] > 7200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    // A etapa "senha certa, falta o código" vale por 15 minutos
    if (isset($_SESSION['pre_uid']) && $now - (int) ($_SESSION['pre_ts'] ?? 0) > 900) {
        unset($_SESSION['pre_uid'], $_SESSION['pre_ts'], $_SESSION['totp_novo'], $_SESSION['codigos_novos']);
    }
    $_SESSION['last'] = $now;
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

/** Usuário com sessão completa (ou null). Usuário desativado perde o acesso na hora. */
function admin_user(): ?array
{
    admin_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $u = null;
    if ($u === null || (int) $u['id'] !== (int) $_SESSION['uid']) {
        $u = db_one('SELECT id, nome, email, senha_hash FROM usuarios WHERE id = ? AND ativo = 1', [(int) $_SESSION['uid']]);
        // Usuário desativado/removido, ou senha trocada/redefinida depois que esta sessão começou: sessão encerrada
        if (!$u || !hash_equals(senha_carimbo((string) $u['senha_hash']), (string) ($_SESSION['stamp'] ?? ''))) {
            unset($_SESSION['uid'], $_SESSION['stamp']);
            $u = null;
            return null;
        }
    }
    $GLOBALS['JC_LOG_USER'] = $u;
    return $u;
}

function senha_carimbo(string $senhaHash): string
{
    return substr(hash('sha256', 'jc-stamp|' . $senhaHash), 0, 32);
}

/** Depois de trocar a senha da própria sessão: mantém esta sessão e deixa as outras caírem. */
function admin_carimbo_atualizar(int $uid): void
{
    admin_session();
    $_SESSION['stamp'] = senha_carimbo((string) db_val('SELECT senha_hash FROM usuarios WHERE id = ?', [$uid]));
}

/** Usuário que acertou a senha e ainda precisa da 2ª etapa. */
function admin_pre_user(): ?array
{
    admin_session();
    if (empty($_SESSION['pre_uid'])) {
        return null;
    }
    $u = db_one('SELECT id, nome, email, totp_segredo, totp_ativo, totp_passo, codigos_recuperacao FROM usuarios WHERE id = ? AND ativo = 1', [(int) $_SESSION['pre_uid']]);
    if (!$u) {
        unset($_SESSION['pre_uid'], $_SESSION['pre_ts'], $_SESSION['totp_novo'], $_SESSION['codigos_novos']);
    }
    return $u;
}

/** Em que ponto do acesso a pessoa está: painel | 2fa | configurar | codigos | login. */
function admin_estagio(): string
{
    if (admin_user()) {
        return 'painel';
    }
    $u = admin_pre_user();
    if (!$u) {
        return 'login';
    }
    if (!empty($_SESSION['codigos_novos'])) {
        return 'codigos';
    }
    return (int) $u['totp_ativo'] === 1 ? '2fa' : 'configurar';
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

function admin_iniciar_pre(int $uid): void
{
    admin_session();
    session_regenerate_id(true);
    $_SESSION = ['last' => time(), 'csrf' => bin2hex(random_bytes(32)), 'pre_uid' => $uid, 'pre_ts' => time()];
}

/** Etapa 1: e-mail e senha. Retorna mensagem de erro ou null (segue para a 2ª etapa). */
function admin_login_senha(string $email, string $senha): ?string
{
    $email = mb_strtolower(trim($email));
    if (!rate_ok('login', 5, 900, false, $email) || !rate_ok('login-ip', 20, 900, false)) {
        log_evento('login_bloqueado', null, ['email' => $email]);
        return 'Muitas tentativas. O acesso fica bloqueado por 15 minutos.';
    }
    $u = db_one('SELECT id, nome, senha_hash, ativo FROM usuarios WHERE email = ?', [$email]);
    // Verifica mesmo sem usuário, para não revelar quais e-mails existem
    $hash = $u['senha_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $senhaOk = password_verify($senha, $hash);
    $motivo = null;
    if (!$u) {
        $motivo = 'e-mail não cadastrado';
    } elseif (!$senhaOk) {
        $motivo = 'senha incorreta';
    } elseif ((int) $u['ativo'] !== 1) {
        $motivo = 'usuário desativado';
    }
    if ($motivo !== null) {
        rate_ok('login', 5, 900, true, $email);
        rate_ok('login-ip', 20, 900, true);
        log_evento('login_falha', $motivo, ['email' => $email, 'usuario' => $u ? ['id' => $u['id'], 'nome' => $u['nome']] : null]);
        return 'E-mail ou senha incorretos.';
    }
    rate_clear('login', $email);
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db_q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    }
    admin_iniciar_pre((int) $u['id']);
    return null;
}

/**
 * Libera o painel. Só funciona se a 2ª etapa foi PROVADA nesta sessão ($_SESSION['fator2'] é gravado apenas por
 * admin_2fa_conferir e admin_enroll_confirmar). Ter acertado a senha não basta.
 */
function admin_concluir(array $u): bool
{
    admin_session();
    if (($_SESSION['fator2'] ?? 0) !== (int) $u['id']) {
        return false;
    }
    $stamp = senha_carimbo((string) db_val('SELECT senha_hash FROM usuarios WHERE id = ?', [$u['id']]));
    session_regenerate_id(true);
    $_SESSION = ['last' => time(), 'csrf' => bin2hex(random_bytes(32)), 'uid' => (int) $u['id'], 'stamp' => $stamp];
    db_q('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$u['id']]);
    $GLOBALS['JC_LOG_USER'] = ['id' => $u['id'], 'nome' => $u['nome'], 'email' => $u['email']];
    log_evento('login_ok');
    return true;
}

function admin_pre_limpar(): void
{
    unset($_SESSION['pre_uid'], $_SESSION['pre_ts'], $_SESSION['totp_novo'], $_SESSION['codigos_novos'], $_SESSION['fator2']);
}

/**
 * Limite do código de 6 dígitos: cada tentativa é CONTADA antes de verificar (5 por IP+usuário em 10 min e
 * 10 no total por usuário em 15 min). Contar antes impede que várias requisições paralelas furem o limite.
 */
function totp_limite_ok(int $uid): bool
{
    $a = rate_contar('2fa', 5, 600, (string) $uid);
    $b = rate_contar('2fa-user', 10, 900, (string) $uid, false);
    return $a && $b;
}

/** A tentativa já foi contada em totp_limite_ok; mantido para compatibilidade. */
function totp_limite_erro(int $uid): void
{
}

function totp_limite_limpar(int $uid): void
{
    rate_clear('2fa', (string) $uid);
    rate_clear('2fa-user', (string) $uid, false);
}

/** Confere o código TOTP de um usuário já cadastrado (usa o código uma única vez). */
function usuario_totp_ok(array $u, string $codigo): bool
{
    $seg = segredo_decifrar($u['totp_segredo'] ?? null);
    if (!$seg) {
        return false;
    }
    $p = totp_verificar($seg, $codigo, isset($u['totp_passo']) ? (int) $u['totp_passo'] : null);
    if ($p === null) {
        return false;
    }
    // Atômico: se dois pedidos usarem o mesmo código ao mesmo tempo, só um passa
    return db_q('UPDATE usuarios SET totp_passo = ? WHERE id = ? AND (totp_passo IS NULL OR totp_passo < ?)', [$p, $u['id'], $p])->rowCount() === 1;
}

/** Etapa 2: código do aplicativo (6 dígitos) ou código de recuperação. Retorna erro ou null. */
function admin_2fa_conferir(string $codigo): ?string
{
    $u = admin_pre_user();
    if (!$u || (int) $u['totp_ativo'] !== 1) {
        return 'Sessão expirada. Entre novamente.';
    }
    $ctx = ['usuario' => ['id' => $u['id'], 'nome' => $u['nome'], 'email' => $u['email']]];
    if (!totp_limite_ok((int) $u['id'])) {
        log_evento('2fa_bloqueado', null, $ctx);
        admin_pre_limpar();
        return 'Muitas tentativas. Aguarde alguns minutos e entre de novo.';
    }
    $solo = preg_replace('/\s+/', '', trim($codigo));
    if (preg_match('/^\d{6}$/', (string) $solo)) {
        if (usuario_totp_ok($u, $solo)) {
            totp_limite_limpar((int) $u['id']);
            $_SESSION['fator2'] = (int) $u['id'];
            return admin_concluir($u) ? null : 'Sessão expirada. Entre novamente.';
        }
    } else {
        $n = codigo_recuperacao_normalizar($codigo);
        if (strlen($n) === 10) {
            $lista = json_decode((string) ($u['codigos_recuperacao'] ?? '[]'), true) ?: [];
            $i = array_search(codigo_recuperacao_hash($n), $lista, true);
            if ($i !== false) {
                unset($lista[$i]);
                // Atômico: o mesmo código de recuperação não vale duas vezes
                $st = db_q('UPDATE usuarios SET codigos_recuperacao = ? WHERE id = ? AND codigos_recuperacao = ?', [json_encode(array_values($lista)), $u['id'], $u['codigos_recuperacao']]);
                if ($st->rowCount() === 1) {
                    totp_limite_limpar((int) $u['id']);
                    $_SESSION['fator2'] = (int) $u['id'];
                    if (!admin_concluir($u)) {
                        return 'Sessão expirada. Entre novamente.';
                    }
                    log_evento('codigo_recuperacao_usado', 'Restam ' . count($lista) . ' códigos');
                    return null;
                }
            }
        }
    }
    log_evento('2fa_falha', null, $ctx);
    return 'Código incorreto.';
}

/** Segredo novo para configurar a 2ª etapa (fica só na sessão até a pessoa provar que o aplicativo funciona). */
function admin_enroll_segredo(): string
{
    admin_session();
    if (empty($_SESSION['totp_novo'])) {
        $_SESSION['totp_novo'] = totp_novo_segredo();
    }
    return (string) $_SESSION['totp_novo'];
}

/** Confirma o primeiro código, grava o segredo cifrado e gera os códigos de recuperação. */
function admin_enroll_confirmar(string $codigo): ?string
{
    $u = admin_pre_user();
    $seg = $_SESSION['totp_novo'] ?? null;
    if (!$u || (int) $u['totp_ativo'] === 1 || !$seg) {
        return 'Sessão expirada. Entre novamente.';
    }
    $ctx = ['usuario' => ['id' => $u['id'], 'nome' => $u['nome'], 'email' => $u['email']]];
    if (!totp_limite_ok((int) $u['id'])) {
        log_evento('2fa_bloqueado', 'durante a configuração', $ctx);
        admin_pre_limpar();
        return 'Muitas tentativas. Aguarde alguns minutos e entre de novo.';
    }
    $p = totp_verificar($seg, $codigo);
    if ($p === null) {
        log_evento('2fa_falha', 'durante a configuração', $ctx);
        return 'Código incorreto. Digite o código que está aparecendo agora no aplicativo.';
    }
    $codigos = codigos_recuperacao_gerar();
    // Só grava se a conta ainda não tem 2ª etapa (duas abas configurando ao mesmo tempo não se sobrescrevem)
    $st = db_q(
        'UPDATE usuarios SET totp_segredo = ?, totp_ativo = 1, totp_passo = ?, codigos_recuperacao = ? WHERE id = ? AND totp_ativo = 0',
        [segredo_cifrar($seg), $p, json_encode(array_map('codigo_recuperacao_hash', $codigos)), $u['id']]
    );
    if ($st->rowCount() !== 1) {
        admin_pre_limpar();
        return 'Esta conta já foi configurada em outra janela. Entre novamente.';
    }
    totp_limite_limpar((int) $u['id']);
    unset($_SESSION['totp_novo']);
    $_SESSION['fator2'] = (int) $u['id'];
    $_SESSION['codigos_novos'] = $codigos;
    log_evento('2fa_configurado', null, $ctx);
    return null;
}

/** A pessoa confirmou que guardou os códigos de recuperação: entra no painel. */
function admin_codigos_guardados(): void
{
    $u = admin_pre_user();
    if ($u && !empty($_SESSION['codigos_novos']) && (int) $u['totp_ativo'] === 1 && ($_SESSION['fator2'] ?? 0) === (int) $u['id']) {
        unset($_SESSION['codigos_novos']);
        admin_concluir($u);
    }
}

/* ---------- convites (novo usuário ou redefinição de senha) ---------- */
function convite_criar(int $uid): string
{
    $token = bin2hex(random_bytes(24));
    db_q('UPDATE usuarios SET convite_hash = ?, convite_expira = NOW() + INTERVAL 48 HOUR WHERE id = ?', [hash('sha256', $token), $uid]);
    return $token;
}

function convite_buscar(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    return db_one('SELECT id, nome, email, totp_ativo FROM usuarios WHERE convite_hash = ? AND convite_expira > NOW() AND ativo = 1', [hash('sha256', $token)]);
}

/** Aceita o convite: define nome e senha; em seguida a pessoa passa pela 2ª etapa. */
function convite_aceitar(array $u, string $nome, string $senha): void
{
    db_q('UPDATE usuarios SET nome = ?, senha_hash = ?, convite_hash = NULL, convite_expira = NULL WHERE id = ?', [$nome, password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    $GLOBALS['JC_LOG_USER'] = ['id' => $u['id'], 'nome' => $nome, 'email' => $u['email']];
    log_evento('convite_aceito');
    admin_iniciar_pre((int) $u['id']);
}

/** Torna a senha atual inutilizável (usado ao redefinir o 2FA de outra pessoa: ela precisa passar por um novo convite). */
function senha_invalidar(int $uid): void
{
    db_q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $uid]);
}

function admin_logout(): void
{
    admin_session();
    if (admin_user()) {
        log_evento('logout');
    }
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
