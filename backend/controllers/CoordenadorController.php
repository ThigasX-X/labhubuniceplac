<?php
require_once BACKEND_PATH . '/models/Agendamento.php';
require_once BACKEND_PATH . '/models/Laboratorio.php';
require_once BACKEND_PATH . '/models/Disciplina.php';
require_once BACKEND_PATH . '/DAOImpl/UsuarioDAOImpl.php';
require_once BACKEND_PATH . '/DAOImpl/QuadroHorarioDAOImpl.php';
require_once BACKEND_PATH . '/models/ChamadoSuporte.php';
require_once BACKEND_PATH . '/helpers/Auth.php';
require_once BACKEND_PATH . '/helpers/Upload.php';
require_once BACKEND_PATH . '/helpers/HorarioHelper.php';
require_once BACKEND_PATH . '/helpers/EnsalamentoHelper.php';

class CoordenadorController extends BaseController
{
    public function kanban(): void
    {
        $this->requireAuth('coordenador');
        $this->json($this->moverAula());
    }

    public function index(): void
    {
        $this->requireAuth('coordenador');

        // AJAX: Drag & Drop do Kanban
        if ($this->isPost() && isset($_POST['action']) && $_POST['action'] === 'mover_aula') {
            $this->json($this->moverAula());
        }

        $idUsuario = Auth::id();
        $mensagem  = $this->consumeFlash();

        $agendamentoModel = new Agendamento($this->pdo);
        $labModel         = new Laboratorio($this->pdo);
        $discModel        = new Disciplina($this->pdo);
        $usuarioModel     = new UsuarioDAOImpl($this->pdo);
        $quadroModel      = new QuadroHorarioDAOImpl($this->pdo);

        // --- UPLOAD DE FOTO ---
        if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
            $caminho = Upload::foto($_FILES['nova_foto'], $idUsuario, $_SESSION['foto_perfil'] ?? null);
            if ($caminho) {
                $usuarioModel->updateFoto($idUsuario, $caminho);
                $_SESSION['foto_perfil'] = $caminho;
                $mensagem = '<div class="alert alert-success alert-autohide mb-4">Foto atualizada!</div>';
            }
        }

        // --- QUADRO DE HORÁRIOS ---
        if ($this->isPost() && isset($_POST['criar_quadro'])) {
            try {
                $quadroModel->criar(trim($_POST['nome_quadro']), trim($_POST['periodo_letivo']));
                $mensagem = '<div class="alert alert-success alert-autohide mb-4">Cenário de Quadro criado!</div>';
            } catch (PDOException $e) {
                $mensagem = '<div class="alert alert-danger mb-4">Erro: ' . $e->getMessage() . '</div>';
            }
        }
        if ($this->isPost() && isset($_POST['excluir_quadro'])) {
            $quadroModel->excluir((int) $_POST['id_quadro']);
            $mensagem = '<div class="alert alert-info alert-autohide mb-4">Quadro excluído.</div>';
        }
        if ($this->isPost() && isset($_POST['duplicar_quadro'])) {
            try {
                $quadroModel->duplicar((int) $_POST['id_quadro_origem'], trim($_POST['novo_nome_quadro']), trim($_POST['novo_periodo_letivo']));
                $mensagem = '<div class="alert alert-success alert-autohide mb-4">Cenário duplicado!</div>';
            } catch (PDOException $e) {
                $mensagem = '<div class="alert alert-danger mb-4">Erro ao duplicar: ' . $e->getMessage() . '</div>';
            }
        }

        // --- AULAS DO QUADRO ---
        if ($this->isPost() && (isset($_POST['salvar_aula_quadro']) || isset($_POST['editar_aula_quadro']))) {
            $mensagem = $this->processarAulaQuadro($quadroModel);
        }
        if ($this->isPost() && isset($_POST['excluir_aula_quadro'])) {
            $quadroModel->excluirAula((int) $_POST['id_aula_q']);
            $mensagem = '<div class="alert alert-info alert-autohide mb-4">Aula removida do quadro.</div>';
        }

        // --- APROVAR/REJEITAR RESERVAS ---
        if ($this->isPost() && isset($_POST['acao_reserva'])) {
            $idAg = (int) $_POST['id_agendamento'];
            if ($_POST['acao_reserva'] === 'aprovar') {
                $ag = $agendamentoModel->buscarPorId($idAg);
                $conflito = HorarioHelper::verificaChoque($this->pdo, $ag['id_laboratorio'], $ag['data_reserva'], $ag['turno'], $ag['periodo'], $idAg);
                if ($conflito) {
                    $mensagem = "<div class='alert alert-warning alert-autohide mb-4'><strong>Aprovação Bloqueada:</strong> $conflito</div>";
                } else {
                    $agendamentoModel->atualizarStatus($idAg, 'aprovado');
                    $mensagem = "<div class='alert alert-success alert-autohide mb-4'>Reserva Aprovada!</div>";
                }
            } else {
                $agendamentoModel->atualizarStatus($idAg, 'rejeitado');
                $mensagem = "<div class='alert alert-danger alert-autohide mb-4'>Reserva Rejeitada!</div>";
            }
        }

        // --- AGENDAMENTOS AVULSOS ---
        if ($this->isPost() && isset($_POST['agendar_lab_coord'])) {
            $mensagem = $this->processarAgendamento($agendamentoModel);
        }
        if ($this->isPost() && isset($_POST['editar_agendamento_coord'])) {
            $mensagem = $this->processarEdicaoAgendamento($agendamentoModel);
        }
        if ($this->isPost() && isset($_POST['cancelar_agendamento'])) {
            try {
                $agendamentoModel->excluir((int) $_POST['id_agendamento']);
                $mensagem = '<div class="alert alert-warning alert-autohide mb-4">Agendamento cancelado.</div>';
            } catch (PDOException) {
                $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao cancelar.</div>';
            }
        }

        // --- CADASTROS BASE ---
        $mensagem = $this->processarCadastrosBase($mensagem);

        // --- ENSALAMENTO ---
        $mensagem = $this->processarEnsalamento($mensagem);

        if ($this->isPost()) {
            $params = [];
            if (!empty($_POST['id_quadro_ativo'])) {
                $params['q_id'] = $_POST['id_quadro_ativo'];
            }
            $this->finishPostRedirect('coordenador', $this->definirAbaAtiva(), $params, $mensagem);
        }

        // --- DADOS ---
        $reservasPendentes    = $agendamentoModel->listarSolicitacoesPendentes();
        $agendamentosAprovados = $agendamentoModel->listarReservasConfirmadas();
        $historicoCompleto    = $agendamentoModel->listarHistoricoCompleto();
        $qtdPendentes         = count($reservasPendentes);

        $professores            = $usuarioModel->listProfessores();
        $laboratoriosCadastrados = $labModel->all();
        $disciplinas            = $discModel->all();

        $cursosCadastrados  = $this->pdo->query("SELECT * FROM cursos ORDER BY nome")->fetchAll();
        $semestres          = $this->pdo->query("SELECT * FROM semestres ORDER BY nome")->fetchAll();
        $blocosCadastrados  = $this->pdo->query("SELECT * FROM blocos ORDER BY nome")->fetchAll();
        $andaresCadastrados = $this->pdo->query(
            "SELECT a.*, b.nome AS bloco_nome
             FROM andares a JOIN blocos b ON a.id_bloco = b.id
             ORDER BY b.nome, a.nome"
        )->fetchAll();
        $salasCadastradas   = $this->pdo->query(
            "SELECT s.*, a.nome AS andar_nome, b.nome AS bloco_nome, a.id_bloco
             FROM salas s
             JOIN andares a ON s.id_andar = a.id
             JOIN blocos  b ON a.id_bloco = b.id
             ORDER BY b.nome, a.nome, s.nome"
        )->fetchAll();

        $listaEnsalamentos = $this->pdo->query(
            "SELECT e.*,
                    u.nome   AS professor,
                    d.nome   AS disciplina,
                    c.nome   AS curso,
                    sem.nome AS turma,
                    s.nome   AS sala,
                    a.nome   AS andar,
                    b.nome   AS bloco,
                    a.id     AS id_andar,
                    b.id     AS id_bloco
             FROM ensalamento e
             JOIN usuarios    u   ON e.id_professor  = u.id
             JOIN disciplinas d   ON e.id_disciplina = d.id
             JOIN cursos      c   ON e.id_curso      = c.id
             JOIN salas       s   ON e.id_sala       = s.id
             JOIN andares     a   ON s.id_andar      = a.id
             JOIN blocos      b   ON a.id_bloco      = b.id
             LEFT JOIN semestres sem ON e.id_semestre = sem.id
             ORDER BY c.nome, e.turno, b.nome, a.nome, s.nome"
        )->fetchAll();

        $listaQuadros     = $quadroModel->all();
        // Preserva o quadro que estava sendo editado após um POST (ex.: nova aula),
        // senão a tela voltaria para o quadro mais recente da lista.
        $quadroSelecionado = $_GET['q_id']
            ?? ($_POST['id_quadro_ativo'] ?? null)
            ?? (count($listaQuadros) > 0 ? $listaQuadros[0]['id'] : null);

        $mapaEnsalamento = EnsalamentoHelper::mapaCentralizado(
            $this->pdo,
            $quadroSelecionado ? (int) $quadroSelecionado : null
        );

        $todasAulas   = [];
        $aulasDiaSemana = ['Segunda' => [], 'Terça' => [], 'Quarta' => [], 'Quinta' => [], 'Sexta' => [], 'Sábado' => []];

        if ($quadroSelecionado) {
            $todasAulas = $quadroModel->aulasDoQuadro((int) $quadroSelecionado);
            foreach ($todasAulas as $a) {
                $aulasDiaSemana[$a['dia_semana']][] = $a;
            }
        }

        // Relatórios BI
        $relatorioProfessores = $relatorioLabs = [];
        $graficoProfNomes = $graficoProfHoras = $graficoProfLab = $graficoProfSala = [];
        $graficoLabNomes = $graficoLabUso = $graficoLabOcioso = [];
        $graficoNomeCursos = $graficoHorasCursos = [];
        $taxaOcupacaoGlobal = $taxaOciosidadeGlobal = $usoGlobal = 0;
        $labMaisUsado  = ['nome' => '-', 'horas' => 0];
        $labMaisOcioso = ['nome' => '-', 'horas' => 0];

        if ($quadroSelecionado) {
            [$relatorioProfessores, $relatorioLabs,
             $graficoProfNomes, $graficoProfHoras, $graficoProfLab, $graficoProfSala,
             $graficoLabNomes, $graficoLabUso, $graficoLabOcioso,
             $graficoNomeCursos, $graficoHorasCursos,
             $taxaOcupacaoGlobal, $taxaOciosidadeGlobal, $usoGlobal,
             $labMaisUsado, $labMaisOcioso] = $this->calcularRelatoriosBI((int) $quadroSelecionado, $laboratoriosCadastrados);
        }

        // Calendário
        $eventosCalendario = [];
        foreach ($agendamentosAprovados as $av) {
            [$start, $end] = HorarioHelper::converter($av['turno'], $av['periodo']);
            $eventosCalendario[] = [
                'title'         => $av['disciplina'] . ' (' . $av['professor'] . ')',
                'start'         => $av['data_reserva'] . 'T' . $start,
                'end'           => $av['data_reserva'] . 'T' . $end,
                'className'     => 'apple-event-avulsa',
                'extendedProps' => ['local' => '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($av['laboratorio'])],
            ];
        }

        if ($quadroSelecionado && count($todasAulas) > 0) {
            $diasMapNum = ['Domingo' => 0, 'Segunda' => 1, 'Terça' => 2, 'Quarta' => 3, 'Quinta' => 4, 'Sexta' => 5, 'Sábado' => 6];
            $anoAtual   = date('Y');
            $mesAtual   = (int) date('m');
            $startRecur = $mesAtual <= 6 ? "{$anoAtual}-01-01" : "{$anoAtual}-07-01";
            $endRecur   = $mesAtual <= 6 ? "{$anoAtual}-07-31" : "{$anoAtual}-12-31";

            foreach ($todasAulas as $f) {
                [$start, $end] = HorarioHelper::converter($f['turno'], $f['horario']);
                $diaNum = $diasMapNum[$f['dia_semana']] ?? 1;
                $loc = $f['id_laboratorio']
                    ? '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($f['lab_nome'])
                    : '<i class="bi bi-door-open me-1"></i> Sala: ' . htmlspecialchars($f['sala'] ?? '-');
                $eventosCalendario[] = [
                    'title'         => $f['disc_nome'] . ' (' . ($f['prof_nome'] ?? 'EAD') . ')',
                    'daysOfWeek'    => [$diaNum],
                    'startTime'     => $start,
                    'endTime'       => $end,
                    'startRecur'    => $startRecur,
                    'endRecur'      => $endRecur,
                    'className'     => 'apple-event-fixa',
                    'extendedProps' => ['local' => $loc],
                ];
            }
        }

        foreach (HorarioHelper::feriados2026() as $data => $nome) {
            $eventosCalendario[] = ['title' => 'Feriado: ' . $nome, 'start' => $data, 'allDay' => true, 'className' => 'apple-event-feriado', 'extendedProps' => ['local' => '<i class="bi bi-calendar-x me-1"></i> Instituição Fechada']];
        }

        $eventosJson = json_encode($eventosCalendario);
        $fotoAtual   = Auth::foto();
        $abaAtiva    = $this->definirAbaAtiva();

        $this->render('coordenador/painel', compact(
            'mensagem', 'qtdPendentes', 'fotoAtual', 'abaAtiva',
            'reservasPendentes', 'agendamentosAprovados', 'historicoCompleto',
            'professores', 'laboratoriosCadastrados', 'disciplinas',
            'cursosCadastrados', 'semestres', 'blocosCadastrados', 'andaresCadastrados', 'salasCadastradas',
            'listaEnsalamentos', 'mapaEnsalamento', 'listaQuadros', 'quadroSelecionado',
            'todasAulas', 'aulasDiaSemana',
            'relatorioProfessores', 'relatorioLabs',
            'graficoProfNomes', 'graficoProfHoras', 'graficoProfLab', 'graficoProfSala',
            'graficoLabNomes', 'graficoLabUso', 'graficoLabOcioso',
            'graficoNomeCursos', 'graficoHorasCursos',
            'taxaOcupacaoGlobal', 'taxaOciosidadeGlobal', 'usoGlobal',
            'labMaisUsado', 'labMaisOcioso',
            'eventosJson'
        ));
    }

    // =========================================================================
    // Métodos privados de suporte
    // =========================================================================

    /**
     * Mantém o coordenador na mesma seção após um POST (o form recarrega a
     * página inteira e, sem isto, cairia sempre no calendário).
     */
    private function definirAbaAtiva(): string
    {
        if (!$this->isPost()) {
            return 'calendario';
        }

        $secoes = [
            'quadro'       => ['criar_quadro', 'duplicar_quadro', 'excluir_quadro', 'salvar_aula_quadro', 'editar_aula_quadro', 'excluir_aula_quadro'],
            'ensalamento'  => ['salvar_ensalamento', 'editar_ensalamento', 'excluir_ensalamento'],
            'reservas'     => ['editar_agendamento_coord', 'cancelar_agendamento'],
            'solicitacoes' => ['acao_reserva', 'agendar_lab_coord'],
        ];

        foreach ($secoes as $aba => $campos) {
            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    return $aba;
                }
            }
        }

        foreach (array_keys($_POST) as $campo) {
            if (preg_match('/^(salvar|editar|excluir)_(lab|disciplina|curso|semestre|bloco|andar|sala)$/', $campo)) {
                return 'cadastros';
            }
        }

        return 'calendario';
    }

    private function moverAula(): array
    {
        try {
            (new QuadroHorarioDAOImpl($this->pdo))->moverAula((int) $_POST['id_aula'], $_POST['novo_dia']);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processarAulaQuadro(QuadroHorarioDAOImpl $quadroModel): string
    {
        if (empty($_POST['id_quadro_ativo'])) {
            return '<div class="alert alert-danger alert-autohide mb-4">Selecione um Cenário antes de alocar aulas.</div>';
        }

        $modalidade = $_POST['modalidade'];
        $dados = [
            'id_quadro'           => $_POST['id_quadro_ativo'],
            'turno'               => $_POST['turno_aula'],
            'dia_semana'          => $_POST['dia_semana'],
            'id_curso'            => (int) $_POST['id_curso_aula'],
            'id_semestre'         => (int) $_POST['id_semestre_aula'],
            'id_disciplina'       => (int) $_POST['id_disciplina_aula'],
            'modalidade'          => $modalidade,
            'numero_alunos'       => (int) $_POST['numero_alunos'],
            'id_professor'        => $modalidade === 'EAD' ? null : (empty($_POST['id_professor_aula'])   ? null : (int) $_POST['id_professor_aula']),
            'id_laboratorio'      => $modalidade === 'EAD' ? null : (empty($_POST['id_laboratorio_aula']) ? null : (int) $_POST['id_laboratorio_aula']),
            'id_sala'             => $modalidade === 'EAD' ? null : (empty($_POST['id_sala_aula'])        ? null : (int) $_POST['id_sala_aula']),
            'horario'             => $_POST['horario_aula'],
            'carga_horaria_total' => (int) ($_POST['carga_horaria_total'] ?? 2),
            'horas_laboratorio'   => (int) ($_POST['horas_laboratorio'] ?? 0),
        ];

        $editando = isset($_POST['editar_aula_quadro']);
        $idAulaQ  = $editando ? (int) $_POST['id_aula_q'] : null;

        try {
            if ($editando) {
                $quadroModel->editarAula($idAulaQ, $dados);
            } else {
                $quadroModel->salvarAula($dados);
            }
            return '<div class="alert alert-success alert-autohide mb-4">Grade atualizada!</div>';
        } catch (PDOException $e) {
            return '<div class="alert alert-danger mb-4">Erro: ' . $e->getMessage() . '</div>';
        }
    }

    private function processarAgendamento(Agendamento $model): string
    {
        $idLab   = (int) $_POST['id_laboratorio'];
        $data    = $_POST['data_reserva'];
        $turno   = $_POST['turno'];
        $periodo = $_POST['periodo'];
        $conflito = HorarioHelper::verificaChoque($this->pdo, $idLab, $data, $turno, $periodo);
        if ($conflito) {
            return '<div class="alert alert-warning alert-autohide mb-4"><strong>Bloqueado:</strong> ' . $conflito . '</div>';
        }
        try {
            $model->criarReserva($idLab, (int) $_POST['id_professor'], (int) $_POST['id_disciplina'], $turno, $periodo, $data);
            return '<div class="alert alert-success alert-autohide mb-4">Agendamento criado!</div>';
        } catch (PDOException $e) {
            $msg = $e->getCode() == 23000
                ? 'Já existe reserva para este laboratório nesse dia, turno e período.'
                : 'Erro ao agendar: ' . htmlspecialchars($e->getMessage());
            return '<div class="alert alert-danger alert-autohide mb-4">' . $msg . '</div>';
        }
    }

    private function processarEdicaoAgendamento(Agendamento $model): string
    {
        $idAg    = (int) $_POST['id_agendamento'];
        $idLab   = (int) $_POST['id_laboratorio'];
        $data    = $_POST['data_reserva'];
        $turno   = $_POST['turno'];
        $periodo = $_POST['periodo'];
        $conflito = HorarioHelper::verificaChoque($this->pdo, $idLab, $data, $turno, $periodo, $idAg);
        if ($conflito) {
            return '<div class="alert alert-warning alert-autohide mb-4"><strong>Bloqueado:</strong> ' . $conflito . '</div>';
        }
        try {
            $model->atualizar($idAg, $idLab, (int) $_POST['id_professor'], (int) $_POST['id_disciplina'], $turno, $periodo, $data);
            return '<div class="alert alert-primary alert-autohide mb-4">Agendamento atualizado!</div>';
        } catch (PDOException) {
            return '<div class="alert alert-danger alert-autohide mb-4">Erro ao atualizar.</div>';
        }
    }

    private function processarCadastrosBase(string $mensagem): string
    {
        if (!$this->isPost()) return $mensagem;

        $ops = [
            'salvar_lab'       => fn() => $this->pdo->prepare("INSERT INTO laboratorios (nome,capacidade,localizacao,andar) VALUES(?,?,?,?)")->execute([trim($_POST['nome_lab']),(int)$_POST['capacidade_lab'],trim($_POST['localizacao_lab']),trim($_POST['andar_lab'])]),
            'editar_lab'       => fn() => $this->pdo->prepare("UPDATE laboratorios SET nome=?,capacidade=?,localizacao=?,andar=? WHERE id=?")->execute([trim($_POST['nome_lab']),(int)$_POST['capacidade_lab'],trim($_POST['localizacao_lab']),trim($_POST['andar_lab']),$_POST['id_lab']]),
            'excluir_lab'      => fn() => $this->pdo->prepare("DELETE FROM laboratorios WHERE id=?")->execute([$_POST['id_lab']]),
            'salvar_disciplina'  => fn() => $this->pdo->prepare("INSERT INTO disciplinas(nome) VALUES(?)")->execute([trim($_POST['nome_disciplina'])]),
            'editar_disciplina'  => fn() => $this->pdo->prepare("UPDATE disciplinas SET nome=? WHERE id=?")->execute([trim($_POST['nome_disciplina']),$_POST['id_disciplina']]),
            'excluir_disciplina' => fn() => $this->pdo->prepare("DELETE FROM disciplinas WHERE id=?")->execute([$_POST['id_disciplina']]),
            'salvar_curso'     => fn() => $this->pdo->prepare("INSERT INTO cursos(nome) VALUES(?)")->execute([trim($_POST['nome_curso'])]),
            'editar_curso'     => fn() => $this->pdo->prepare("UPDATE cursos SET nome=? WHERE id=?")->execute([trim($_POST['nome_curso']),$_POST['id_curso']]),
            'excluir_curso'    => fn() => $this->pdo->prepare("DELETE FROM cursos WHERE id=?")->execute([$_POST['id_curso']]),
            'salvar_semestre'  => fn() => $this->pdo->prepare("INSERT INTO semestres(nome) VALUES(?)")->execute([trim($_POST['nome_semestre'])]),
            'editar_semestre'  => fn() => $this->pdo->prepare("UPDATE semestres SET nome=? WHERE id=?")->execute([trim($_POST['nome_semestre']),$_POST['id_semestre']]),
            'excluir_semestre' => fn() => $this->pdo->prepare("DELETE FROM semestres WHERE id=?")->execute([$_POST['id_semestre']]),
            'salvar_bloco'     => fn() => $this->pdo->prepare("INSERT INTO blocos(nome) VALUES(?)")->execute([trim($_POST['nome_bloco'])]),
            'editar_bloco'     => fn() => $this->pdo->prepare("UPDATE blocos SET nome=? WHERE id=?")->execute([trim($_POST['nome_bloco']),$_POST['id_bloco']]),
            'excluir_bloco'    => fn() => $this->pdo->prepare("DELETE FROM blocos WHERE id=?")->execute([$_POST['id_bloco']]),
            'salvar_andar'     => fn() => $this->pdo->prepare("INSERT INTO andares(id_bloco,nome) VALUES(?,?)")->execute([(int)$_POST['id_bloco'],trim($_POST['nome_andar'])]),
            'editar_andar'     => fn() => $this->pdo->prepare("UPDATE andares SET id_bloco=?,nome=? WHERE id=?")->execute([(int)$_POST['id_bloco'],trim($_POST['nome_andar']),$_POST['id_andar']]),
            'excluir_andar'    => fn() => $this->pdo->prepare("DELETE FROM andares WHERE id=?")->execute([$_POST['id_andar']]),
            'salvar_sala'      => fn() => $this->pdo->prepare("INSERT INTO salas(id_andar,nome) VALUES(?,?)")->execute([(int)$_POST['id_andar'],trim($_POST['nome_sala'])]),
            'editar_sala'      => fn() => $this->pdo->prepare("UPDATE salas SET id_andar=?,nome=? WHERE id=?")->execute([(int)$_POST['id_andar'],trim($_POST['nome_sala']),$_POST['id_sala']]),
            'excluir_sala'     => fn() => $this->pdo->prepare("DELETE FROM salas WHERE id=?")->execute([$_POST['id_sala']]),
        ];

        foreach ($ops as $key => $fn) {
            if (isset($_POST[$key])) {
                try {
                    $fn();
                    $verbo = str_starts_with($key, 'salvar')  ? 'criado'
                           : (str_starts_with($key, 'editar') ? 'atualizado' : 'removido');
                    return '<div class="alert alert-success alert-autohide mb-4">Cadastro ' . $verbo . ' com sucesso!</div>';
                } catch (PDOException $e) {
                    $msg = $e->getCode() == 23000
                        ? 'Operação bloqueada: registro está em uso ou já existe.'
                        : 'Erro no cadastro: ' . htmlspecialchars($e->getMessage());
                    return '<div class="alert alert-danger alert-autohide mb-4">' . $msg . '</div>';
                }
            }
        }

        return $mensagem;
    }

    private function processarEnsalamento(string $mensagem): string
    {
        if (!$this->isPost()) return $mensagem;

        if (isset($_POST['salvar_ensalamento']) || isset($_POST['editar_ensalamento'])) {
            $idProfessor  = (int) ($_POST['id_professor']  ?? 0);
            $idDisciplina = (int) ($_POST['id_disciplina'] ?? 0);
            $idCurso      = (int) ($_POST['id_curso']      ?? 0);
            $idSemestre   = (int) ($_POST['id_semestre']   ?? 0);
            $idSala       = (int) ($_POST['id_sala']       ?? 0);
            $turno        = $_POST['turno'] ?? '';

            // Selects desabilitados (cascata bloco→andar→sala) não são enviados pelo navegador
            if ($idProfessor <= 0 || $idDisciplina <= 0 || $idCurso <= 0 || $idSemestre <= 0 || $idSala <= 0 || $turno === '') {
                return '<div class="alert alert-warning alert-autohide mb-4">Preencha todos os campos — selecione Bloco, Andar, Sala e Turma.</div>';
            }

            $editando = isset($_POST['editar_ensalamento']);
            $idEnsalamento = $editando ? (int) $_POST['id_ensalamento'] : null;

            $sql = "SELECT u.nome AS prof_existente
                    FROM ensalamento e
                    JOIN usuarios u ON e.id_professor = u.id
                    WHERE e.id_sala = ? AND e.turno = ?";
            $params = [$idSala, $turno];
            if ($editando) { $sql .= " AND e.id != ?"; $params[] = $idEnsalamento; }
            $check = $this->pdo->prepare($sql);
            $check->execute($params);
            if ($c = $check->fetch()) {
                return '<div class="alert alert-warning alert-autohide mb-4"><strong>Choque de Sala!</strong> Em uso por Prof. ' . htmlspecialchars($c['prof_existente']) . '.</div>';
            }

            try {
                if ($editando) {
                    $this->pdo->prepare("UPDATE ensalamento SET id_professor=?,id_disciplina=?,id_curso=?,id_semestre=?,id_sala=?,categoria=?,turno=? WHERE id=?")
                        ->execute([$idProfessor, $idDisciplina, $idCurso, $idSemestre, $idSala, $_POST['categoria'] ?? null, $turno, $idEnsalamento]);
                    return '<div class="alert alert-primary alert-autohide mb-4">Ensalamento atualizado!</div>';
                }
                $this->pdo->prepare("INSERT INTO ensalamento(id_professor,id_disciplina,id_curso,id_semestre,id_sala,categoria,turno) VALUES(?,?,?,?,?,?,?)")
                    ->execute([$idProfessor, $idDisciplina, $idCurso, $idSemestre, $idSala, $_POST['categoria'] ?? null, $turno]);
                return '<div class="alert alert-success alert-autohide mb-4">Ensalamento registrado!</div>';
            } catch (PDOException $e) {
                $msg = $e->getCode() == 23000
                    ? 'Sala já ocupada neste turno ou dados inválidos.'
                    : 'Erro ao salvar ensalamento: ' . htmlspecialchars($e->getMessage());
                return '<div class="alert alert-danger alert-autohide mb-4">' . $msg . '</div>';
            }
        }
        if (isset($_POST['excluir_ensalamento'])) {
            $this->pdo->prepare("DELETE FROM ensalamento WHERE id=?")->execute([$_POST['id_ensalamento']]);
            return '<div class="alert alert-info alert-autohide mb-4">Ensalamento removido.</div>';
        }

        return $mensagem;
    }

    private function calcularRelatoriosBI(int $idQuadro, array $labs): array
    {
        $relatorioProfessores = $relatorioLabs = [];
        $graficoProfNomes = $graficoProfHoras = $graficoProfLab = $graficoProfSala = [];
        $graficoLabNomes = $graficoLabUso = $graficoLabOcioso = [];
        $graficoNomeCursos = $graficoHorasCursos = [];
        $taxaOcupacao = $taxaOciosidade = $usoGlobal = 0;
        $labMaisUsado  = ['nome' => '-', 'horas' => 0];
        $labMaisOcioso = ['nome' => '-', 'horas' => 0];

        try {
            $sql = "SELECT p.id as id_professor, p.nome as professor,
                    SUM(CASE WHEN qa.dia_semana='Segunda' THEN qa.carga_horaria_total ELSE 0 END) as seg_t,
                    SUM(CASE WHEN qa.dia_semana='Segunda' THEN qa.horas_laboratorio ELSE 0 END) as seg_l,
                    SUM(CASE WHEN qa.dia_semana='Terça'   THEN qa.carga_horaria_total ELSE 0 END) as ter_t,
                    SUM(CASE WHEN qa.dia_semana='Terça'   THEN qa.horas_laboratorio ELSE 0 END) as ter_l,
                    SUM(CASE WHEN qa.dia_semana='Quarta'  THEN qa.carga_horaria_total ELSE 0 END) as qua_t,
                    SUM(CASE WHEN qa.dia_semana='Quarta'  THEN qa.horas_laboratorio ELSE 0 END) as qua_l,
                    SUM(CASE WHEN qa.dia_semana='Quinta'  THEN qa.carga_horaria_total ELSE 0 END) as qui_t,
                    SUM(CASE WHEN qa.dia_semana='Quinta'  THEN qa.horas_laboratorio ELSE 0 END) as qui_l,
                    SUM(CASE WHEN qa.dia_semana='Sexta'   THEN qa.carga_horaria_total ELSE 0 END) as sex_t,
                    SUM(CASE WHEN qa.dia_semana='Sexta'   THEN qa.horas_laboratorio ELSE 0 END) as sex_l,
                    SUM(CASE WHEN qa.dia_semana='Sábado'  THEN qa.carga_horaria_total ELSE 0 END) as sab_t,
                    SUM(CASE WHEN qa.dia_semana='Sábado'  THEN qa.horas_laboratorio ELSE 0 END) as sab_l,
                    SUM(qa.carga_horaria_total) as total,
                    SUM(qa.horas_laboratorio) as total_l
                    FROM quadro_aulas qa JOIN usuarios p ON qa.id_professor=p.id
                    WHERE qa.id_quadro=? GROUP BY p.id,p.nome ORDER BY total DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$idQuadro]);
            $relatorioProfessores = $stmt->fetchAll();

            // Laboratório/sala principal de cada professor (onde concentra mais horas).
            $principalPorProf = [];
            $stmtPrinc = $this->pdo->prepare(
                "SELECT qa.id_professor, l.nome AS lab, s.nome AS sala,
                        SUM(qa.horas_laboratorio) AS h, COUNT(*) AS c
                 FROM quadro_aulas qa
                 LEFT JOIN laboratorios l ON qa.id_laboratorio = l.id
                 LEFT JOIN salas        s ON qa.id_sala        = s.id
                 WHERE qa.id_quadro = ?
                 GROUP BY qa.id_professor, l.id, s.id
                 ORDER BY h DESC, c DESC"
            );
            $stmtPrinc->execute([$idQuadro]);
            foreach ($stmtPrinc->fetchAll() as $row) {
                $pid = (int) $row['id_professor'];
                if (!isset($principalPorProf[$pid])) {
                    $principalPorProf[$pid] = [
                        'lab'  => $row['lab']  ?? '-',
                        'sala' => $row['sala'] ?? '-',
                    ];
                }
            }

            // Enriquece cada linha com as chaves consumidas pela tabela (horas/lab/sala).
            foreach ($relatorioProfessores as &$rp) {
                $pid        = (int) ($rp['id_professor'] ?? 0);
                $rp['horas'] = $rp['total'];
                $rp['lab']   = $principalPorProf[$pid]['lab']  ?? '-';
                $rp['sala']  = $principalPorProf[$pid]['sala'] ?? '-';
            }
            unset($rp);

            foreach (array_slice($relatorioProfessores, 0, 10) as $rp) {
                $graficoProfNomes[] = $rp['professor'];
                $graficoProfHoras[] = $rp['total'];
                $graficoProfLab[]   = $rp['total_l'];
                $graficoProfSala[]  = $rp['total'] - $rp['total_l'];
            }

            $sqlLab = "SELECT l.nome as laboratorio,
                       COALESCE(SUM(CASE WHEN qa.dia_semana='Segunda' THEN qa.horas_laboratorio ELSE 0 END),0) as seg,
                       COALESCE(SUM(CASE WHEN qa.dia_semana='Terça'   THEN qa.horas_laboratorio ELSE 0 END),0) as ter,
                       COALESCE(SUM(CASE WHEN qa.dia_semana='Quarta'  THEN qa.horas_laboratorio ELSE 0 END),0) as qua,
                       COALESCE(SUM(CASE WHEN qa.dia_semana='Quinta'  THEN qa.horas_laboratorio ELSE 0 END),0) as qui,
                       COALESCE(SUM(CASE WHEN qa.dia_semana='Sexta'   THEN qa.horas_laboratorio ELSE 0 END),0) as sex,
                       COALESCE(SUM(CASE WHEN qa.dia_semana='Sábado'  THEN qa.horas_laboratorio ELSE 0 END),0) as sab,
                       COALESCE(SUM(qa.horas_laboratorio),0) as total
                       FROM laboratorios l
                       LEFT JOIN quadro_aulas qa ON l.id=qa.id_laboratorio AND qa.id_quadro=?
                       GROUP BY l.id,l.nome ORDER BY total DESC";
            $stmt = $this->pdo->prepare($sqlLab);
            $stmt->execute([$idQuadro]);
            $relatorioLabs = $stmt->fetchAll();

            // Capacidade = janela operacional do laboratório por semana, em horas.
            // (6 dias úteis × 3 turnos × ~3,3h ≈ 60h). É uma medida de TEMPO, por isso
            // não usamos a capacidade de assentos, que mede pessoas, não horas.
            $capSemanalLab = 60;
            $capGlobal = 0;
            $minOcio = -1; $maxUso = -1;
            foreach ($relatorioLabs as &$rl) {
                $uso    = (float) $rl['total'];
                $ocioso = max(0, $capSemanalLab - $uso);
                $taxa   = $capSemanalLab > 0 ? round(($uso / $capSemanalLab) * 100) : 0;

                // Chaves consumidas pela tabela "Relatório por Laboratório".
                $rl['horas_uso']     = $uso;
                $rl['horas_ocioso']  = $ocioso;
                $rl['taxa_ocupacao'] = $taxa;

                $capGlobal += $capSemanalLab;
                $usoGlobal += $uso;
                $graficoLabNomes[]  = $rl['laboratorio'];
                $graficoLabUso[]    = $uso;
                $graficoLabOcioso[] = $ocioso;
                if ($uso > $maxUso)     { $maxUso = $uso;     $labMaisUsado  = ['nome' => $rl['laboratorio'], 'horas' => $uso]; }
                if ($ocioso > $minOcio) { $minOcio = $ocioso; $labMaisOcioso = ['nome' => $rl['laboratorio'], 'horas' => $ocioso]; }
            }
            unset($rl);

            $taxaOcupacao  = $capGlobal > 0 ? round(($usoGlobal / $capGlobal) * 100) : 0;
            $taxaOciosidade = 100 - $taxaOcupacao;

            $stmt = $this->pdo->prepare(
                "SELECT c.nome AS curso, SUM(qa.carga_horaria_total) AS total
                 FROM quadro_aulas qa
                 JOIN cursos c ON qa.id_curso = c.id
                 WHERE qa.id_quadro = ?
                 GROUP BY c.id, c.nome
                 ORDER BY total DESC"
            );
            $stmt->execute([$idQuadro]);
            foreach ($stmt->fetchAll() as $rc) {
                $graficoNomeCursos[]  = $rc['curso'];
                $graficoHorasCursos[] = $rc['total'];
            }
        } catch (PDOException) {}

        return [
            $relatorioProfessores, $relatorioLabs,
            $graficoProfNomes, $graficoProfHoras, $graficoProfLab, $graficoProfSala,
            $graficoLabNomes, $graficoLabUso, $graficoLabOcioso,
            $graficoNomeCursos, $graficoHorasCursos,
            $taxaOcupacao, $taxaOciosidade, $usoGlobal,
            $labMaisUsado, $labMaisOcioso,
        ];
    }
}
