<?php
require_once __DIR__ . '/../DAOs/UsuarioDAO.php';

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

    public function updateFoto(int $id, string $caminho): void
    {
        $this->pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")
            ->execute([$caminho, $id]);
    }

    public function upsertGoogle(string $email, string $nome, string $foto): array
    {
        $existing = $this->findByEmail($email);

        if ($existing) {
            $this->pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE email = ?")
                ->execute([$foto, $email]);
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (nome, email, senha, foto_perfil, perfil, email_verificado)
             VALUES (?, ?, '', ?, 'professor', 1)"
        );
        $stmt->execute([$nome, $email, $foto]);

        return [
            'id'          => (int) $this->pdo->lastInsertId(),
            'nome'        => $nome,
            'perfil'      => 'professor',
            'foto_perfil' => $foto,
        ];
    }
}