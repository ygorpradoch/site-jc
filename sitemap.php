<?php
/* /sitemap.xml (reescrito pelo .htaccess). lastmod só com data real de alteração. */
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$ultimo = db_val("SELECT MAX(atualizado_em) FROM artigos WHERE status = 'publicado'");
$ultimoTs = $ultimo ? strtotime($ultimo) : 0;
$homeTs = max(filemtime(__DIR__ . '/index.php'), $ultimoTs);
$urls = [
    [abs_url(''), date('Y-m-d', $homeTs)],
    [abs_url('guia'), $ultimoTs ? date('Y-m-d', $ultimoTs) : null],
    [abs_url('privacidade'), date('Y-m-d', filemtime(__DIR__ . '/privacidade.php'))],
];
foreach (artigos_publicados() as $a) {
    $urls[] = [abs_url('guia/' . $a['slug']), date('Y-m-d', strtotime($a['atualizado_em']))];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod]) {
    echo '  <url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . "</url>\n";
}
echo "</urlset>\n";
