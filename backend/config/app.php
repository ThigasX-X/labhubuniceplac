<?php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}

if (!defined('BACKEND_PATH')) {
    define('BACKEND_PATH', dirname(__DIR__));
}

if (!defined('VIEWS_PATH')) {
    define('VIEWS_PATH', ROOT_PATH . '/frontend/views');
}

if (!defined('APP_PATH')) {
    define('APP_PATH', BACKEND_PATH);
}

require_once ROOT_PATH . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(ROOT_PATH)->safeLoad();

// PHP 8.5 transforma muitos casos legados (ex.: null em htmlspecialchars) em
// deprecations. Mantemos warnings/erros reais visíveis, mas não poluímos a UI
// com deprecations/notices — tudo continua sendo registrado no log.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
// Em produção/exposição pública, APP_DEBUG=false esconde erros da tela (ficam no log)
$debug = (getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? 'true')) === 'true';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set('America/Sao_Paulo');

if (!defined('APP_NAME'))       define('APP_NAME', 'UNICEPLAC - Central de Reservas');
if (!defined('UPLOAD_DIR'))     define('UPLOAD_DIR', ROOT_PATH . '/public/uploads/');
if (!defined('UPLOAD_URL'))     define('UPLOAD_URL', '/uploads/');
if (!defined('DEFAULT_AVATAR')) define('DEFAULT_AVATAR', '/assets/images/uniceplac.png');