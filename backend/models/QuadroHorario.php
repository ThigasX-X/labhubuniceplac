<?php
class QuadroHorario
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        try {
            return $this->pdo->query("SELECT * FROM quadros_horarios ORDER BY data_criacao DESC")->fetchAll();
        } catch (Exception) {
            return [];
        }
    }

    public function idAtivo(): int|false
    {
        try {
            return $this->pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn();
        } catch (Exception) {
            return false;
        }
    }

    public function criar(string $nome, string $periodoLetivo): void
    {
        $this->pdo->prepare("INSERT INTO quadros_horarios (nome, periodo_letivo) VALUES (?, ?)")
            ->execute([$nome, $periodoLetivo]);
    }

    public function excluir(int $id): void
    {
        $this->pdo->prepare("DELETE FROM quadros_horarios WHERE id=?")->execute([$id]);
    }

    public function duplicar(int $idOrigem, string $novoNome, string $novoPeriodo): void
    {
        $this->pdo->beginTransaction();

        $this->pdo->prepare("INSERT INTO quadros_horarios (nome, periodo_letivo) VALUES (?, ?)")
            ->execute([$novoNome, $novoPeriodo]);
        $novoId = (int) $this->pdo->lastInsertId();

        $aulas = $this->pdo->prepare("SELECT * FROM quadro_aulas WHERE id_quadro=?");
        $aulas->execute([$idOrigem]);

        $insert = $this->pdo->prepare(
            "INSERT INTO quadro_aulas
             (id_quadro, turno, dia_semana, curso, semestre, id_disciplina, modalidade, numero_alunos,
              id_professor, id_laboratorio, horario, bloco, andar, sala, carga_horaria_total, horas_laboratorio)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($aulas->fetchAll() as $aula) {
            $insert->execute([
                $novoId, $aula['turno'], $aula['dia_semana'], $aula['curso'], $aula['semestre'],
                $aula['id_disciplina'], $aula['modalidade'], $aula['numero_alunos'],
                $aula['id_professor'], $aula['id_laboratorio'], $aula['horario'],
                $aula['bloco'], $aula['andar'], $aula['sala'],
                $aula['carga_horaria_total'], $aula['horas_laboratorio'],
            ]);
        }

        $this->pdo->commit();
    }

    public function aulasDoQuadro(int $idQuadro): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT qa.*, p.nome as prof_nome, d.nome as disc_nome, l.nome as lab_nome,
                        l.localizacao as lab_local, l.andar as lab_andar
                 FROM quadro_aulas qa
                 LEFT JOIN usuarios p    ON qa.id_professor  = p.id
                 JOIN disciplinas d      ON qa.id_disciplina = d.id
                 LEFT JOIN laboratorios l ON qa.id_laboratorio = l.id
                 WHERE qa.id_quadro = ?
                 ORDER BY FIELD(qa.turno,'Matutino','Vespertino','Noturno'), qa.horario, qa.curso, qa.semestre"
            );
            $stmt->execute([$idQuadro]);
            return $stmt->fetchAll();
        } catch (Exception) {
            return [];
        }
    }

    public function salvarAula(array $dados): void
    {
        $this->pdo->prepare(
            "INSERT INTO quadro_aulas
             (id_quadro, turno, dia_semana, curso, semestre, id_disciplina, modalidade, numero_alunos,
              id_professor, id_laboratorio, horario, bloco, andar, sala, carga_horaria_total, horas_laboratorio)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $dados['id_quadro'], $dados['turno'], $dados['dia_semana'], $dados['curso'],
            $dados['semestre'], $dados['id_disciplina'], $dados['modalidade'], $dados['numero_alunos'],
            $dados['id_professor'], $dados['id_laboratorio'], $dados['horario'],
            $dados['bloco'], $dados['andar'], $dados['sala'],
            $dados['carga_horaria_total'], $dados['horas_laboratorio'],
        ]);
    }

    public function editarAula(int $idAula, array $dados): void
    {
        $this->pdo->prepare(
            "UPDATE quadro_aulas SET turno=?, dia_semana=?, curso=?, semestre=?, id_disciplina=?,
             modalidade=?, numero_alunos=?, id_professor=?, id_laboratorio=?, horario=?,
             bloco=?, andar=?, sala=?, carga_horaria_total=?, horas_laboratorio=? WHERE id=?"
        )->execute([
            $dados['turno'], $dados['dia_semana'], $dados['curso'], $dados['semestre'],
            $dados['id_disciplina'], $dados['modalidade'], $dados['numero_alunos'],
            $dados['id_professor'], $dados['id_laboratorio'], $dados['horario'],
            $dados['bloco'], $dados['andar'], $dados['sala'],
            $dados['carga_horaria_total'], $dados['horas_laboratorio'],
            $idAula,
        ]);
    }

    public function excluirAula(int $idAula): void
    {
        $this->pdo->prepare("DELETE FROM quadro_aulas WHERE id=?")->execute([$idAula]);
    }

    public function moverAula(int $idAula, string $novoDia): void
    {
        $this->pdo->prepare("UPDATE quadro_aulas SET dia_semana=? WHERE id=?")->execute([$novoDia, $idAula]);
    }
}
