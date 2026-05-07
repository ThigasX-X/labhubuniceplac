<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'coordenador') {
    header("Location: index.php");
    exit;
}

require 'conexao.php';
require 'Agendamento.php';

date_default_timezone_set('America/Sao_Paulo');

$agendamento = new Agendamento($pdo);
$mensagem = '';
$id_usuario_logado = $_SESSION['usuario_id'];

// =================================================================================
// AJAX: MOTOR DE ARRASTAR E SOLTAR (DRAG AND DROP) DO KANBAN
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'mover_aula') {
    header('Content-Type: application/json');
    $id_aula = $_POST['id_aula'];
    $novo_dia = $_POST['novo_dia'];

    try {
        // Atualiza a aula para o novo dia que o Coordenador arrastou
        $stmt = $pdo->prepare("UPDATE quadro_aulas SET dia_semana = ? WHERE id = ?");
        $stmt->execute([$novo_dia, $id_aula]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit; // Para a execução do PHP aqui para não imprimir o HTML inteiro no fundo
}

// =================================================================================
// FUNÇÃO: O "DUPLO CHECK" DE CHOQUES (Avulsos vs Grade Fixa)
// =================================================================================
function verificaChoqueHorario($pdo, $id_lab, $data_reserva, $turno, $periodo, $id_ignorar = null)
{
    $sql_ag = "SELECT periodo FROM agendamentos WHERE id_laboratorio = ? AND data_reserva = ? AND turno = ? AND status = 'aprovado'";
    $params_ag = [$id_lab, $data_reserva, $turno];

    if ($id_ignorar) {
        $sql_ag .= " AND id != ?";
        $params_ag[] = $id_ignorar;
    }
    $stmt_ag = $pdo->prepare($sql_ag);
    $stmt_ag->execute($params_ag);
    $avulsos = $stmt_ag->fetchAll(PDO::FETCH_COLUMN);

    foreach ($avulsos as $p_banco) {
        if ($periodo === '1º e 2º Horários' || $p_banco === '1º e 2º Horários' || $periodo === $p_banco) {
            return "Já existe uma reserva avulsa aprovada para este laboratório neste dia e horário.";
        }
    }

    $dias_map = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
    $dia_semana = $dias_map[date('w', strtotime($data_reserva))];

    $id_quadro_ativo = false;
    try {
        $id_quadro_ativo = $pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn();
    } catch (Exception $e) {
    }

    if ($id_quadro_ativo) {
        $stmt_qa = $pdo->prepare("SELECT horario FROM quadro_aulas WHERE id_quadro = ? AND id_laboratorio = ? AND dia_semana = ? AND turno = ?");
        $stmt_qa->execute([$id_quadro_ativo, $id_lab, $dia_semana, $turno]);
        $fixos = $stmt_qa->fetchAll(PDO::FETCH_COLUMN);

        foreach ($fixos as $h_fixo) {
            if ($periodo === '1º e 2º Horários' || $h_fixo === '1º e 2º Horários' || $periodo === $h_fixo) {
                return "A Grade Fixa já ocupa este laboratório toda " . $dia_semana . " (" . $turno . ").";
            }
        }
    }
    return false;
}

// --- UPLOAD DE FOTO ---
if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
    $extensao = strtolower(pathinfo($_FILES['nova_foto']['name'], PATHINFO_EXTENSION));
    if (in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
        $diretorio = 'uploads/';
        if (!is_dir($diretorio))
            mkdir($diretorio, 0777, true);
        $destino = $diretorio . 'user_' . $id_usuario_logado . '_' . time() . '.' . $extensao;
        if (move_uploaded_file($_FILES['nova_foto']['tmp_name'], $destino)) {
            if (!empty($_SESSION['foto_perfil']) && file_exists($_SESSION['foto_perfil']) && strpos($_SESSION['foto_perfil'], 'padrao') === false) {
                unlink($_SESSION['foto_perfil']);
            }
            $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")->execute([$destino, $id_usuario_logado]);
            $_SESSION['foto_perfil'] = $destino;
            $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-start border-4 border-success mb-4">Foto atualizada!</div>';
        }
    }
}

// --- QUADRO DE HORÁRIOS (CRIAR/EXCLUIR) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_quadro'])) {
    try {
        $pdo->prepare("INSERT INTO quadros_horarios (nome, periodo_letivo) VALUES (?, ?)")->execute([trim($_POST['nome_quadro']), trim($_POST['periodo_letivo'])]);
        $mensagem = '<div class="alert alert-success alert-autohide mb-4">Cenário de Quadro Horário criado!</div>';
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger mb-4"><strong>Erro no Banco de Dados:</strong> ' . $e->getMessage() . '</div>';
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir_quadro'])) {
    $pdo->prepare("DELETE FROM quadros_horarios WHERE id = ?")->execute([$_POST['id_quadro']]);
    $mensagem = '<div class="alert alert-info alert-autohide mb-4">Quadro Horário excluído com todas as suas aulas.</div>';
}
// -- Bloco Novo : Duplicar Quadro Inteiro --
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['duplicar_quadro'])) {
    $id_origem = $_POST['id_quadro_origem'];
    $novo_nome = trim($_POST['novo_nome_quadro']);
    $novo_periodo = trim($_POST['novo_periodo_letivo']);

    try {
        $pdo->beginTransaction(); // Inicia a transação segura

        // 1. Cria o Novo Quadro Vazio
        $stmt = $pdo->prepare("INSERT INTO quadros_horarios (nome, periodo_letivo) VALUES (?, ?)");
        $stmt->execute([$novo_nome, $novo_periodo]);
        $novo_id = $pdo->lastInsertId(); // Pega o ID do quadro que acabou de nascer

        // 2. Busca todas as aulas do quadro antigo
        $stmt_aulas = $pdo->prepare("SELECT * FROM quadro_aulas WHERE id_quadro = ?");
        $stmt_aulas->execute([$id_origem]);
        $aulas_antigas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);

        // 3. Copia aula por aula para o novo quadro
        if (count($aulas_antigas) > 0) {
            $sql_insert = "INSERT INTO quadro_aulas (id_quadro, turno, dia_semana, curso, semestre, id_disciplina, modalidade, numero_alunos, id_professor, id_laboratorio, horario, bloco, andar, sala, carga_horaria_total, horas_laboratorio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $pdo->prepare($sql_insert);

            foreach ($aulas_antigas as $aula) {
                $stmt_insert->execute([
                    $novo_id,
                    $aula['turno'],
                    $aula['dia_semana'],
                    $aula['curso'],
                    $aula['semestre'],
                    $aula['id_disciplina'],
                    $aula['modalidade'],
                    $aula['numero_alunos'],
                    $aula['id_professor'],
                    $aula['id_laboratorio'],
                    $aula['horario'],
                    $aula['bloco'],
                    $aula['andar'],
                    $aula['sala'],
                    $aula['carga_horaria_total'],
                    $aula['horas_laboratorio']
                ]);
            }
        }

        $pdo->commit(); // Confirma a clonagem em massa
        $mensagem = '<div class="alert alert-success alert-autohide mb-4">Cenário duplicado com sucesso! Aulas copiadas perfeitamente.</div>';
    } catch (PDOException $e) {
        $pdo->rollBack(); // Desfaz se der erro
        $mensagem = '<div class="alert alert-danger mb-4"><strong>Erro ao duplicar:</strong> ' . $e->getMessage() . '</div>';
    }
}

// --- QUADRO DE HORÁRIOS (ADICIONAR/EDITAR AULA) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['salvar_aula_quadro']) || isset($_POST['editar_aula_quadro']))) {
    if (empty($_POST['id_quadro_ativo'])) {
        $mensagem = '<div class="alert alert-danger alert-autohide"><strong>Erro:</strong> Selecione um Cenário antes de alocar aulas.</div>';
    } else {
        $id_q = $_POST['id_quadro_ativo'];
        $turno = $_POST['turno_aula'];
        $dia = $_POST['dia_semana'];
        $curso = $_POST['curso_aula'];
        $semestre = $_POST['semestre_aula'];
        $disc = $_POST['id_disciplina_aula'];
        $modalidade = $_POST['modalidade'];
        $num_alunos = (int) $_POST['numero_alunos'];

        // --- A BALA DE PRATA DO EAD ---
        if ($modalidade === 'EAD') {
            $prof = null;
            $lab = null;
            $sala = null;
            $bloco = null;
            $andar = null;
        } else {
            $prof = empty($_POST['id_professor_aula']) ? null : $_POST['id_professor_aula'];
            $lab = empty($_POST['id_laboratorio_aula']) ? null : $_POST['id_laboratorio_aula'];
            $bloco = empty($_POST['bloco_aula']) ? null : $_POST['bloco_aula'];
            $andar = empty($_POST['andar_aula']) ? null : $_POST['andar_aula'];
            $sala = empty($_POST['sala_aula']) ? null : $_POST['sala_aula'];
        }

        $horario = $_POST['horario_aula'];

        $carga_horaria_total = isset($_POST['carga_horaria_total']) ? (int) $_POST['carga_horaria_total'] : 2;
        $horas_laboratorio = isset($_POST['horas_laboratorio']) ? (int) $_POST['horas_laboratorio'] : 0;

        $editando = isset($_POST['editar_aula_quadro']);
        $id_aula_q = $editando ? $_POST['id_aula_q'] : null;
        $erro_conflito = false;
        $msg_erro = "";

        if ($lab) {
            $cap_lab = $pdo->prepare("SELECT capacidade FROM laboratorios WHERE id = ?");
            $cap_lab->execute([$lab]);
            $limite = $cap_lab->fetchColumn();
            if ($num_alunos > $limite) {
                $erro_conflito = true;
                $msg_erro = "Alunos ($num_alunos) excede capacidade do lab ($limite).";
            }
        }

        if (!$erro_conflito) {
            $sql_check = "SELECT id_professor, id_laboratorio, bloco, andar, sala, horario, modalidade FROM quadro_aulas WHERE id_quadro = ? AND dia_semana = ? AND turno = ?";
            $params_check = [$id_q, $dia, $turno];
            if ($editando) {
                $sql_check .= " AND id != ?";
                $params_check[] = $id_aula_q;
            }
            $check = $pdo->prepare($sql_check);
            $check->execute($params_check);
            $aulas_existentes = $check->fetchAll(PDO::FETCH_ASSOC);

            foreach ($aulas_existentes as $ae) {
                if ($modalidade === 'EAD') {
                    continue;
                }

                if ($horario === '1º e 2º Horários' || $ae['horario'] === '1º e 2º Horários' || $horario === $ae['horario']) {
                    if ($prof !== null && $ae['id_professor'] == $prof) {
                        $erro_conflito = true;
                        $msg_erro = "Choque de Professor.";
                        break;
                    }
                    if ($lab && $ae['id_laboratorio'] == $lab) {
                        $erro_conflito = true;
                        $msg_erro = "Choque de Laboratório.";
                        break;
                    }
                    if ($sala && $ae['sala'] == $sala && $ae['bloco'] == $bloco && $ae['andar'] == $andar) {
                        $erro_conflito = true;
                        $msg_erro = "Choque de Sala.";
                        break;
                    }
                }
            }
        }

        if ($erro_conflito) {
            $mensagem = "<div class='alert alert-danger alert-autohide mb-4'><strong>Bloqueado:</strong> $msg_erro</div>";
        } else {
            try {
                if ($editando) {
                    $pdo->prepare("UPDATE quadro_aulas SET turno=?, dia_semana=?, curso=?, semestre=?, id_disciplina=?, modalidade=?, numero_alunos=?, id_professor=?, id_laboratorio=?, horario=?, bloco=?, andar=?, sala=?, carga_horaria_total=?, horas_laboratorio=? WHERE id=?")
                        ->execute([$turno, $dia, $curso, $semestre, $disc, $modalidade, $num_alunos, $prof, $lab, $horario, $bloco, $andar, $sala, $carga_horaria_total, $horas_laboratorio, $id_aula_q]);
                } else {
                    $pdo->prepare("INSERT INTO quadro_aulas (id_quadro, turno, dia_semana, curso, semestre, id_disciplina, modalidade, numero_alunos, id_professor, id_laboratorio, horario, bloco, andar, sala, carga_horaria_total, horas_laboratorio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$id_q, $turno, $dia, $curso, $semestre, $disc, $modalidade, $num_alunos, $prof, $lab, $horario, $bloco, $andar, $sala, $carga_horaria_total, $horas_laboratorio]);
                }
                $mensagem = '<div class="alert alert-success alert-autohide mb-4">Grade atualizada com sucesso!</div>';
            } catch (PDOException $e) {
                if ($editando) {
                    $pdo->prepare("UPDATE quadro_aulas SET turno=?, dia_semana=?, curso=?, semestre=?, id_disciplina=?, modalidade=?, numero_alunos=?, id_professor=?, id_laboratorio=?, horario=?, bloco=?, andar=?, sala=? WHERE id=?")
                        ->execute([$turno, $dia, $curso, $semestre, $disc, $modalidade, $num_alunos, $prof, $lab, $horario, $bloco, $andar, $sala, $id_aula_q]);
                } else {
                    $pdo->prepare("INSERT INTO quadro_aulas (id_quadro, turno, dia_semana, curso, semestre, id_disciplina, modalidade, numero_alunos, id_professor, id_laboratorio, horario, bloco, andar, sala) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$id_q, $turno, $dia, $curso, $semestre, $disc, $modalidade, $num_alunos, $prof, $lab, $horario, $bloco, $andar, $sala]);
                }
                $mensagem = '<div class="alert alert-warning alert-autohide mb-4">Aula salva, mas os campos de carga horária foram ignorados (banco desatualizado).</div>';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir_aula_quadro'])) {
    $pdo->prepare("DELETE FROM quadro_aulas WHERE id = ?")->execute([$_POST['id_aula_q']]);
    $mensagem = '<div class="alert alert-info alert-autohide mb-4">Aula removida do quadro.</div>';
}

// --- APROVAR/REJEITAR RESERVAS PENDENTES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao_reserva'])) {
    $id_agendamento = $_POST['id_agendamento'];
    if ($_POST['acao_reserva'] == 'aprovar') {
        $stmt_ag = $pdo->prepare("SELECT id_laboratorio, data_reserva, turno, periodo FROM agendamentos WHERE id = ?");
        $stmt_ag->execute([$id_agendamento]);
        $ag = $stmt_ag->fetch(PDO::FETCH_ASSOC);
        $conflito = verificaChoqueHorario($pdo, $ag['id_laboratorio'], $ag['data_reserva'], $ag['turno'], $ag['periodo'], $id_agendamento);
        if ($conflito) {
            $mensagem = "<div class='alert alert-warning alert-autohide mb-4'><strong>Aprovação Bloqueada:</strong> $conflito</div>";
        } else {
            $pdo->prepare("UPDATE agendamentos SET status = 'aprovado' WHERE id = ?")->execute([$id_agendamento]);
            $mensagem = "<div class='alert alert-success alert-autohide mb-4'>Reserva Aprovada!</div>";
        }
    } else {
        $pdo->prepare("UPDATE agendamentos SET status = 'rejeitado' WHERE id = ?")->execute([$id_agendamento]);
        $mensagem = "<div class='alert alert-danger alert-autohide mb-4'>Reserva Rejeitada!</div>";
    }
}

// --- AGENDAR LAB AVULSO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['agendar_lab_coord'])) {
        $id_lab = $_POST['id_laboratorio'];
        $data_res = $_POST['data_reserva'];
        $turno_req = $_POST['turno'];
        $periodo_req = $_POST['periodo'];
        $conflito = verificaChoqueHorario($pdo, $id_lab, $data_res, $turno_req, $periodo_req);
        if ($conflito) {
            $mensagem = '<div class="alert alert-warning alert-autohide mb-4"><strong>Bloqueado:</strong> ' . $conflito . '</div>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO agendamentos (id_laboratorio, id_professor, id_disciplina, data_reserva, turno, periodo, status) VALUES (?, ?, ?, ?, ?, ?, 'aprovado')");
                $stmt->execute([$id_lab, $_POST['id_professor'], $_POST['id_disciplina'], $data_res, $turno_req, $periodo_req]);
                $mensagem = '<div class="alert alert-success alert-autohide mb-4">Agendamento criado!</div>';
            } catch (PDOException $e) {
                $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao agendar.</div>';
            }
        }
    } elseif (isset($_POST['editar_agendamento_coord'])) {
        $id_ag = $_POST['id_agendamento'];
        $id_lab = $_POST['id_laboratorio'];
        $data_res = $_POST['data_reserva'];
        $turno_req = $_POST['turno'];
        $periodo_req = $_POST['periodo'];
        $conflito = verificaChoqueHorario($pdo, $id_lab, $data_res, $turno_req, $periodo_req, $id_ag);
        if ($conflito) {
            $mensagem = '<div class="alert alert-warning alert-autohide mb-4"><strong>Bloqueado:</strong> ' . $conflito . '</div>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE agendamentos SET id_laboratorio=?, id_professor=?, id_disciplina=?, data_reserva=?, turno=?, periodo=? WHERE id=?");
                $stmt->execute([$id_lab, $_POST['id_professor'], $_POST['id_disciplina'], $data_res, $turno_req, $periodo_req, $id_ag]);
                $mensagem = '<div class="alert alert-primary alert-autohide mb-4">Agendamento atualizado!</div>';
            } catch (PDOException $e) {
                $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao atualizar.</div>';
            }
        }
    } elseif (isset($_POST['cancelar_agendamento'])) {
        try {
            $pdo->prepare("DELETE FROM agendamentos WHERE id = ?")->execute([$_POST['id_agendamento']]);
            $mensagem = '<div class="alert alert-warning alert-autohide mb-4">Agendamento cancelado.</div>';
        } catch (PDOException $e) {
            $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao cancelar.</div>';
        }
    }
}

// --- CADASTROS BASE E INFRA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['salvar_lab'])) {
        $pdo->prepare("INSERT INTO laboratorios (nome, capacidade, localizacao, andar) VALUES (?, ?, ?, ?)")->execute([trim($_POST['nome_lab']), (int) $_POST['capacidade_lab'], trim($_POST['localizacao_lab']), trim($_POST['andar_lab'])]);
    } elseif (isset($_POST['editar_lab'])) {
        $pdo->prepare("UPDATE laboratorios SET nome = ?, capacidade = ?, localizacao = ?, andar = ? WHERE id = ?")->execute([trim($_POST['nome_lab']), (int) $_POST['capacidade_lab'], trim($_POST['localizacao_lab']), trim($_POST['andar_lab']), $_POST['id_lab']]);
    } elseif (isset($_POST['excluir_lab'])) {
        $pdo->prepare("DELETE FROM laboratorios WHERE id = ?")->execute([$_POST['id_lab']]);
    }

    if (isset($_POST['salvar_disciplina'])) {
        $pdo->prepare("INSERT INTO disciplinas (nome) VALUES (?)")->execute([trim($_POST['nome_disciplina'])]);
    } elseif (isset($_POST['editar_disciplina'])) {
        $pdo->prepare("UPDATE disciplinas SET nome = ? WHERE id = ?")->execute([trim($_POST['nome_disciplina']), $_POST['id_disciplina']]);
    } elseif (isset($_POST['excluir_disciplina'])) {
        $pdo->prepare("DELETE FROM disciplinas WHERE id = ?")->execute([$_POST['id_disciplina']]);
    }

    if (isset($_POST['salvar_curso'])) {
        $pdo->prepare("INSERT INTO cursos (nome) VALUES (?)")->execute([trim($_POST['nome_curso'])]);
    } elseif (isset($_POST['editar_curso'])) {
        $pdo->prepare("UPDATE cursos SET nome = ? WHERE id = ?")->execute([trim($_POST['nome_curso']), $_POST['id_curso']]);
    } elseif (isset($_POST['excluir_curso'])) {
        $pdo->prepare("DELETE FROM cursos WHERE id = ?")->execute([$_POST['id_curso']]);
    }

    if (isset($_POST['salvar_semestre'])) {
        $pdo->prepare("INSERT INTO semestres (nome) VALUES (?)")->execute([trim($_POST['nome_semestre'])]);
    } elseif (isset($_POST['editar_semestre'])) {
        $pdo->prepare("UPDATE semestres SET nome = ? WHERE id = ?")->execute([trim($_POST['nome_semestre']), $_POST['id_semestre']]);
    } elseif (isset($_POST['excluir_semestre'])) {
        $pdo->prepare("DELETE FROM semestres WHERE id = ?")->execute([$_POST['id_semestre']]);
    }

    if (isset($_POST['salvar_bloco'])) {
        $pdo->prepare("INSERT INTO blocos (nome) VALUES (?)")->execute([trim($_POST['nome_bloco'])]);
    }
    if (isset($_POST['editar_bloco'])) {
        $pdo->prepare("UPDATE blocos SET nome = ? WHERE id = ?")->execute([trim($_POST['nome_bloco']), $_POST['id_bloco']]);
    }
    if (isset($_POST['excluir_bloco'])) {
        $pdo->prepare("DELETE FROM blocos WHERE id = ?")->execute([$_POST['id_bloco']]);
    }

    if (isset($_POST['salvar_andar'])) {
        $pdo->prepare("INSERT INTO andares (nome) VALUES (?)")->execute([trim($_POST['nome_andar'])]);
    }
    if (isset($_POST['editar_andar'])) {
        $pdo->prepare("UPDATE andares SET nome = ? WHERE id = ?")->execute([trim($_POST['nome_andar']), $_POST['id_andar']]);
    }
    if (isset($_POST['excluir_andar'])) {
        $pdo->prepare("DELETE FROM andares WHERE id = ?")->execute([$_POST['id_andar']]);
    }

    if (isset($_POST['salvar_sala'])) {
        $pdo->prepare("INSERT INTO salas (nome) VALUES (?)")->execute([trim($_POST['nome_sala'])]);
    }
    if (isset($_POST['editar_sala'])) {
        $pdo->prepare("UPDATE salas SET nome = ? WHERE id = ?")->execute([trim($_POST['nome_sala']), $_POST['id_sala']]);
    }
    if (isset($_POST['excluir_sala'])) {
        $pdo->prepare("DELETE FROM salas WHERE id = ?")->execute([$_POST['id_sala']]);
    }
}

// --- ENSALAMENTO NORMAL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['salvar_ensalamento'])) {
        $stmt_check = $pdo->prepare("SELECT u.nome as prof_existente, d.nome as disc_existente FROM ensalamento e JOIN usuarios u ON e.id_professor = u.id JOIN disciplinas d ON e.id_disciplina = d.id WHERE e.bloco = ? AND e.andar = ? AND e.sala = ? AND e.turno = ?");
        $stmt_check->execute([$_POST['bloco'], $_POST['andar'], $_POST['sala'], $_POST['turno']]);
        if ($stmt_check->rowCount() > 0) {
            $conflito = $stmt_check->fetch(PDO::FETCH_ASSOC);
            $mensagem = '<div class="alert alert-warning alert-autohide mb-4"><strong>Choque de Sala!</strong> Local em uso no turno ' . htmlspecialchars($_POST['turno']) . ' por Prof. ' . htmlspecialchars($conflito['prof_existente']) . '.</div>';
        } else {
            $pdo->prepare("INSERT INTO ensalamento (id_professor, id_disciplina, curso, bloco, andar, sala, categoria, turno) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$_POST['id_professor'], $_POST['id_disciplina'], $_POST['curso'], $_POST['bloco'], $_POST['andar'], $_POST['sala'], $_POST['categoria'], $_POST['turno']]);
            $mensagem = '<div class="alert alert-success alert-autohide mb-4">Ensalamento registrado!</div>';
        }
    } elseif (isset($_POST['editar_ensalamento'])) {
        $stmt_check = $pdo->prepare("SELECT u.nome as prof_existente, d.nome as disc_existente FROM ensalamento e JOIN usuarios u ON e.id_professor = u.id JOIN disciplinas d ON e.id_disciplina = d.id WHERE e.bloco = ? AND e.andar = ? AND e.sala = ? AND e.turno = ? AND e.id != ?");
        $stmt_check->execute([$_POST['bloco'], $_POST['andar'], $_POST['sala'], $_POST['turno'], $_POST['id_ensalamento']]);
        if ($stmt_check->rowCount() > 0) {
            $mensagem = '<div class="alert alert-warning alert-autohide mb-4"><strong>Choque na Edição!</strong> Sala já ocupada.</div>';
        } else {
            $pdo->prepare("UPDATE ensalamento SET id_professor=?, id_disciplina=?, curso=?, bloco=?, andar=?, sala=?, categoria=?, turno=? WHERE id=?")->execute([$_POST['id_professor'], $_POST['id_disciplina'], $_POST['curso'], $_POST['bloco'], $_POST['andar'], $_POST['sala'], $_POST['categoria'], $_POST['turno'], $_POST['id_ensalamento']]);
            $mensagem = '<div class="alert alert-primary alert-autohide mb-4">Ensalamento atualizado!</div>';
        }
    } elseif (isset($_POST['excluir_ensalamento'])) {
        $pdo->prepare("DELETE FROM ensalamento WHERE id = ?")->execute([$_POST['id_ensalamento']]);
        $mensagem = '<div class="alert alert-info alert-autohide mb-4">Ensalamento removido.</div>';
    }
}

if (!isset($_SESSION['foto_perfil'])) {
    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id_usuario_logado]);
    $_SESSION['foto_perfil'] = $stmt->fetchColumn();
}
$foto_atual = !empty($_SESSION['foto_perfil']) && file_exists($_SESSION['foto_perfil']) ? $_SESSION['foto_perfil'] : 'uploads/padrao-usuario.png';

// --- BUSCAS DE DADOS GERAIS ---
$reservas_pendentes = $pdo->query("SELECT a.*, l.nome as laboratorio, u.nome as professor, d.nome as disciplina FROM agendamentos a JOIN laboratorios l ON a.id_laboratorio = l.id JOIN usuarios u ON a.id_professor = u.id JOIN disciplinas d ON a.id_disciplina = d.id WHERE a.status = 'pendente' ORDER BY a.data_reserva ASC")->fetchAll(PDO::FETCH_ASSOC);
$qtd_pendentes = count($reservas_pendentes);
$agendamentos_aprovados = $pdo->query("SELECT a.*, l.nome as laboratorio, u.nome as professor, d.nome as disciplina, a.id_professor, a.id_laboratorio, a.id_disciplina FROM agendamentos a JOIN laboratorios l ON a.id_laboratorio = l.id JOIN usuarios u ON a.id_professor = u.id JOIN disciplinas d ON a.id_disciplina = d.id WHERE a.status = 'aprovado' ORDER BY a.data_reserva DESC")->fetchAll(PDO::FETCH_ASSOC);
$historico_completo = $pdo->query("SELECT a.*, l.nome as laboratorio, u.nome as professor, d.nome as disciplina FROM agendamentos a JOIN laboratorios l ON a.id_laboratorio = l.id JOIN usuarios u ON a.id_professor = u.id JOIN disciplinas d ON a.id_disciplina = d.id ORDER BY a.data_reserva DESC, a.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$professores = $pdo->query("SELECT id, nome FROM usuarios WHERE perfil = 'professor' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$laboratorios_cadastrados = $pdo->query("SELECT * FROM laboratorios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$disciplinas = $pdo->query("SELECT * FROM disciplinas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$cursos_cadastrados = [];
$semestres_cadastrados = [];
$blocos_cadastrados = [];
$andares_cadastrados = [];
$salas_cadastradas = [];
try {
    $cursos_cadastrados = $pdo->query("SELECT * FROM cursos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $semestres_cadastrados = $pdo->query("SELECT * FROM semestres ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $blocos_cadastrados = $pdo->query("SELECT * FROM blocos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $andares_cadastrados = $pdo->query("SELECT * FROM andares ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $salas_cadastradas = $pdo->query("SELECT * FROM salas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

$lista_ensalamentos = $pdo->query("SELECT e.*, u.nome as professor, d.nome as disciplina FROM ensalamento e JOIN usuarios u ON e.id_professor = u.id JOIN disciplinas d ON e.id_disciplina = d.id ORDER BY e.curso ASC, e.turno ASC, e.bloco ASC")->fetchAll(PDO::FETCH_ASSOC);

$lista_quadros = [];
try {
    $lista_quadros = $pdo->query("SELECT * FROM quadros_horarios ORDER BY data_criacao DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
$quadro_selecionado = isset($_GET['q_id']) ? $_GET['q_id'] : (count($lista_quadros) > 0 ? $lista_quadros[0]['id'] : null);

$aulas_do_quadro = [];
$dias_semana = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$todas_aulas = [];

if ($quadro_selecionado) {
    try {
        $stmt_qa = $pdo->prepare("SELECT qa.*, p.nome as prof_nome, d.nome as disc_nome, l.nome as lab_nome, l.localizacao as lab_local, l.andar as lab_andar FROM quadro_aulas qa LEFT JOIN usuarios p ON qa.id_professor = p.id JOIN disciplinas d ON qa.id_disciplina = d.id LEFT JOIN laboratorios l ON qa.id_laboratorio = l.id WHERE qa.id_quadro = ? ORDER BY FIELD(qa.turno, 'Matutino', 'Vespertino', 'Noturno'), qa.horario ASC, qa.curso ASC, qa.semestre ASC");
        $stmt_qa->execute([$quadro_selecionado]);
        $todas_aulas = $stmt_qa->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
    foreach ($dias_semana as $dia) {
        $aulas_do_quadro[$dia] = [];
    }
    foreach ($todas_aulas as $a) {
        $aulas_do_quadro[$a['dia_semana']][] = $a;
    }
}

// =================================================================================
// LÓGICA MASTER DOS RELATÓRIOS BI E GRÁFICOS
// =================================================================================
$relatorio_professores = [];
$relatorio_labs = [];
$erro_banco_relatorio = false;
$grafico_prof_nomes = [];
$grafico_prof_horas = [];
$grafico_prof_lab = [];
$grafico_prof_sala = [];
$grafico_lab_nomes = [];
$grafico_lab_uso = [];
$grafico_lab_ocioso = [];
$uso_global = 0;
$lab_mais_usado = ['nome' => '-', 'horas' => 0];
$lab_mais_ocioso = ['nome' => '-', 'horas' => 0];

if ($quadro_selecionado) {
    try {
        $sql_prof = "SELECT p.nome as professor, SUM(CASE WHEN qa.dia_semana = 'Segunda' THEN qa.carga_horaria_total ELSE 0 END) as seg_t, SUM(CASE WHEN qa.dia_semana = 'Segunda' THEN qa.horas_laboratorio ELSE 0 END) as seg_l, SUM(CASE WHEN qa.dia_semana = 'Terça' THEN qa.carga_horaria_total ELSE 0 END) as ter_t, SUM(CASE WHEN qa.dia_semana = 'Terça' THEN qa.horas_laboratorio ELSE 0 END) as ter_l, SUM(CASE WHEN qa.dia_semana = 'Quarta' THEN qa.carga_horaria_total ELSE 0 END) as qua_t, SUM(CASE WHEN qa.dia_semana = 'Quarta' THEN qa.horas_laboratorio ELSE 0 END) as qua_l, SUM(CASE WHEN qa.dia_semana = 'Quinta' THEN qa.carga_horaria_total ELSE 0 END) as qui_t, SUM(CASE WHEN qa.dia_semana = 'Quinta' THEN qa.horas_laboratorio ELSE 0 END) as qui_l, SUM(CASE WHEN qa.dia_semana = 'Sexta' THEN qa.carga_horaria_total ELSE 0 END) as sex_t, SUM(CASE WHEN qa.dia_semana = 'Sexta' THEN qa.horas_laboratorio ELSE 0 END) as sex_l, SUM(CASE WHEN qa.dia_semana = 'Sábado' THEN qa.carga_horaria_total ELSE 0 END) as sab_t, SUM(CASE WHEN qa.dia_semana = 'Sábado' THEN qa.horas_laboratorio ELSE 0 END) as sab_l, SUM(qa.carga_horaria_total) as total, SUM(qa.horas_laboratorio) as total_l FROM quadro_aulas qa JOIN usuarios p ON qa.id_professor = p.id WHERE qa.id_quadro = ? GROUP BY p.id, p.nome ORDER BY total DESC, p.nome ASC";
        $stmt_prof = $pdo->prepare($sql_prof);
        $stmt_prof->execute([$quadro_selecionado]);
        $relatorio_professores = $stmt_prof->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($relatorio_professores as $rp) {
            if ($count < 10) {
                $grafico_prof_nomes[] = $rp['professor'];
                $grafico_prof_horas[] = $rp['total'];
                $grafico_prof_lab[] = $rp['total_l'];
                $grafico_prof_sala[] = $rp['total'] - $rp['total_l'];
                $count++;
            }
        }

        $sql_lab = "SELECT l.nome as laboratorio, COALESCE(SUM(CASE WHEN qa.dia_semana = 'Segunda' THEN qa.horas_laboratorio ELSE 0 END), 0) as seg, COALESCE(SUM(CASE WHEN qa.dia_semana = 'Terça' THEN qa.horas_laboratorio ELSE 0 END), 0) as ter, COALESCE(SUM(CASE WHEN qa.dia_semana = 'Quarta' THEN qa.horas_laboratorio ELSE 0 END), 0) as qua, COALESCE(SUM(CASE WHEN qa.dia_semana = 'Quinta' THEN qa.horas_laboratorio ELSE 0 END), 0) as qui, COALESCE(SUM(CASE WHEN qa.dia_semana = 'Sexta' THEN qa.horas_laboratorio ELSE 0 END), 0) as sex, COALESCE(SUM(CASE WHEN qa.dia_semana = 'Sábado' THEN qa.horas_laboratorio ELSE 0 END), 0) as sab, COALESCE(SUM(qa.horas_laboratorio), 0) as total FROM laboratorios l LEFT JOIN quadro_aulas qa ON l.id = qa.id_laboratorio AND qa.id_quadro = ? GROUP BY l.id, l.nome ORDER BY total DESC, l.nome ASC";
        $stmt_lab = $pdo->prepare($sql_lab);
        $stmt_lab->execute([$quadro_selecionado]);
        $relatorio_labs = $stmt_lab->fetchAll(PDO::FETCH_ASSOC);

        $capacidade_max_semanal = 60;
        $min_uso = 9999;
        $max_uso = -1;
        foreach ($relatorio_labs as $rl) {
            $uso_global += $rl['total'];
            $ocioso = $capacidade_max_semanal - $rl['total'];
            $grafico_lab_nomes[] = $rl['laboratorio'];
            $grafico_lab_uso[] = $rl['total'];
            $grafico_lab_ocioso[] = $ocioso;

            if ($rl['total'] > $max_uso) {
                $max_uso = $rl['total'];
                $lab_mais_usado = ['nome' => $rl['laboratorio'], 'horas' => $rl['total']];
            }
            if ($rl['total'] < $min_uso) {
                $min_uso = $rl['total'];
                $lab_mais_ocioso = ['nome' => $rl['laboratorio'], 'horas' => $ocioso];
            }
        }

        $total_labs_count = count($laboratorios_cadastrados);
        $capacidade_global = $total_labs_count * $capacidade_max_semanal;
        $taxa_ocupacao_global = $capacidade_global > 0 ? round(($uso_global / $capacidade_global) * 100) : 0;
        $taxa_ociosidade_global = 100 - $taxa_ocupacao_global;

        $sql_curso = "SELECT curso, SUM(carga_horaria_total) as total FROM quadro_aulas WHERE id_quadro = ? GROUP BY curso ORDER BY total DESC";
        $stmt_curso = $pdo->prepare($sql_curso);
        $stmt_curso->execute([$quadro_selecionado]);
        $relatorio_cursos = $stmt_curso->fetchAll(PDO::FETCH_ASSOC);

        $grafico_curso_nomes = [];
        $grafico_curso_horas = [];
        foreach ($relatorio_cursos as $rc) {
            $grafico_curso_nomes[] = $rc['curso'];
            $grafico_curso_horas[] = $rc['total'];
        }

    } catch (PDOException $e) {
        $erro_banco_relatorio = true;
    }
}

function renderCellProf($t, $l)
{
    if ($t == 0)
        return '<td class="text-muted opacity-25">-</td>';
    $s = $t - $l;
    $html = '<td><div class="fw-bold text-dark">' . $t . 'h</div><div style="font-size:0.75rem; line-height:1; margin-top:2px;">';
    if ($l > 0)
        $html .= '<span class="text-danger">' . $l . 'L</span> ';
    if ($s > 0)
        $html .= '<span class="text-success">' . $s . 'S</span>';
    $html .= '</div></td>';
    return $html;
}

// --- CALENDÁRIO ---
$eventos_calendario = [];
function converterHorario($turno, $periodo)
{
    if ($turno == 'Matutino') {
        return ($periodo == '1º Horário') ? ['08:20:00', '10:00:00'] : (($periodo == '2º Horário') ? ['10:15:00', '11:55:00'] : ['08:20:00', '11:55:00']);
    }
    if ($turno == 'Vespertino') {
        return ($periodo == '1º Horário') ? ['14:20:00', '16:00:00'] : (($periodo == '2º Horário') ? ['16:20:00', '18:00:00'] : ['14:20:00', '18:00:00']);
    }
    return ($periodo == '1º Horário') ? ['19:20:00', '21:00:00'] : (($periodo == '2º Horário') ? ['21:10:00', '22:50:00'] : ['19:20:00', '22:50:00']);
}

foreach ($agendamentos_aprovados as $av) {
    list($start, $end) = converterHorario($av['turno'], $av['periodo']);
    $eventos_calendario[] = ['title' => $av['disciplina'] . ' (' . $av['professor'] . ')', 'start' => $av['data_reserva'] . 'T' . $start, 'end' => $av['data_reserva'] . 'T' . $end, 'className' => 'apple-event-avulsa', 'extendedProps' => ['local' => '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($av['laboratorio'])]];
}

if ($quadro_selecionado && count($todas_aulas) > 0) {
    $dias_map_num = ['Domingo' => 0, 'Segunda' => 1, 'Terça' => 2, 'Quarta' => 3, 'Quinta' => 4, 'Sexta' => 5, 'Sábado' => 6];
    $ano_atual = date('Y');
    $mes_atual = (int) date('m');
    if ($mes_atual <= 6) {
        $start_recur = $ano_atual . '-01-01';
        $end_recur = $ano_atual . '-07-31';
    } else {
        $start_recur = $ano_atual . '-07-01';
        $end_recur = $ano_atual . '-12-31';
    }
    foreach ($todas_aulas as $f) {
        list($start, $end) = converterHorario($f['turno'], $f['horario']);
        $dia_num = $dias_map_num[$f['dia_semana']] ?? 1;
        $loc = $f['id_laboratorio'] ? '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($f['lab_nome']) : '<i class="bi bi-door-open me-1"></i> Sala: ' . htmlspecialchars($f['sala'] ?? '-');
        $eventos_calendario[] = ['title' => $f['disc_nome'] . ' (' . ($f['prof_nome'] ?? 'EAD') . ')', 'daysOfWeek' => [$dia_num], 'startTime' => $start, 'endTime' => $end, 'startRecur' => $start_recur, 'endRecur' => $end_recur, 'className' => 'apple-event-fixa', 'extendedProps' => ['local' => $loc]];
    }
}

$feriados_2026 = ['2026-01-01' => 'Ano Novo', '2026-02-16' => 'Recesso de Carnaval', '2026-02-17' => 'Carnaval', '2026-04-03' => 'Paixão de Cristo', '2026-04-21' => 'Tiradentes', '2026-05-01' => 'Dia do Trabalho', '2026-06-04' => 'Corpus Christi', '2026-09-07' => 'Independência', '2026-10-12' => 'Nossa Sra. Aparecida', '2026-11-02' => 'Finados', '2026-11-15' => 'Proclamação da República', '2026-12-25' => 'Natal'];
foreach ($feriados_2026 as $data => $nome_feriado) {
    $eventos_calendario[] = ['title' => 'Feriado: ' . $nome_feriado, 'start' => $data, 'allDay' => true, 'className' => 'apple-event-feriado', 'extendedProps' => ['local' => '<i class="bi bi-calendar-x me-1"></i> Instituição Fechada']];
}
$eventos_json = json_encode($eventos_calendario);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Painel da Coordenação - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales-all.global.min.js"></script>

    <script>const savedTheme = localStorage.getItem('tema-uniceplac') || 'light'; document.documentElement.setAttribute('data-bs-theme', savedTheme);</script>

    <style>
        :root {
            --verde-uniceplac: #00734F;
            --roxo-uniceplac: #421B71;
            --laranja-uniceplac: #F0733C;
            --azul-google: #4285F4;
            --manha-cor: var(--verde-uniceplac);
            --tarde-cor: var(--laranja-uniceplac);
            --noite-cor: var(--roxo-uniceplac);
        }

        body {
            background-color: #f8f9fa;
            transition: background-color 0.3s ease;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        .card,
        .card-header,
        .form-control,
        .form-select,
        .btn,
        .badge,
        .alert,
        .offcanvas,
        .modal-content {
            border-radius: 0 !important;
        }

        .bg-uniceplac {
            background-color: var(--verde-uniceplac) !important;
        }

        .content-section {
            display: none;
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .offcanvas-menu-link {
            padding: 12px 20px;
            color: #495057;
            text-decoration: none;
            display: block;
            border-bottom: 1px solid #f1f1f1;
            font-weight: 500;
            transition: 0.2s;
            cursor: pointer;
        }

        .offcanvas-menu-link:hover,
        .offcanvas-menu-link.active-link {
            background-color: rgba(0, 115, 79, 0.05);
            color: var(--verde-uniceplac);
            border-right: 4px solid var(--verde-uniceplac);
        }

        .avatar-img-small {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50% !important;
            border: 2px solid #dee2e6;
            cursor: pointer;
        }

        .top-icon-btn {
            color: #495057;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            transition: 0.2s;
        }

        .top-icon-btn:hover {
            color: var(--verde-uniceplac);
        }

        #nova_foto_input {
            display: none;
        }

        .transition-transform {
            transition: transform 0.3s ease;
        }

        .apple-search-box {
            background: #f5f5f7;
            border-radius: 20px;
            padding: 6px 16px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .apple-search-box:focus-within {
            background: #fff;
            border-color: rgba(0, 122, 255, 0.5);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
        }

        .apple-search-input {
            border: none;
            background: transparent;
            width: 100%;
            padding: 8px;
            outline: none;
            color: #1d1d1f;
        }

        .grade-wrapper {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            max-height: 700px;
            overflow-y: auto;
        }

        .grade-container {
            display: flex;
            width: 100%;
            min-width: 1200px;
        }

        .grade-coluna {
            flex: 1;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .grade-coluna:last-child {
            border-right: none;
        }

        .grade-cabecalho {
            background: #f8f9fa;
            text-align: center;
            padding: 12px 10px;
            font-weight: 800;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #495057;
            border-bottom: 3px solid var(--azul-google);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .grade-corpo {
            padding: 8px;
            flex-grow: 1;
            background-color: #fafbfe;
        }

        .aula-card-google {
            background: rgba(0, 115, 79, 0.05);
            border-left: 4px solid var(--verde-uniceplac);
            border-radius: 6px;
            padding: 10px;
            margin: 8px;
            font-size: 0.85rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .aula-card-google.matutino {
            background: rgba(0, 115, 79, 0.12);
            border-left-color: var(--manha-cor);
        }

        .aula-card-google.vespertino {
            background: rgba(240, 115, 60, 0.12);
            border-left-color: var(--tarde-cor);
        }

        .aula-card-google.noturno {
            background: rgba(66, 27, 113, 0.12);
            border-left-color: var(--noite-cor);
        }

        /* =======================================================
           EFEITO VIDRO E SELOS (EAD NA GRADE)
           ======================================================= */
        .aula-card-google.aula-ead-glass {
            background: rgba(255, 193, 7, 0.15) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            border: 1px solid rgba(255, 193, 7, 0.4) !important;
            border-left: 4px solid #ffc107 !important;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.1) !important;
        }

        [data-bs-theme="dark"] .aula-card-google.aula-ead-glass {
            background: rgba(255, 193, 7, 0.08) !important;
            border: 1px solid rgba(255, 193, 7, 0.2) !important;
            border-left: 4px solid #ffca2c !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3) !important;
            color: #fff !important;
        }

        .selo-matutino {
            background-color: var(--manha-cor) !important;
            color: #fff !important;
        }

        .selo-vespertino {
            background-color: var(--tarde-cor) !important;
            color: #fff !important;
        }

        .selo-noturno {
            background-color: var(--noite-cor) !important;
            color: #fff !important;
        }

        [data-bs-theme="dark"] .text-dark.prof-nome {
            color: #f8f9fa !important;
        }

        #calendarioGeral {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        /* Estilos Padrões do Calendário Claro */
        .fc-theme-standard .fc-scrollgrid {
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }

        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: rgba(0, 0, 0, 0.05);
        }

        .fc-col-header-cell {
            background-color: #fbfbfd;
            padding: 8px 0;
            font-weight: 600;
            color: #86868b;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .fc .fc-button-group>.fc-button {
            background: #f5f5f7 !important;
            color: #007aff !important;
            border-color: #d2d2d7 !important;
            text-transform: capitalize;
            box-shadow: none !important;
            font-weight: 500;
            transition: 0.2s;
        }

        .fc .fc-button-group>.fc-button:hover {
            background: #e8e8ed !important;
        }

        .fc .fc-button-group>.fc-button.fc-button-active {
            background: #007aff !important;
            color: #fff !important;
            border-color: #007aff !important;
        }

        .fc .fc-today-button {
            background: #fff !important;
            color: #007aff !important;
            border-color: #d2d2d7 !important;
            font-weight: 600;
            text-transform: capitalize;
        }

        .fc-toolbar-title {
            font-weight: 700 !important;
            color: #1d1d1f;
            text-transform: capitalize;
        }

        .apple-event-fixa {
            --fc-event-bg-color: rgba(66, 27, 113, 0.12);
            --fc-event-border-color: var(--roxo-uniceplac);
            --fc-event-text-color: var(--roxo-uniceplac);
        }

        .apple-event-avulsa {
            --fc-event-bg-color: rgba(240, 115, 60, 0.12);
            --fc-event-border-color: var(--laranja-uniceplac);
            --fc-event-text-color: #c95b28;
        }

        .apple-event-feriado {
            --fc-event-bg-color: rgba(220, 53, 69, 0.12);
            --fc-event-border-color: #dc3545;
            --fc-event-text-color: #a71d2a;
        }

        .fc-event {
            border-left-width: 4px !important;
            border-radius: 6px !important;
            border-top: none !important;
            border-right: none !important;
            border-bottom: none !important;
            padding: 2px !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin-bottom: 2px;
            cursor: pointer;
        }

        .fc-event-main {
            width: 100%;
        }

        .text-truncate-multi {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .text-truncate-single {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        /* ==========================================================
           TEMA ESCURO (DARK MODE) - GERAL
           ========================================================== */
        [data-bs-theme="dark"] body {
            background-color: #121212;
            color: #e0e0e0;
        }

        [data-bs-theme="dark"] .bg-white,
        [data-bs-theme="dark"] .bg-light {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }

        [data-bs-theme="dark"] .card {
            background-color: #1e1e1e;
            border-color: #333 !important;
        }

        [data-bs-theme="dark"] .text-dark {
            color: #f8f9fa !important;
        }

        [data-bs-theme="dark"] .text-secondary,
        [data-bs-theme="dark"] .text-muted {
            color: #adb5bd !important;
        }

        [data-bs-theme="dark"] .border,
        [data-bs-theme="dark"] .border-bottom {
            border-color: #333 !important;
        }

        [data-bs-theme="dark"] .table {
            color: #e0e0e0;
            border-color: #444;
        }

        [data-bs-theme="dark"] .table-light th {
            background-color: #2a2a2a !important;
            color: #e0e0e0;
            border-color: #444;
        }

        [data-bs-theme="dark"] .offcanvas {
            background-color: #1e1e1e !important;
        }

        [data-bs-theme="dark"] .offcanvas-menu-link {
            color: #e0e0e0;
            border-bottom-color: #333;
        }

        [data-bs-theme="dark"] .offcanvas-menu-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #2a2a2a;
            color: #fff;
            border-color: #444;
        }

        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #333;
            color: #fff;
            border-color: var(--verde-uniceplac);
        }

        [data-bs-theme="dark"] .modal-content {
            background-color: #1e1e1e;
            border-color: #444;
            border-radius: 20px !important;
        }

        [data-bs-theme="dark"] .top-icon-btn {
            color: #e0e0e0;
        }

        [data-bs-theme="dark"] .top-icon-btn:hover {
            color: var(--laranja-uniceplac);
        }

        [data-bs-theme="dark"] .apple-search-box {
            background: #2c2c2e;
        }

        [data-bs-theme="dark"] .apple-search-box:focus-within {
            background: #1c1c1e;
        }

        [data-bs-theme="dark"] .apple-search-input {
            color: #f8f9fa;
        }

        [data-bs-theme="dark"] .aula-card-google {
            background: rgba(0, 115, 79, 0.1);
            color: #e0e0e0;
        }

        [data-bs-theme="dark"] .aula-card-google.matutino {
            background: rgba(0, 115, 79, 0.15);
        }

        [data-bs-theme="dark"] .aula-card-google.vespertino {
            background: rgba(240, 115, 60, 0.15);
        }

        [data-bs-theme="dark"] .aula-card-google.noturno {
            background: rgba(66, 27, 113, 0.25);
        }

        /* ==========================================================
           DARK MODE - GRADE DE HORÁRIOS (FORÇA MÁXIMA)
           ========================================================== */
        [data-bs-theme="dark"] .grade-wrapper {
            background-color: #1e1e1e !important;
            border-color: #333 !important;
        }

        [data-bs-theme="dark"] .grade-coluna {
            border-right-color: #333 !important;
        }

        [data-bs-theme="dark"] .grade-cabecalho {
            background-color: #2a2a2a !important;
            color: #e0e0e0 !important;
            border-bottom: 3px solid #0d6efd !important;
        }

        [data-bs-theme="dark"] .grade-corpo {
            background-color: #121212 !important;
        }

        /* ==========================================================
           GARANTIA: ESCONDE CABEÇALHOS DE IMPRESSÃO NA TELA
           ========================================================== */
        @media screen {

            .print-only-header,
            .d-print-block {
                display: none !important;
            }
        }

        /* ==========================================================
           MODO DE IMPRESSÃO (PDF) - EXTERMINADOR DE PÁGINAS EM BRANCO
           ========================================================== */
        @media print {
            @page {
                size: landscape;
                margin: 5mm;
            }

            /* 1. A BOMBA ATÔMICA: Destrava qualquer altura ou rolagem oculta em TODOS os elementos */
            *,
            *::before,
            *::after {
                overflow: visible !important;
                height: auto !important;
                min-height: 0 !important;
                max-height: none !important;
            }

            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* 2. ESCONDE O LIXO (Menus, modais, painéis) */
            nav,
            .offcanvas,
            form,
            .btn,
            .top-icon-btn,
            #container-mensagens,
            .card-header,
            .apple-search-box,
            .d-print-none,
            #navBell,
            .modal,
            .modal-backdrop {
                display: none !important;
            }

            .content-section {
                display: none !important;
            }

            #sessao-quadro-horario {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* 3. O CABEÇALHO DE IMPRESSÃO */
            .print-only-header {
                display: flex !important;
                margin-bottom: 10px !important;
                page-break-after: avoid !important;
            }

            /* O SEGREDO PARA A LOGO FICAR PERFEITA NO PAPEL */
            .print-only-header img {
                height: 15mm !important;
                /* Altura exata de 1,5 centímetros no papel */
                max-height: 15mm !important;
                width: auto !important;
                /* Mantém a proporção sem amassar a imagem */
                margin-right: 15px !important;
            }

            /* 4. A GRADE (Forçada a caber na largura da página) */
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
            }

            .bg-white.border.shadow-sm.p-3.overflow-auto {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            .grade-wrapper {
                border: none !important;
                display: block !important;
            }

            .grade-container {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                width: 100% !important;
                page-break-inside: auto !important;
            }

            .grade-coluna {
                flex: 1 1 0 !important;
                width: 16.66% !important;
                min-width: 0 !important;
                /* Ajuda a não estourar a largura do A4 */
                border-right: 1px solid #ccc !important;
                display: block !important;
                /* Libera os cards para caírem naturalmente */
            }

            .grade-coluna:last-child {
                border-right: none !important;
            }

            .grade-cabecalho {
                font-size: 10px !important;
                padding: 4px !important;
                border-bottom: 2px solid #000 !important;
                text-align: center !important;
                page-break-after: avoid !important;
            }

            .grade-corpo {
                display: block !important;
                padding: 2px !important;
            }

            /* 5. OS CARDS DAS AULAS (Compactos e inquebráveis) */
            .aula-card-google {
                page-break-inside: avoid !important;
                /* Impede que corte a aula no meio */
                break-inside: avoid !important;
                font-size: 8px !important;
                padding: 4px !important;
                margin: 2px !important;
                border: 1px solid #e0e0e0 !important;
                border-left-width: 4px !important;
            }

            .aula-card-google * {
                font-size: 8px !important;
                line-height: 1.1 !important;
                margin-bottom: 1px !important;
            }
        }
    </style>
</head>

<body>

    <div class="d-none d-print-block w-100 mb-4 print-only-header">
        <div class="d-flex align-items-center border-bottom pb-3">
            <img src="uniceplac2.png" alt="Logo" style="height: 60px; margin-right: 20px;">
            <div>
                <h4 class="mb-0 fw-bold" style="color: #00734F;">CENTRAL DE RESERVAS ACADÊMICAS</h4>
                <p class="mb-0 text-muted small">Relatório Gerencial de Ocupação de Laboratórios</p>
            </div>
        </div>
    </div>

    <form id="formFotoPerfil" action="painel_coordenador.php" method="POST" enctype="multipart/form-data"
        class="d-none">
        <input type="file" name="nova_foto" id="nova_foto_input" accept="image/png, image/jpeg, image/webp">
    </form>

    <nav class="navbar navbar-light bg-white mb-4 border-bottom shadow-sm sticky-top">
        <div class="container-fluid px-3 px-md-4">
            <span class="navbar-brand d-flex align-items-center"><img src="uniceplac2.png" id="navbarLogo" alt="Logo"
                    style="height: 70px; margin-right: 12px; transition: 0.3s;"></span>
            <div class="ms-auto d-flex align-items-center">
                <div class="me-4 top-icon-btn" id="themeToggleBtn" title="Alternar Tema"><i class="bi bi-moon-stars"
                        id="themeIcon"></i></div>
                <div class="position-relative me-4 top-icon-btn" id="navBell" title="Ver Solicitações Pendentes"
                    onclick="showSection('sessao-aprovacoes')">
                    <i class="bi bi-bell"></i>
                    <span id="badge-nav-bell"
                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light text-white <?= $qtd_pendentes > 0 ? '' : 'd-none' ?>"
                        style="font-size: 0.65rem; padding: 0.25em 0.4em;"><?= $qtd_pendentes ?></span>
                </div>
                <div class="me-3 top-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu"><i
                        class="bi bi-grid-3x3-gap fs-5"></i></div>
                <img src="<?= htmlspecialchars($foto_atual) ?>" alt="Foto" class="avatar-img-small ms-1"
                    id="btnAlterarFotoNav">
            </div>
        </div>
    </nav>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="sidebarMenu">
        <div class="offcanvas-header bg-uniceplac text-white py-3 border-0">
            <h6 class="offcanvas-title fw-bold">Coordenação</h6><button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column bg-white">
            <div class="p-4 text-center border-bottom bg-light">
                <img src="<?= htmlspecialchars($foto_atual) ?>" alt="Foto"
                    style="width: 80px; height: 80px; object-fit: cover; border-radius: 50% !important; border: 3px solid var(--roxo-uniceplac);"
                    class="shadow-sm mb-2">
                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($_SESSION['nome']) ?></h5>
                <span class="badge bg-uniceplac text-uppercase mt-2 px-3 py-1">Coordenador</span>
            </div>
            <div class="flex-grow-1 overflow-auto">
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50">Macro-Gestão</div>
                <a href="javascript:void(0);" onclick="showSection('sessao-calendario-geral')"
                    data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link text-primary fw-bold bg-light border-start border-4 border-primary"><i
                        class="bi bi-calendar3 me-2"></i> Calendário Geral</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-quadro-horario')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-table me-2"></i> Quadro de Horários (Editor)</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-relatorios')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-graph-up-arrow text-success me-2"></i> Relatórios e
                    Métricas</a>

                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50 border-top mt-2">Gestão Dinâmica
                </div>
                <a href="javascript:void(0);" onclick="showSection('sessao-agendar-lab')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-calendar-plus text-primary me-2"></i> Agendar
                    Laboratório</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-historico-geral')"
                    data-bs-dismiss="offcanvas" class="offcanvas-menu-link"><i
                        class="bi bi-clock-history text-info me-2"></i> Histórico de Solicitações</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-ensalamento')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-building text-primary me-2"></i> Grade de
                    Ensalamento</a>

                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50 border-top mt-2">Cadastros Base</div>
                <a href="javascript:void(0);" onclick="showSection('sessao-cursos')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-mortarboard text-primary me-2"></i> Cursos</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-semestres')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-calendar-range text-dark me-2"></i> Semestres</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-disciplinas')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-book-half text-secondary me-2"></i> Disciplinas</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-labs')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-pc-display text-info me-2"></i> Laboratórios</a>
                <a href="javascript:void(0);" onclick="showSection('sessao-locais')" data-bs-dismiss="offcanvas"
                    class="offcanvas-menu-link"><i class="bi bi-geo-alt-fill text-danger me-2"></i> Locais
                    (Blocos/Salas)</a>

                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50 border-top mt-2">Visão Operacional
                </div>
                <div class="fw-bold text-dark d-flex justify-content-between align-items-center p-3"
                    onclick="abrirSanfona('dropMenuTI', 'setaDropTI')"
                    style="background-color: #fff3cd; border-left: 4px solid #ffc107; cursor: pointer; transition: 0.3s;">
                    <span><i class="bi bi-headset text-warning me-2 fs-5"></i> Menu de TI</span>
                    <i class="bi bi-chevron-down transition-transform text-muted" id="setaDropTI"
                        style="transition: transform 0.3s ease;"></i>
                </div>
                <div id="dropMenuTI" style="display: none;">
                    <div class="bg-light border-start border-4 border-warning ms-3 mb-2">
                        <a href="painel_suporte.php" class="p-3 text-muted d-block text-decoration-none fw-bold"
                            onmouseover="this.style.backgroundColor='rgba(0,0,0,0.05)'"
                            onmouseout="this.style.backgroundColor='transparent'"><i
                                class="bi bi-ticket-detailed me-2"></i> Acessar Painel TI</a>
                    </div>
                </div>
            </div>
            <div class="p-3 border-top mt-auto"><a href="logout.php"
                    class="btn btn-outline-danger w-100 fw-bold">Sair</a></div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">
        <div id="container-mensagens"><?= $mensagem ?></div>

        <?php if ($erro_banco_relatorio): ?>
            <div class="alert alert-warning text-center shadow-sm"><i
                    class="bi bi-tools fs-3 d-block mb-2"></i><strong>Quase lá!</strong> Rode o UPDATE SQL no banco de dados
                para criar as colunas de Carga Horária e habilitar os Relatórios.</div>
        <?php endif; ?>

        <div id="sessao-calendario-geral" class="content-section">
            <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid var(--verde-uniceplac);">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center"><i
                            class="bi bi-calendar3 text-primary me-3 fs-4"></i> Calendário Consolidado</h5>
                    <p class="text-muted small mb-0 mt-1">
                        <span class="badge me-2"
                            style="background-color: rgba(66, 27, 113, 0.15); color: var(--roxo-uniceplac); border: 1px solid var(--roxo-uniceplac);">Aulas
                            Fixas</span>
                        <span class="badge me-2"
                            style="background-color: rgba(240, 115, 60, 0.15); color: #c95b28; border: 1px solid var(--laranja-uniceplac);">Reservas
                            Avulsas</span>
                        <span class="badge"
                            style="background-color: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545;">Feriados
                            Nacionais</span>
                    </p>
                </div>
                <div class="card-body bg-white p-3 p-md-4">
                    <div id="calendarioGeral"></div>
                </div>
            </div>
        </div>

        <div id="sessao-relatorios" class="content-section">
            <?php if (!$quadro_selecionado): ?>
                <div class="alert alert-warning text-center py-5 shadow-sm border-0" style="border-radius: 12px;"><i
                        class="bi bi-exclamation-triangle fs-1 d-block mb-3"></i><strong>Atenção:</strong> Você precisa
                    selecionar um "Quadro de Horários" na aba Editor para gerar os relatórios.</div>
            <?php else: ?>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                    <div>
                        <h4 class="text-uniceplac fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2"></i> Dashboard
                            Interativo</h4>
                        <p class="text-muted small mb-0">Métricas referentes ao cenário atual. <span class="fw-bold">Clique
                                nos gráficos para filtrar as tabelas abaixo.</span></p>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <button class="btn btn-outline-success" onclick="exportarDashboardCSV()">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar CSV
                        </button>
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Imprimir
                        </button>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100 bg-white"
                            style="border-left: 4px solid var(--verde-uniceplac);">
                            <div class="card-body p-3">
                                <h6 class="text-muted text-uppercase fw-bold small mb-1">Ocupação Global (Labs)</h6>
                                <h2 class="fw-bold text-dark mb-2"><?= $taxa_ocupacao_global ?>%</h2>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: <?= $taxa_ocupacao_global ?>%;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100 bg-white"
                            style="border-left: 4px solid var(--laranja-uniceplac);">
                            <div class="card-body p-3">
                                <h6 class="text-muted text-uppercase fw-bold small mb-1">Ociosidade Global (Labs)</h6>
                                <h2 class="fw-bold text-danger mb-2"><?= $taxa_ociosidade_global ?>%</h2>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-danger" style="width: <?= $taxa_ociosidade_global ?>%;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100 bg-white" style="border-left: 4px solid var(--info);">
                            <div class="card-body p-3">
                                <h6 class="text-muted text-uppercase fw-bold small mb-1">Lab Mais Ocioso</h6>
                                <h4 class="fw-bold text-dark mb-0 text-truncate"
                                    title="<?= htmlspecialchars($lab_mais_ocioso['nome']) ?>">
                                    <?= htmlspecialchars($lab_mais_ocioso['nome']) ?>
                                </h4>
                                <small class="text-danger fw-bold"><?= $lab_mais_ocioso['horas'] ?>h Livres na
                                    semana</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 h-100 bg-white"
                            style="border-left: 4px solid var(--roxo-uniceplac);">
                            <div class="card-body p-3">
                                <h6 class="text-muted text-uppercase fw-bold small mb-1">Lab Mais Utilizado</h6>
                                <h4 class="fw-bold text-dark mb-0 text-truncate"
                                    title="<?= htmlspecialchars($lab_mais_usado['nome']) ?>">
                                    <?= htmlspecialchars($lab_mais_usado['nome']) ?>
                                </h4>
                                <small class="text-success fw-bold"><?= $lab_mais_usado['horas'] ?>h Ocupadas na
                                    semana</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="apple-search-box shadow-sm">
                        <i class="bi bi-search text-muted ms-2 fs-5"></i>
                        <input type="text" id="filtroDashInput" class="apple-search-input fs-5 ms-2"
                            placeholder="Pesquise por professor ou laboratório...">
                        <i class="bi bi-x-circle-fill text-muted me-2 fs-5" id="btnLimparFiltroDash"
                            style="cursor: pointer; display: none;" title="Limpar Filtro"></i>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                            <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                                <h6 class="fw-bold text-dark mb-0">Perfil de Ensino (Top 10 Professores)</h6>
                                <small class="text-muted">Horas em Sala (Verde) vs Horas no Lab (Vermelho)</small>
                            </div>
                            <div class="card-body" style="position: relative; height: 300px;">
                                <canvas id="chartPerfilAulas"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                            <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                                <h6 class="fw-bold text-dark mb-0">Demanda de Infraestrutura por Curso</h6>
                                <small class="text-muted">Volume total de horas consumidas na grade</small>
                            </div>
                            <div class="card-body"
                                style="position: relative; height: 300px; display: flex; justify-content: center;">
                                <canvas id="chartCursos"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold text-dark mb-3 mt-5"><i class="bi bi-battery-half text-warning me-2"></i> Raio-X de
                    Ociosidade dos Laboratórios</h5>
                <div class="row g-3 mb-4">
                    <?php
                    if (count($relatorio_labs) > 0):
                        foreach ($relatorio_labs as $rl):
                            $ocioso = $capacidade_max_semanal - $rl['total'];
                            $pct = round(($rl['total'] / $capacidade_max_semanal) * 100);
                            ?>
                            <div class="col-md-3 card-ociosidade" data-search="<?= strtolower($rl['laboratorio']) ?>">
                                <div class="card shadow-sm border-0">
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold text-dark mb-1 text-truncate"
                                            title="<?= htmlspecialchars($rl['laboratorio']) ?>">
                                            <?= htmlspecialchars($rl['laboratorio']) ?>
                                        </h6>
                                        <div class="d-flex justify-content-between small mb-2">
                                            <span class="text-danger fw-bold">Uso: <?= $rl['total'] ?>h</span>
                                            <span class="text-success fw-bold">Livre: <?= $ocioso ?>h</span>
                                        </div>
                                        <div class="progress bg-success bg-opacity-25" style="height: 6px;">
                                            <div class="progress-bar bg-danger" style="width: <?= $pct ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; else:
                        echo '<div class="col-12"><div class="alert alert-light border text-center text-muted">Sem dados de laboratórios.</div></div>';
                    endif; ?>
                </div>

                <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid #0dcaf0; border-radius: 12px;">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"
                        onclick="abrirSanfona('collapseTabelaBI', 'setaTabelaBI')" style="cursor:pointer;">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns text-info me-2"></i> Relatório
                            Analítico: Onde estão os professores?</h5>
                        <i class="bi bi-chevron-up text-muted transition-transform" id="setaTabelaBI"
                            style="transform: rotate(180deg);"></i>
                    </div>
                    <div id="collapseTabelaBI" style="display: block;">
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0 text-center">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="text-start ps-4">Professor</th>
                                            <th>Disciplina</th>
                                            <th>Localização</th>
                                            <th>Dia / Turno</th>
                                            <th>Carga Total</th>
                                            <th class="text-danger">Horas Lab</th>
                                            <th class="text-success">Horas Sala</th>
                                            <th class="pe-4 text-start">Alerta / Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpoTabelaBI">
                                        <?php if (count($todas_aulas) > 0): ?>
                                            <?php foreach ($todas_aulas as $aula):
                                                $ch_t = $aula['carga_horaria_total'] ?? 2;
                                                $ch_l = $aula['horas_laboratorio'] ?? 0;
                                                $ch_s = $ch_t - $ch_l;
                                                $badge = '<span class="badge bg-secondary">Teórica (Sala)</span>';
                                                if ($ch_l > 0 && $ch_l == $ch_t) {
                                                    $badge = '<span class="badge bg-primary">Prática (100% Lab)</span>';
                                                } elseif ($ch_l > 0 && $ch_l < $ch_t) {
                                                    $badge = '<span class="badge bg-warning text-dark"><i class="bi bi-arrow-left-right me-1"></i> Transição Lab ➔ Sala</span>';
                                                }
                                                ?>
                                                <tr class="linha-bi">
                                                    <td class="text-start ps-4 fw-bold text-dark"
                                                        data-search="<?= strtolower($aula['prof_nome'] ?? '') ?>">
                                                        <?= htmlspecialchars($aula['prof_nome'] ?? 'EAD') ?>
                                                    </td>
                                                    <td><small
                                                            class="text-muted"><?= htmlspecialchars($aula['disc_nome']) ?></small>
                                                    </td>
                                                    <td data-search="<?= strtolower($aula['lab_nome'] ?? '') ?>">
                                                        <?php if ($aula['lab_nome']): ?><span class="text-primary fw-bold"><i
                                                                    class="bi bi-pc-display me-1"></i><?= htmlspecialchars($aula['lab_nome']) ?></span>
                                                        <?php else: ?><span class="text-success"><i
                                                                    class="bi bi-door-open me-1"></i>Sala
                                                                <?= htmlspecialchars($aula['sala'] ?? '-') ?></span><?php endif; ?>
                                                    </td>
                                                    <td><?= $aula['dia_semana'] ?> <br> <small
                                                            class="text-muted"><?= $aula['turno'] ?></small></td>
                                                    <td class="fw-bold"><?= $ch_t ?>h</td>
                                                    <td class="text-danger fw-bold"><?= $ch_l > 0 ? $ch_l . 'h' : '-' ?></td>
                                                    <td class="text-success fw-bold"><?= $ch_s > 0 ? $ch_s . 'h' : '-' ?></td>
                                                    <td class="pe-4 text-start"><?= $badge ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5 text-muted">Nenhuma aula alocada neste
                                                    quadro.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4"
                    style="border-top: 4px solid var(--roxo-uniceplac); border-radius: 12px;">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"
                        onclick="abrirSanfona('collapseProfTabela', 'setaProfTabela')" style="cursor:pointer;">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-person-video3 text-primary me-2"></i> Relatório
                            Diário de Professores (Lab vs Sala)</h5>
                        <i class="bi bi-chevron-up text-muted transition-transform" id="setaProfTabela"
                            style="transform: rotate(180deg);"></i>
                    </div>
                    <div id="collapseProfTabela" style="display: block;">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-center" id="tabelaProfessores">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start ps-4">Professor</th>
                                            <th>Seg</th>
                                            <th>Ter</th>
                                            <th>Qua</th>
                                            <th>Qui</th>
                                            <th>Sex</th>
                                            <th>Sáb</th>
                                            <th class="pe-4 bg-light text-primary border-start">TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($relatorio_professores) > 0): ?>
                                            <?php foreach ($relatorio_professores as $rp): ?>
                                                <tr class="linha-filtro">
                                                    <td class="text-start ps-4 fw-bold text-dark"
                                                        data-search="<?= strtolower($rp['professor'] ?? '') ?>">
                                                        <?= htmlspecialchars($rp['professor']) ?>
                                                    </td>
                                                    <?= renderCellProf($rp['seg_t'], $rp['seg_l']) ?>
                                                    <?= renderCellProf($rp['ter_t'], $rp['ter_l']) ?>
                                                    <?= renderCellProf($rp['qua_t'], $rp['qua_l']) ?>
                                                    <?= renderCellProf($rp['qui_t'], $rp['qui_l']) ?>
                                                    <?= renderCellProf($rp['sex_t'], $rp['sex_l']) ?>
                                                    <?= renderCellProf($rp['sab_t'], $rp['sab_l']) ?>
                                                    <td class="pe-4 bg-light fw-bold fs-5 text-primary border-start">
                                                        <?= $rp['total'] ?>h<br>
                                                        <small class="fs-6 fw-normal"><span
                                                                class="text-danger"><?= $rp['total_l'] ?>L</span> | <span
                                                                class="text-success"><?= $rp['total'] - $rp['total_l'] ?>S</span></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">Nenhum professor alocado
                                                    neste quadro.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid var(--info); border-radius: 12px;">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"
                        onclick="abrirSanfona('collapseLabTabela', 'setaLabTabela')" style="cursor:pointer;">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pc-display text-info me-2"></i> Relatório Diário
                            de Ocupação de Laboratórios</h5>
                        <i class="bi bi-chevron-up text-muted transition-transform" id="setaLabTabela"
                            style="transform: rotate(180deg);"></i>
                    </div>
                    <div id="collapseLabTabela" style="display: block;">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 text-center" id="tabelaLabs">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start ps-4">Laboratório</th>
                                            <th>Seg</th>
                                            <th>Ter</th>
                                            <th>Qua</th>
                                            <th>Qui</th>
                                            <th>Sex</th>
                                            <th>Sáb</th>
                                            <th class="pe-4 bg-light text-info border-start">TOTAL Ocupado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($relatorio_labs) > 0): ?>
                                            <?php foreach ($relatorio_labs as $rl): ?>
                                                <tr class="linha-filtro">
                                                    <td class="text-start ps-4 fw-bold text-dark"
                                                        data-search="<?= strtolower($rl['laboratorio']) ?>">
                                                        <?= htmlspecialchars($rl['laboratorio']) ?>
                                                    </td>
                                                    <td
                                                        class="<?= $rl['seg'] > 0 ? 'text-danger fw-bold' : 'text-muted opacity-25' ?>">
                                                        <?= $rl['seg'] ?: '-' ?>
                                                    </td>
                                                    <td
                                                        class="<?= $rl['ter'] > 0 ? 'text-danger fw-bold' : 'text-muted opacity-25' ?>">
                                                        <?= $rl['ter'] ?: '-' ?>
                                                    </td>
                                                    <td
                                                        class="<?= $rl['qua'] > 0 ? 'text-danger fw-bold' : 'text-muted opacity-25' ?>">
                                                        <?= $rl['qua'] ?: '-' ?>
                                                    </td>
                                                    <td
                                                        class="<?= $rl['qui'] > 0 ? 'text-danger fw-bold' : 'text-muted opacity-25' ?>">
                                                        <?= $rl['qui'] ?: '-' ?>
                                                    </td>
                                                    <td
                                                        class="<?= $rl['sex'] > 0 ? 'text-danger fw-bold' : 'text-muted opacity-25' ?>">
                                                        <?= $rl['sex'] ?: '-' ?>
                                                    </td>
                                                    <td
                                                        class="<?= $rl['sab'] > 0 ? 'text-danger fw-bold' : 'text-muted opacity-25' ?>">
                                                        <?= $rl['sab'] ?: '-' ?>
                                                    </td>
                                                    <td class="pe-4 bg-light fw-bold fs-5 text-info border-start">
                                                        <?= $rl['total'] ?>h
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">Nenhum laboratório ocupado.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div id="sessao-historico-geral" class="content-section">
            <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid var(--info);">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-info me-2"></i> Histórico
                        Geral de Solicitações</h5>
                    <p class="text-muted small mb-0 mt-1">Acompanhe quem está solicitando os laboratórios e o status de
                        cada pedido.</p>
                </div>
                <div class="card-body p-0" id="container-tabela-historico-geral">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4 py-3">Data da Reserva</th>
                                    <th>Professor Solicitante</th>
                                    <th>Laboratório / Disciplina</th>
                                    <th>Turno / Horário</th>
                                    <th class="pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($historico_completo) > 0): ?>
                                    <?php foreach ($historico_completo as $h): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <strong><?= date('d/m/Y', strtotime($h['data_reserva'])) ?></strong>
                                            </td>
                                            <td class="fw-bold text-primary"><i
                                                    class="bi bi-person-badge me-2"></i><?= htmlspecialchars($h['professor']) ?>
                                            </td>
                                            <td><span
                                                    class="badge bg-secondary"><?= htmlspecialchars($h['laboratorio']) ?></span><br><small
                                                    class="text-muted"><?= htmlspecialchars($h['disciplina']) ?></small></td>
                                            <td><?= htmlspecialchars($h['turno']) ?> <br><small
                                                    class="text-muted"><?= htmlspecialchars($h['periodo']) ?></small></td>
                                            <td class="pe-4">
                                                <?php if ($h['status'] == 'aprovado')
                                                    echo '<span class="badge bg-success rounded-pill px-3">Aprovado</span>';
                                                elseif ($h['status'] == 'pendente')
                                                    echo '<span class="badge bg-warning text-dark rounded-pill px-3">Pendente</span>';
                                                else
                                                    echo '<span class="badge bg-danger rounded-pill px-3">Rejeitado</span>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted"><i
                                                class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>Nenhum registro de
                                            solicitação encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="sessao-quadro-horario" class="content-section">
            <div class="card shadow-sm border-0 mb-4 d-print-none" style="border-top: 4px solid var(--azul-google);">
                <div
                    class="card-body bg-light d-flex flex-column flex-md-row justify-content-between align-items-md-center p-3">
                    <form method="GET" action="painel_coordenador.php" class="d-flex flex-grow-1 me-md-4 mb-3 mb-md-0"
                        id="formMudarQuadro">
                        <div class="input-group">
                            <span class="input-group-text bg-white fw-bold text-secondary"><i
                                    class="bi bi-display me-2"></i> Exibir Quadro:</span>
                            <select name="q_id" class="form-select"
                                onchange="window.location.href='?q_id=' + this.value + '#sessao-quadro-horario'">
                                <?php if (count($lista_quadros) == 0): ?>
                                    <option value="">Nenhum cenário criado...</option><?php endif; ?>
                                <?php foreach ($lista_quadros as $q): ?>
                                    <option value="<?= $q['id'] ?>" <?= $quadro_selecionado == $q['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($q['nome']) ?> (<?= htmlspecialchars($q['periodo_letivo']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary fw-bold text-nowrap" data-bs-toggle="modal"
                            data-bs-target="#modalNovoQuadro" title="Criar Novo Quadro"><i
                                class="bi bi-plus-lg me-1"></i></button>

                        <?php if ($quadro_selecionado): ?>
                            <button type="button" class="btn btn-outline-warning fw-bold text-dark" data-bs-toggle="modal"
                                data-bs-target="#modalDuplicarQuadro" title="Duplicar Cenário Atual"><i
                                    class="bi bi-copy"></i></button>

                            <form method="POST" action="painel_coordenador.php#sessao-quadro-horario"
                                onsubmit="return confirm('Excluir este Quadro inteiro e TODAS as suas aulas?');">
                                <input type="hidden" name="id_quadro" value="<?= $quadro_selecionado ?>">
                                <button type="submit" name="excluir_quadro" class="btn btn-outline-danger"
                                    title="Excluir Quadro"><i class="bi bi-trash"></i></button>
                            </form>

                            <button class="btn btn-outline-success fw-bold" onclick="window.print()"
                                title="Imprimir Grade"><i class="bi bi-printer me-1"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalNovoQuadro" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title"><i class="bi bi-calendar-range me-2"></i>Criar Novo Cenário</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-start p-4">
                            <form method="POST" action="painel_coordenador.php#sessao-quadro-horario">
                                <div class="mb-3"><label class="form-label fw-bold small text-secondary">Nome do Cenário
                                        (Ex: Oficial 2026.1):</label><input type="text" name="nome_quadro"
                                        class="form-control" required></div>
                                <div class="mb-4"><label class="form-label fw-bold small text-secondary">Período
                                        Letivo:</label><input type="text" name="periodo_letivo" class="form-control"
                                        required placeholder="Ex: 2026/1"></div>
                                <button type="submit" name="criar_quadro" class="btn btn-primary w-100 fw-bold">Salvar
                                    Quadro</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($quadro_selecionado): ?>
                <div class="modal fade" id="modalDuplicarQuadro" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content border-warning" style="border-width: 3px;">
                            <div class="modal-header bg-warning text-dark border-0">
                                <h5 class="modal-title fw-bold"><i class="bi bi-copy me-2"></i>Duplicar Cenário Atual</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-start p-4">
                                <div class="alert alert-light border-warning text-dark small mb-4">
                                    <i class="bi bi-info-circle-fill text-warning me-2"></i> Você está prestes a fazer uma
                                    cópia exata deste quadro e de todas as aulas que estão nele.
                                </div>
                                <form method="POST" action="painel_coordenador.php#sessao-quadro-horario">
                                    <input type="hidden" name="id_quadro_origem" value="<?= $quadro_selecionado ?>">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-secondary">Nome da Cópia (Ex: Oficial
                                            2026.2):</label>
                                        <input type="text" name="novo_nome_quadro" class="form-control" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-secondary">Novo Período Letivo:</label>
                                        <input type="text" name="novo_periodo_letivo" class="form-control" required
                                            placeholder="Ex: 2026/2">
                                    </div>
                                    <button type="submit" name="duplicar_quadro"
                                        class="btn btn-warning w-100 fw-bold text-dark"><i class="bi bi-magic me-2"></i>
                                        Clonar Cenário Inteiro</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($quadro_selecionado): ?>

                <div class="card shadow-sm border-0 mb-4"></div>
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center"
                    onclick="abrirSanfona('formAlocarAulaBox', 'setaToggleForm')" style="cursor: pointer;">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle-fill text-success me-2"
                            id="iconeToggleForm"></i>Alocar Aula no Quadro</h6>
                    <i class="bi bi-chevron-down text-muted transition-transform" id="setaToggleForm"></i>
                </div>

                <div id="formAlocarAulaBox" style="display: none;">
                    <div class="card-body bg-light p-4 border-top">
                        <form method="POST" action="painel_coordenador.php#sessao-quadro-horario">
                            <input type="hidden" name="id_quadro_ativo" value="<?= $quadro_selecionado ?>">

                            <div class="bg-white p-4 rounded-3 shadow-sm mb-4 border-start border-4 border-primary">
                                <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom"><i
                                        class="bi bi-mortarboard-fill me-2"></i>1. Identificação da Turma e Matéria</h6>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">Curso:</label>
                                        <select class="form-select bg-light" name="curso_aula" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($cursos_cadastrados as $c): ?>
                                                <option value="<?= htmlspecialchars($c['nome']) ?>">
                                                    <?= htmlspecialchars($c['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-bold small text-secondary">Semestre:</label>
                                        <select class="form-select bg-light" name="semestre_aula" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($semestres_cadastrados as $sem): ?>
                                                <option value="<?= htmlspecialchars($sem['nome']) ?>">
                                                    <?= htmlspecialchars($sem['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-secondary">Disciplina:</label>
                                        <select class="form-select bg-light" name="id_disciplina_aula" required>
                                            <option value="">Selecione a matéria...</option>
                                            <?php foreach ($disciplinas as $d): ?>
                                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">Professor:</label>
                                        <select class="form-select bg-light border-primary border-opacity-50"
                                            name="id_professor_aula" required>
                                            <option value="">Selecione o docente...</option>
                                            <?php foreach ($professores as $p): ?>
                                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-4 rounded-3 shadow-sm mb-4 border-start border-4 border-success">
                                <h6 class="fw-bold text-success mb-3 pb-2 border-bottom"><i
                                        class="bi bi-clock-history me-2"></i>2. Horário e Formato da Aula</h6>
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label fw-bold small text-secondary">Dia da Semana:</label>
                                        <select class="form-select bg-light" name="dia_semana" required>
                                            <option>Segunda</option>
                                            <option>Terça</option>
                                            <option>Quarta</option>
                                            <option>Quinta</option>
                                            <option>Sexta</option>
                                            <option>Sábado</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-bold small text-secondary">Turno:</label>
                                        <select class="form-select bg-light" name="turno_aula" required>
                                            <option>Matutino</option>
                                            <option>Vespertino</option>
                                            <option>Noturno</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">Horário:</label>
                                        <select class="form-select bg-light" name="horario_aula" required>
                                            <option>1º e 2º Horários</option>
                                            <option>1º Horário</option>
                                            <option>2º Horário</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">Modalidade:</label>
                                        <select class="form-select bg-light border-warning border-opacity-50"
                                            name="modalidade" onchange="travarProfEAD(this)" required>
                                            <option>Presencial</option>
                                            <option>EAD</option>
                                            <option>Híbrido</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-bold small text-secondary">Qtd. Alunos:</label>
                                        <input type="number" name="numero_alunos" class="form-control bg-light" required
                                            placeholder="Ex: 40">
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white p-4 rounded-3 shadow-sm mb-4 border-start border-4 border-warning">
                                <h6 class="fw-bold text-dark mb-3 pb-2 border-bottom"><i
                                        class="bi bi-building-gear text-warning me-2"></i>3. Infraestrutura e Carga Horária
                                </h6>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">Carga Horária (1 a
                                            8):</label>
                                        <input type="number" name="carga_horaria_total"
                                            class="form-control bg-light carga-total" min="1" max="8" value="2" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">Horas Laboratório (0 a
                                            8):</label>
                                        <input type="number" name="horas_laboratorio" class="form-control bg-light" min="0"
                                            max="8" value="0" required
                                            oninput="let total = this.closest('.row').querySelector('.carga-total').value; if(parseInt(this.value) > parseInt(total)) { alert('Erro: Horas de laboratório não podem exceder a carga horária total!'); this.value = total; }">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-primary"><i
                                                class="bi bi-pc-display me-1"></i> Laboratório Especializado
                                            (Opcional):</label>
                                        <select class="form-select border-primary" name="id_laboratorio_aula"
                                            style="background-color: rgba(13, 110, 253, 0.05);">
                                            <option value="">Nenhum laboratório...</option>
                                            <?php foreach ($laboratorios_cadastrados as $lab): ?>
                                                <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['nome']) ?>
                                                    (Capacidade: <?= $lab['capacidade'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="p-3 bg-light rounded-3 border border-success border-opacity-25">
                                    <label class="form-label fw-bold small text-success mb-3"><i
                                            class="bi bi-door-open-fill me-1"></i> Alocação em Sala de Aula Comum</label>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small text-secondary mb-1 fw-bold">Bloco:</label>
                                            <select class="form-select border-success border-opacity-50" name="bloco_aula">
                                                <option value="">Nenhum...</option>
                                                <?php foreach ($blocos_cadastrados as $b): ?>
                                                    <option value="<?= htmlspecialchars($b['nome']) ?>">
                                                        <?= htmlspecialchars($b['nome']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small text-secondary mb-1 fw-bold">Andar:</label>
                                            <select class="form-select border-success border-opacity-50" name="andar_aula">
                                                <option value="">Nenhum...</option>
                                                <?php foreach ($andares_cadastrados as $a): ?>
                                                    <option value="<?= htmlspecialchars($a['nome']) ?>">
                                                        <?= htmlspecialchars($a['nome']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small text-secondary mb-1 fw-bold">Sala:</label>
                                            <select class="form-select border-success border-opacity-50" name="sala_aula">
                                                <option value="">Nenhuma...</option>
                                                <?php foreach ($salas_cadastradas as $s): ?>
                                                    <option value="<?= htmlspecialchars($s['nome']) ?>">
                                                        <?= htmlspecialchars($s['nome']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" name="salvar_aula_quadro"
                                    class="btn btn-primary btn-lg px-5 fw-bold shadow-sm"
                                    style="border-radius: 8px; transition: 0.3s;">
                                    <i class="bi bi-check2-circle me-2 fs-5 align-middle"></i> Confirmar Alocação no Quadro
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card shadow-sm border-0 mb-3 bg-light d-print-none" style="border-radius: 12px;">
                    <div class="card-body p-3 d-flex flex-column flex-md-row gap-3 align-items-center">
                        <div class="fw-bold text-secondary"><i class="bi bi-funnel-fill me-1 text-primary"></i> Filtrar
                            Grade:</div>
                        <select id="filtroTurnoGrade" class="form-select w-auto border-primary" onchange="filtrarGrade()">
                            <option value="todos">Todos os Turnos</option>
                            <option value="Matutino">Matutino</option>
                            <option value="Vespertino">Vespertino</option>
                            <option value="Noturno">Noturno</option>
                        </select>
                        <select id="filtroCursoGrade" class="form-select w-auto border-primary" onchange="filtrarGrade()">
                            <option value="todos">Todos os Cursos</option>
                            <?php foreach ($cursos_cadastrados as $c): ?>
                                <option value="<?= htmlspecialchars($c['nome']) ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="filtroModalidadeGrade" class="form-select w-auto border-primary"
                            onchange="filtrarGrade()">
                            <option value="todos">Todas Modalidades</option>
                            <option value="Presencial">Presencial</option>
                            <option value="EAD">EAD</option>
                            <option value="Híbrido">Híbrido</option>
                        </select>

                        <button class="btn btn-outline-secondary btn-sm fw-bold ms-auto"
                            onclick="document.getElementById('filtroTurnoGrade').value='todos'; document.getElementById('filtroCursoGrade').value='todos'; document.getElementById('filtroModalidadeGrade').value='todos'; filtrarGrade();">
                            <i class="bi bi-eraser-fill me-1"></i> Limpar Filtros
                        </button>
                    </div>
                </div>

                <?php
                $nome_cenario_impresso = "Grade de Horários";
                foreach ($lista_quadros as $q) {
                    if ($q['id'] == $quadro_selecionado) {
                        $nome_cenario_impresso = htmlspecialchars($q['nome']) . ' <span class="text-secondary fs-5 fw-normal">(' . htmlspecialchars($q['periodo_letivo']) . ')</span>';
                        break;
                    }
                }
                ?>
                <div class="print-only-header mb-4">
                    <div class="d-flex align-items-center border-bottom border-3 pb-3"
                        style="border-color: var(--verde-uniceplac) !important;">
                        <img src="uniceplac2.png" alt="UNICEPLAC" style="height: 55px; margin-right: 20px;">
                        <div class="border-start border-2 ps-4" style="border-color: #dee2e6 !important;">
                            <div class="text-muted small fw-bold text-uppercase mb-1" style="letter-spacing: 1px;">
                                Coordenação: <?= htmlspecialchars($_SESSION['nome']) ?>
                            </div>
                            <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.5px;">
                                <?= $nome_cenario_impresso ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="bg-white border shadow-sm p-3 overflow-auto" style="border-radius: 12px;">
                    <div class="grade-wrapper">
                        <div class="grade-container">
                            <?php foreach ($dias_semana as $dia): ?>
                                <div class="grade-coluna">
                                    <div class="grade-cabecalho shadow-sm mb-2"><?= $dia ?></div>
                                    <div class="grade-corpo coluna-sortable" data-dia="<?= $dia ?>" style="min-height: 150px;">
                                        <?php
                                        if (empty($aulas_do_quadro[$dia])) {
                                            echo "<div class='text-center mt-4 text-muted small opacity-50 fw-bold'><i class='bi bi-cup-hot fs-4 d-block mb-1'></i>Livre</div>";
                                        } else {
                                            $aulas_dia = $aulas_do_quadro[$dia];
                                            foreach ($aulas_dia as $a) {
                                                $classe_turno = strtolower($a['turno']);

                                                // --- A MÁGICA DO VIDRO EAD ---
                                                $classe_ead = ($a['modalidade'] === 'EAD') ? 'aula-ead-glass' : '';
                                                ?>
                                                <div class="aula-card-google <?= $classe_turno ?> <?= $classe_ead ?> card-grade-aula"
                                                    data-turno="<?= htmlspecialchars($a['turno']) ?>"
                                                    data-curso="<?= htmlspecialchars($a['curso']) ?>"
                                                    data-modalidade="<?= htmlspecialchars($a['modalidade']) ?>">

                                                    <div class="position-absolute top-0 end-0 p-1 d-flex d-print-none">
                                                        <button type="button" class="btn btn-sm text-primary p-0 m-0 border-0 me-2"
                                                            data-bs-toggle="modal" data-bs-target="#editAulaQuadro<?= $a['id'] ?>"
                                                            title="Editar Aula"><i class="bi bi-pencil-fill"></i></button>
                                                        <form method="POST" action="painel_coordenador.php#sessao-quadro-horario"
                                                            onsubmit="return confirm('Remover esta aula do quadro?');">
                                                            <input type="hidden" name="id_aula_q" value="<?= $a['id'] ?>">
                                                            <button type="submit" name="excluir_aula_quadro"
                                                                class="btn btn-sm text-danger p-0 m-0 border-0" title="Excluir"><i
                                                                    class="bi bi-x-circle-fill"></i></button>
                                                        </form>
                                                    </div>

                                                    <div class="fw-bold text-dark lh-sm mb-1"
                                                        style="padding-right: 35px; font-size:0.85rem;">
                                                        <?= htmlspecialchars($a['curso']) ?> <br>
                                                        <span class="text-secondary"
                                                            style="font-size:0.75rem;"><?= htmlspecialchars($a['semestre']) ?></span>
                                                    </div>

                                                    <div
                                                        class="text-secondary small fw-bold mb-2 border-bottom pb-2 border-secondary border-opacity-25">
                                                        <?= htmlspecialchars($a['disc_nome']) ?>
                                                    </div>

                                                    <div>
                                                        <?php if ($a['modalidade'] === 'EAD'): ?>
                                                            <span class="badge bg-warning text-dark border border-warning shadow-sm mb-1"><i
                                                                    class="bi bi-laptop me-1"></i> EAD Online</span>
                                                        <?php else: ?>
                                                            <span class="badge selo-<?= $classe_turno ?> shadow-sm mb-1"><i
                                                                    class="bi bi-building-check me-1"></i> <?= $a['modalidade'] ?></span>
                                                            <div class="small lh-sm mt-1 text-dark fw-bold prof-nome"><i
                                                                    class="bi bi-person me-1"></i><?= htmlspecialchars($a['prof_nome'] ?? 'Sem Professor') ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if ($a['id_laboratorio']):
                                                        $lab_det = [];
                                                        if (!empty($a['lab_local']))
                                                            $lab_det[] = htmlspecialchars($a['lab_local']);
                                                        if (!empty($a['lab_andar']))
                                                            $lab_det[] = 'And ' . htmlspecialchars($a['lab_andar']);
                                                        $lab_str = !empty($lab_det) ? ' <span class="text-muted" style="font-size:0.7rem;">(' . implode(' - ', $lab_det) . ')</span>' : '';
                                                        ?>
                                                        <div class="small mt-2"><i class="bi bi-pc-display me-1 text-primary"></i><span
                                                                class="fw-bold text-primary"><?= htmlspecialchars($a['lab_nome']) ?></span><?= $lab_str ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($a['sala']) || !empty($a['bloco'])): ?>
                                                        <div class="small <?= $a['id_laboratorio'] ? 'mt-1' : 'mt-2' ?>"><i
                                                                class="bi bi-door-open me-1 text-success"></i><span
                                                                class="fw-bold text-success">Sala
                                                                <?= htmlspecialchars($a['sala'] ?? '-') ?></span><span class="text-muted"
                                                                style="font-size:0.7rem;">(Bl
                                                                <?= htmlspecialchars($a['bloco'] ?? '-') ?>)</span></div>
                                                    <?php endif; ?>

                                                    <div class="small lh-sm mt-2 fw-bold text-dark"><i
                                                            class="bi bi-clock me-1"></i><?= $a['turno'] ?> (<?= $a['horario'] ?>)</div>

                                                    <div class="mt-2 d-flex justify-content-between align-items-center">
                                                        <span class="small fw-bold text-muted"><i
                                                                class="bi bi-people-fill me-1"></i><?= $a['numero_alunos'] ?></span>
                                                        <span class="small text-muted"
                                                            style="font-size: 0.7rem;">CH:<?= $a['carga_horaria_total'] ?? 2 ?>h |
                                                            L:<?= $a['horas_laboratorio'] ?? 0 ?>h</span>
                                                    </div>
                                                </div>

                                                <div class="modal fade d-print-none" id="editAulaQuadro<?= $a['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content border-primary" style="border-width:3px;">
                                                            <div class="modal-header bg-primary text-white border-0">
                                                                <h5 class="modal-title">Editar Aula do Quadro</h5>
                                                                <button type="button" class="btn-close btn-close-white"
                                                                    data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body text-start">
                                                                <form method="POST"
                                                                    action="painel_coordenador.php#sessao-quadro-horario">
                                                                    <input type="hidden" name="editar_aula_quadro" value="1">
                                                                    <input type="hidden" name="id_aula_q" value="<?= $a['id'] ?>">
                                                                    <input type="hidden" name="id_quadro_ativo"
                                                                        value="<?= $quadro_selecionado ?>">
                                                                    <div class="row g-3 mb-3">
                                                                        <div class="col-md-2"><label
                                                                                class="form-label fw-bold small text-secondary">Dia:</label><select
                                                                                class="form-select" name="dia_semana" required>
                                                                                <option <?= $a['dia_semana'] == 'Segunda' ? 'selected' : '' ?>>Segunda</option>
                                                                                <option <?= $a['dia_semana'] == 'Terça' ? 'selected' : '' ?>>Terça</option>
                                                                                <option <?= $a['dia_semana'] == 'Quarta' ? 'selected' : '' ?>>Quarta</option>
                                                                                <option <?= $a['dia_semana'] == 'Quinta' ? 'selected' : '' ?>>Quinta</option>
                                                                                <option <?= $a['dia_semana'] == 'Sexta' ? 'selected' : '' ?>>Sexta</option>
                                                                                <option <?= $a['dia_semana'] == 'Sábado' ? 'selected' : '' ?>>Sábado</option>
                                                                            </select></div>
                                                                        <div class="col-md-2"><label
                                                                                class="form-label fw-bold small text-secondary">Turno:</label><select
                                                                                class="form-select" name="turno_aula" required>
                                                                                <option <?= $a['turno'] == 'Matutino' ? 'selected' : '' ?>>
                                                                                    Matutino</option>
                                                                                <option <?= $a['turno'] == 'Vespertino' ? 'selected' : '' ?>>Vespertino</option>
                                                                                <option <?= $a['turno'] == 'Noturno' ? 'selected' : '' ?>>
                                                                                    Noturno</option>
                                                                            </select></div>
                                                                        <div class="col-md-2"><label
                                                                                class="form-label fw-bold small text-secondary">Horário:</label><select
                                                                                class="form-select" name="horario_aula" required>
                                                                                <option <?= $a['horario'] == '1º e 2º Horários' ? 'selected' : '' ?>>1º e 2º Horários</option>
                                                                                <option <?= $a['horario'] == '1º Horário' ? 'selected' : '' ?>>1º Horário</option>
                                                                                <option <?= $a['horario'] == '2º Horário' ? 'selected' : '' ?>>2º Horário</option>
                                                                            </select></div>
                                                                        <div class="col-md-3"><label
                                                                                class="form-label fw-bold small text-secondary">Curso:</label><select
                                                                                class="form-select" name="curso_aula"
                                                                                required><?php foreach ($cursos_cadastrados as $c): ?>
                                                                                    <option value="<?= htmlspecialchars($c['nome']) ?>"
                                                                                        <?= $c['nome'] == $a['curso'] ? 'selected' : '' ?>>
                                                                                        <?= htmlspecialchars($c['nome']) ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select></div>
                                                                        <div class="col-md-3"><label
                                                                                class="form-label fw-bold small text-secondary">Semestre:</label><select
                                                                                class="form-select" name="semestre_aula" required>
                                                                                <option value="">Selecione...</option>
                                                                                <?php foreach ($semestres_cadastrados as $sem): ?>
                                                                                    <option value="<?= htmlspecialchars($sem['nome']) ?>"
                                                                                        <?= $sem['nome'] == $a['semestre'] ? 'selected' : '' ?>><?= htmlspecialchars($sem['nome']) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select></div>
                                                                    </div>
                                                                    <div class="row g-3 mb-3">
                                                                        <div class="col-md-4"><label
                                                                                class="form-label fw-bold small text-secondary">Disciplina:</label><select
                                                                                class="form-select" name="id_disciplina_aula"
                                                                                required><?php foreach ($disciplinas as $d): ?>
                                                                                    <option value="<?= $d['id'] ?>"
                                                                                        <?= $d['id'] == $a['id_disciplina'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nome']) ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select></div>
                                                                        <div class="col-md-4"><label
                                                                                class="form-label fw-bold small text-secondary">Professor:</label><select
                                                                                class="form-select" name="id_professor_aula"
                                                                                required><?php foreach ($professores as $p): ?>
                                                                                    <option value="<?= $p['id'] ?>"
                                                                                        <?= $p['id'] == $a['id_professor'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nome']) ?>
                                                                                    </option><?php endforeach; ?>
                                                                            </select></div>
                                                                        <div class="col-md-2"><label
                                                                                class="form-label fw-bold small text-secondary">Modalidade:</label><select
                                                                                class="form-select" name="modalidade"
                                                                                onchange="travarProfEAD(this)" required>
                                                                                <option <?= $a['modalidade'] == 'Presencial' ? 'selected' : '' ?>>
                                                                                    Presencial</option>
                                                                                <option <?= $a['modalidade'] == 'EAD' ? 'selected' : '' ?>>
                                                                                    EAD</option>
                                                                                <option <?= $a['modalidade'] == 'Híbrido' ? 'selected' : '' ?>>
                                                                                    Híbrido</option>
                                                                            </select></div>
                                                                        <div class="col-md-2"><label
                                                                                class="form-label fw-bold small text-secondary">Qtd.
                                                                                Alunos:</label><input type="number" name="numero_alunos"
                                                                                class="form-control" value="<?= $a['numero_alunos'] ?>"
                                                                                required></div>
                                                                    </div>
                                                                    <div class="row g-3 mb-3">
                                                                        <div class="col-md-3"><label
                                                                                class="form-label fw-bold small text-secondary">Carga
                                                                                Horária (1 a 8):</label><input type="number"
                                                                                name="carga_horaria_total"
                                                                                class="form-control carga-total" min="1" max="8"
                                                                                value="<?= $a['carga_horaria_total'] ?? 2 ?>" required>
                                                                        </div>
                                                                        <div class="col-md-3"><label
                                                                                class="form-label fw-bold small text-secondary">Horas
                                                                                Lab (0 a 8):</label><input type="number"
                                                                                name="horas_laboratorio" class="form-control" min="0"
                                                                                max="8" value="<?= $a['horas_laboratorio'] ?? 0 ?>"
                                                                                required
                                                                                oninput="let total = this.closest('.row').querySelector('.carga-total').value; if(parseInt(this.value) > parseInt(total)) { alert('Erro: Horas de laboratório não podem exceder a carga horária total!'); this.value = total; }">
                                                                        </div>
                                                                    </div>
                                                                    <hr class="my-3 opacity-25">
                                                                    <div class="row g-3 mb-4">
                                                                        <div class="col-md-4">
                                                                            <label class="form-label fw-bold small text-primary"><i
                                                                                    class="bi bi-pc-display me-1"></i>
                                                                                Laboratório:</label>
                                                                            <select class="form-select border-primary"
                                                                                name="id_laboratorio_aula"
                                                                                style="background-color: rgba(13, 110, 253, 0.05);">
                                                                                <option value="">Nenhum...</option>
                                                                                <?php foreach ($laboratorios_cadastrados as $lab): ?>
                                                                                    <option value="<?= $lab['id'] ?>"
                                                                                        <?= $lab['id'] == $a['id_laboratorio'] ? 'selected' : '' ?>>
                                                                                        <?= htmlspecialchars($lab['nome']) ?> (Cap:
                                                                                        <?= $lab['capacidade'] ?>)
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div
                                                                            class="col-md-8 border-start border-success border-opacity-25 ps-4">
                                                                            <label
                                                                                class="form-label fw-bold small text-success w-100 mb-2"><i
                                                                                    class="bi bi-door-open-fill fs-6 me-1"></i> Sala de
                                                                                Aula Comum</label>
                                                                            <div class="row g-2">
                                                                                <div class="col-md-4"><label
                                                                                        class="form-label small text-secondary mb-1">Bloco:</label><select
                                                                                        class="form-select border-success"
                                                                                        name="bloco_aula">
                                                                                        <option value="">Nenhum...</option>
                                                                                        <?php foreach ($blocos_cadastrados as $b): ?>
                                                                                            <option
                                                                                                value="<?= htmlspecialchars($b['nome']) ?>"
                                                                                                <?= $b['nome'] == $a['bloco'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nome']) ?>
                                                                                            </option><?php endforeach; ?>
                                                                                    </select></div>
                                                                                <div class="col-md-4"><label
                                                                                        class="form-label small text-secondary mb-1">Andar:</label><select
                                                                                        class="form-select border-success"
                                                                                        name="andar_aula">
                                                                                        <option value="">Nenhum...</option>
                                                                                        <?php foreach ($andares_cadastrados as $a_db): ?>
                                                                                            <option
                                                                                                value="<?= htmlspecialchars($a_db['nome']) ?>"
                                                                                                <?= $a_db['nome'] == $a['andar'] ? 'selected' : '' ?>><?= htmlspecialchars($a_db['nome']) ?>
                                                                                            </option><?php endforeach; ?>
                                                                                    </select></div>
                                                                                <div class="col-md-4"><label
                                                                                        class="form-label small text-secondary mb-1">Sala:</label><select
                                                                                        class="form-select border-success"
                                                                                        name="sala_aula">
                                                                                        <option value="">Nenhum...</option>
                                                                                        <?php foreach ($salas_cadastradas as $s): ?>
                                                                                            <option
                                                                                                value="<?= htmlspecialchars($s['nome']) ?>"
                                                                                                <?= $s['nome'] == $a['sala'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nome']) ?>
                                                                                            </option><?php endforeach; ?>
                                                                                    </select></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-end"><button type="submit"
                                                                            name="editar_aula_quadro"
                                                                            class="btn btn-primary px-5 fw-bold"><i
                                                                                class="bi bi-pencil-fill me-2"></i> Salvar
                                                                            Edição</button></div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
    <div id="sessao-cursos" class="content-section">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0" style="border-top: 4px solid var(--roxo-uniceplac);">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-mortarboard text-primary me-2"></i> Novo
                            Curso</h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" action="painel_coordenador.php#sessao-cursos">
                            <div class="mb-3"><label class="form-label small fw-bold">Nome do Curso:</label><input
                                    type="text" name="nome_curso" class="form-control" required
                                    placeholder="Ex: Engenharia"></div>
                            <button type="submit" name="salvar_curso" class="btn btn-primary w-100 fw-bold">Salvar
                                Curso</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark">Cursos Cadastrados</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="ps-4">Nome</th>
                                        <th class="text-end pe-4">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cursos_cadastrados as $c): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold">
                                                <?= htmlspecialchars($c['nome']) ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                                                    data-bs-target="#editCurso<?= $c['id'] ?>"><i
                                                        class="bi bi-pencil"></i></button>
                                                <form method="POST" action="painel_coordenador.php#sessao-cursos"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Deseja excluir este curso?');"><input
                                                        type="hidden" name="id_curso" value="<?= $c['id'] ?>"><button
                                                        type="submit" name="excluir_curso"
                                                        class="btn btn-sm btn-outline-danger"><i
                                                            class="bi bi-trash"></i></button></form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="editCurso<?= $c['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Editar Curso</h6><button
                                                            type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <form method="POST" action="painel_coordenador.php#sessao-cursos">
                                                            <input type="hidden" name="id_curso"
                                                                value="<?= $c['id'] ?>"><label
                                                                class="form-label small fw-bold">Nome do
                                                                Curso:</label><input type="text" name="nome_curso"
                                                                class="form-control mb-3"
                                                                value="<?= htmlspecialchars($c['nome']) ?>" required><button
                                                                type="submit" name="editar_curso"
                                                                class="btn btn-primary w-100">Atualizar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="sessao-semestres" class="content-section">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0" style="border-top: 4px solid var(--verde-uniceplac);">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-range text-success me-2"></i>
                            Novo Semestre</h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" action="painel_coordenador.php#sessao-semestres">
                            <div class="mb-3"><label class="form-label small fw-bold">Nome do
                                    Semestre:</label><input type="text" name="nome_semestre" class="form-control"
                                    required placeholder="Ex: 1º Semestre"></div>
                            <button type="submit" name="salvar_semestre" class="btn btn-success w-100 fw-bold">Salvar
                                Semestre</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark">Semestres Cadastrados</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="ps-4">Nome</th>
                                        <th class="text-end pe-4">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($semestres_cadastrados as $sem): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold">
                                                <?= htmlspecialchars($sem['nome']) ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                                                    data-bs-target="#editSemestre<?= $sem['id'] ?>"><i
                                                        class="bi bi-pencil"></i></button>
                                                <form method="POST" action="painel_coordenador.php#sessao-semestres"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Deseja excluir este semestre?');"><input
                                                        type="hidden" name="id_semestre" value="<?= $sem['id'] ?>"><button
                                                        type="submit" name="excluir_semestre"
                                                        class="btn btn-sm btn-outline-danger"><i
                                                            class="bi bi-trash"></i></button></form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="editSemestre<?= $sem['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Editar Semestre</h6><button
                                                            type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <form method="POST"
                                                            action="painel_coordenador.php#sessao-semestres"><input
                                                                type="hidden" name="id_semestre"
                                                                value="<?= $sem['id'] ?>"><label
                                                                class="form-label small fw-bold">Nome do
                                                                Semestre:</label><input type="text" name="nome_semestre"
                                                                class="form-control mb-3"
                                                                value="<?= htmlspecialchars($sem['nome']) ?>"
                                                                required><button type="submit" name="editar_semestre"
                                                                class="btn btn-success w-100">Atualizar</button></form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="sessao-disciplinas" class="content-section">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0" style="border-top: 4px solid var(--laranja-uniceplac);">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-book-half text-primary me-2"></i> Nova
                            Disciplina</h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" action="painel_coordenador.php#sessao-disciplinas">
                            <div class="mb-3"><label class="form-label small fw-bold">Nome da
                                    Disciplina:</label><input type="text" name="nome_disciplina" class="form-control"
                                    required placeholder="Ex: Algoritmos"></div>
                            <button type="submit" name="salvar_disciplina" class="btn btn-primary w-100 fw-bold">Salvar
                                Disciplina</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark">Disciplinas Cadastradas</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="ps-4">Nome</th>
                                        <th class="text-end pe-4">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($disciplinas as $d): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold">
                                                <?= htmlspecialchars($d['nome']) ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                                                    data-bs-target="#editDisciplina<?= $d['id'] ?>"><i
                                                        class="bi bi-pencil"></i></button>
                                                <form method="POST" action="painel_coordenador.php#sessao-disciplinas"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Deseja excluir esta disciplina?');"><input
                                                        type="hidden" name="id_disciplina" value="<?= $d['id'] ?>"><button
                                                        type="submit" name="excluir_disciplina"
                                                        class="btn btn-sm btn-outline-danger"><i
                                                            class="bi bi-trash"></i></button></form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="editDisciplina<?= $d['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Editar Disciplina</h6><button
                                                            type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <form method="POST"
                                                            action="painel_coordenador.php#sessao-disciplinas"><input
                                                                type="hidden" name="id_disciplina"
                                                                value="<?= $d['id'] ?>"><label
                                                                class="form-label small fw-bold">Nome da
                                                                Disciplina:</label><input type="text" name="nome_disciplina"
                                                                class="form-control mb-3"
                                                                value="<?= htmlspecialchars($d['nome']) ?>" required><button
                                                                type="submit" name="editar_disciplina"
                                                                class="btn btn-primary w-100">Atualizar</button></form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="sessao-labs" class="content-section">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0" style="border-top: 4px solid var(--info);">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle text-info me-2"></i> Novo
                            Laboratório</h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" action="painel_coordenador.php#sessao-labs">
                            <div class="mb-3"><label class="form-label small fw-bold">Nome do Lab:</label><input
                                    type="text" name="nome_lab" class="form-control" required
                                    placeholder="Ex: Lab de Redes"></div>
                            <div class="mb-3"><label class="form-label small fw-bold">Localização / Bloco
                                    (Opcional):</label><input type="text" name="localizacao_lab" class="form-control"
                                    placeholder="Ex: Bloco B"></div>
                            <div class="mb-3"><label class="form-label small fw-bold">Andar
                                    (Opcional):</label><input type="text" name="andar_lab" class="form-control"
                                    placeholder="Ex: 1º Andar"></div>
                            <div class="mb-3"><label class="form-label small fw-bold">Capacidade
                                    (Lugares):</label><input type="number" name="capacidade_lab" class="form-control"
                                    required></div>
                            <button type="submit" name="salvar_lab" class="btn btn-info text-white w-100 fw-bold">Salvar
                                Laboratório</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-pc-display text-secondary me-2"></i>
                            Laboratórios Ativos</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="ps-4">Nome</th>
                                        <th>Localização</th>
                                        <th>Capacidade</th>
                                        <th class="pe-4 text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laboratorios_cadastrados as $lab): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark">
                                                <?= htmlspecialchars($lab['nome']) ?>
                                            </td>
                                            <td><span class="small text-muted">
                                                    <?= htmlspecialchars($lab['localizacao'] ?? '-') ?>
                                                    <br> Andar:
                                                    <?= htmlspecialchars($lab['andar'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $lab['capacidade'] ?> vagas
                                            </td>
                                            <td class="pe-4 text-end">
                                                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal"
                                                    data-bs-target="#editLab<?= $lab['id'] ?>"><i
                                                        class="bi bi-pencil"></i></button>
                                                <form method="POST" action="painel_coordenador.php#sessao-labs"
                                                    class="d-inline" onsubmit="return confirm('Deseja excluir este lab?');">
                                                    <input type="hidden" name="id_lab" value="<?= $lab['id'] ?>"><button
                                                        type="submit" name="excluir_lab"
                                                        class="btn btn-sm btn-outline-danger"><i
                                                            class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="editLab<?= $lab['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h6 class="modal-title fw-bold">Editar Laboratório</h6><button
                                                            type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <form method="POST" action="painel_coordenador.php#sessao-labs">
                                                            <input type="hidden" name="id_lab" value="<?= $lab['id'] ?>">
                                                            <div class="mb-3"><label class="form-label small fw-bold">Nome
                                                                    do
                                                                    Lab:</label><input type="text" name="nome_lab"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($lab['nome']) ?>" required>
                                                            </div>
                                                            <div class="mb-3"><label
                                                                    class="form-label small fw-bold">Localização /
                                                                    Bloco:</label><input type="text" name="localizacao_lab"
                                                                    class="form-control"
                                                                    value="<?= htmlspecialchars($lab['localizacao'] ?? '') ?>"
                                                                    placeholder="Ex: Bloco C"></div>
                                                            <div class="mb-3"><label
                                                                    class="form-label small fw-bold">Andar:</label><input
                                                                    type="text" name="andar_lab" class="form-control"
                                                                    value="<?= htmlspecialchars($lab['andar'] ?? '') ?>"
                                                                    placeholder="Ex: Térreo"></div>
                                                            <div class="mb-4"><label
                                                                    class="form-label small fw-bold">Capacidade:</label><input
                                                                    type="number" name="capacidade_lab" class="form-control"
                                                                    value="<?= $lab['capacidade'] ?>" required></div>
                                                            <button type="submit" name="editar_lab"
                                                                class="btn btn-info text-white w-100 fw-bold">Atualizar
                                                                Laboratório</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="sessao-locais" class="content-section">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0" style="border-top: 4px solid #6c757d;">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-building text-secondary me-2"></i> Blocos
                        </h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" action="painel_coordenador.php#sessao-locais" class="d-flex mb-3"><input
                                type="text" name="nome_bloco" class="form-control me-2" required
                                placeholder="Novo bloco..."><button type="submit" name="salvar_bloco"
                                class="btn btn-secondary"><i class="bi bi-plus-lg"></i></button></form>
                        <ul class="list-group" style="max-height: 250px; overflow-y: auto;">
                            <?php foreach ($blocos_cadastrados as $b): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center"><span
                                        class="small fw-bold">
                                        <?= htmlspecialchars($b['nome']) ?>
                                    </span>
                                    <div><button class="btn btn-sm text-primary p-0 me-2" data-bs-toggle="modal"
                                            data-bs-target="#editBloco<?= $b['id'] ?>"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="painel_coordenador.php#sessao-locais" class="d-inline"
                                            onsubmit="return confirm('Excluir?');"><input type="hidden" name="id_bloco"
                                                value="<?= $b['id'] ?>"><button type="submit" name="excluir_bloco"
                                                class="btn btn-sm text-danger border-0 p-0"><i
                                                    class="bi bi-trash"></i></button></form>
                                    </div>
                                </li>
                                <div class="modal fade" id="editBloco<?= $b['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6>Editar Bloco</h6><button type="button" class="btn-close"
                                                    data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-start">
                                                <form method="POST" action="painel_coordenador.php#sessao-locais"><input
                                                        type="hidden" name="id_bloco" value="<?= $b['id'] ?>"><input
                                                        type="text" name="nome_bloco" class="form-control mb-3"
                                                        value="<?= htmlspecialchars($b['nome']) ?>" required><button
                                                        type="submit" name="editar_bloco"
                                                        class="btn btn-secondary w-100">Atualizar</button></form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0" style="border-top: 4px solid #198754;">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-layers text-success me-2"></i> Andares
                        </h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" action="painel_coordenador.php#sessao-locais" class="d-flex mb-3"><input
                                type="text" name="nome_andar" class="form-control me-2" required
                                placeholder="Novo andar..."><button type="submit" name="salvar_andar"
                                class="btn btn-success"><i class="bi bi-plus-lg"></i></button></form>
                        <ul class="list-group" style="max-height: 250px; overflow-y: auto;">
                            <?php foreach ($andares_cadastrados as $a): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center"><span
                                        class="small fw-bold">
                                        <?= htmlspecialchars($a['nome']) ?>
                                    </span>
                                    <div><button class="btn btn-sm text-primary p-0 me-2" data-bs-toggle="modal"
                                            data-bs-target="#editAndar<?= $a['id'] ?>"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="painel_coordenador.php#sessao-locais" class="d-inline"
                                            onsubmit="return confirm('Excluir?');"><input type="hidden" name="id_andar"
                                                value="<?= $a['id'] ?>"><button type="submit" name="excluir_andar"
                                                class="btn btn-sm text-danger border-0 p-0"><i
                                                    class="bi bi-trash"></i></button></form>
                                    </div>
                                </li>
                                <div class="modal fade" id="editAndar<?= $a['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6>Editar Andar</h6><button type="button" class="btn-close"
                                                    data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-start">
                                                <form method="POST" action="painel_coordenador.php#sessao-locais"><input
                                                        type="hidden" name="id_andar" value="<?= $a['id'] ?>"><input
                                                        type="text" name="nome_andar" class="form-control mb-3"
                                                        value="<?= htmlspecialchars($a['nome']) ?>" required><button
                                                        type="submit" name="editar_andar"
                                                        class="btn btn-success w-100">Atualizar</button></form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0" style="border-top: 4px solid #0dcaf0;">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-door-open text-info me-2"></i> Salas</h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" action="painel_coordenador.php#sessao-locais" class="d-flex mb-3"><input
                                type="text" name="nome_sala" class="form-control me-2" required
                                placeholder="Nova sala..."><button type="submit" name="salvar_sala"
                                class="btn btn-info text-white"><i class="bi bi-plus-lg"></i></button></form>
                        <ul class="list-group" style="max-height: 250px; overflow-y: auto;">
                            <?php foreach ($salas_cadastradas as $s): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center"><span
                                        class="small fw-bold">
                                        <?= htmlspecialchars($s['nome']) ?>
                                    </span>
                                    <div><button class="btn btn-sm text-primary p-0 me-2" data-bs-toggle="modal"
                                            data-bs-target="#editSala<?= $s['id'] ?>"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="painel_coordenador.php#sessao-locais" class="d-inline"
                                            onsubmit="return confirm('Excluir?');"><input type="hidden" name="id_sala"
                                                value="<?= $s['id'] ?>"><button type="submit" name="excluir_sala"
                                                class="btn btn-sm text-danger border-0 p-0"><i
                                                    class="bi bi-trash"></i></button></form>
                                    </div>
                                </li>
                                <div class="modal fade" id="editSala<?= $s['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6>Editar Sala</h6><button type="button" class="btn-close"
                                                    data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-start">
                                                <form method="POST" action="painel_coordenador.php#sessao-locais"><input
                                                        type="hidden" name="id_sala" value="<?= $s['id'] ?>"><input
                                                        type="text" name="nome_sala" class="form-control mb-3"
                                                        value="<?= htmlspecialchars($s['nome']) ?>" required><button
                                                        type="submit" name="editar_sala"
                                                        class="btn btn-info text-white w-100">Atualizar</button></form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalDetalheEvento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                <div class="modal-header bg-light border-0" style="border-radius: 16px 16px 0 0;">
                    <h5 class="modal-title fw-bold text-primary" id="modalDetalheTitulo">Detalhes da Aula</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body fs-6 text-dark" id="modalDetalheCorpo"></div>
                <div class="modal-footer border-0"><button type="button"
                        class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <script>
        let calendarioCoordenadorGlobal;

        window.abrirSanfona = function (caixaId, setaId) {
            let caixa = document.getElementById(caixaId);
            let seta = document.getElementById(setaId);

            if (caixa.style.display === 'none' || caixa.style.display === '') {
                caixa.style.display = 'block';
                if (seta) seta.style.transform = 'rotate(180deg)';
            } else {
                caixa.style.display = 'none';
                if (seta) seta.style.transform = 'rotate(0deg)';
            }
        };

        document.addEventListener('DOMContentLoaded', function () {

            // TRAVA DO EAD NO JAVASCRIPT (Técnica Blindada de Formulário)
            window.travarProfEAD = function (elementoModalidade) {
                let form = elementoModalidade.closest('form');
                if (!form) return;

                let selectProfessor = form.querySelector('[name="id_professor_aula"]');

                if (selectProfessor) {
                    if (elementoModalidade.value === 'EAD') {
                        selectProfessor.value = "";
                        selectProfessor.disabled = true;
                        selectProfessor.required = false;
                        selectProfessor.removeAttribute('required');
                        selectProfessor.parentElement.style.display = 'none';
                    } else {
                        selectProfessor.parentElement.style.display = 'block';
                        selectProfessor.disabled = false;
                        selectProfessor.required = true;
                        selectProfessor.setAttribute('required', 'required');
                    }
                }
            };

            // Roda a verificação assim que a tela abre (Para os modais de edição)
            document.querySelectorAll('select[name="modalidade"]').forEach(function (caixaModalidade) {
                travarProfEAD(caixaModalidade);
            });

            // MOTOR DO DRAG AND DROP (KANBAN)
            const colunasGrade = document.querySelectorAll('.coluna-sortable');

            colunasGrade.forEach(coluna => {
                new Sortable(coluna, {
                    group: 'gradeUniceplac', // Permite arrastar entre colunas diferentes
                    animation: 150, // Suavidade da animação (ms)
                    ghostClass: 'opacity-50', // Efeito visual no card original enquanto arrasta

                    onEnd: function (evt) {
                        const cardArrastado = evt.item;
                        const colunaDestino = evt.to;
                        const colunaOrigem = evt.from;

                        // Se soltou no mesmo lugar, não faz nada
                        if (colunaOrigem === colunaDestino) return;

                        // Pega os dados invisíveis que colocamos no HTML
                        const idAula = cardArrastado.getAttribute('data-id-aula');
                        const novoDia = colunaDestino.getAttribute('data-dia');

                        // Envia o comando silencioso pro PHP (Ajax)
                        let formData = new FormData();
                        formData.append('action', 'mover_aula');
                        formData.append('id_aula', idAula);
                        formData.append('novo_dia', novoDia);

                        fetch('painel_coordenador.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    alert("Opa! Houve um erro no banco de dados ao salvar a posição.");
                                    // Se der erro, joga o card de volta pra onde estava
                                    evt.from.appendChild(evt.item);
                                }
                            })
                            .catch(error => {
                                console.error('Erro de conexão:', error);
                                alert("Falha de conexão. A aula voltou para a posição original.");
                                evt.from.appendChild(evt.item);
                            });
                    }
                });
            });

            const filtroInput = document.getElementById('filtroDashInput');
            const limparBtn = document.getElementById('btnLimparFiltroDash');

            function aplicarFiltroDashboard(termo) {
                if (!filtroInput) return;
                filtroInput.value = termo;
                limparBtn.style.display = termo ? 'block' : 'none';
                let textoBusca = termo.toLowerCase();

                document.querySelectorAll('.linha-filtro, .linha-bi').forEach(linha => {
                    let conteudo = linha.innerText.toLowerCase();
                    linha.style.display = conteudo.includes(textoBusca) ? '' : 'none';
                });

                document.querySelectorAll('.card-ociosidade').forEach(card => {
                    let nomeLab = card.getAttribute('data-search');
                    card.style.display = nomeLab.includes(textoBusca) ? '' : 'none';
                });

                ['collapseTabelaBI', 'collapseProfTabela', 'collapseLabTabela'].forEach(id => {
                    let tb = document.getElementById(id);
                    let seta = document.getElementById(id.replace('collapse', 'seta'));
                    if (tb && tb.style.display === 'none') {
                        tb.style.display = 'block';
                        if (seta) seta.style.transform = 'rotate(180deg)';
                    }
                });

                filtroInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            if (filtroInput) filtroInput.addEventListener('keyup', function () { aplicarFiltroDashboard(this.value); });
            if (limparBtn) limparBtn.addEventListener('click', function () { aplicarFiltroDashboard(''); filtroInput.focus(); });

            <?php if ($quadro_selecionado): ?>
                const profNomes = <?= json_encode($grafico_prof_nomes ?? []) ?>;
                const profLab = <?= json_encode($grafico_prof_lab ?? []) ?>;
                const profSala = <?= json_encode($grafico_prof_sala ?? []) ?>;

                if (document.getElementById('chartPerfilAulas') && profNomes.length > 0) {
                    new Chart(document.getElementById('chartPerfilAulas'), {
                        type: 'bar',
                        data: {
                            labels: profNomes,
                            datasets: [
                                { label: 'Prática (Lab)', data: profLab, backgroundColor: 'rgba(220, 53, 69, 0.8)', borderRadius: 4 },
                                { label: 'Teórica (Sala)', data: profSala, backgroundColor: 'rgba(25, 135, 84, 0.8)', borderRadius: 4 }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            scales: { x: { stacked: true }, y: { stacked: true } },
                            plugins: { legend: { display: true } },
                            onHover: (e, el) => { e.native.target.style.cursor = el[0] ? 'pointer' : 'default'; },
                            onClick: (e, el) => { if (el.length > 0) aplicarFiltroDashboard(profNomes[el[0].index]); }
                        }
                    });
                }

                const cursoNomes = <?= json_encode($grafico_curso_nomes ?? []) ?>;
                const cursoHoras = <?= json_encode($grafico_curso_horas ?? []) ?>;

                if (document.getElementById('chartCursos') && cursoNomes.length > 0) {
                    new Chart(document.getElementById('chartCursos'), {
                        type: 'doughnut',
                        data: {
                            labels: cursoNomes,
                            datasets: [{
                                data: cursoHoras,
                                backgroundColor: ['rgba(66, 27, 113, 0.8)', 'rgba(0, 115, 79, 0.8)', 'rgba(240, 115, 60, 0.8)', 'rgba(13, 202, 240, 0.8)', 'rgba(255, 193, 7, 0.8)'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, cutout: '60%',
                            plugins: { legend: { position: 'bottom' } },
                            onHover: (e, el) => { e.native.target.style.cursor = el[0] ? 'pointer' : 'default'; },
                            onClick: (e, el) => { if (el.length > 0) aplicarFiltroDashboard(cursoNomes[el[0].index]); }
                        }
                    });
                }
            <?php endif; ?>

            var calendarEl = document.getElementById('calendarioGeral');
            if (calendarEl) {
                // Inteligência Mobile
                let isMobile = window.innerWidth < 768;
                let visaoInicial = isMobile ? 'listWeek' : 'dayGridMonth';

                calendarioCoordenadorGlobal = new FullCalendar.Calendar(calendarEl, {
                    locale: 'pt-br',
                    initialView: visaoInicial,

                    navLinks: true,
                    nowIndicator: true,
                    dayMaxEvents: 3,
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia', list: 'Lista' },
                    events: <?= $eventos_json ?>,
                    slotMinTime: '08:00:00',
                    slotMaxTime: '23:30:00',
                    allDaySlot: false,
                    expandRows: true,

                    windowResize: function (arg) {
                        if (window.innerWidth < 768) {
                            calendarioCoordenadorGlobal.changeView('listWeek');
                        } else {
                            calendarioCoordenadorGlobal.changeView('dayGridMonth');
                        }
                    },

                    eventContent: function (arg) {
                        let isGrid = arg.view.type === 'dayGridMonth';
                        let truncateClass = isGrid ? 'text-truncate-multi' : 'text-truncate-single';

                        let timeHtml = arg.timeText
                            ? `<div style="font-size: 0.65rem; font-weight: 800; opacity: 0.8; margin-bottom: 2px;">${arg.timeText}</div>`
                            : '';

                        let titleHtml = `<div class="${truncateClass}" style="font-size: 0.75rem; font-weight: 700; line-height: 1.2;">${arg.event.title}</div>`;

                        let localHtml = '';
                        if (!isGrid && arg.event.extendedProps.local) {
                            localHtml = `<div class="${truncateClass}" style="font-size: 0.7rem; margin-top: 2px; opacity: 0.9;">${arg.event.extendedProps.local}</div>`;
                        }

                        let content = document.createElement('div');
                        content.style.width = '100%';
                        content.style.overflow = 'hidden';
                        content.innerHTML = timeHtml + titleHtml + localHtml;

                        let tooltipText = arg.event.title;
                        if (arg.event.extendedProps.local) {
                            tooltipText += " - " + arg.event.extendedProps.local.replace(/<[^>]*>?/gm, '');
                        }
                        content.title = tooltipText;

                        return { domNodes: [content] };
                    },

                    eventClick: function (arg) {
                        arg.jsEvent.preventDefault();
                        document.getElementById('modalDetalheTitulo').innerHTML = '<i class="bi bi-calendar2-event me-2"></i>' + arg.event.title;
                        let dataStr = arg.event.start.toLocaleDateString('pt-BR');
                        let horaInicio = arg.event.start.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                        let horaFim = arg.event.end ? arg.event.end.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
                        let horarioStr = arg.event.allDay ? 'Dia Inteiro' : `${horaInicio} às ${horaFim}`;
                        let tipo = '<span class="badge bg-secondary">Reserva Avulsa</span>';
                        if (arg.event.classNames.includes('apple-event-fixa')) tipo = '<span class="badge" style="background-color: var(--roxo-uniceplac);">Aula Fixa da Grade</span>';
                        if (arg.event.classNames.includes('apple-event-feriado')) tipo = '<span class="badge bg-danger">Feriado</span>';
                        let localStr = arg.event.extendedProps.local ? arg.event.extendedProps.local : '<i class="bi bi-geo-alt me-1"></i> Local não definido';
                        let corpoHtml = `
                            <div class="mb-3"><strong class="text-secondary"><i class="bi bi-clock me-1"></i> Data e Horário:</strong><br> <span class="fs-6 fw-bold">${dataStr}</span> &nbsp;|&nbsp; <span class="fs-6">${horarioStr}</span></div>
                            <div class="mb-3"><strong class="text-secondary"><i class="bi bi-geo-alt me-1"></i> Localização:</strong><br> <span class="fs-6">${localStr}</span></div>
                            <div class="mb-2"><strong class="text-secondary"><i class="bi bi-tag me-1"></i> Categoria:</strong><br> ${tipo}</div>
                        `;
                        document.getElementById('modalDetalheCorpo').innerHTML = corpoHtml;
                        let modal = new bootstrap.Modal(document.getElementById('modalDetalheEvento'));
                        modal.show();
                    }
                });
                calendarioCoordenadorGlobal.render();
            }

            function updateThemeElements(theme) {
                const themeIcon = document.getElementById('themeIcon'); const navbarLogo = document.getElementById('navbarLogo');
                if (theme === 'dark') { if (themeIcon) { themeIcon.className = 'bi bi-sun text-warning'; } if (navbarLogo) navbarLogo.src = 'uniceplac.png'; }
                else { if (themeIcon) { themeIcon.className = 'bi bi-moon-stars'; } if (navbarLogo) navbarLogo.src = 'uniceplac2.png'; }
            }

            updateThemeElements(document.documentElement.getAttribute('data-bs-theme'));

            const themeToggleBtn = document.getElementById('themeToggleBtn');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function () {
                    let newTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('tema-uniceplac', newTheme);
                    updateThemeElements(newTheme);
                });
            }

            document.querySelectorAll('.alert-autohide').forEach(alerta => {
                setTimeout(() => { alerta.style.transition = "opacity 0.6s ease"; alerta.style.opacity = "0"; setTimeout(() => alerta.remove(), 600); }, 4000);
            });

            const btnFoto = document.getElementById('btnAlterarFotoNav');
            const inputFoto = document.getElementById('nova_foto_input');
            if (btnFoto && inputFoto) {
                btnFoto.addEventListener('click', () => inputFoto.click());
                inputFoto.addEventListener('change', function () { if (this.value) document.getElementById('formFotoPerfil').submit(); });
            }

            window.showSection = function (sectionId) {
                document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
                document.querySelectorAll('.offcanvas-menu-link').forEach(link => link.classList.remove('active-link'));
                let targetSection = document.getElementById(sectionId);
                if (targetSection) {
                    targetSection.style.display = 'block';
                    let activeLink = document.querySelector(`.offcanvas-menu-link[onclick*="${sectionId}"]`);
                    if (activeLink) activeLink.classList.add('active-link');
                    window.history.replaceState(null, null, '#' + sectionId);
                    if (sectionId === 'sessao-calendario-geral' && typeof calendarioCoordenadorGlobal !== 'undefined') {
                        setTimeout(() => { calendarioCoordenadorGlobal.updateSize(); }, 150);
                    }
                }
            }

            let hashURL = window.location.hash.replace('#', '');
            let abaPadrao = 'sessao-quadro-horario';
            let abaInicial = hashURL ? hashURL : abaPadrao;
            showSection(abaInicial);

            let qtdPendentesAnterior = <?= $qtd_pendentes ?>;
            const somNotificacao = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

            function monitorarTempoReal() {
                const modalAberto = document.querySelector('.modal.show');
                if (!modalAberto) {
                    fetch('painel_coordenador.php?_t=' + new Date().getTime(), { cache: "no-store" })
                        .then(res => res.text()).then(html => {
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            const novaTabela = doc.getElementById('container-tabela-pendentes'); const tabelaAtual = document.getElementById('container-tabela-pendentes');
                            if (novaTabela && tabelaAtual && tabelaAtual.innerHTML.trim() !== novaTabela.innerHTML.trim()) { tabelaAtual.innerHTML = novaTabela.innerHTML; }
                            const novaTabelaHistGeral = doc.getElementById('container-tabela-historico-geral'); const tabelaHistGeralAtual = document.getElementById('container-tabela-historico-geral');
                            if (novaTabelaHistGeral && tabelaHistGeralAtual && tabelaHistGeralAtual.innerHTML.trim() !== novaTabelaHistGeral.innerHTML.trim()) { tabelaHistGeralAtual.innerHTML = novaTabelaHistGeral.innerHTML; }
                            const novoTituloQtd = doc.getElementById('titulo-qtd-pendentes'); const tituloQtdAtual = document.getElementById('titulo-qtd-pendentes');
                            if (novoTituloQtd && tituloQtdAtual) { tituloQtdAtual.innerText = novoTituloQtd.innerText; }
                            const novoBadgeNav = document.getElementById('badge-nav-bell'); const badgeNavAtual = document.getElementById('badge-nav-bell');
                            if (novoBadgeNav && badgeNavAtual) { badgeNavAtual.outerHTML = novoBadgeNav.outerHTML; }
                            const novaQtdText = novoBadgeNav ? novoBadgeNav.innerText : "0"; const novaQtd = parseInt(novaQtdText) || 0;
                            if (novaQtd > qtdPendentesAnterior) { somNotificacao.play().catch(e => { }); }
                            qtdPendentesAnterior = novaQtd;
                        }).catch(err => { });
                }
            }

            window.filtrarGrade = function () {
                const turnoSelecionado = document.getElementById('filtroTurnoGrade').value;
                const cursoSelecionado = document.getElementById('filtroCursoGrade').value;
                const cardsAula = document.querySelectorAll('.card-grade-aula');

                const modalidadeSelecionada = document.getElementById('filtroModalidadeGrade').value; // Captura o novo filtro

                cardsAula.forEach(card => {
                    const cardTurno = card.getAttribute('data-turno');
                    const cardCurso = card.getAttribute('data-curso');
                    const cardModalidade = card.getAttribute('data-modalidade');


                    let matchTurno = (turnoSelecionado === 'todos' || cardTurno === turnoSelecionado);
                    let matchCurso = (cursoSelecionado === 'todos' || cardCurso === cursoSelecionado);
                    let matchModalidade = (modalidadeSelecionada === 'todos' || cardModalidade === modalidadeSelecionada);


                    if (matchTurno && matchCurso && (modalidadeSelecionada === 'todos' || cardModalidade === modalidadeSelecionada)) {
                        card.style.display = 'block';

                    } else { card.style.display = 'none'; }
                });
            };
            setInterval(monitorarTempoReal, 5000);
        });

        function exportarDashboardCSV() {
            let csvContent = "\uFEFF";
            csvContent += "RELATÓRIO DE PERFORMANCE - UNICEPLAC\n";
            csvContent += "Coordenador: " + "<?= htmlspecialchars($_SESSION['nome']) ?>" + "\n";
            csvContent += "Data de Extração: " + new Date().toLocaleDateString() + "\n\n";

            function extrairDadosTabela(idTabela, titulo) {
                const tabela = document.getElementById(idTabela);
                if (!tabela) return "";
                let conteudo = "--- " + titulo + " ---\n";
                const linhas = tabela.querySelectorAll("tr");
                linhas.forEach(linha => {
                    const colunas = linha.querySelectorAll("th, td");
                    const dadosLinha = Array.from(colunas).map(col => {
                        let texto = col.innerText.replace(/\n/g, " ").replace(/;/g, ",").trim();
                        return '"' + texto + '"';
                    });
                    conteudo += dadosLinha.join(";") + "\n";
                });
                return conteudo + "\n";
            }

            csvContent += extrairDadosTabela("tabelaProfessores", "RELATÓRIO DE PROFESSORES (CH)");
            csvContent += extrairDadosTabela("tabelaLabs", "OCUPAÇÃO DE LABORATÓRIOS");

            const tabelaBI = document.getElementById("corpoTabelaBI") ? document.getElementById("corpoTabelaBI").closest('table') : null;
            if (tabelaBI) {
                csvContent += "--- ANÁLISE DETALHADA DE ALOCAÇÃO ---\n";
                const linhasBI = tabelaBI.querySelectorAll("tr");
                linhasBI.forEach(linha => {
                    const colunas = linha.querySelectorAll("th, td");
                    csvContent += Array.from(colunas).map(col => '"' + col.innerText.trim() + '"').join(";") + "\n";
                });
            }

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "Relatorio_Dashboard_Uniceplac.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>