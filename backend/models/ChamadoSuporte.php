<?php
class ChamadoSuporte
{
    public function __construct(private PDO $pdo) {}

    public function abrir(int $idProfessor, string $professorNome, string $laboratorio, string $mensagem): void
    {
        $this->pdo->prepare(
            "INSERT INTO chamados_suporte (id_professor, professor_nome, laboratorio, mensagem) VALUES (?, ?, ?, ?)"
        )->execute([$idProfessor, $professorNome, $laboratorio, $mensagem]);
    }

    public function resolver(int $id): void
    {
        $this->pdo->prepare("UPDATE chamados_suporte SET status='resolvido' WHERE id=?")->execute([$id]);
    }

    public function pendentes(): array
    {
        return $this->pdo->query(
            "SELECT * FROM chamados_suporte WHERE status='pendente' ORDER BY data_hora DESC"
        )->fetchAll();
    }

    public function resolvidos(): array
    {
        return $this->pdo->query(
            "SELECT * FROM chamados_suporte WHERE status='resolvido' ORDER BY data_hora DESC"
        )->fetchAll();
    }

    public function countPendentes(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM chamados_suporte WHERE status='pendente'"
        )->fetchColumn();
    }
}
