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

date_default_timezone_set('America/Sao_Paulo');

if (!defined('APP_NAME'))       define('APP_NAME', 'UNICEPLAC - Central de Reservas');
if (!defined('UPLOAD_DIR'))     define('UPLOAD_DIR', ROOT_PATH . '/public/uploads/');
if (!defined('UPLOAD_URL'))     define('UPLOAD_URL', '/uploads/');
if (!defined('DEFAULT_AVATAR')) define('DEFAULT_AVATAR', '/assets/images/padrao-usuario.png');