<?php
/* /sitemap.xml (reescrito pelo .htaccess) */
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$urls = [
    [abs_url(''), date('Y-m-d'), '1.0'],
    [abs_url('guia'), date('Y-m-d'), '0.7'],
    [abs_url('privacidade'), null, '0.2'],
];
foreach (artigos_publicados() as $a) {
    $urls[] = [abs_url('guia/' . $a['slug']), date('Y-m-d', strtotime($a['atualizado_em'])), '0.6'];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $pri]) {
    echo '  <url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . '<priority>' . $pri . "</priority></url>\n";
}
echo "</urlset>\n";
