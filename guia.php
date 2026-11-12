<?php
/**
 * Guia do condomínio.
 *   /guia           → lista de artigos
 *   /guia/<slug>    → artigo (o .htaccess reescreve para guia.php?slug=...)
 * admin/previa.php inclui este arquivo com $JC_PREVIA definido para mostrar rascunhos.
 */
if (!defined('JC')) {
    require __DIR__ . '/app/bootstrap.php';
}
require_once JC_APP . '/layout.php';

$previa = $GLOBALS['JC_PREVIA'] ?? null; // artigo vindo do painel (pré-visualização)
$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';

if ($previa === null && $slug !== '' && !preg_match('/^[a-z0-9-]{1,100}$/', $slug)) {
    $slug = '#invalido';
}

$art = null;
if ($previa !== null) {
    $art = $previa;
} elseif ($slug !== '') {
    $art = db_one("SELECT * FROM artigos WHERE slug = ? AND status = 'publicado'", [$slug]);
    if (!$art) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
    db_q('UPDATE artigos SET leituras = leituras + 1, atualizado_em = atualizado_em WHERE id = ?', [$art['id']]);
}

if ($art) {
    $outros = db_all("SELECT slug, titulo, categoria, minutos FROM artigos WHERE status = 'publicado' AND id <> ? ORDER BY publicado_em DESC LIMIT 3", [(int) ($art['id'] ?? 0)]);
    if ($previa === null) {
        header('Cache-Control: public, max-age=600');
    } else {
        header('Cache-Control: no-store');
    }
    layout_head([
        'title' => $art['titulo'] . ' | Guia do condomínio · JC Síndico',
        'desc' => $art['resumo'],
        'path' => 'guia/' . $art['slug'],
        'type' => 'article',
        'robots' => $previa !== null ? 'noindex, nofollow' : null,
        'jsonld' => [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $art['titulo'],
            'description' => $art['resumo'],
            'datePublished' => $art['publicado_em'] ? date('c', strtotime($art['publicado_em'])) : null,
            'dateModified' => date('c', strtotime($art['atualizado_em'] ?? 'now')),
            'author' => ['@type' => 'Person', 'name' => 'Junior Chagas'],
            'publisher' => ['@type' => 'Organization', 'name' => 'JC Síndico Profissional', 'logo' => ['@type' => 'ImageObject', 'url' => abs_url('assets/img/logo-v-azul.svg')]],
            'mainEntityOfPage' => abs_url('guia/' . $art['slug']),
        ],
    ]);
    ?>
<body class="sub">
<?php if ($previa !== null): ?><div class="preview-bar">PRÉ-VISUALIZAÇÃO · <?= $art['status'] === 'publicado' ? 'artigo publicado' : 'rascunho, ainda não aparece no site' ?></div><?php endif; ?>
<?php layout_header(false); ?>
<main id="conteudo" class="art-page">
  <article class="wrap art">
    <nav class="crumbs" aria-label="Você está em"><a href="<?= e(url('')) ?>">Início</a> / <a href="<?= e(url('guia')) ?>">Guia</a></nav>
    <span class="eyebrow"><?= e(mb_strtoupper($art['categoria'])) ?> · <?= (int) $art['minutos'] ?> MIN DE LEITURA</span>
    <h1><?= e($art['titulo']) ?></h1>
    <?php if ($art['resumo']): ?><p class="art-lead"><?= e($art['resumo']) ?></p><?php endif; ?>
    <div class="art-by"><img src="<?= e(url('assets/img/junior-foto.webp')) ?>" alt="" width="44" height="44"><span><b>Junior Chagas</b>Síndico profissional<?= $art['publicado_em'] ? ' · ' . e(fmt_data($art['publicado_em'])) : '' ?></span></div>
    <div class="art-body">
      <?= artigo_html((string) $art['corpo']) ?>
    </div>
    <aside class="art-cta">
      <div><b>Quer ajuda com isso no seu condomínio?</b><span>Converse com o Junior. A primeira conversa é sem custo.</span></div>
      <a class="btn btn-sun" href="<?= e(url('')) ?>#contato">Pedir proposta</a>
      <a class="btn btn-line" href="<?= e(wa_link('Olá Junior, li o artigo "' . $art['titulo'] . '" no site e queria conversar.')) ?>" target="_blank" rel="noopener">WhatsApp</a>
    </aside>
  </article>
  <?php if ($outros): ?>
  <section class="wrap art-more" aria-label="Outros artigos">
    <span class="eyebrow">Continue lendo</span>
    <div class="posts">
      <?php foreach ($outros as $p): ?>
      <a class="post" href="<?= e(url('guia/' . $p['slug'])) ?>"><span class="meta"><?= e(mb_strtoupper($p['categoria'])) ?> · <?= (int) $p['minutos'] ?> MIN</span><span class="pt"><?= e($p['titulo']) ?></span><span class="more">Ler artigo</span></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php layout_footer(false); ?>
</body>
</html>
<?php
    exit;
}

/* ---------- lista ---------- */
$todos = artigos_publicados();
header('Cache-Control: public, max-age=600');
layout_head([
    'title' => 'Guia do condomínio: artigos para síndicos e conselheiros | JC Síndico',
    'desc' => 'Textos curtos sobre as dúvidas que aparecem nas assembleias: eleição de síndico, fundo de reserva, manutenção e prestação de contas.',
    'path' => 'guia',
]);
?>
<body class="sub">
<?php layout_header(false); ?>
<main id="conteudo" class="art-page">
  <div class="wrap">
    <nav class="crumbs" aria-label="Você está em"><a href="<?= e(url('')) ?>">Início</a> / Guia</nav>
    <span class="eyebrow">Guia do condomínio</span>
    <h1 class="list-h">Artigos para síndicos e conselheiros</h1>
    <p class="art-lead">Textos curtos sobre as dúvidas que aparecem nas assembleias, escritos pelo Junior.</p>
    <?php if ($todos): ?>
    <div class="posts posts-all">
      <?php foreach ($todos as $p): ?>
      <a class="post" href="<?= e(url('guia/' . $p['slug'])) ?>"><span class="meta"><?= e(mb_strtoupper($p['categoria'])) ?> · <?= (int) $p['minutos'] ?> MIN</span><span class="pt"><?= e($p['titulo']) ?></span><p><?= e($p['resumo']) ?></p><span class="more">Ler artigo</span></a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p>Nenhum artigo publicado ainda.</p>
    <?php endif; ?>
  </div>
</main>
<?php layout_footer(false); ?>
</body>
</html>
