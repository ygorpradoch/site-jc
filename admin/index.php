<?php
/**
 * Painel do site.
 * Telas (nesta ordem de acesso): primeiro acesso → login (senha) → código de 2 etapas
 * (ou configuração da 2ª etapa + códigos de recuperação) → painel.
 * Também recebe o link de convite (?convite=...) para criar a senha de um novo usuário.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require JC_APP . '/auth.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
admin_session();

$erro = null;
$aviso = null;
$temUsuario = (int) db_val('SELECT COUNT(*) FROM usuarios') > 0;
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$acao = (string) ($_POST['acao'] ?? '');
$tokenConvite = (string) ($_GET['convite'] ?? ($_POST['convite'] ?? ''));
$convite = null;

if ($tokenConvite !== '' && !admin_user()) {
    $convite = convite_buscar($tokenConvite);
    if (!$convite && !rate_ok('convite', 10, 900)) {
        $erro = 'Muitas tentativas. Aguarde 15 minutos.';
    } elseif (!$convite) {
        log_evento('convite_invalido');
        $erro = 'Este link de convite é inválido ou já expirou. Peça um novo para quem convidou você.';
    }
}

if ($metodo === 'POST') {
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $erro = 'A página expirou. Tente de novo.';
        log_evento('csrf_invalido', 'Tela de login');
    } elseif ($acao === 'sair') {
        admin_logout();
        header('Location: ' . url('admin/'));
        exit;
    } elseif ($acao === 'cancelar') {
        admin_pre_limpar();
        header('Location: ' . url('admin/'));
        exit;
    } elseif (!$temUsuario && $acao === 'criar') {
        $token = (string) cfg('setup_token', '');
        $nome = clean_line($_POST['nome'] ?? '', 80);
        $email = mb_strtolower(clean_line($_POST['email'] ?? '', 160));
        $senha = (string) ($_POST['senha'] ?? '');
        if (!rate_ok('setup', 5, 900)) {
            $erro = 'Muitas tentativas. Aguarde 15 minutos.';
        } elseif ($token === '' || str_starts_with($token, 'TROQUE') || !hash_equals($token, (string) ($_POST['token'] ?? ''))) {
            log_evento('setup_falha');
            $erro = 'Código de instalação incorreto.';
        } elseif (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe nome e um e-mail válido.';
        } elseif (($e = senha_forte($senha)) !== null) {
            $erro = $e;
        } elseif ($senha !== ($_POST['senha2'] ?? '')) {
            $erro = 'As senhas não conferem.';
        } else {
            db_q('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)', [$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
            $novoId = (int) db()->lastInsertId();
            log_evento('primeiro_acesso', null, ['usuario' => ['id' => $novoId, 'nome' => $nome, 'email' => $email]]);
            admin_iniciar_pre($novoId);
            header('Location: ' . url('admin/'));
            exit;
        }
    } elseif ($temUsuario && $acao === 'entrar') {
        $erro = admin_login_senha((string) ($_POST['email'] ?? ''), (string) ($_POST['senha'] ?? ''));
        if ($erro === null) {
            header('Location: ' . url('admin/'));
            exit;
        }
    } elseif ($acao === '2fa') {
        $erro = admin_2fa_conferir((string) ($_POST['codigo'] ?? ''));
        if ($erro === null) {
            header('Location: ' . url('admin/'));
            exit;
        }
    } elseif ($acao === 'configurar') {
        $erro = admin_enroll_confirmar((string) ($_POST['codigo'] ?? ''));
        if ($erro === null) {
            header('Location: ' . url('admin/'));
            exit;
        }
    } elseif ($acao === 'codigos_ok') {
        admin_codigos_guardados();
        header('Location: ' . url('admin/'));
        exit;
    } elseif ($acao === 'convite' && $convite) {
        $nome = clean_line($_POST['nome'] ?? '', 80);
        $senha = (string) ($_POST['senha'] ?? '');
        if (mb_strlen($nome) < 2) {
            $erro = 'Informe seu nome.';
        } elseif (($e = senha_forte($senha)) !== null) {
            $erro = $e;
        } elseif ($senha !== ($_POST['senha2'] ?? '')) {
            $erro = 'As senhas não conferem.';
        } else {
            convite_aceitar($convite, $nome, $senha);
            header('Location: ' . url('admin/'));
            exit;
        }
    }
}

$estagio = admin_estagio();
$user = $estagio === 'painel' ? admin_user() : null;
$pre = in_array($estagio, ['2fa', 'configurar', 'codigos'], true) ? admin_pre_user() : null;
$csrf = csrf_token();
$segredo = $estagio === 'configurar' ? admin_enroll_segredo() : '';
$codigosNovos = $estagio === 'codigos' ? (array) ($_SESSION['codigos_novos'] ?? []) : [];
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
    <div class="admin-id"><span><?= e($user['nome']) ?> · master</span><a href="<?= e(url('')) ?>" target="_blank" rel="noopener">Ver site</a>
      <form method="post" class="logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="acao" value="sair"><button type="submit">Sair</button></form>
    </div>
    <?php endif; ?>
  </div>
</header>

<?php if (!$user): ?>
<main class="login">
  <form method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <?php if ($estagio === 'codigos'): ?>
      <input type="hidden" name="acao" value="codigos_ok">
      <h1 class="lh">Guarde seus códigos de recuperação</h1>
      <p>Se você perder o celular, cada código abaixo permite <strong>uma</strong> entrada no painel. Eles <strong>não serão mostrados de novo</strong>. Anote ou salve em um lugar seguro (gerenciador de senhas, papel guardado).</p>
      <ul class="rec-codes" id="rec-codes"><?php foreach ($codigosNovos as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
      <div class="rec-acts"><button type="button" class="btn btn-line btn-sm" id="rec-copy">Copiar códigos</button><button type="button" class="btn btn-line btn-sm" id="rec-save">Baixar arquivo</button></div>
      <label class="consent"><input type="checkbox" id="rec-ok" required> <span>Guardei os códigos em um lugar seguro.</span></label>
      <button class="btn btn-ink" type="submit">Entrar no painel</button>
    <?php elseif ($estagio === 'configurar'): ?>
      <input type="hidden" name="acao" value="configurar">
      <h1 class="lh">Ative a verificação em 2 etapas</h1>
      <p>Para proteger o painel, além da senha é preciso um código que muda a cada 30 segundos, gerado no seu celular.</p>
      <?php if ($erro): ?><p class="err-msg" role="alert"><?= e($erro) ?></p><?php endif; ?>
      <ol class="steps2fa">
        <li>Instale um aplicativo autenticador (Google Authenticator, Microsoft Authenticator ou Authy).</li>
        <li>No aplicativo, adicione uma conta e <strong>leia este QR code</strong>:
          <div class="qr" id="qr" data-uri="<?= e(totp_uri($segredo, $pre['email'])) ?>" role="img" aria-label="QR code para o aplicativo autenticador"></div>
          <small>Não consegue ler? Digite esta chave no aplicativo: <code class="chave"><?= e(trim(chunk_split($segredo, 4, ' '))) ?></code></small></li>
        <li>Digite abaixo o código de 6 dígitos que o aplicativo mostrar.</li>
      </ol>
      <div class="field"><label for="a-cod">Código de 6 dígitos</label><input id="a-cod" name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus></div>
      <button class="btn btn-ink" type="submit">Ativar e continuar</button>
      <button class="link-btn" type="submit" name="acao" value="cancelar" formnovalidate>Cancelar</button>
    <?php elseif ($estagio === '2fa'): ?>
      <input type="hidden" name="acao" value="2fa">
      <h1 class="lh">Verificação em 2 etapas</h1>
      <p>Olá, <?= e($pre['nome']) ?>. Digite o código de 6 dígitos do seu aplicativo autenticador.</p>
      <?php if ($erro): ?><p class="err-msg" role="alert"><?= e($erro) ?></p><?php endif; ?>
      <div class="field"><label for="a-cod">Código do aplicativo</label><input id="a-cod" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="14" required autofocus></div>
      <button class="btn btn-ink" type="submit">Entrar</button>
      <button class="link-btn" type="submit" name="acao" value="cancelar" formnovalidate>Voltar</button>
      <p class="hint">Sem o celular? Digite um dos seus <strong>códigos de recuperação</strong> (formato XXXXX-XXXXX) no mesmo campo.</p>
    <?php elseif ($convite): ?>
      <input type="hidden" name="acao" value="convite">
      <input type="hidden" name="convite" value="<?= e($tokenConvite) ?>">
      <h1 class="lh">Criar seu acesso</h1>
      <p>Você foi convidado para o painel do site. Escolha uma senha; em seguida você ativa a verificação em 2 etapas.</p>
      <?php if ($erro): ?><p class="err-msg" role="alert"><?= e($erro) ?></p><?php endif; ?>
      <div class="field"><label for="a-email">E-mail</label><input id="a-email" value="<?= e($convite['email']) ?>" disabled></div>
      <div class="field"><label for="a-nome">Seu nome</label><input id="a-nome" name="nome" required autocomplete="name" value="<?= e($_POST['nome'] ?? $convite['nome']) ?>"></div>
      <div class="field"><label for="a-pass">Senha <span>(mín. 10, letras e números)</span></label><input id="a-pass" name="senha" type="password" required autocomplete="new-password" minlength="10"></div>
      <div class="field"><label for="a-pass2">Repita a senha</label><input id="a-pass2" name="senha2" type="password" required autocomplete="new-password" minlength="10"></div>
      <button class="btn btn-ink" type="submit">Criar acesso</button>
    <?php elseif (!$temUsuario): ?>
      <input type="hidden" name="acao" value="criar">
      <h1 class="lh">Primeiro acesso</h1>
      <p>Crie o acesso do usuário master. Use o código de instalação que está no arquivo de configuração do servidor.</p>
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
      <p>Acesso restrito, com verificação em 2 etapas. Após 5 tentativas erradas o acesso fica bloqueado por 15 minutos.</p>
      <?php if ($erro): ?><p class="err-msg" role="alert"><?= e($erro) ?></p><?php endif; ?>
      <div class="field"><label for="a-email">E-mail</label><input id="a-email" name="email" type="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="field"><label for="a-pass">Senha</label><input id="a-pass" name="senha" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-ink" type="submit">Continuar</button>
    <?php endif; ?>
  </form>
</main>
<?php if ($estagio === 'configurar'): ?><script defer src="<?= e(asset('assets/js/vendor/qrcode.js')) ?>"></script><?php endif; ?>
<?php if (in_array($estagio, ['configurar', 'codigos'], true)): ?><script defer src="<?= e(asset('admin/login.js')) ?>"></script><?php endif; ?>
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
    <span class="grp">Administração</span>
    <button type="button" data-tab="usuarios">Usuários</button>
    <button type="button" data-tab="logs">Registros <span class="badge bad" id="b-logs" hidden></span></button>
    <button type="button" data-tab="config">Configurações</button>
  </nav>
  <main class="main" id="pane" aria-live="polite"><p class="loading-pane">Carregando…</p></main>
</div>
<div class="toast" id="toast" role="status" hidden></div>
<script defer src="<?= e(asset('admin/admin.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
