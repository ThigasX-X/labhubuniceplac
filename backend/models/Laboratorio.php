<?php
class Laboratorio
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM laboratorios ORDER BY nome")->fetchAll();
    }

    public function create(string $nome, int $capacidade, string $localizacao, string $andar): void
    {
        $this->pdo->prepare("INSERT INTO laboratorios (nome, capacidade, localizacao, andar) VALUES (?, ?, ?, ?)")
            ->execute([$nome, $capacidade, $localizacao, $andar]);
    }

    public function update(int $id, string $nome, int $capacidade, string $localizacao, string $andar): void
    {
        $this->pdo->prepare("UPDATE laboratorios SET nome=?, capacidade=?, localizacao=?, andar=? WHERE id=?")
            ->execute([$nome, $capacidade, $localizacao, $andar, $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare("DELETE FROM laboratorios WHERE id=?")->execute([$id]);
    }
}
