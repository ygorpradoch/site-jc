<?php
/* /robots.txt (reescrito pelo .htaccess). No subdomínio de teste bloqueia tudo. */
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
if (!cfg('indexar', false)) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
echo "User-agent: *\nDisallow: " . url('admin/') . "\nDisallow: " . url('api/') . "\n\nSitemap: " . abs_url('sitemap.xml') . "\n";
