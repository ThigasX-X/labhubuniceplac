<?php
require_once BACKEND_PATH . '/models/Agendamento.php';
require_once BACKEND_PATH . '/models/Laboratorio.php';
require_once BACKEND_PATH . '/models/Disciplina.php';
require_once BACKEND_PATH . '/models/Usuario.php';
require_once BACKEND_PATH . '/models/QuadroHorario.php';
require_once BACKEND_PATH . '/models/ChamadoSuporte.php';
require_once BACKEND_PATH . '/helpers/Auth.php';
require_once BACKEND_PATH . '/helpers/Upload.php';
require_once BACKEND_PATH . '/helpers/HorarioHelper.php';

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
        $mensagem  = '';

        $agendamentoModel = new Agendamento($this->pdo);
        $labModel         = new Laboratorio($this->pdo);
        $discModel        = new Disciplina($this->pdo);
        $usuarioModel     = new Usuario($this->pdo);
        $quadroModel      = new QuadroHorario($this->pdo);

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

        // --- DADOS ---
        $reservasPendentes    = $agendamentoModel->listarSolicitacoesPendentes();
        $agendamentosAprovados = $agendamentoModel->listarReservasConfirmadas();
        $historicoCompleto    = $agendamentoModel->listarHistoricoCompleto();
        $qtdPendentes         = count($reservasPendentes);

        $professores            = $usuarioModel->listProfessores();
        $laboratoriosCadastrados = $labModel->all();
        $disciplinas            = $discModel->all();

        $cursosCadastrados = $semestres = $blocos = $andares = $salas = [];
        foreach (['cursos', 'semestres', 'blocos', 'andares', 'salas'] as $tabela) {
            try {
                $$tabela = $this->pdo->query("SELECT * FROM {$tabela} ORDER BY nome")->fetchAll();
            } catch (Exception) {}
        }
        $cursosCadastrados = $cursos ?? [];
        $semestres         = $semestres ?? [];
        $blocosCadastrados = $blocos ?? [];
        $andaresCadastrados = $andares ?? [];
        $salasCadastradas  = $salas ?? [];

        $listaEnsalamentos = [];
        try {
            $listaEnsalamentos = $this->pdo->query(
                "SELECT e.*, u.nome as professor, d.nome as disciplina
                 FROM ensalamento e
                 JOIN usuarios u    ON e.id_professor  = u.id
                 JOIN disciplinas d ON e.id_disciplina = d.id
                 ORDER BY e.curso, e.turno, e.bloco"
            )->fetchAll();
        } catch (Exception) {}

        $listaQuadros     = $quadroModel->all();
        $quadroSelecionado = $_GET['q_id'] ?? (count($listaQuadros) > 0 ? $listaQuadros[0]['id'] : null);

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

        $this->render('coordenador/painel', compact(
            'mensagem', 'qtdPendentes', 'fotoAtual',
            'reservasPendentes', 'agendamentosAprovados', 'historicoCompleto',
            'professores', 'laboratoriosCadastrados', 'disciplinas',
            'cursosCadastrados', 'semestres', 'blocosCadastrados', 'andaresCadastrados', 'salasCadastradas',
            'listaEnsalamentos', 'listaQuadros', 'quadroSelecionado',
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

    private function moverAula(): array
    {
        try {
            (new QuadroHorario($this->pdo))->moverAula((int) $_POST['id_aula'], $_POST['novo_dia']);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processarAulaQuadro(QuadroHorario $quadroModel): string
    {
        if (empty($_POST['id_quadro_ativo'])) {
            return '<div class="alert alert-danger alert-autohide mb-4">Selecione um Cenário antes de alocar aulas.</div>';
        }

        $modalidade = $_POST['modalidade'];
        $dados = [
            'id_quadro'          => $_POST['id_quadro_ativo'],
            'turno'              => $_POST['turno_aula'],
            'dia_semana'         => $_POST['dia_semana'],
            'curso'              => $_POST['curso_aula'],
            'semestre'           => $_POST['semestre_aula'],
            'id_disciplina'      => $_POST['id_disciplina_aula'],
            'modalidade'         => $modalidade,
            'numero_alunos'      => (int) $_POST['numero_alunos'],
            'id_professor'       => $modalidade === 'EAD' ? null : (empty($_POST['id_professor_aula']) ? null : $_POST['id_professor_aula']),
            'id_laboratorio'     => $modalidade === 'EAD' ? null : (empty($_POST['id_laboratorio_aula']) ? null : $_POST['id_laboratorio_aula']),
            'bloco'              => $modalidade === 'EAD' ? null : (empty($_POST['bloco_aula']) ? null : $_POST['bloco_aula']),
            'andar'              => $modalidade === 'EAD' ? null : (empty($_POST['andar_aula']) ? null : $_POST['andar_aula']),
            'sala'               => $modalidade === 'EAD' ? null : (empty($_POST['sala_aula']) ? null : $_POST['sala_aula']),
            'horario'            => $_POST['horario_aula'],
            'carga_horaria_total' => (int) ($_POST['carga_horaria_total'] ?? 2),
            'horas_laboratorio'  => (int) ($_POST['horas_laboratorio'] ?? 0),
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
            $model->criarReserva($idLab, (int) $_POST['id_professor'], (int) $_POST['id_disciplina'], $data, $turno, $periodo);
            return '<div class="alert alert-success alert-autohide mb-4">Agendamento criado!</div>';
        } catch (PDOException) {
            return '<div class="alert alert-danger alert-autohide mb-4">Erro ao agendar.</div>';
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
            'salvar_andar'     => fn() => $this->pdo->prepare("INSERT INTO andares(nome) VALUES(?)")->execute([trim($_POST['nome_andar'])]),
            'editar_andar'     => fn() => $this->pdo->prepare("UPDATE andares SET nome=? WHERE id=?")->execute([trim($_POST['nome_andar']),$_POST['id_andar']]),
            'excluir_andar'    => fn() => $this->pdo->prepare("DELETE FROM andares WHERE id=?")->execute([$_POST['id_andar']]),
            'salvar_sala'      => fn() => $this->pdo->prepare("INSERT INTO salas(nome) VALUES(?)")->execute([trim($_POST['nome_sala'])]),
            'editar_sala'      => fn() => $this->pdo->prepare("UPDATE salas SET nome=? WHERE id=?")->execute([trim($_POST['nome_sala']),$_POST['id_sala']]),
            'excluir_sala'     => fn() => $this->pdo->prepare("DELETE FROM salas WHERE id=?")->execute([$_POST['id_sala']]),
        ];

        foreach ($ops as $key => $fn) {
            if (isset($_POST[$key])) { $fn(); break; }
        }

        return $mensagem;
    }

    private function processarEnsalamento(string $mensagem): string
    {
        if (!$this->isPost()) return $mensagem;

        if (isset($_POST['salvar_ensalamento'])) {
            $check = $this->pdo->prepare("SELECT u.nome as prof_existente FROM ensalamento e JOIN usuarios u ON e.id_professor=u.id WHERE e.bloco=? AND e.andar=? AND e.sala=? AND e.turno=?");
            $check->execute([$_POST['bloco'], $_POST['andar'], $_POST['sala'], $_POST['turno']]);
            if ($check->rowCount() > 0) {
                $c = $check->fetch();
                return '<div class="alert alert-warning alert-autohide mb-4"><strong>Choque de Sala!</strong> Em uso por Prof. ' . htmlspecialchars($c['prof_existente']) . '.</div>';
            }
            $this->pdo->prepare("INSERT INTO ensalamento(id_professor,id_disciplina,curso,bloco,andar,sala,categoria,turno) VALUES(?,?,?,?,?,?,?,?)")
                ->execute([$_POST['id_professor'],$_POST['id_disciplina'],$_POST['curso'],$_POST['bloco'],$_POST['andar'],$_POST['sala'],$_POST['categoria'],$_POST['turno']]);
            return '<div class="alert alert-success alert-autohide mb-4">Ensalamento registrado!</div>';
        }
        if (isset($_POST['editar_ensalamento'])) {
            $this->pdo->prepare("UPDATE ensalamento SET id_professor=?,id_disciplina=?,curso=?,bloco=?,andar=?,sala=?,categoria=?,turno=? WHERE id=?")
                ->execute([$_POST['id_professor'],$_POST['id_disciplina'],$_POST['curso'],$_POST['bloco'],$_POST['andar'],$_POST['sala'],$_POST['categoria'],$_POST['turno'],$_POST['id_ensalamento']]);
            return '<div class="alert alert-primary alert-autohide mb-4">Ensalamento atualizado!</div>';
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
            $sql = "SELECT p.nome as professor,
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

            $capMax = 60;
            $minUso = 9999; $maxUso = -1;
            foreach ($relatorioLabs as $rl) {
                $usoGlobal += $rl['total'];
                $graficoLabNomes[]  = $rl['laboratorio'];
                $graficoLabUso[]    = $rl['total'];
                $graficoLabOcioso[] = $capMax - $rl['total'];
                if ($rl['total'] > $maxUso) { $maxUso = $rl['total']; $labMaisUsado  = ['nome' => $rl['laboratorio'], 'horas' => $rl['total']]; }
                if ($rl['total'] < $minUso) { $minUso = $rl['total']; $labMaisOcioso = ['nome' => $rl['laboratorio'], 'horas' => $capMax - $rl['total']]; }
            }

            $capGlobal  = count($labs) * $capMax;
            $taxaOcupacao  = $capGlobal > 0 ? round(($usoGlobal / $capGlobal) * 100) : 0;
            $taxaOciosidade = 100 - $taxaOcupacao;

            $stmt = $this->pdo->prepare("SELECT curso, SUM(carga_horaria_total) as total FROM quadro_aulas WHERE id_quadro=? GROUP BY curso ORDER BY total DESC");
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
