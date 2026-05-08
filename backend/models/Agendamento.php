<?php
class Agendamento
{
    public function __construct(private PDO $pdo) {}

    public function buscarLaboratorios(): array
    {
        return $this->pdo->query("SELECT id, nome, capacidade FROM laboratorios ORDER BY nome")->fetchAll();
    }

    public function buscarProfessores(): array
    {
        return $this->pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' ORDER BY nome")->fetchAll();
    }

    public function buscarDisciplinas(): array
    {
        return $this->pdo->query("SELECT id, nome FROM disciplinas ORDER BY nome")->fetchAll();
    }

    public function criarReserva(int $idLab, int $idProf, int $idDisc, string $turno, string $periodo, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO agendamentos (id_laboratorio, id_professor, id_disciplina, turno, periodo, data_reserva, status)
             VALUES (?, ?, ?, ?, ?, ?, 'aprovado')"
        );
        return $stmt->execute([$idLab, $idProf, $idDisc, $turno, $periodo, $data]);
    }

    public function solicitarReserva(int $idLab, int $idProf, int $idDisc, string $turno, string $periodo, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO agendamentos (id_laboratorio, id_professor, id_disciplina, turno, periodo, data_reserva, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pendente')"
        );
        return $stmt->execute([$idLab, $idProf, $idDisc, $turno, $periodo, $data]);
    }

    public function listarAlocacoesDoDia(string $data): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.nome as laboratorio, u.nome as professor, d.nome as disciplina, a.turno, a.periodo
             FROM agendamentos a
             JOIN laboratorios l ON a.id_laboratorio = l.id
             JOIN usuarios u     ON a.id_professor   = u.id
             JOIN disciplinas d  ON a.id_disciplina  = d.id
             WHERE a.data_reserva = ? AND a.status = 'aprovado'
             ORDER BY FIELD(a.turno,'Matutino','Vespertino','Noturno'), l.nome"
        );
        $stmt->execute([$data]);
        return $stmt->fetchAll();
    }

    public function listarAlocacoesProfessor(int $idProfessor): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.id, l.nome as laboratorio, d.nome as disciplina, a.turno, a.periodo, a.data_reserva, a.status
             FROM agendamentos a
             JOIN laboratorios l ON a.id_laboratorio = l.id
             JOIN disciplinas d  ON a.id_disciplina  = d.id
             WHERE a.id_professor = ?
             ORDER BY a.data_reserva DESC, FIELD(a.turno,'Matutino','Vespertino','Noturno')"
        );
        $stmt->execute([$idProfessor]);
        return $stmt->fetchAll();
    }

    public function listarSolicitacoesPendentes(): array
    {
        return $this->pdo->query(
            "SELECT a.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina,
                    a.turno, a.periodo, a.data_reserva
             FROM agendamentos a
             JOIN laboratorios l ON a.id_laboratorio = l.id
             JOIN usuarios u     ON a.id_professor   = u.id
             JOIN disciplinas d  ON a.id_disciplina  = d.id
             WHERE a.status = 'pendente'
             ORDER BY a.data_reserva ASC"
        )->fetchAll();
    }

    public function listarReservasConfirmadas(): array
    {
        return $this->pdo->query(
            "SELECT a.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina,
                    a.turno, a.periodo, a.data_reserva, a.id_professor, a.id_laboratorio, a.id_disciplina
             FROM agendamentos a
             JOIN laboratorios l ON a.id_laboratorio = l.id
             JOIN usuarios u     ON a.id_professor   = u.id
             JOIN disciplinas d  ON a.id_disciplina  = d.id
             WHERE a.status = 'aprovado'
             ORDER BY a.data_reserva DESC"
        )->fetchAll();
    }

    public function listarHistoricoCompleto(): array
    {
        return $this->pdo->query(
            "SELECT a.*, l.nome as laboratorio, u.nome as professor, d.nome as disciplina
             FROM agendamentos a
             JOIN laboratorios l ON a.id_laboratorio = l.id
             JOIN usuarios u     ON a.id_professor   = u.id
             JOIN disciplinas d  ON a.id_disciplina  = d.id
             ORDER BY a.data_reserva DESC, a.id DESC"
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM agendamentos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function atualizarStatus(int $id, string $status): bool
    {
        return $this->pdo->prepare("UPDATE agendamentos SET status = ? WHERE id = ?")
            ->execute([$status, $id]);
    }

    public function atualizar(int $id, int $idLab, int $idProf, int $idDisc, string $turno, string $periodo, string $data): bool
    {
        return $this->pdo->prepare(
            "UPDATE agendamentos SET id_laboratorio=?, id_professor=?, id_disciplina=?, turno=?, periodo=?, data_reserva=? WHERE id=?"
        )->execute([$idLab, $idProf, $idDisc, $turno, $periodo, $data, $id]);
    }

    public function excluir(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM agendamentos WHERE id = ?")->execute([$id]);
    }
}
