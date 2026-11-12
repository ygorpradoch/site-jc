<?php
/**
 * Painel do site.
 *  - Primeiro acesso (nenhum usuário): cria o administrador com o setup_token da configuração.
 *  - Sem sessão: formulário de login (5 tentativas erradas = bloqueio de 15 min).
 *  - Com sessão: casca do painel; os dados vêm de admin/api.php.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require JC_APP . '/auth.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
admin_session();

$erro = null;
$temUsuario = (int) db_val('SELECT COUNT(*) FROM usuarios') > 0;
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $erro = 'A página expirou. Tente de novo.';
    } elseif (($_POST['acao'] ?? '') === 'sair') {
        admin_logout();
        header('Location: ' . url('admin/'));
        exit;
    } elseif (!$temUsuario && ($_POST['acao'] ?? '') === 'criar') {
        $token = (string) cfg('setup_token', '');
        $nome = clean_line($_POST['nome'] ?? '', 80);
        $email = mb_strtolower(clean_line($_POST['email'] ?? '', 160));
        $senha = (string) ($_POST['senha'] ?? '');
        if (!rate_ok('setup', 5, 900)) {
            $erro = 'Muitas tentativas. Aguarde 15 minutos.';
        } elseif ($token === '' || str_starts_with($token, 'TROQUE') || !hash_equals($token, (string) ($_POST['token'] ?? ''))) {
            $erro = 'Código de instalação incorreto.';
        } elseif (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe nome e um e-mail válido.';
        } elseif (($e = senha_forte($senha)) !== null) {
            $erro = $e;
        } elseif ($senha !== ($_POST['senha2'] ?? '')) {
            $erro = 'As senhas não conferem.';
        } else {
            db_q('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)', [$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
            admin_login($email, $senha);
            header('Location: ' . url('admin/'));
            exit;
        }
    } elseif ($temUsuario && ($_POST['acao'] ?? '') === 'entrar') {
        $erro = admin_login((string) ($_POST['email'] ?? ''), (string) ($_POST['senha'] ?? ''));
        if ($erro === null) {
            header('Location: ' . url('admin/'));
            exit;
        }
    }
}

$user = admin_user();
$csrf = csrf_token();
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e($csrf) ?>">
<title>Painel · JC Síndico Profissional</title>
<link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
</head>
<body class="adm" data-api="<?= e(url('admin/api.php')) ?>" data-site="<?= e(url('')) ?>">
<header class="top admin-top scrolled" id="topbar">
  <div class="wrap">
    <a class="brand" href="<?= e(url('admin/')) ?>" aria-label="Painel do site"><img src="<?= e(url('assets/img/logo-h-branco.svg')) ?>" alt="JC Síndico Profissional" width="1148" height="641"></a>
    <?php if ($user): ?>
    <div class="admin-id"><span><?= e($user['nome']) ?> · administrador</span><a href="<?= e(url('')) ?>" target="_blank" rel="noopener">Ver site</a>
      <form method="post" class="logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="sair"><button type="submit">Sair</button></form>
    </div>
    <?php endif; ?>
  </div>
</header>

<?php if (!$user): ?>
<main class="login">
  <form method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <?php if (!$temUsuario): ?>
      <input type="hidden" name="acao" value="criar">
      <h1 class="lh">Primeiro acesso</h1>
      <p>Crie o acesso do administrador. Use o código de instalação que está no arquivo de configuração do servidor.</p>
      <?php if ($erro): ?><p class="err-msg" role="alert"><?= e($erro) ?></p><?php endif; ?>
      <div class="field"><label for="a-token">Código de instalação</label><input id="a-token" name="token" required autocomplete="off"></div>
      <div class="field"><label for="a-nome">Seu nome</label><input id="a-nome" name="nome" required autocomplete="name" value="<?= e($_POST['nome'] ?? '') ?>"></div>
      <div class="field"><label for="a-email">E-mail</label><input id="a-email" name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="field"><label for="a-pass">Senha <span>(mín. 10, letras e números)</span></label><input id="a-pass" name="senha" type="password" required autocomplete="new-password" minlength="10"></div>
      <div class="field"><label for="a-pass2">Repita a senha</label><input id="a-pass2" name="senha2" type="password" required autocomplete="new-password" minlength="10"></div>
      <button class="btn btn-ink" type="submit">Criar acesso</button>
    <?php else: ?>
      <input type="hidden" name="acao" value="entrar">
      <h1 class="lh">Painel do site</h1>
      <p>Acesso restrito. Após 5 tentativas erradas o acesso fica bloqueado por 15 minutos.</p>
      <?php if ($erro): ?><p class="err-msg" role="alert"><?= e($erro) ?></p><?php endif; ?>
      <div class="field"><label for="a-email">E-mail</label><input id="a-email" name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="field"><label for="a-pass">Senha</label><input id="a-pass" name="senha" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-ink" type="submit">Entrar</button>
    <?php endif; ?>
  </form>
</main>
<?php else: ?>
<div class="app">
  <nav class="side" aria-label="Menu do painel">
    <span class="grp">Atendimento</span>
    <button type="button" data-tab="geral" aria-current="page">Visão geral</button>
    <button type="button" data-tab="contatos">Contatos <span class="badge" id="b-novos" hidden></span></button>
    <button type="button" data-tab="agenda">Agenda <span class="badge" id="b-agenda" hidden></span></button>
    <span class="grp">Conteúdo do site</span>
    <button type="button" data-tab="condos">Condomínios</button>
    <button type="button" data-tab="depo">Depoimentos <span class="badge" id="b-depo" hidden></span></button>
    <button type="button" data-tab="artigos">Guia (artigos)</button>
    <button type="button" data-tab="config">Configurações</button>
  </nav>
  <main class="main" id="pane" aria-live="polite"><p class="loading-pane">Carregando…</p></main>
</div>
<div class="toast" id="toast" role="status" hidden></div>
<script defer src="<?= e(asset('admin/admin.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
