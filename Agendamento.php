<?php
class Agendamento {
    private $pdo;

    public function __construct($conexao) {
        $this->pdo = $conexao;
    }

    public function buscarLaboratorios() {
        $stmt = $this->pdo->query("SELECT id, nome, capacidade FROM laboratorios ORDER BY nome");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarProfessores() {
        $stmt = $this->pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' ORDER BY nome");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarDisciplinas() {
        $stmt = $this->pdo->query("SELECT id, nome FROM disciplinas ORDER BY nome");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- MÉTODOS DE CRIAÇÃO (AGORA COM O PERIODO) ---
    public function criarReserva($id_lab, $id_prof, $id_disciplina, $turno, $periodo, $data) {
        $sql = "INSERT INTO agendamentos (id_laboratorio, id_professor, id_disciplina, turno, periodo, data_reserva, status) 
                VALUES (:lab, :prof, :disc, :turno, :periodo, :data, 'aprovado')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':lab', $id_lab);
        $stmt->bindParam(':prof', $id_prof);
        $stmt->bindParam(':disc', $id_disciplina);
        $stmt->bindParam(':turno', $turno);
        $stmt->bindParam(':periodo', $periodo);
        $stmt->bindParam(':data', $data);
        
        return $stmt->execute();
    }

    public function solicitarReserva($id_lab, $id_prof, $id_disciplina, $turno, $periodo, $data) {
        $sql = "INSERT INTO agendamentos (id_laboratorio, id_professor, id_disciplina, turno, periodo, data_reserva, status) 
                VALUES (:lab, :prof, :disc, :turno, :periodo, :data, 'pendente')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':lab', $id_lab);
        $stmt->bindParam(':prof', $id_prof);
        $stmt->bindParam(':disc', $id_disciplina);
        $stmt->bindParam(':turno', $turno);
        $stmt->bindParam(':periodo', $periodo);
        $stmt->bindParam(':data', $data);
        
        return $stmt->execute();
    }

    // --- MÉTODOS DE LEITURA (AGORA TRAZENDO O PERIODO) ---
    public function listarAlocacoesDoDia($data) {
        $sql = "SELECT l.nome as laboratorio, u.nome as professor, d.nome as disciplina, a.turno, a.periodo 
                FROM agendamentos a
                INNER JOIN laboratorios l ON a.id_laboratorio = l.id
                INNER JOIN usuarios u ON a.id_professor = u.id
                INNER JOIN disciplinas d ON a.id_disciplina = d.id
                WHERE a.data_reserva = :data AND a.status = 'aprovado'
                ORDER BY FIELD(a.turno, 'Matutino', 'Vespertino', 'Noturno'), l.nome ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAlocacoesProfessor($id_professor) {
        $sql = "SELECT a.id, l.nome as laboratorio, d.nome as disciplina, a.turno, a.periodo, a.data_reserva, a.status 
                FROM agendamentos a
                INNER JOIN laboratorios l ON a.id_laboratorio = l.id
                INNER JOIN disciplinas d ON a.id_disciplina = d.id
                WHERE a.id_professor = :id_professor 
                ORDER BY a.data_reserva DESC, FIELD(a.turno, 'Matutino', 'Vespertino', 'Noturno')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id_professor', $id_professor);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarSolicitacoesPendentes() {
        $sql = "SELECT a.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina, a.turno, a.periodo, a.data_reserva 
                FROM agendamentos a
                INNER JOIN laboratorios l ON a.id_laboratorio = l.id
                INNER JOIN usuarios u ON a.id_professor = u.id
                INNER JOIN disciplinas d ON a.id_disciplina = d.id
                WHERE a.status = 'pendente'
                ORDER BY a.data_reserva ASC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarReservasConfirmadas() {
        $sql = "SELECT a.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina, a.turno, a.periodo, a.data_reserva 
                FROM agendamentos a
                INNER JOIN laboratorios l ON a.id_laboratorio = l.id
                INNER JOIN usuarios u ON a.id_professor = u.id
                INNER JOIN disciplinas d ON a.id_disciplina = d.id
                WHERE a.status = 'aprovado'
                ORDER BY a.data_reserva DESC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarReservaPorId($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM agendamentos WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- MÉTODOS DE ATUALIZAÇÃO E EXCLUSÃO ---
    public function atualizarStatusReserva($id_agendamento, $novo_status) {
        $sql = "UPDATE agendamentos SET status = :status WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':status', $novo_status);
        $stmt->bindParam(':id', $id_agendamento);
        return $stmt->execute();
    }

    public function atualizarReserva($id, $id_lab, $id_prof, $id_disc, $turno, $periodo, $data) {
        $sql = "UPDATE agendamentos 
                SET id_laboratorio = :lab, id_professor = :prof, id_disciplina = :disc, turno = :turno, periodo = :periodo, data_reserva = :data 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':lab', $id_lab);
        $stmt->bindParam(':prof', $id_prof);
        $stmt->bindParam(':disc', $id_disc); 
        $stmt->bindParam(':turno', $turno);
        $stmt->bindParam(':periodo', $periodo);
        $stmt->bindParam(':data', $data);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    public function excluirReserva($id) {
        $stmt = $this->pdo->prepare("DELETE FROM agendamentos WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>