<?php
abstract class BaseController
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = VIEWS_PATH . '/' . $view . '.php';

        if (!file_exists($viewFile)) {
            die("View não encontrada: {$view}");
        }

        include $viewFile;
    }

    protected function redirect(string $page, array $params = []): void
    {
        $query = http_build_query(array_merge(['page' => $page], $params));
        header("Location: /index.php?{$query}");
        exit;
    }

    protected function redirectBack(string $fallback = 'login'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? null;
        header('Location: ' . ($ref ?: "/index.php?page={$fallback}"));
        exit;
    }

    protected function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function requireAuth(string|array|null $perfil = null): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            $this->redirect('login');
        }

        $permitidos = $perfil === null ? null : (array) $perfil;

        if ($permitidos !== null && !in_array($_SESSION['perfil'], $permitidos, true)) {
            require_once BACKEND_PATH . '/helpers/Auth.php';
            header('Location: ' . Auth::destinoAposLogin($_SESSION['perfil']));
            exit;
        }
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
