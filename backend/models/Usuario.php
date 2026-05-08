<?php
class Usuario
{
    public function __construct(private PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $nome, string $email, string $senhaHash, string $perfil = 'professor'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha, perfil, email_verificado) VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$nome, $email, $senhaHash, $perfil]);
        return (int) $this->pdo->lastInsertId();
    }

    public function upsertGoogle(string $email, string $nome, string $googleId, string $foto): array
    {
        $existing = $this->findByEmail($email);

        if ($existing) {
            $this->pdo->prepare("UPDATE usuarios SET google_id = ?, foto_perfil = ? WHERE email = ?")
                ->execute([$googleId, $foto, $email]);
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (nome, email, google_id, foto_perfil, perfil, email_verificado) VALUES (?, ?, ?, ?, 'professor', 1)"
        );
        $stmt->execute([$nome, $email, $googleId, $foto]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'perfil' => 'professor', 'nome' => $nome, 'foto_perfil' => $foto];
    }

    public function updateFoto(int $id, string $caminho): void
    {
        $this->pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")->execute([$caminho, $id]);
    }

    public function verificarEmail(string $token): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM usuarios WHERE token_verificacao = ? AND email_verificado = 0 LIMIT 1"
        );
        $stmt->execute([$token]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return false;
        }

        $this->pdo->prepare("UPDATE usuarios SET email_verificado = 1, token_verificacao = NULL WHERE id = ?")
            ->execute([$usuario['id']]);

        return true;
    }

    public function listProfessores(): array
    {
        return $this->pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' ORDER BY nome")->fetchAll();
    }
}
