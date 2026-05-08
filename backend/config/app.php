<?php
define('ROOT_PATH',    dirname(__DIR__, 2));
define('BACKEND_PATH', dirname(__DIR__));
define('VIEWS_PATH',   ROOT_PATH . '/frontend/views');
define('APP_PATH',     BACKEND_PATH); // compat alias

date_default_timezone_set('America/Sao_Paulo');

define('APP_NAME',       'UNICEPLAC - Central de Reservas');
define('UPLOAD_DIR',     ROOT_PATH . '/public/uploads/');
define('UPLOAD_URL',     '/uploads/');
define('DEFAULT_AVATAR', '/assets/images/padrao-usuario.png');
