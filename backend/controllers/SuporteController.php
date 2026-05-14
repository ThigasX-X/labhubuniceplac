    <?php
    require_once BACKEND_PATH . '/models/Agendamento.php';
    require_once BACKEND_PATH . '/models/ControleChave.php';
    require_once BACKEND_PATH . '/models/ChamadoSuporte.php';
    require_once BACKEND_PATH . '/models/Laboratorio.php';
    require_once BACKEND_PATH . '/models/Usuario.php';
    require_once BACKEND_PATH . '/helpers/Auth.php';
    require_once BACKEND_PATH . '/helpers/Upload.php';

    class SuporteController extends BaseController
    {
        public function index(): void
        {
            if (!isset($_SESSION['usuario_id']) ||
                !in_array($_SESSION['perfil'], ['suporte', 'coordenador'])) {
                $this->redirect('login');
            }

            $idUsuario   = Auth::id();
            $mensagem    = '';
            $chaveModel  = new ControleChave($this->pdo);
            $sosModel    = new ChamadoSuporte($this->pdo);

            // --- DAR BAIXA NA CHAVE ---
            if ($this->isPost() && isset($_POST['dar_baixa_chave'])) {
                try {
                    $chaveModel->darBaixa((int) $_POST['id_chave'], $_POST['func_recebe'], $_POST['hora_devolucao_real']);
                    $mensagem = '<div class="alert alert-primary alert-autohide rounded-0 border-start border-4 border-primary mb-4">'
                        . '<i class="bi bi-check-circle-fill me-2"></i>Chave recebida e baixada com sucesso!</div>';
                } catch (PDOException $e) {
                    $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao dar baixa: '
                        . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }

            // --- RESOLVER SOS ---
            if ($this->isPost() && isset($_POST['resolver_chamado'])) {
                try {
                    $sosModel->resolver((int) $_POST['id_chamado']);
                    $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-start border-4 border-success mb-4">'
                        . '<i class="bi bi-check-circle-fill me-2"></i>Chamado encerrado!</div>';
                } catch (PDOException) {
                    $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao encerrar chamado.</div>';
                }
            }

            // --- UPLOAD DE FOTO ---
            if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
                $caminho = Upload::foto($_FILES['nova_foto'], $idUsuario, $_SESSION['foto_perfil'] ?? null);
                if ($caminho) {
                    (new Usuario($this->pdo))->updateFoto($idUsuario, $caminho);
                    $_SESSION['foto_perfil'] = $caminho;
                    $mensagem = '<div class="alert alert-success alert-autohide mb-4">Foto atualizada!</div>';
                }
            }

            // --- DADOS ---
            $alertasSuporte   = $sosModel->pendentes();
            $historicoChamados = $sosModel->resolvidos();
            $qtdAlertas       = count($alertasSuporte);

            $chavesEmUso = [];
            foreach ($chaveModel->emUso() as $chave) {
                $chavesEmUso[$chave['id_agendamento']] = $chave;
            }
            $historicoChaves   = $chaveModel->historico();
            $listaLaboratorios = (new Laboratorio($this->pdo))->all();

            $dataFiltro    = $_GET['data_busca'] ?? date('Y-m-d');
            $diasMap       = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
            $diaSemana     = $diasMap[date('w', strtotime($dataFiltro))];

            // Agendamentos avulsos do dia
            $stmt = $this->pdo->prepare(
                "SELECT a.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina, a.turno, a.periodo
                FROM agendamentos a
                JOIN laboratorios l ON a.id_laboratorio = l.id
                JOIN usuarios u     ON a.id_professor   = u.id
                JOIN disciplinas d  ON a.id_disciplina  = d.id
                WHERE a.data_reserva = ? AND a.status = 'aprovado'
                ORDER BY FIELD(a.turno,'Matutino','Vespertino','Noturno'), l.nome"
            );
            $stmt->execute([$dataFiltro]);
            $alocacoes = $stmt->fetchAll();

            // Integração com quadro fixo
            try {
                $idQuadroAtivo = $this->pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn();
                if ($idQuadroAtivo) {
                    $stmt = $this->pdo->prepare(
                        "SELECT qa.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina, qa.turno, qa.horario as periodo
                        FROM quadro_aulas qa
                        JOIN laboratorios l ON qa.id_laboratorio = l.id
                        JOIN usuarios u     ON qa.id_professor   = u.id
                        JOIN disciplinas d  ON qa.id_disciplina  = d.id
                        WHERE qa.id_quadro = ? AND qa.dia_semana = ? AND qa.id_laboratorio IS NOT NULL"
                    );
                    $stmt->execute([$idQuadroAtivo, $diaSemana]);
                    foreach ($stmt->fetchAll() as $af) {
                        $af['id'] = $af['id'] + 1000000;
                        $alocacoes[] = $af;
                    }
                }
            } catch (Exception) {}

            // Agrupar por turno
            $alocacoesMatutino = $alocacoesVespertino = $alocacoesNoturno = [];
            foreach ($alocacoes as $linha) {
                $t = trim($linha['turno']);
                if (in_array($t, ['Matutino', 'Manhã']))      $alocacoesMatutino[]  = $linha;
                elseif (in_array($t, ['Vespertino', 'Tarde'])) $alocacoesVespertino[] = $linha;
                elseif (in_array($t, ['Noturno', 'Noite']))    $alocacoesNoturno[]   = $linha;
            }

            $totalReservas  = count($alocacoes);
            $qtdMatutino   = count($alocacoesMatutino);
            $qtdVespertino = count($alocacoesVespertino);
            $qtdNoturno    = count($alocacoesNoturno);
            $fotoAtual     = Auth::foto();

            $this->render('suporte/suporte_index', compact(
                'mensagem', 'alertasSuporte', 'historicoChamados', 'qtdAlertas',
                'chavesEmUso', 'historicoChaves', 'listaLaboratorios',
                'dataFiltro', 'alocacoes', 'alocacoesMatutino', 'alocacoesVespertino', 'alocacoesNoturno',
                'totalReservas', 'qtdMatutino', 'qtdVespertino', 'qtdNoturno', 'fotoAtual'
            ));
        }
    }
