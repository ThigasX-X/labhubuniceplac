<?php
require_once __DIR__ . '/../DAOs/QuadroHorarioDAO.php';

class QuadroHorarioDAOImpl implements QuadroHorarioDAO
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        try {
            return $this->pdo->query("SELECT * FROM quadros_horarios ORDER BY data_criacao DESC")
                             ->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception) {
            return [];
        }
    }

    public function idAtivo(): int|false
    {
        try {
            return $this->pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")
                             ->fetchColumn();
        } catch (Exception) {
            return false;
        }
    }

    public function criar(string $nome, string $periodoLetivo): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO quadros_horarios (nome, periodo_letivo) VALUES (?, ?)");
        $stmt->execute([$nome, $periodoLetivo]);
    }

    public function excluir(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM quadros_horarios WHERE id=?");
        $stmt->execute([$id]);
    }

    public function duplicar(int $idOrigem, string $novoNome, string $novoPeriodo): void
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("INSERT INTO quadros_horarios (nome, periodo_letivo) VALUES (?, ?)");
            $stmt->execute([$novoNome, $novoPeriodo]);
            $novoId = (int) $this->pdo->lastInsertId();

            $aulasStmt = $this->pdo->prepare("SELECT * FROM quadro_aulas WHERE id_quadro=?");
            $aulasStmt->execute([$idOrigem]);
            $aulas = $aulasStmt->fetchAll(PDO::FETCH_ASSOC);

            $insert = $this->pdo->prepare(
                "INSERT INTO quadro_aulas
                 (id_quadro, id_disciplina, id_professor, id_laboratorio, id_curso, id_semestre, id_sala,
                  turno, dia_semana, modalidade, numero_alunos, horario, carga_horaria_total, horas_laboratorio)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($aulas as $aula) {
                $insert->execute([
                    $novoId,
                    $aula['id_disciplina'], $aula['id_professor'], $aula['id_laboratorio'],
                    $aula['id_curso'], $aula['id_semestre'], $aula['id_sala'],
                    $aula['turno'], $aula['dia_semana'], $aula['modalidade'], $aula['numero_alunos'],
                    $aula['horario'], $aula['carga_horaria_total'], $aula['horas_laboratorio'],
                ]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function aulasDoQuadro(int $idQuadro): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT qa.*,
                        p.nome  AS prof_nome,
                        d.nome  AS disc_nome,
                        c.nome  AS curso,
                        sem.nome AS semestre,
                        s.nome  AS sala,
                        a.nome  AS andar,
                        a.id    AS id_andar,
                        b.nome  AS bloco,
                        b.id    AS id_bloco,
                        l.nome  AS lab_nome,
                        l.localizacao AS lab_local,
                        l.andar AS lab_andar
                 FROM quadro_aulas qa
                 LEFT JOIN usuarios     p   ON qa.id_professor   = p.id
                 JOIN      disciplinas  d   ON qa.id_disciplina  = d.id
                 JOIN      cursos       c   ON qa.id_curso       = c.id
                 JOIN      semestres    sem ON qa.id_semestre    = sem.id
                 LEFT JOIN salas        s   ON qa.id_sala        = s.id
                 LEFT JOIN andares      a   ON s.id_andar        = a.id
                 LEFT JOIN blocos       b   ON a.id_bloco        = b.id
                 LEFT JOIN laboratorios l   ON qa.id_laboratorio = l.id
                 WHERE qa.id_quadro = ?
                 ORDER BY FIELD(qa.turno,'Matutino','Vespertino','Noturno'), qa.horario, c.nome, sem.nome"
            );
            $stmt->execute([$idQuadro]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception) {
            return [];
        }
    }

    public function salvarAula(array $dados): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO quadro_aulas
             (id_quadro, id_disciplina, id_professor, id_laboratorio, id_curso, id_semestre, id_sala,
              turno, dia_semana, modalidade, numero_alunos, horario, carga_horaria_total, horas_laboratorio)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $dados['id_quadro'],
            $dados['id_disciplina'], $dados['id_professor'] ?: null, $dados['id_laboratorio'] ?: null,
            $dados['id_curso'], $dados['id_semestre'], $dados['id_sala'] ?: null,
            $dados['turno'], $dados['dia_semana'], $dados['modalidade'] ?? null,
            $dados['numero_alunos'] ?? 0, $dados['horario'] ?? null,
            $dados['carga_horaria_total'] ?? 0, $dados['horas_laboratorio'] ?? 0,
        ]);
    }

    public function editarAula(int $idAula, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE quadro_aulas SET
                id_disciplina=?, id_professor=?, id_laboratorio=?, id_curso=?, id_semestre=?, id_sala=?,
                turno=?, dia_semana=?, modalidade=?, numero_alunos=?, horario=?,
                carga_horaria_total=?, horas_laboratorio=?
             WHERE id=?"
        );
        $stmt->execute([
            $dados['id_disciplina'], $dados['id_professor'] ?: null, $dados['id_laboratorio'] ?: null,
            $dados['id_curso'], $dados['id_semestre'], $dados['id_sala'] ?: null,
            $dados['turno'], $dados['dia_semana'], $dados['modalidade'] ?? null,
            $dados['numero_alunos'] ?? 0, $dados['horario'] ?? null,
            $dados['carga_horaria_total'] ?? 0, $dados['horas_laboratorio'] ?? 0,
            $idAula,
        ]);
    }

    public function excluirAula(int $idAula): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM quadro_aulas WHERE id=?");
        $stmt->execute([$idAula]);
    }

    public function moverAula(int $idAula, string $novoDia): void
    {
        $stmt = $this->pdo->prepare("UPDATE quadro_aulas SET dia_semana=? WHERE id=?");
        $stmt->execute([$novoDia, $idAula]);
    }
}
