<?php
require_once __DIR__ . '/UsuarioDAO.php';

class UsuarioDAOImpl implements UsuarioDAO 
{
    public function __construct(private PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(string $nome, string $email, string $senhaHash, string $perfil = 'professor'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha, perfil, email_verificado) VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$nome, $email, $senhaHash, $perfil]);
        return (int) $this->pdo->lastInsertId();
    }
    
    public function listProfessores(): array
    {
        return $this->pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    }
}