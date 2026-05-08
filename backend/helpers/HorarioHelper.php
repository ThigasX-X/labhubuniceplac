<?php
class HorarioHelper
{
    /**
     * Converte turno + período nos horários de início e fim.
     */
    public static function converter(string $turno, string $periodo): array
    {
        if ($turno === 'Matutino') {
            return match ($periodo) {
                '1º Horário' => ['08:20:00', '10:00:00'],
                '2º Horário' => ['10:15:00', '11:55:00'],
                default      => ['08:20:00', '11:55:00'],
            };
        }

        if ($turno === 'Vespertino') {
            return match ($periodo) {
                '1º Horário' => ['14:20:00', '16:00:00'],
                '2º Horário' => ['16:20:00', '18:00:00'],
                default      => ['14:20:00', '18:00:00'],
            };
        }

        // Noturno
        return match ($periodo) {
            '1º Horário' => ['19:20:00', '21:00:00'],
            '2º Horário' => ['21:10:00', '22:50:00'],
            default      => ['19:20:00', '22:50:00'],
        };
    }

    /**
     * Verifica choque de horário entre reservas avulsas e grade fixa.
     * Retorna mensagem de erro ou false se não houver conflito.
     */
    public static function verificaChoque(
        PDO    $pdo,
        int    $idLab,
        string $dataReserva,
        string $turno,
        string $periodo,
        ?int   $ignorarId = null
    ): string|false {
        // 1. Checa agendamentos avulsos aprovados
        $sql = "SELECT periodo FROM agendamentos
                WHERE id_laboratorio = ? AND data_reserva = ? AND turno = ? AND status = 'aprovado'";
        $params = [$idLab, $dataReserva, $turno];

        if ($ignorarId) {
            $sql .= ' AND id != ?';
            $params[] = $ignorarId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $avulsos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($avulsos as $pBanco) {
            if ($periodo === '1º e 2º Horários' || $pBanco === '1º e 2º Horários' || $periodo === $pBanco) {
                return 'Já existe uma reserva aprovada para este laboratório nesse dia e horário.';
            }
        }

        // 2. Checa grade fixa
        $diasMap  = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
        $diaSemana = $diasMap[date('w', strtotime($dataReserva))];

        try {
            $idQuadro = $pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn();
        } catch (Exception) {
            $idQuadro = false;
        }

        if ($idQuadro) {
            $stmt = $pdo->prepare("SELECT horario FROM quadro_aulas WHERE id_quadro = ? AND id_laboratorio = ? AND dia_semana = ? AND turno = ?");
            $stmt->execute([$idQuadro, $idLab, $diaSemana, $turno]);
            $fixos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($fixos as $hFixo) {
                if ($periodo === '1º e 2º Horários' || $hFixo === '1º e 2º Horários' || $periodo === $hFixo) {
                    return "A Grade Fixa já ocupa este laboratório toda {$diaSemana} ({$turno}).";
                }
            }
        }

        return false;
    }

    public static function feriados2026(): array
    {
        return [
            '2026-01-01' => 'Ano Novo',
            '2026-02-16' => 'Recesso de Carnaval',
            '2026-02-17' => 'Carnaval',
            '2026-04-03' => 'Paixão de Cristo',
            '2026-04-21' => 'Tiradentes',
            '2026-05-01' => 'Dia do Trabalho',
            '2026-06-04' => 'Corpus Christi',
            '2026-09-07' => 'Independência do Brasil',
            '2026-10-12' => 'Nossa Sra. Aparecida',
            '2026-11-02' => 'Finados',
            '2026-11-15' => 'Proclamação da República',
            '2026-12-25' => 'Natal',
        ];
    }
}
