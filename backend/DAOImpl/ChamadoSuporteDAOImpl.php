<?php
require_once __DIR__ . '/ChamadoSuporteDAO.php';

class ChamadoSuporteDAOImpl implements ChamadoSuporteDAO
{
    public function __construct(private PDO $pdo) {}

    public function abrir(int $idProfessor, string $professorNome, string $laboratorio, string $mensagem): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO chamados_suporte (id_professor, professor_nome, laboratorio, mensagem) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$idProfessor, $professorNome, $laboratorio, $mensagem]);
    }

    public function resolver(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE chamados_suporte SET status='resolvido' WHERE id=?");
        $stmt->execute([$id]);
    }

    public function pendentes(): array
    {
        return $this->pdo->query(
            "SELECT * FROM chamados_suporte WHERE status='pendente' ORDER BY data_hora DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resolvidos(): array
    {
        return $this->pdo->query(
            "SELECT * FROM chamados_suporte WHERE status='resolvido' ORDER BY data_hora DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPendentes(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM chamados_suporte WHERE status='pendente'"
        )->fetchColumn();
    }
}