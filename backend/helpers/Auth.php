<?php
class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['usuario_id']);
    }

    public static function perfil(): ?string
    {
        return $_SESSION['perfil'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }

    public static function nome(): ?string
    {
        return $_SESSION['nome'] ?? null;
    }

    public static function foto(): string
    {
        $foto = $_SESSION['foto_perfil'] ?? null;

        if ($foto && file_exists(ROOT_PATH . '/public/' . ltrim($foto, '/'))) {
            return $foto;
        }

        return DEFAULT_AVATAR;
    }

    public static function login(array $usuario): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nome']       = $usuario['nome'];
        $_SESSION['perfil']     = $usuario['perfil'];
        $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? null;
    }

    public static function logout(): void
    {
        session_destroy();
    }

    public static function destinoAposLogin(string $perfil): string
    {
        return match ($perfil) {
            'coordenador' => 'index.php?page=coordenador',
            'suporte'     => 'index.php?page=suporte',
            default       => 'index.php?page=professor',
        };
    }
}
