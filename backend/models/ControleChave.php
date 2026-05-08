<?php
class ControleChave
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
        $this->pdo->prepare(
            "INSERT INTO controle_chaves
             (id_agendamento, professor_nome, laboratorio, data_uso, celular, hora_retirada, hora_devolucao_prevista, funcionario_entrega, status)
             VALUES (?, ?, ?, CURDATE(), ?, CURTIME(), ?, ?, 'em_uso')"
        )->execute([$idAgendamento, $professorNome, $laboratorio, $celular, $horaDevolucaoPrevista, $funcionarioEntrega]);
    }

    public function darBaixa(int $idChave, string $funcionarioRecebimento, string $horaDevolucaoReal): void
    {
        $this->pdo->prepare(
            "UPDATE controle_chaves
             SET status='devolvido', funcionario_recebimento=?, hora_devolucao_real=?
             WHERE id=?"
        )->execute([$funcionarioRecebimento, $horaDevolucaoReal, $idChave]);
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
        return $this->pdo->query("SELECT * FROM controle_chaves WHERE status='em_uso'")->fetchAll();
    }

    public function historico(): array
    {
        return $this->pdo->query(
            "SELECT * FROM controle_chaves ORDER BY data_uso DESC, hora_retirada DESC"
        )->fetchAll();
    }
}
