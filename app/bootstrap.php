<?php
/**
 * Inicialização comum a todas as páginas e endpoints.
 * Este diretório (app/) é bloqueado para acesso web pelo .htaccess.
 */
declare(strict_types=1);

define('JC', true);
define('JC_APP', __DIR__);
define('JC_ROOT', dirname(__DIR__));

date_default_timezone_set('America/Fortaleza');
mb_internal_encoding('UTF-8');

/* ---------- configuração (fora da pasta pública) ---------- */
function jc_config_path(): ?string
{
    $env = getenv('JC_CONFIG');
    if ($env && is_file($env)) {
        return $env;
    }
    // Procura jc-config/config.php nas pastas ACIMA da raiz do site
    // (ex.: /home/u123/domains/juniorsindico.pro/jc-config/config.php)
    $dir = dirname(JC_ROOT);
    for ($i = 0; $i < 4; $i++) {
        $f = $dir . '/jc-config/config.php';
        if (is_file($f)) {
            return $f;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    // Só para desenvolvimento local (arquivo ignorado pelo Git e bloqueado pelo .htaccess)
    $local = JC_APP . '/config.local.php';
    return is_file($local) ? $local : null;
}

$__cfgPath = jc_config_path();
if ($__cfgPath === null) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Site em configuração.');
}
$GLOBALS['JC_CFG'] = require $__cfgPath;
unset($__cfgPath);

function cfg(string $key, $default = null)
{
    $c = $GLOBALS['JC_CFG'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($c) || !array_key_exists($k, $c)) {
            return $default;
        }
        $c = $c[$k];
    }
    return $c;
}

/* ---------- erros: nunca mostrar detalhes ao visitante ---------- */
$debug = (bool) cfg('debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e) use ($debug) {
    error_log('[JC] ' . get_class($e) . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api');
    if ($wantsJson) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'erro' => 'Erro interno. Tente novamente em instantes.']);
    } else {
        echo $debug ? '<pre>' . htmlspecialchars((string) $e) . '</pre>' : 'Ocorreu um erro. Tente novamente em instantes.';
    }
    exit;
});

require JC_APP . '/db.php';
require JC_APP . '/helpers.php';
require JC_APP . '/content.php';
