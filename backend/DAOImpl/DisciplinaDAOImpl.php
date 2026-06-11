<?php
require_once __DIR__ . '/../DAOs/DisciplinaDAO.php';

class DisciplinaDAOImpl implements DisciplinaDAO
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM disciplinas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $nome): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO disciplinas (nome) VALUES (?)");
        $stmt->execute([trim($nome)]);
    }

    public function update(int $id, string $nome): void
    {
        $stmt = $this->pdo->prepare("UPDATE disciplinas SET nome=? WHERE id=?");
        $stmt->execute([trim($nome), $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM disciplinas WHERE id=?");
        $stmt->execute([$id]);
    }
}