<?php
/**
 * Front Controller — único ponto de entrada da aplicação.
 * O servidor web deve ter document root apontando para /public.
 */
define('BASE_PATH',    dirname(__DIR__));
define('BACKEND_PATH', BASE_PATH . '/backend');
define('VIEWS_PATH',   BASE_PATH . '/frontend/views');

require BACKEND_PATH . '/config/app.php';
require BACKEND_PATH . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,                          // JS não lê o cookie (mitiga roubo via XSS)
        'samesite' => 'Lax',                         // bloqueia envio cross-site (mitiga CSRF)
        'secure'   => !empty($_SERVER['HTTPS']),     // só HTTPS quando disponível
    ]);
    session_start();
}

require_once BACKEND_PATH . '/helpers/Csrf.php';

// Proteção CSRF: toda requisição POST precisa de token válido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
}

$page = trim($_GET['page'] ?? 'login', '/');

$routes = require BACKEND_PATH . '/routes/web.php';

if (!isset($routes[$page])) {
    $page = 'login';
}

[$controllerName, $method] = $routes[$page];

$controllerFile = BACKEND_PATH . "/controllers/{$controllerName}.php";

if (!file_exists($controllerFile)) {
    http_response_code(404);
    die('Controller não encontrado.');
}

require_once BACKEND_PATH . '/controllers/BaseController.php';
require_once $controllerFile;

$controller = new $controllerName($pdo);
$controller->$method();
