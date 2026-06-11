<?php
require_once __DIR__ . '/../DAOs/ControleChaveDAO.php';

class ControleChaveDAOImpl implements ControleChaveDAO
{
    public function __construct(private PDO $pdo) {}

    public function registrarRetirada(
        int    $idAgendamento,
        string $professorNome,
        string $laboratorio,
        string $celular,
        string $horaDevolucaoPrevista,
        string $funcionarioEntrega
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO controle_chaves
             (id_agendamento, professor_nome, laboratorio, data_uso, celular, hora_retirada, hora_devolucao_prevista, funcionario_entrega, status)
             VALUES (?, ?, ?, CURDATE(), ?, CURTIME(), ?, ?, 'em_uso')"
        );
        $stmt->execute([$idAgendamento, $professorNome, $laboratorio, $celular, $horaDevolucaoPrevista, $funcionarioEntrega]);
    }

    public function darBaixa(int $idChave, string $funcionarioRecebimento, string $horaDevolucaoReal): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE controle_chaves
             SET status='devolvido', funcionario_recebimento=?, hora_devolucao_real=?
             WHERE id=?"
        );
        $stmt->execute([$funcionarioRecebimento, $horaDevolucaoReal, $idChave]);
    }

    public function chavesPorProfessor(string $professorNome): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_agendamento FROM controle_chaves WHERE status='em_uso' AND professor_nome=?"
        );
        $stmt->execute([$professorNome]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return array_fill_keys($rows, true);
    }

    public function emUso(): array
    {
        return $this->pdo->query("SELECT * FROM controle_chaves WHERE status='em_uso'")
                         ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function historico(): array
    {
        return $this->pdo->query(
            "SELECT * FROM controle_chaves ORDER BY data_uso DESC, hora_retirada DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}