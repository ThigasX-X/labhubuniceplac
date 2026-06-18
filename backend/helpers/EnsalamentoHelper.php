<?php

class EnsalamentoHelper
{
    /**
     * Mapa unificado: ensalamento cadastrado + salas do quadro de horários ativo.
     */
    public static function mapaCentralizado(PDO $pdo, ?int $idQuadro = null): array
    {
        $itens = [];

        $sqlEnsal = "SELECT e.id,
                            u.nome   AS professor,
                            d.nome   AS disciplina,
                            c.nome   AS curso,
                            sem.nome AS turma,
                            e.categoria,
                            e.turno,
                            b.nome   AS bloco,
                            a.nome   AS andar,
                            s.nome   AS sala,
                            'ensalamento' AS origem
                     FROM ensalamento e
                     JOIN usuarios    u   ON e.id_professor  = u.id
                     JOIN disciplinas d   ON e.id_disciplina = d.id
                     JOIN cursos      c   ON e.id_curso      = c.id
                     JOIN salas       s   ON e.id_sala       = s.id
                     JOIN andares     a   ON s.id_andar      = a.id
                     JOIN blocos      b   ON a.id_bloco      = b.id
                     LEFT JOIN semestres sem ON e.id_semestre = sem.id
                     ORDER BY b.nome, a.nome, s.nome, e.turno";

        try {
            foreach ($pdo->query($sqlEnsal)->fetchAll() as $row) {
                $itens[] = $row;
            }
        } catch (PDOException) {}

        if ($idQuadro === null) {
            try {
                $idQuadro = (int) $pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn();
            } catch (PDOException) {
                $idQuadro = 0;
            }
        }

        if ($idQuadro > 0) {
            $sqlQuadro = "SELECT qa.id,
                                 COALESCE(u.nome, 'EAD') AS professor,
                                 d.nome   AS disciplina,
                                 c.nome   AS curso,
                                 sem.nome AS turma,
                                 qa.modalidade AS categoria,
                                 qa.turno,
                                 b.nome   AS bloco,
                                 a.nome   AS andar,
                                 s.nome   AS sala,
                                 qa.dia_semana,
                                 'quadro' AS origem
                          FROM quadro_aulas qa
                          JOIN disciplinas d  ON qa.id_disciplina = d.id
                          JOIN cursos      c  ON qa.id_curso      = c.id
                          JOIN semestres   sem ON qa.id_semestre  = sem.id
                          JOIN salas       s  ON qa.id_sala       = s.id
                          JOIN andares     a  ON s.id_andar       = a.id
                          JOIN blocos      b  ON a.id_bloco       = b.id
                          LEFT JOIN usuarios u ON qa.id_professor = u.id
                          WHERE qa.id_quadro = ? AND qa.id_sala IS NOT NULL
                          ORDER BY b.nome, a.nome, s.nome, qa.turno, qa.dia_semana";

            try {
                $stmt = $pdo->prepare($sqlQuadro);
                $stmt->execute([$idQuadro]);
                foreach ($stmt->fetchAll() as $row) {
                    $row['categoria'] = trim(($row['categoria'] ?? '') . ' · ' . ($row['dia_semana'] ?? ''), ' ·');
                    unset($row['dia_semana']);
                    $itens[] = $row;
                }
            } catch (PDOException) {}
        }

        usort($itens, fn($a, $b) => [$a['bloco'], $a['andar'], $a['sala'], $a['turno']]
            <=> [$b['bloco'], $b['andar'], $b['sala'], $b['turno']]);

        return $itens;
    }
}
