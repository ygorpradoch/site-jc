<?php
/**
 * Guia do condomínio (blog da JC Síndico Profissional).
 *   /guia                              → lista (filtro ?categoria=, paginação ?pagina=)
 *   /guia/<slug>                       → artigo (o .htaccess reescreve para guia.php?slug=...)
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
    $outros = artigos_relacionados((int) ($art['id'] ?? 0), (string) $art['categoria'], 3);
    header('Cache-Control: ' . ($previa === null ? 'public, max-age=600' : 'no-store'));
    $capa = capa_url($art['capa'] ?? null);
    $linkArt = abs_url('guia/' . $art['slug']);
    $imgSocial = $capa ? $art['capa'] : 'assets/img/og.jpg';
    layout_head([
        'title' => $art['titulo'] . ' | Guia do condomínio · JC Síndico',
        'desc' => $art['resumo'],
        'path' => 'guia/' . $art['slug'],
        'type' => 'article',
        'image' => $imgSocial,
        'robots' => $previa !== null ? 'noindex, nofollow' : null,
        'jsonld' => [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $art['titulo'],
            'description' => $art['resumo'],
            'image' => [abs_url($imgSocial)],
            'datePublished' => $art['publicado_em'] ? date('c', strtotime($art['publicado_em'])) : null,
            'dateModified' => date('c', strtotime($art['atualizado_em'] ?? 'now')),
            'author' => ['@type' => 'Organization', 'name' => 'JC Síndico Profissional'],
            'publisher' => ['@type' => 'Organization', 'name' => 'JC Síndico Profissional', 'logo' => ['@type' => 'ImageObject', 'url' => abs_url('assets/img/logo-v-azul.svg')]],
            'mainEntityOfPage' => $linkArt,
        ],
    ]);
    $textoShare = $art['titulo'] . ' — Guia do condomínio, JC Síndico Profissional';
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
    <div class="art-by"><img src="<?= e(url('favicon.svg')) ?>" alt="" width="44" height="44"><span><b>JC Síndico Profissional</b>Guia do condomínio<?= $art['publicado_em'] ? ' · ' . e(fmt_data($art['publicado_em'])) : '' ?></span></div>
    <?php if ($capa): ?><figure class="art-cover"><img src="<?= e($capa) ?>" alt="" width="1200" height="630" fetchpriority="high"></figure><?php endif; ?>
    <div class="art-body">
      <?= artigo_html((string) $art['corpo']) ?>
    </div>
    <?php if ($previa === null): ?>
    <div class="art-share" data-share data-url="<?= e($linkArt) ?>" data-title="<?= e($art['titulo']) ?>">
      <b>Compartilhe com quem precisa ler</b>
      <div class="sh-btns">
        <a class="btn btn-line btn-sm" href="https://wa.me/?text=<?= e(rawurlencode($textoShare . "\n" . $linkArt)) ?>" target="_blank" rel="noopener noreferrer"><?= JC_WA_SVG ?> WhatsApp</a>
        <a class="btn btn-line btn-sm" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e(rawurlencode($linkArt)) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
        <button class="btn btn-line btn-sm" type="button" data-copy>Copiar link</button>
        <button class="btn btn-line btn-sm" type="button" data-native hidden>Mais opções</button>
      </div>
    </div>
    <?php endif; ?>
    <aside class="art-cta">
      <div><b>Quer ajuda com isso no seu condomínio?</b><span>Converse com a JC. A primeira conversa é sem custo.</span></div>
      <a class="btn btn-sun" href="<?= e(url('')) ?>#contato">Pedir proposta</a>
      <a class="btn btn-line" href="<?= e(wa_link('Olá, li o artigo "' . $art['titulo'] . '" no site e queria conversar.')) ?>" target="_blank" rel="noopener">WhatsApp</a>
    </aside>
  </article>
  <?php if ($outros): ?>
  <section class="wrap art-more" aria-label="Artigos relacionados">
    <span class="eyebrow">Continue lendo</span>
    <div class="posts">
      <?php foreach ($outros as $p) { echo post_card($p, false); } ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php layout_footer(false); ?>
<script src="<?= e(asset('assets/js/guia.js')) ?>" defer></script>
</body>
</html>
<?php
    exit;
}

/* ---------- lista ---------- */
$cats = artigos_categorias();
$cat = (string) ($_GET['categoria'] ?? '');
$cat = in_array($cat, $cats, true) ? $cat : '';
$total = artigos_total($cat);
$paginas = max(1, (int) ceil($total / JC_POR_PAGINA));
$pag = min($paginas, max(1, (int) ($_GET['pagina'] ?? 1)));
$todos = artigos_publicados(JC_POR_PAGINA, ($pag - 1) * JC_POR_PAGINA, $cat);
$qs = fn(?string $c, int $n) => 'guia' . (($c || $n > 1) ? '?' . http_build_query(array_filter(['categoria' => $c, 'pagina' => $n > 1 ? $n : null])) : '');

header('Cache-Control: public, max-age=600');
layout_head([
    'title' => ($cat ? mb_convert_case(mb_strtolower($cat), MB_CASE_TITLE) . ': artigos' : 'Guia do condomínio: artigos para síndicos e conselheiros') . ($pag > 1 ? ' (página ' . $pag . ')' : '') . ' | JC Síndico',
    'desc' => 'Textos curtos sobre as dúvidas que aparecem nas assembleias: eleição de síndico, fundo de reserva, manutenção e prestação de contas.',
    'path' => $qs($cat ?: null, $pag),
]);
?>
<body class="sub">
<?php layout_header(false); ?>
<main id="conteudo" class="art-page">
  <div class="wrap">
    <nav class="crumbs" aria-label="Você está em"><a href="<?= e(url('')) ?>">Início</a> / Guia</nav>
    <span class="eyebrow">Guia do condomínio</span>
    <h1 class="list-h">Artigos para síndicos e conselheiros</h1>
    <p class="art-lead">Textos curtos sobre as dúvidas que aparecem nas assembleias, escritos pela equipe da JC.</p>
    <?php if (count($cats) > 1): ?>
    <nav class="chips" aria-label="Filtrar por assunto">
      <a href="<?= e(url('guia')) ?>"<?= $cat === '' ? ' aria-current="true"' : '' ?>>Todos</a>
      <?php foreach ($cats as $c): ?>
      <a href="<?= e(url($qs($c, 1))) ?>"<?= $cat === $c ? ' aria-current="true"' : '' ?>><?= e(mb_convert_case(mb_strtolower($c), MB_CASE_TITLE)) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if ($todos): ?>
    <div class="posts posts-all">
      <?php foreach ($todos as $p) { echo post_card($p); } ?>
    </div>
    <?php if ($paginas > 1): ?>
    <nav class="pager" aria-label="Páginas">
      <?php if ($pag > 1): ?><a href="<?= e(url($qs($cat ?: null, $pag - 1))) ?>" rel="prev">← Anteriores</a><?php endif; ?>
      <span>Página <?= $pag ?> de <?= $paginas ?></span>
      <?php if ($pag < $paginas): ?><a href="<?= e(url($qs($cat ?: null, $pag + 1))) ?>" rel="next">Mais artigos →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php else: ?>
    <p>Nenhum artigo publicado ainda.</p>
    <?php endif; ?>
  </div>
</main>
<?php layout_footer(false); ?>
</body>
</html>
