<?php
/** Pré-visualização de artigo (inclusive rascunho), só para quem está logado. */
require dirname(__DIR__) . '/app/bootstrap.php';
require JC_APP . '/auth.php';
header('X-Robots-Tag: noindex, nofollow');
if (!admin_user()) {
    header('Location: ' . url('admin/'));
    exit;
}
$a = db_one('SELECT * FROM artigos WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$a) {
    http_response_code(404);
    exit('Artigo não encontrado.');
}
$GLOBALS['JC_PREVIA'] = $a;
require JC_ROOT . '/guia.php';
