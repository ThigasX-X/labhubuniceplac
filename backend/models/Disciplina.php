<?php
class Disciplina
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM disciplinas ORDER BY nome")->fetchAll();
    }

    public function create(string $nome): void
    {
        $this->pdo->prepare("INSERT INTO disciplinas (nome) VALUES (?)")->execute([trim($nome)]);
    }

    public function update(int $id, string $nome): void
    {
        $this->pdo->prepare("UPDATE disciplinas SET nome=? WHERE id=?")->execute([trim($nome), $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare("DELETE FROM disciplinas WHERE id=?")->execute([$id]);
    }
}
