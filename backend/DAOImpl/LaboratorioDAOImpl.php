<?php
require_once __DIR__ . '/LaboratorioDAO.php';

class LaboratorioDAOImpl implements LaboratorioDAO
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM laboratorios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $nome, int $capacidade, string $localizacao, string $andar): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO laboratorios (nome, capacidade, localizacao, andar) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $capacidade, $localizacao, $andar]);
    }

    public function update(int $id, string $nome, int $capacidade, string $localizacao, string $andar): void
    {
        $stmt = $this->pdo->prepare("UPDATE laboratorios SET nome=?, capacidade=?, localizacao=?, andar=? WHERE id=?");
        $stmt->execute([$nome, $capacidade, $localizacao, $andar, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM laboratorios WHERE id=?");
        $stmt->execute([$id]);
    }
}