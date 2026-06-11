<?php
/**
 * Proteção CSRF: token por sessão, validado em toda requisição POST.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Campo oculto para inserir manualmente em formulários (opcional). */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    /** Valida o token enviado (corpo POST ou header X-CSRF-Token). Encerra com 419 se inválido. */
    public static function check(): void
    {
        $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!hash_equals($_SESSION['_csrf'] ?? '', (string) $enviado)) {
            http_response_code(419);
            die('Sessão expirada ou requisição inválida. Recarregue a página e tente novamente.');
        }
    }
}
