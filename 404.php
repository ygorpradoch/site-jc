<?php
if (!defined('JC')) {
    require __DIR__ . '/app/bootstrap.php';
}
require_once JC_APP . '/layout.php';
http_response_code(404);
header('Cache-Control: no-store');
layout_head([
    'title' => 'Página não encontrada | JC Síndico Profissional',
    'desc' => 'O endereço procurado não existe ou mudou de lugar.',
    'path' => '',
    'robots' => 'noindex, follow',
]);
?>
<body class="sub">
<?php layout_header(false); ?>
<main id="conteudo" class="art-page">
  <div class="wrap nf">
    <span class="nf-floor" aria-hidden="true">404</span>
    <span class="eyebrow">Andar inexistente</span>
    <h1 class="list-h">Este andar não existe no prédio</h1>
    <p class="art-lead">O endereço pode ter mudado. Volte ao início ou veja os artigos do guia.</p>
    <p class="nf-btns"><a class="btn btn-sun" href="<?= e(url('')) ?>">Ir para o início</a> <a class="btn btn-line" href="<?= e(url('guia')) ?>">Ver o guia</a></p>
  </div>
</main>
<?php layout_footer(false); ?>
</body>
</html>
