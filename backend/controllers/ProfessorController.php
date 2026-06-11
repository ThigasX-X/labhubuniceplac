<?php
require_once BACKEND_PATH . '/models/Agendamento.php';
require_once BACKEND_PATH . '/models/ControleChave.php';
require_once BACKEND_PATH . '/models/ChamadoSuporte.php';
require_once BACKEND_PATH . '/helpers/Auth.php';
require_once BACKEND_PATH . '/helpers/Upload.php';
require_once BACKEND_PATH . '/helpers/HorarioHelper.php';

class ProfessorController extends BaseController
{
    public function index(): void
    {
        $this->requireAuth('professor');

        $idProfessor = Auth::id();
        $mensagem    = '';
        $abaAtiva    = 'sessao-calendario';

        $agendamento   = new Agendamento($this->pdo);
        $chaveModel    = new ControleChave($this->pdo);
        $chamadoModel  = new ChamadoSuporte($this->pdo);

        // --- UPLOAD DE FOTO ---
        if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
            require_once BACKEND_PATH . '/DAOImpl/UsuarioDAOImpl.php';
            $caminho = Upload::foto($_FILES['nova_foto'], $idProfessor, $_SESSION['foto_perfil'] ?? null);
            if ($caminho) {
                (new UsuarioDAOImpl($this->pdo))->updateFoto($idProfessor, $caminho);
                $_SESSION['foto_perfil'] = $caminho;
                $mensagem = '<div class="alert alert-success alert-autohide mb-4">Foto atualizada!</div>';
            }
        }

        // --- RETIRADA DE CHAVE ---
        if ($this->isPost() && isset($_POST['registrar_retirada'])) {
            $abaAtiva      = 'sessao-dashboard';
            $idAgendamento = (int) ($_POST['id_agendamento'] ?? 0);
            // Valida que a aula pertence ao professor logado (lab e turno vêm do banco, não do POST)
            $aulaChave = $this->buscarAulaParaChave($idAgendamento, $idProfessor);

            $horaAtual  = date('H:i');
            $podeRetirar = $aulaChave && match ($aulaChave['turno']) {
                'Matutino'   => $horaAtual >= '07:00' && $horaAtual <= '12:30',
                'Vespertino' => $horaAtual >= '13:00' && $horaAtual <= '18:30',
                'Noturno'    => $horaAtual >= '18:00' && $horaAtual <= '23:00',
                default      => false,
            };

            if (!$aulaChave) {
                $mensagem = '<div class="alert alert-danger alert-autohide mb-4">'
                    . '<i class="bi bi-shield-x me-2"></i>Reserva não encontrada ou não pertence a você.</div>';
            } elseif (!$podeRetirar) {
                $mensagem = '<div class="alert alert-warning alert-autohide rounded-0 border-start border-4 border-warning mb-4">'
                    . '<i class="bi bi-clock-fill me-2"></i><strong>Acesso Negado:</strong> Retirada não permitida neste horário ('
                    . $horaAtual . ').</div>';
            } else {
                try {
                    $chaveModel->registrarRetirada(
                        $idAgendamento,
                        Auth::nome(),
                        $aulaChave['laboratorio'],
                        $_POST['celular'],
                        $_POST['hora_devolucao_prevista'],
                        $_POST['funcionario_entrega']
                    );
                    $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-start border-4 border-success mb-4">'
                        . '<i class="bi bi-key-fill me-2"></i>Retirada registrada com sucesso!</div>';
                } catch (PDOException) {
                    $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao registrar retirada.</div>';
                }
            }
        }

        // --- SOS ---
        if ($this->isPost() && isset($_POST['acao_sos'])) {
            $abaAtiva = 'sessao-dashboard';
            try {
                $chamadoModel->abrir($idProfessor, Auth::nome(), $_POST['laboratorio_sos'], trim($_POST['mensagem_sos']));
                $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-start border-4 border-success mb-4">'
                    . '<i class="bi bi-check-circle-fill me-2"></i><strong>SOS Enviado!</strong> Suporte notificado.</div>';
            } catch (PDOException) {
                $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao enviar SOS.</div>';
            }
        }

        // --- SOLICITAR AGENDAMENTO ---
        if ($this->isPost() && isset($_POST['data_reserva'])) {
            $abaAtiva = 'sessao-solicitar';
            $conflito = HorarioHelper::verificaChoque(
                $this->pdo,
                (int) $_POST['id_laboratorio'],
                $_POST['data_reserva'],
                $_POST['turno'],
                $_POST['periodo']
            );

            if ($conflito) {
                $mensagem = '<div class="alert alert-warning alert-autohide rounded-0 border-start border-4 border-warning mb-4">'
                    . '<i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Não foi possível solicitar:</strong> '
                    . $conflito . '</div>';
            } else {
                try {
                    $agendamento->solicitarReserva(
                        (int) $_POST['id_laboratorio'],
                        $idProfessor,
                        (int) $_POST['id_disciplina'],
                        $_POST['turno'],
                        $_POST['periodo'],
                        $_POST['data_reserva']
                    );
                    $mensagem = '<div class="alert alert-info alert-autohide mb-4">'
                        . '<i class="bi bi-info-circle-fill me-2"></i><strong>Solicitação enviada!</strong> Aguarde a coordenação.</div>';
                    $abaAtiva = 'sessao-historico';
                } catch (PDOException $e) {
                    $mensagem = $e->getCode() == 23000
                        ? '<div class="alert alert-warning alert-autohide mb-4">Você já tem reserva pendente para este lab/dia/turno.</div>'
                        : '<div class="alert alert-danger alert-autohide mb-4">Erro ao solicitar.</div>';
                }
            }
        }

        // --- DADOS ---
        $laboratorios = $agendamento->buscarLaboratorios();
        $disciplinas  = $agendamento->buscarDisciplinas();

        $minhasAlocacoes = $agendamento->listarAlocacoesProfessor($idProfessor);
        $chavesRetiradas = $chaveModel->chavesPorProfessor(Auth::nome());

        // Integração com quadro fixo
        $meuEnsalamento = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT e.id, e.categoria, e.turno,
                        d.nome  AS disciplina,
                        c.nome  AS curso,
                        s.nome  AS sala,
                        a.nome  AS andar,
                        b.nome  AS bloco
                 FROM ensalamento e
                 JOIN disciplinas d ON e.id_disciplina = d.id
                 JOIN cursos      c ON e.id_curso      = c.id
                 JOIN salas       s ON e.id_sala       = s.id
                 JOIN andares     a ON s.id_andar      = a.id
                 JOIN blocos      b ON a.id_bloco      = b.id
                 WHERE e.id_professor = ?
                 ORDER BY FIELD(e.turno,'Matutino','Vespertino','Noturno'), c.nome"
            );
            $stmt->execute([$idProfessor]);
            $meuEnsalamento = $stmt->fetchAll();
        } catch (Exception) {}

        $idQuadroAtivo = false;
        try {
            $idQuadroAtivo = $this->pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn();
        } catch (Exception) {}

        $hoje   = date('Y-m-d');
        $hojeEn = date('l');
        $mapEn  = ['Segunda' => 'Monday', 'Terça' => 'Tuesday', 'Quarta' => 'Wednesday', 'Quinta' => 'Thursday', 'Sexta' => 'Friday', 'Sábado' => 'Saturday'];

        if ($idQuadroAtivo) {
            // Aulas práticas (laboratórios) da grade fixa
            $stmt = $this->pdo->prepare(
                "SELECT qa.id,
                        l.nome AS laboratorio, l.localizacao AS lab_local, l.andar AS lab_andar,
                        d.nome AS disciplina,
                        qa.turno, qa.horario AS periodo, qa.dia_semana,
                        b.nome AS bloco, a.nome AS andar, s.nome AS sala
                 FROM quadro_aulas qa
                 JOIN laboratorios l ON qa.id_laboratorio = l.id
                 JOIN disciplinas  d ON qa.id_disciplina  = d.id
                 LEFT JOIN salas   s ON qa.id_sala        = s.id
                 LEFT JOIN andares a ON s.id_andar        = a.id
                 LEFT JOIN blocos  b ON a.id_bloco        = b.id
                 WHERE qa.id_quadro = ? AND qa.id_professor = ? AND qa.id_laboratorio IS NOT NULL"
            );
            $stmt->execute([$idQuadroAtivo, $idProfessor]);
            foreach ($stmt->fetchAll() as $mlf) {
                $mlf['id'] = $mlf['id'] + 1000000;
                $strDia    = $mapEn[$mlf['dia_semana']] ?? 'Monday';
                $mlf['data_reserva'] = ($hojeEn === $strDia) ? date('Y-m-d') : date('Y-m-d', strtotime('next ' . $strDia));
                $mlf['status']       = 'aprovado';
                $minhasAlocacoes[]   = $mlf;
            }

            // Ensalamentos da grade fixa
            $stmt = $this->pdo->prepare(
                "SELECT qa.id, qa.turno, qa.modalidade, qa.dia_semana,
                        d.nome AS disciplina,
                        c.nome AS curso,
                        s.nome AS sala,
                        a.nome AS andar,
                        b.nome AS bloco
                 FROM quadro_aulas qa
                 JOIN disciplinas d ON qa.id_disciplina = d.id
                 JOIN cursos      c ON qa.id_curso      = c.id
                 JOIN salas       s ON qa.id_sala       = s.id
                 JOIN andares     a ON s.id_andar       = a.id
                 JOIN blocos      b ON a.id_bloco       = b.id
                 WHERE qa.id_quadro = ? AND qa.id_professor = ? AND qa.id_sala IS NOT NULL"
            );
            $stmt->execute([$idQuadroAtivo, $idProfessor]);
            foreach ($stmt->fetchAll() as $sf) {
                $meuEnsalamento[] = [
                    'id'         => 'q_' . $sf['id'],
                    'disciplina' => $sf['disciplina'],
                    'turno'      => $sf['turno'],
                    'curso'      => $sf['curso'],
                    'categoria'  => $sf['modalidade'] . ' (' . $sf['dia_semana'] . ')',
                    'bloco'      => $sf['bloco'],
                    'andar'      => $sf['andar'],
                    'sala'       => $sf['sala'],
                ];
            }
        }

        // Calendário — eventos JSON
        $eventosCalendario = [];

        $stmt = $this->pdo->prepare(
            "SELECT a.*, d.nome as disc_nome, l.nome as lab_nome
             FROM agendamentos a
             JOIN disciplinas d  ON a.id_disciplina  = d.id
             JOIN laboratorios l ON a.id_laboratorio = l.id
             WHERE a.id_professor = ? AND a.status = 'aprovado'"
        );
        $stmt->execute([$idProfessor]);
        foreach ($stmt->fetchAll() as $av) {
            [$start, $end]       = HorarioHelper::converter($av['turno'], $av['periodo']);
            $eventosCalendario[] = [
                'title'         => $av['disc_nome'],
                'start'         => $av['data_reserva'] . 'T' . $start,
                'end'           => $av['data_reserva'] . 'T' . $end,
                'className'     => 'apple-event-avulsa',
                'extendedProps' => ['local' => '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($av['lab_nome'])],
            ];
        }

        if ($idQuadroAtivo) {
            $diasMapNum = ['Domingo' => 0, 'Segunda' => 1, 'Terça' => 2, 'Quarta' => 3, 'Quinta' => 4, 'Sexta' => 5, 'Sábado' => 6];
            $anoAtual   = date('Y');
            $mesAtual   = (int) date('m');
            $startRecur = $mesAtual <= 6 ? "{$anoAtual}-01-01" : "{$anoAtual}-07-01";
            $endRecur   = $mesAtual <= 6 ? "{$anoAtual}-07-31" : "{$anoAtual}-12-31";

            $stmt = $this->pdo->prepare(
                "SELECT qa.*,
                        d.nome  AS disc_nome,
                        l.nome  AS lab_nome, l.localizacao AS lab_local, l.andar AS lab_andar,
                        s.nome  AS sala,
                        a.nome  AS andar,
                        b.nome  AS bloco
                 FROM quadro_aulas qa
                 JOIN disciplinas d ON qa.id_disciplina = d.id
                 LEFT JOIN laboratorios l ON qa.id_laboratorio = l.id
                 LEFT JOIN salas       s ON qa.id_sala        = s.id
                 LEFT JOIN andares     a ON s.id_andar        = a.id
                 LEFT JOIN blocos      b ON a.id_bloco        = b.id
                 WHERE qa.id_quadro = ? AND qa.id_professor = ?"
            );
            $stmt->execute([$idQuadroAtivo, $idProfessor]);
            foreach ($stmt->fetchAll() as $f) {
                [$start, $end] = HorarioHelper::converter($f['turno'], $f['horario']);
                $diaN = $diasMapNum[$f['dia_semana']] ?? 1;

                $locParts = [];
                if ($f['id_laboratorio']) {
                    $labInfo = '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($f['lab_nome']);
                    $det     = array_filter([
                        !empty($f['lab_local']) ? htmlspecialchars($f['lab_local']) : null,
                        !empty($f['lab_andar']) ? 'Andar ' . htmlspecialchars($f['lab_andar']) : null,
                    ]);
                    if ($det) $labInfo .= ' (' . implode(' - ', $det) . ')';
                    $locParts[] = $labInfo;
                }
                if (!empty($f['sala']) || !empty($f['bloco'])) {
                    $locParts[] = '<i class="bi bi-door-open me-1"></i> Sala: ' . htmlspecialchars($f['sala'] ?? '')
                        . ' (Bl ' . htmlspecialchars($f['bloco'] ?? '') . ')';
                }
                $loc = implode('<br>', $locParts) ?: '<i class="bi bi-geo-alt me-1"></i> A definir';

                $eventosCalendario[] = [
                    'title'         => $f['disc_nome'],
                    'daysOfWeek'    => [$diaN],
                    'startTime'     => $start,
                    'endTime'       => $end,
                    'startRecur'    => $startRecur,
                    'endRecur'      => $endRecur,
                    'className'     => 'apple-event-fixa',
                    'extendedProps' => ['local' => $loc],
                ];
            }
        }

        foreach (HorarioHelper::feriados2026() as $data => $nomeFeriado) {
            $eventosCalendario[] = [
                'title'         => 'Feriado: ' . $nomeFeriado,
                'start'         => $data,
                'allDay'        => true,
                'className'     => 'apple-event-feriado',
                'extendedProps' => ['local' => '<i class="bi bi-calendar-x me-1"></i> Instituição Fechada'],
            ];
        }

        $eventosJson = json_encode($eventosCalendario);

        // Agrupamento por turno
        $qtdPendentes    = 0;
        $proximasMatutino = $proximasVespertino = $proximasNoturno = [];

        foreach ($minhasAlocacoes as $al) {
            if ($al['status'] === 'pendente') {
                $qtdPendentes++;
            } elseif ($al['status'] === 'aprovado' && $al['data_reserva'] >= $hoje) {
                match (trim($al['turno'])) {
                    'Matutino'   => $proximasMatutino[]   = $al,
                    'Vespertino' => $proximasVespertino[] = $al,
                    'Noturno'    => $proximasNoturno[]    = $al,
                    default      => null,
                };
            }
        }
        usort($proximasMatutino,   fn($a, $b) => strtotime($a['data_reserva']) - strtotime($b['data_reserva']));
        usort($proximasVespertino, fn($a, $b) => strtotime($a['data_reserva']) - strtotime($b['data_reserva']));
        usort($proximasNoturno,    fn($a, $b) => strtotime($a['data_reserva']) - strtotime($b['data_reserva']));

        $ensalamentoMatutino = $ensalamentoVespertino = $ensalamentoNoturno = [];
        foreach ($meuEnsalamento as $e) {
            match (trim($e['turno'])) {
                'Matutino'   => $ensalamentoMatutino[]   = $e,
                'Vespertino' => $ensalamentoVespertino[] = $e,
                'Noturno'    => $ensalamentoNoturno[]    = $e,
                default      => null,
            };
        }

        $fotoAtual = Auth::foto();

        $this->render('professor/painel_professor', compact(
            'mensagem', 'abaAtiva', 'laboratorios', 'disciplinas',
            'minhasAlocacoes', 'chavesRetiradas',
            'proximasMatutino', 'proximasVespertino', 'proximasNoturno',
            'ensalamentoMatutino', 'ensalamentoVespertino', 'ensalamentoNoturno',
            'qtdPendentes', 'eventosJson', 'hoje', 'fotoAtual'
        ));
    }

    /**
     * Busca a aula (avulsa ou da grade fixa, ids >= 1000000) garantindo
     * que pertence ao professor logado. Retorna ['laboratorio', 'turno'] ou null.
     */
    private function buscarAulaParaChave(int $idAgendamento, int $idProfessor): ?array
    {
        if ($idAgendamento >= 1000000) {
            $stmt = $this->pdo->prepare(
                "SELECT l.nome AS laboratorio, qa.turno
                 FROM quadro_aulas qa
                 JOIN laboratorios l ON qa.id_laboratorio = l.id
                 WHERE qa.id = ? AND qa.id_professor = ?"
            );
            $stmt->execute([$idAgendamento - 1000000, $idProfessor]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT l.nome AS laboratorio, a.turno
                 FROM agendamentos a
                 JOIN laboratorios l ON a.id_laboratorio = l.id
                 WHERE a.id = ? AND a.id_professor = ? AND a.status = 'aprovado'"
            );
            $stmt->execute([$idAgendamento, $idProfessor]);
        }

        return $stmt->fetch() ?: null;
    }
}
