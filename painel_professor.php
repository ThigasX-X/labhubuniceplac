<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'professor') {
    header("Location: index.php");
    exit;
}

require 'conexao.php';
require 'Agendamento.php';

// Garante que o PHP valide a hora de Brasília
date_default_timezone_set('America/Sao_Paulo');

$agendamento = new Agendamento($pdo);
$mensagem = '';
$id_professor_logado = $_SESSION['usuario_id'];
$aba_ativa = 'sessao-calendario'; // Inicia no calendário

// =================================================================================
// NOVA FUNÇÃO: O "DUPLO CHECK" PARA O PROFESSOR (Avulsos vs Grade Fixa)
// =================================================================================
function verificaChoqueHorario($pdo, $id_lab, $data_reserva, $turno, $periodo) {
    $stmt_ag = $pdo->prepare("SELECT periodo FROM agendamentos WHERE id_laboratorio = ? AND data_reserva = ? AND turno = ? AND status = 'aprovado'");
    $stmt_ag->execute([$id_lab, $data_reserva, $turno]);
    $avulsos = $stmt_ag->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($avulsos as $p_banco) {
        if ($periodo === '1º e 2º Horários' || $p_banco === '1º e 2º Horários' || $periodo === $p_banco) {
            return "Já existe uma reserva aprovada para outro professor neste laboratório nesse dia e horário.";
        }
    }
    
    $dias_map = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
    $dia_semana = $dias_map[date('w', strtotime($data_reserva))];
    
    $id_quadro_ativo = false;
    try { $id_quadro_ativo = $pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn(); } catch(Exception $e) {}
    
    if ($id_quadro_ativo) {
        $stmt_qa = $pdo->prepare("SELECT horario FROM quadro_aulas WHERE id_quadro = ? AND id_laboratorio = ? AND dia_semana = ? AND turno = ?");
        $stmt_qa->execute([$id_quadro_ativo, $id_lab, $dia_semana, $turno]);
        $fixos = $stmt_qa->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($fixos as $h_fixo) {
            if ($periodo === '1º e 2º Horários' || $h_fixo === '1º e 2º Horários' || $periodo === $h_fixo) {
                return "A Grade Fixa Oficial já ocupa este laboratório toda " . $dia_semana . " (" . $turno . ").";
            }
        }
    }
    return false; // Caminho livre! Nenhum conflito.
}

// --- LÓGICA: REGISTRAR RETIRADA DE CHAVE (COM TRAVA DE HORÁRIO AJUSTADA) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_retirada'])) {
    $hora_atual = date('H:i');
    $turno_aula = $_POST['turno_aula'] ?? '';
    $pode_retirar = false;

    if ($turno_aula === 'Matutino' || $turno_aula === 'Manhã') {
        if ($hora_atual >= '07:00' && $hora_atual <= '12:30') $pode_retirar = true;
    } elseif ($turno_aula === 'Vespertino' || $turno_aula === 'Tarde') {
        if ($hora_atual >= '13:00' && $hora_atual <= '18:30') $pode_retirar = true;
    } elseif ($turno_aula === 'Noturno' || $turno_aula === 'Noite') {
        if ($hora_atual >= '18:00' && $hora_atual <= '23:00') $pode_retirar = true;
    }

    if (!$pode_retirar) {
        $mensagem = '<div class="alert alert-warning alert-autohide rounded-0 border-0 border-start border-4 border-warning shadow-sm mb-4"><i class="bi bi-clock-fill me-2"></i><strong>Acesso Negado:</strong> A retirada de chaves para o turno <strong>' . htmlspecialchars($turno_aula) . '</strong> não é permitida neste horário (' . $hora_atual . ').</div>';
    } else {
        try {
            $id_age = $_POST['id_agendamento'];
            $stmt = $pdo->prepare("INSERT INTO controle_chaves (id_agendamento, professor_nome, laboratorio, data_uso, celular, hora_retirada, hora_devolucao_prevista, funcionario_entrega, status) VALUES (:id_age, :nome, :lab, :data, :cel, CURTIME(), :h_dev, :func, 'em_uso')");
            $stmt->execute([':id_age' => $id_age, ':nome' => $_SESSION['nome'], ':lab' => $_POST['laboratorio_chave'], ':data' => date('Y-m-d'), ':cel' => $_POST['celular'], ':h_dev' => $_POST['hora_devolucao_prevista'], ':func' => $_POST['funcionario_entrega']]);
            $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-0 border-start border-4 border-success shadow-sm mb-4"><i class="bi bi-key-fill me-2"></i>Retirada de chave registrada com sucesso!</div>';
        } catch (PDOException $e) {
            $mensagem = '<div class="alert alert-danger alert-autohide rounded-0 border-0 border-start border-4 border-danger shadow-sm mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>Erro ao registrar no banco de dados.</div>';
        }
    }
}

// --- LÓGICA: ACIONAR SUPORTE (SOS) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao_sos'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO chamados_suporte (id_professor, professor_nome, laboratorio, mensagem) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_professor_logado, $_SESSION['nome'], $_POST['laboratorio_sos'], trim($_POST['mensagem_sos'])]);
        $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-0 border-start border-4 border-success shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i><strong>SOS Enviado!</strong> O suporte técnico foi notificado.</div>';
    } catch (PDOException $e) { $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao chamar suporte.</div>'; }
}

// --- LÓGICA: UPLOAD DE FOTO DE PERFIL ---
if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
    $extensao = strtolower(pathinfo($_FILES['nova_foto']['name'], PATHINFO_EXTENSION));
    if (in_array($extensao, ['jpg', 'jpeg', 'png', 'webp'])) {
        $diretorio = 'uploads/';
        if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
        $destino = $diretorio . 'prof_' . $id_professor_logado . '_' . time() . '.' . $extensao;
        if (move_uploaded_file($_FILES['nova_foto']['tmp_name'], $destino)) {
            if (!empty($_SESSION['foto_perfil']) && file_exists($_SESSION['foto_perfil']) && strpos($_SESSION['foto_perfil'], 'padrao') === false) unlink($_SESSION['foto_perfil']);
            $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")->execute([$destino, $id_professor_logado]);
            $_SESSION['foto_perfil'] = $destino;
            $mensagem = '<div class="alert alert-success alert-autohide mb-4">Foto atualizada!</div>';
        }
    }
}

// --- LÓGICA: SOLICITAR AGENDAMENTO COM DUPLO CHECK ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['data_reserva'])) {
    $aba_ativa = 'sessao-solicitar'; 
    $conflito = verificaChoqueHorario($pdo, $_POST['id_laboratorio'], $_POST['data_reserva'], $_POST['turno'], $_POST['periodo']);
    if ($conflito) {
        $mensagem = '<div class="alert alert-warning alert-autohide rounded-0 border-start border-4 border-warning mb-4 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Não foi possível solicitar:</strong> ' . $conflito . '</div>';
    } else {
        try {
            $agendamento->solicitarReserva($_POST['id_laboratorio'], $id_professor_logado, $_POST['id_disciplina'], $_POST['turno'], $_POST['periodo'], $_POST['data_reserva']);
            $mensagem = '<div class="alert alert-info alert-autohide mb-4"><i class="bi bi-info-circle-fill me-2"></i><strong>Solicitação enviada!</strong> Aguarde a coordenação.</div>';
            $aba_ativa = 'sessao-historico'; 
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $mensagem = '<div class="alert alert-warning alert-autohide mb-4">Você já tem uma reserva pendente para este lab neste dia/turno.</div>';
            else $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao solicitar.</div>';
        }
    }
}

// --- BUSCAS DE DADOS ---
$laboratorios = $agendamento->buscarLaboratorios();
$disciplinas = $agendamento->buscarDisciplinas();
$minhas_alocacoes = $agendamento->listarAlocacoesProfessor($id_professor_logado); // Avulsos

$chaves_retiradas = [];
try {
    $stmt_chaves = $pdo->prepare("SELECT id_agendamento FROM controle_chaves WHERE status = 'em_uso' AND professor_nome = :nome");
    $stmt_chaves->execute([':nome' => $_SESSION['nome']]);
    while ($row = $stmt_chaves->fetch(PDO::FETCH_ASSOC)) { $chaves_retiradas[$row['id_agendamento']] = true; }
} catch (PDOException $e) {}

$meus_ensalamentos = [];
try {
    $stmt_ensalamento = $pdo->prepare("SELECT e.*, d.nome as disciplina FROM ensalamento e JOIN disciplinas d ON e.id_disciplina = d.id WHERE e.id_professor = :id_prof ORDER BY FIELD(e.turno, 'Matutino', 'Vespertino', 'Noturno'), e.curso ASC");
    $stmt_ensalamento->execute([':id_prof' => $id_professor_logado]);
    $meus_ensalamentos = $stmt_ensalamento->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!isset($_SESSION['foto_perfil'])) {
    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id_professor_logado]);
    $_SESSION['foto_perfil'] = $stmt->fetchColumn() ?? null;
}
$foto_atual = !empty($_SESSION['foto_perfil']) && file_exists($_SESSION['foto_perfil']) ? $_SESSION['foto_perfil'] : 'uploads/padrao-usuario.png';

$hoje = date('Y-m-d');
$hoje_en = date('l');
$map_en = ['Segunda'=>'Monday','Terça'=>'Tuesday','Quarta'=>'Wednesday','Quinta'=>'Thursday','Sexta'=>'Friday','Sábado'=>'Saturday'];

// --- INTEGRAÇÃO COM O QUADRO DE HORÁRIOS ---
$id_quadro_ativo = false;
try { $id_quadro_ativo = $pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn(); } catch(Exception $e) {}

if ($id_quadro_ativo) {
    // 1. Puxa as aulas práticas (Laboratórios) do Novo Quadro
    $stmt_qa_lab = $pdo->prepare("SELECT qa.id, l.nome as laboratorio, l.localizacao as lab_local, l.andar as lab_andar, d.nome as disciplina, qa.turno, qa.horario as periodo, qa.dia_semana, qa.bloco, qa.andar, qa.sala FROM quadro_aulas qa INNER JOIN laboratorios l ON qa.id_laboratorio = l.id INNER JOIN disciplinas d ON qa.id_disciplina = d.id WHERE qa.id_quadro = ? AND qa.id_professor = ? AND qa.id_laboratorio IS NOT NULL");
    $stmt_qa_lab->execute([$id_quadro_ativo, $id_professor_logado]);
    foreach ($stmt_qa_lab->fetchAll(PDO::FETCH_ASSOC) as $mlf) {
        $mlf['id'] = $mlf['id'] + 1000000; 
        $str_dia = $map_en[$mlf['dia_semana']];
        $mlf['data_reserva'] = ($hoje_en === $str_dia) ? date('Y-m-d') : date('Y-m-d', strtotime("next " . $str_dia));
        $mlf['status'] = 'aprovado';
        $minhas_alocacoes[] = $mlf;
    }

    // 2. Puxa TODAS as aulas que possuam SALA PREENCHIDA
    $stmt_qa_sala = $pdo->prepare("SELECT qa.*, d.nome as disciplina FROM quadro_aulas qa JOIN disciplinas d ON qa.id_disciplina = d.id WHERE qa.id_quadro = ? AND qa.id_professor = ? AND qa.sala IS NOT NULL AND TRIM(qa.sala) != ''");
    $stmt_qa_sala->execute([$id_quadro_ativo, $id_professor_logado]);
    foreach ($stmt_qa_sala->fetchAll(PDO::FETCH_ASSOC) as $sf) {
        $meus_ensalamentos[] = ['id' => 'q_'.$sf['id'], 'disciplina' => $sf['disciplina'], 'turno' => $sf['turno'], 'curso' => $sf['curso'], 'categoria' => $sf['modalidade'].' ('.$sf['dia_semana'].')', 'bloco' => $sf['bloco'], 'andar' => $sf['andar'], 'sala' => $sf['sala']];
    }
}

// =================================================================================
// LÓGICA DO CALENDÁRIO VISUAL DO PROFESSOR (COM HORÁRIOS EXATOS E FERIADOS)
// =================================================================================
$eventos_calendario = [];

if (!function_exists('converterHorario')) {
    function converterHorario($turno, $periodo) {
        $start = '00:00:00'; $end = '01:00:00';
        if ($turno == 'Matutino') {
            if ($periodo == '1º Horário') { $start = '08:20:00'; $end = '10:00:00'; }
            elseif ($periodo == '2º Horário') { $start = '10:15:00'; $end = '11:55:00'; }
            else { $start = '08:20:00'; $end = '11:55:00'; }
        } elseif ($turno == 'Vespertino') {
            if ($periodo == '1º Horário') { $start = '14:20:00'; $end = '16:00:00'; }
            elseif ($periodo == '2º Horário') { $start = '16:20:00'; $end = '18:00:00'; }
            else { $start = '14:20:00'; $end = '18:00:00'; }
        } elseif ($turno == 'Noturno') {
            if ($periodo == '1º Horário') { $start = '19:20:00'; $end = '21:00:00'; }
            elseif ($periodo == '2º Horário') { $start = '21:10:00'; $end = '22:50:00'; }
            else { $start = '19:20:00'; $end = '22:50:00'; }
        }
        return [$start, $end];
    }
}

// 1. Busca Agendamentos Avulsos Aprovados do Professor
$stmt_av = $pdo->prepare("SELECT a.*, d.nome as disc_nome, l.nome as lab_nome FROM agendamentos a JOIN disciplinas d ON a.id_disciplina = d.id JOIN laboratorios l ON a.id_laboratorio = l.id WHERE a.id_professor = ? AND a.status = 'aprovado'");
$stmt_av->execute([$id_professor_logado]);
foreach ($stmt_av->fetchAll(PDO::FETCH_ASSOC) as $av) {
    list($start, $end) = converterHorario($av['turno'], $av['periodo']);
    $eventos_calendario[] = [
        'title' => $av['disc_nome'],
        'start' => $av['data_reserva'] . 'T' . $start,
        'end'   => $av['data_reserva'] . 'T' . $end,
        'className' => 'apple-event-avulsa', 
        'extendedProps' => [ 'local' => '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($av['lab_nome']) ]
    ];
}

// 2. Busca Grade Fixa Oficial do Professor
if ($id_quadro_ativo) {
    $dias_map_num = ['Domingo'=>0, 'Segunda'=>1, 'Terça'=>2, 'Quarta'=>3, 'Quinta'=>4, 'Sexta'=>5, 'Sábado'=>6];
    $ano_atual = date('Y');
    $mes_atual = (int)date('m');
    if ($mes_atual <= 6) { $start_recur = $ano_atual . '-01-01'; $end_recur = $ano_atual . '-07-31'; }
    else { $start_recur = $ano_atual . '-07-01'; $end_recur = $ano_atual . '-12-31'; }

    $stmt_fixos = $pdo->prepare("SELECT qa.*, d.nome as disc_nome, l.nome as lab_nome, l.localizacao as lab_local, l.andar as lab_andar FROM quadro_aulas qa JOIN disciplinas d ON qa.id_disciplina = d.id LEFT JOIN laboratorios l ON qa.id_laboratorio = l.id WHERE qa.id_quadro = ? AND qa.id_professor = ?");
    $stmt_fixos->execute([$id_quadro_ativo, $id_professor_logado]);
    
    foreach($stmt_fixos->fetchAll(PDO::FETCH_ASSOC) as $f) {
        list($start, $end) = converterHorario($f['turno'], $f['horario']);
        $dia_num = $dias_map_num[$f['dia_semana']] ?? 1;
        
        $loc_parts = [];
        if ($f['id_laboratorio']) {
            $lab_info = '<i class="bi bi-pc-display me-1"></i> Lab: ' . htmlspecialchars($f['lab_nome']);
            $lab_detalhes = [];
            if (!empty($f['lab_local'])) $lab_detalhes[] = htmlspecialchars($f['lab_local']);
            if (!empty($f['lab_andar'])) $lab_detalhes[] = 'Andar ' . htmlspecialchars($f['lab_andar']);
            if (!empty($lab_detalhes)) $lab_info .= ' (' . implode(' - ', $lab_detalhes) . ')';
            $loc_parts[] = $lab_info;
        }
        if (!empty($f['sala']) || !empty($f['bloco'])) {
            $loc_parts[] = '<i class="bi bi-door-open me-1"></i> Sala: ' . htmlspecialchars($f['sala']) . ' (Bl ' . htmlspecialchars($f['bloco']) . ')';
        }
        $loc = implode('<br>', $loc_parts);
        if (empty($loc)) $loc = '<i class="bi bi-geo-alt me-1"></i> A definir';
        
        $eventos_calendario[] = [
            'title' => $f['disc_nome'],
            'daysOfWeek' => [$dia_num],
            'startTime' => $start,
            'endTime' => $end,
            'startRecur' => $start_recur,
            'endRecur' => $end_recur,
            'className' => 'apple-event-fixa', 
            'extendedProps' => [ 'local' => $loc ]
        ];
    }
}

// 3. INJEÇÃO DE FERIADOS NACIONAIS 2026
$feriados_2026 = [
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
    '2026-12-25' => 'Natal'
];

foreach ($feriados_2026 as $data => $nome_feriado) {
    $eventos_calendario[] = [
        'title' => 'Feriado: ' . $nome_feriado,
        'start' => $data,
        'allDay' => true,
        'className' => 'apple-event-feriado',
        'extendedProps' => [ 'local' => '<i class="bi bi-calendar-x me-1"></i> Instituição Fechada' ]
    ];
}

$eventos_json = json_encode($eventos_calendario);
// =================================================================================

$qtd_pendentes = 0; $proximas_matutino = []; $proximas_vespertino = []; $proximas_noturno = [];
foreach ($minhas_alocacoes as $alocacao) {
    if ($alocacao['status'] === 'pendente') { $qtd_pendentes++; } 
    elseif ($alocacao['status'] === 'aprovado' && $alocacao['data_reserva'] >= $hoje) { 
        $t = trim($alocacao['turno']);
        if ($t === 'Matutino' || $t === 'Manhã') $proximas_matutino[] = $alocacao;
        elseif ($t === 'Vespertino' || $t === 'Tarde') $proximas_vespertino[] = $alocacao;
        elseif ($t === 'Noturno' || $t === 'Noite') $proximas_noturno[] = $alocacao;
    }
}
usort($proximas_matutino, function($a, $b) { return strtotime($a['data_reserva']) - strtotime($b['data_reserva']); });
usort($proximas_vespertino, function($a, $b) { return strtotime($a['data_reserva']) - strtotime($b['data_reserva']); });
usort($proximas_noturno, function($a, $b) { return strtotime($a['data_reserva']) - strtotime($b['data_reserva']); });
$qtd_matutino = count($proximas_matutino); $qtd_vespertino = count($proximas_vespertino); $qtd_noturno = count($proximas_noturno);
$total_proximas = $qtd_matutino + $qtd_vespertino + $qtd_noturno;

$ensalamento_matutino = []; $ensalamento_vespertino = []; $ensalamento_noturno = [];
foreach ($meus_ensalamentos as $e) {
    $t = trim($e['turno']);
    if ($t === 'Matutino' || $t === 'Manhã') $ensalamento_matutino[] = $e;
    elseif ($t === 'Vespertino' || $t === 'Tarde') $ensalamento_vespertino[] = $e;
    elseif ($t === 'Noturno' || $t === 'Noite') $ensalamento_noturno[] = $e;
}
$total_ensalamentos = count($meus_ensalamentos);

function renderizarCardAulaProfessor($aula, $hoje, $chaves_retiradas, $borda_classe, $icone_turno) {
    $badge_hoje = ($aula['data_reserva'] == $hoje) ? '<span class="badge bg-danger float-end">HOJE</span>' : '';
    $ja_retirou = isset($chaves_retiradas[$aula['id']]);
    
    $lab_det = [];
    if (!empty($aula['lab_local'])) $lab_det[] = htmlspecialchars($aula['lab_local']);
    if (!empty($aula['lab_andar'])) $lab_det[] = 'Andar ' . htmlspecialchars($aula['lab_andar']);
    $lab_str = !empty($lab_det) ? "<div class='text-muted mb-1' style='font-size:0.8rem;'><i class='bi bi-geo-alt me-1'></i>" . implode(' - ', $lab_det) . "</div>" : "";

    $sala_str = "";
    if (!empty($aula['sala']) || !empty($aula['bloco'])) {
        $sala_str = "<div class='text-success mb-3' style='font-size:0.8rem;'><i class='bi bi-door-open me-1'></i>Sala " . htmlspecialchars($aula['sala'] ?? '-') . " (Bl " . htmlspecialchars($aula['bloco'] ?? '-') . ")</div>";
    }
    
    $local_extra = $lab_str . $sala_str;

    ?>
    <div class="col">
        <div class="card h-100 apple-ticket <?= $borda_classe ?>">
            <div class="card-body p-3">
                <?= $badge_hoje ?>
                <h5 class="mb-1 text-dark fw-bold text-truncate" title="<?= htmlspecialchars($aula['laboratorio']) ?>"><i class="bi bi-pc-display me-2 text-primary"></i><?= htmlspecialchars($aula['laboratorio']) ?></h5>
                <?= $local_extra ?>
                <div class="mb-2 text-primary fw-bold d-flex align-items-center small"><i class="bi bi-calendar-event me-2"></i> <?= date('d/m/Y', strtotime($aula['data_reserva'])) ?> | <?= $icone_turno ?> <?= htmlspecialchars($aula['periodo']) ?></div>
                <div class="text-truncate d-flex align-items-center mb-3 small text-secondary"><i class="bi bi-book-half me-2"></i> <?= htmlspecialchars($aula['disciplina']) ?></div>

                <?php if ($aula['data_reserva'] == $hoje): ?>
                    <hr class="my-3 opacity-25">
                    <?php if ($ja_retirou): ?>
                        <div class="apple-tag mb-2"><div class="apple-dot"></div> EM USO (Sua Aula)</div>
                        <button type="button" class="apple-btn apple-btn-danger heartbeat" data-bs-toggle="modal" data-bs-target="#modalSOS<?= $aula['id'] ?>"><i class="bi bi-exclamation-triangle-fill me-2"></i> Chamado SOS</button>
                    <?php else: ?>
                        <button type="button" class="apple-btn apple-btn-success mb-2" data-bs-toggle="modal" data-bs-target="#modalChave<?= $aula['id'] ?>"><i class="bi bi-key-fill me-2"></i> Retirar Chave</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($aula['data_reserva'] == $hoje && !$ja_retirou): ?>
        <div class="modal fade" id="modalChave<?= $aula['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-success" style="border-width: 3px; border-radius: 20px !important;">
                <div class="modal-header bg-success text-white border-0" style="border-top-left-radius: 16px; border-top-right-radius: 16px;"><h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i> Retirar Chave</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-start p-4">
                    <form method="POST" action="painel_professor.php">
                        <input type="hidden" name="registrar_retirada" value="1">
                        <input type="hidden" name="id_agendamento" value="<?= $aula['id'] ?>">
                        <input type="hidden" name="laboratorio_chave" value="<?= htmlspecialchars($aula['laboratorio']) ?>">
                        <input type="hidden" name="turno_aula" value="<?= htmlspecialchars($aula['turno']) ?>">
                        
                        <p class="mb-1 text-secondary">Lab: <strong class="text-dark"><?= htmlspecialchars($aula['laboratorio']) ?></strong></p>
                        <div class="mb-3 mt-3"><label class="form-label fw-bold small">Seu Celular:</label><input type="text" class="form-control rounded-pill px-3" name="celular" placeholder="(61) 90000-0000" required></div>
                        <div class="mb-3"><label class="form-label fw-bold small">Hora Prevista Devolução:</label><input type="time" class="form-control rounded-pill px-3" name="hora_devolucao_prevista" required></div>
                        <div class="mb-4"><label class="form-label fw-bold small">Técnico que entregou:</label><input type="text" class="form-control rounded-pill px-3" name="funcionario_entrega" required></div>
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i> Confirmar</button>
                    </form>
                </div>
            </div></div>
        </div>
    <?php endif; ?>

    <?php if ($aula['data_reserva'] == $hoje && $ja_retirou): ?>
        <div class="modal fade" id="modalSOS<?= $aula['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-danger" style="border-width: 3px; border-radius: 20px !important;">
                <div class="modal-header bg-danger text-white border-0" style="border-top-left-radius: 16px; border-top-right-radius: 16px;"><h5 class="modal-title fw-bold"><i class="bi bi-headset me-2"></i> Chamado SOS</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body text-start p-4">
                    <form method="POST" action="painel_professor.php">
                        <input type="hidden" name="acao_sos" value="1"><input type="hidden" name="laboratorio_sos" value="<?= htmlspecialchars($aula['laboratorio']) ?>">
                        <p class="text-secondary small mb-3">Lab: <strong class="text-dark"><?= htmlspecialchars($aula['laboratorio']) ?></strong></p>
                        <div class="mb-4"><label class="form-label fw-bold">Problema:</label><textarea class="form-control" style="border-radius: 15px;" name="mensagem_sos" rows="4" placeholder="O PC não liga..." required></textarea></div>
                        <button type="submit" class="btn btn-danger w-100 fw-bold py-2 rounded-pill"><i class="bi bi-send me-1"></i> Enviar Alerta</button>
                    </form>
                </div>
            </div></div>
        </div>
    <?php endif; 
}

function renderizarCardEnsalamento($e, $badge_cor, $borda_classe) {
    ?>
    <div class="col">
        <div class="card h-100 apple-ticket <?= $borda_classe ?> p-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-bold text-dark text-truncate mb-0" style="max-width: 80%;" title="<?= htmlspecialchars($e['disciplina']) ?>"><?= htmlspecialchars($e['disciplina']) ?></h5>
                <span class="badge <?= $badge_cor ?>" <?= trim($e['turno']) === 'Vespertino' ? 'style="background-color: var(--tarde-cor);"' : '' ?>><?= $e['turno'] ?></span>
            </div>
            <p class="text-muted small mb-4"><i class="bi bi-mortarboard me-1"></i> <?= htmlspecialchars($e['curso']) ?> &nbsp;|&nbsp; <?= htmlspecialchars($e['categoria']) ?></p>
            <div class="d-flex align-items-center bg-light p-3 rounded border">
                <div class="flex-fill text-center"><span class="d-block small text-secondary fw-bold text-uppercase mb-1">Bloco</span><span class="fs-5 fw-bold text-dark"><?= htmlspecialchars($e['bloco'] ?? '-') ?></span></div>
                <div class="flex-fill text-center border-start"><span class="d-block small text-secondary fw-bold text-uppercase mb-1">Andar</span><span class="fs-5 fw-bold text-dark"><?= htmlspecialchars($e['andar'] ?? '-') ?></span></div>
                <div class="flex-fill text-center border-start"><span class="d-block small text-secondary fw-bold text-uppercase mb-1">Sala</span><span class="fs-4 fw-bold text-uniceplac"><?= htmlspecialchars($e['sala'] ?? '-') ?></span></div>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Portal Docente - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales-all.global.min.js"></script>

    <script>
        const savedTheme = localStorage.getItem('tema-uniceplac') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
    </script>

    <style>
        :root { 
            --verde-uniceplac: #00734F; --roxo-uniceplac: #421B71; --laranja-uniceplac: #F0733C; 
            --manha-cor: #ffc107; --tarde-cor: #fd7e14; --noite-cor: #421B71; 
        }
        body { background-color: #f4f6f8; transition: background-color 0.3s ease; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card, .card-header, .form-control, .form-select, .btn, .badge, .alert, .offcanvas, .modal-content { border-radius: 0 !important; }
        .bg-uniceplac { background-color: var(--verde-uniceplac) !important; }
        .text-uniceplac { color: var(--verde-uniceplac) !important; }
        
        .btn-uniceplac { background-color: var(--verde-uniceplac); color: white; border: none; font-weight: 500; }
        .btn-uniceplac:hover { background-color: var(--roxo-uniceplac); color: white; }
        
        .navbar { border-bottom: 1px solid rgba(0,0,0,0.05) !important; background: rgba(255, 255, 255, 0.9) !important; backdrop-filter: blur(10px); }
        
        .content-section { display: none; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .header-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin-right: 12px; background-color: rgba(0,0,0,0.05); }

        .offcanvas-menu-link { padding: 12px 20px; color: #495057; font-weight: 500; border-bottom: 1px solid #f1f1f1; display: block; text-decoration: none; transition: background-color 0.2s; cursor: pointer; }
        .offcanvas-menu-link:hover, .offcanvas-menu-link.active-link { background-color: rgba(0, 115, 79, 0.05); color: var(--verde-uniceplac); border-right: 4px solid var(--verde-uniceplac); }
        .offcanvas-menu-link i { width: 25px; text-align: center; }

        .avatar-img-small { width: 40px; height: 40px; object-fit: cover; border-radius: 50% !important; border: 2px solid #dee2e6; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.2s; }
        .avatar-img-small:hover { opacity: 0.8; border-color: var(--verde-uniceplac); transform: scale(1.05); }
        .avatar-img-large { width: 90px; height: 90px; object-fit: cover; border-radius: 50% !important; border: 3px solid var(--laranja-uniceplac); margin-bottom: 15px; }
        #nova_foto_input { display: none; }
        
        .top-icon-btn { color: #495057; font-size: 1.3rem; cursor: pointer; transition: color 0.2s; }
        .top-icon-btn:hover { color: var(--verde-uniceplac); }

        /* APPLE GLASSMORPHISM CARDS & TAGS */
        .apple-ticket {
            border-radius: 20px !important; border: 1px solid rgba(0,0,0,0.08) !important; background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: all 0.3s ease; position: relative; overflow: hidden;
        }
        .apple-ticket:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); transform: translateY(-3px); }
        
        .border-matutino { border-left: 6px solid var(--manha-cor) !important; }
        .border-vespertino { border-left: 6px solid var(--tarde-cor) !important; }
        .border-noturno { border-left: 6px solid var(--noite-cor) !important; }
        .icon-text { color: #6c757d; width: 20px; text-align: center; margin-right: 8px; }
        
        .apple-tag {
            display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px;
            background: rgba(25, 135, 84, 0.1); border: 1px solid rgba(25, 135, 84, 0.2); color: #198754;
            border-radius: 30px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;
            text-transform: uppercase; backdrop-filter: blur(8px); width: 100%;
        }
        .apple-dot {
            width: 8px; height: 8px; background-color: #198754; border-radius: 50%;
            margin-right: 8px; box-shadow: 0 0 6px #198754; animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); } }

        .apple-btn { display: flex; align-items: center; justify-content: center; width: 100%; padding: 8px 16px; border-radius: 30px; font-size: 0.85rem; font-weight: 600; backdrop-filter: blur(8px); transition: all 0.2s ease; cursor: pointer; border: none; outline: none; text-decoration: none; }
        .apple-btn-success { background: rgba(25, 135, 84, 0.1); border: 1px solid rgba(25, 135, 84, 0.2); color: #198754; }
        .apple-btn-success:hover { background: rgba(25, 135, 84, 0.2); transform: translateY(-1px); }
        .apple-btn-danger { background: rgba(220, 53, 69, 0.08); border: 1px solid rgba(220, 53, 69, 0.2); color: #dc3545; }
        .apple-btn-danger:hover { background: rgba(220, 53, 69, 0.15); color: #c82333; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(220, 53, 69, 0.1); }

        @keyframes heartbeat { 0% { transform: scale(1); } 20% { transform: scale(1.03); } 40% { transform: scale(1); } 60% { transform: scale(1.03); } 80% { transform: scale(1); } 100% { transform: scale(1); } }
        .heartbeat { animation: heartbeat 1.5s infinite; }

        .turn-divider { display: flex; align-items: center; margin: 2rem 0 1rem; color: #6c757d; }
        .turn-divider::before, .turn-divider::after { content: ""; flex: 1; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .turn-divider:not(:empty)::before { margin-right: 1em; }
        .turn-divider:not(:empty)::after { margin-left: 1em; }
        .turn-badge { display: inline-flex; align-items: center; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; backdrop-filter: blur(10px); }
        .badge-matutino { background: rgba(255, 193, 7, 0.15); color: #d39e00; border: 1px solid rgba(255, 193, 7, 0.3); }
        .badge-vespertino { background: rgba(253, 126, 20, 0.15); color: #e85e00; border: 1px solid rgba(253, 126, 20, 0.3); }
        .badge-noturno { background: rgba(66, 27, 113, 0.15); color: #421B71; border: 1px solid rgba(66, 27, 113, 0.3); }
        
        /* CALENDÁRIO ESTILO APPLE */
        #calendarioProfessor { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .fc-theme-standard .fc-scrollgrid { border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; overflow: hidden; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: rgba(0,0,0,0.05); }
        .fc-col-header-cell { background-color: #fbfbfd; padding: 8px 0; font-weight: 600; color: #86868b; text-transform: uppercase; font-size: 0.8rem;}
        .fc .fc-button-group > .fc-button { background: #f5f5f7 !important; color: #007aff !important; border-color: #d2d2d7 !important; text-transform: capitalize; box-shadow: none !important; font-weight: 500; transition: 0.2s; }
        .fc .fc-button-group > .fc-button:hover { background: #e8e8ed !important; }
        .fc .fc-button-group > .fc-button.fc-button-active { background: #007aff !important; color: #fff !important; border-color: #007aff !important; }
        .fc .fc-today-button { background: #fff !important; color: #007aff !important; border-color: #d2d2d7 !important; font-weight: 600; text-transform: capitalize; }
        .fc-toolbar-title { font-weight: 700 !important; color: #1d1d1f; text-transform: capitalize; }
        
        .apple-event-fixa { --fc-event-bg-color: rgba(66, 27, 113, 0.12); --fc-event-border-color: var(--roxo-uniceplac); --fc-event-text-color: var(--roxo-uniceplac); }
        .apple-event-avulsa { --fc-event-bg-color: rgba(240, 115, 60, 0.12); --fc-event-border-color: var(--laranja-uniceplac); --fc-event-text-color: #c95b28; }
        
        /* --- ESTILO PARA OS FERIADOS --- */
        .apple-event-feriado { 
            --fc-event-bg-color: rgba(220, 53, 69, 0.12); 
            --fc-event-border-color: #dc3545; 
            --fc-event-text-color: #a71d2a; 
        }

        .fc-event { border-left-width: 4px !important; border-radius: 6px !important; border-top: none !important; border-right: none !important; border-bottom: none !important; padding: 4px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 3px; cursor: pointer; }
        .fc-daygrid-event { white-space: normal !important; align-items: start !important; }
        .fc-daygrid-event .fc-event-main { white-space: normal !important; overflow: hidden; display: block; line-height: 1.2; }
        .fc-v-event .fc-event-title-container { padding-bottom: 4px; }

        html { scroll-behavior: smooth; }

        /* DARK MODE */
        [data-bs-theme="dark"] body { background-color: #121212; color: #e0e0e0; }
        [data-bs-theme="dark"] .bg-white, [data-bs-theme="dark"] .bg-light { background-color: #1e1e1e !important; color: #e0e0e0 !important; }
        [data-bs-theme="dark"] .navbar { background: rgba(30, 30, 30, 0.9) !important; border-bottom-color: #333 !important; }
        [data-bs-theme="dark"] .card { background-color: #1e1e1e; border-color: #333 !important; }
        [data-bs-theme="dark"] .apple-ticket { background: rgba(35,35,35,0.7); border-color: rgba(255,255,255,0.08) !important; }
        [data-bs-theme="dark"] .text-dark { color: #f8f9fa !important; }
        [data-bs-theme="dark"] .text-secondary, [data-bs-theme="dark"] .text-muted { color: #adb5bd !important; }
        [data-bs-theme="dark"] .border, [data-bs-theme="dark"] .border-bottom { border-color: #333 !important; }
        [data-bs-theme="dark"] .table { color: #e0e0e0; border-color: #444; }
        [data-bs-theme="dark"] .table-light th { background-color: #2a2a2a !important; color: #e0e0e0; border-color: #444; }
        [data-bs-theme="dark"] .offcanvas { background-color: #1e1e1e !important; }
        [data-bs-theme="dark"] .offcanvas-menu-link { color: #e0e0e0; border-bottom-color: #333; }
        [data-bs-theme="dark"] .offcanvas-menu-link:hover { background-color: rgba(255,255,255,0.05); }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select { background-color: #2a2a2a; color: #fff; border-color: #444; }
        [data-bs-theme="dark"] .form-control:focus, [data-bs-theme="dark"] .form-select:focus { background-color: #333; color: #fff; border-color: var(--verde-uniceplac); }
        [data-bs-theme="dark"] .modal-content { background-color: #1e1e1e; border-color: #444; border-radius: 20px !important; }
        [data-bs-theme="dark"] .top-icon-btn { color: #e0e0e0; }
        
        [data-bs-theme="dark"] .fc-toolbar-title { color: #fff; }
        [data-bs-theme="dark"] .fc-col-header-cell { background-color: #1c1c1e; color: #98989d; border-color: #333;}
        [data-bs-theme="dark"] .fc-theme-standard .fc-scrollgrid, [data-bs-theme="dark"] .fc-theme-standard td, [data-bs-theme="dark"] .fc-theme-standard th { border-color: #333; }
        [data-bs-theme="dark"] .fc .fc-button-group > .fc-button { background: #1c1c1e !important; border-color: #333 !important; }
        [data-bs-theme="dark"] .fc .fc-button-group > .fc-button.fc-button-active { background: #0a84ff !important; color: #fff !important; border-color: #0a84ff !important; }
        [data-bs-theme="dark"] .fc .fc-today-button { background: #1c1c1e !important; color: #0a84ff !important; border-color: #333 !important; }
        
        [data-bs-theme="dark"] .apple-event-fixa { --fc-event-bg-color: rgba(66, 27, 113, 0.3); --fc-event-text-color: #dcb3ff; }
        [data-bs-theme="dark"] .apple-event-avulsa { --fc-event-bg-color: rgba(240, 115, 60, 0.25); --fc-event-text-color: #ffb088; }
        [data-bs-theme="dark"] .apple-event-feriado { --fc-event-bg-color: rgba(220, 53, 69, 0.25); --fc-event-text-color: #ff8a95; }
    </style>
</head>
<body>

    <form id="formFotoPerfil" action="painel_professor.php" method="POST" enctype="multipart/form-data" class="d-none">
        <input type="file" name="nova_foto" id="nova_foto_input" accept="image/png, image/jpeg, image/webp">
    </form>

    <nav class="navbar navbar-light bg-white mb-4 border-bottom shadow-sm sticky-top">
        <div class="container-fluid px-3 px-md-4">
            <span class="navbar-brand mb-0 h1 d-flex align-items-center">
                <img src="uniceplac.png" id="navbarLogo" alt="Logo" style="height: 70px; margin-right: 12px; transition: 0.3s;">
                <span class="d-none d-md-inline fw-semibold text-uniceplac" style="font-size: 1.1rem;"></span>
            </span>
            <div class="d-flex align-items-center">
                <div class="me-4 top-icon-btn" id="themeToggleBtn" title="Alternar Tema"><i class="bi bi-moon-stars" id="themeIcon"></i></div>
                <div class="position-relative me-4 top-icon-btn" id="navBell" title="Ver Status das Solicitações">
                    <i class="bi bi-bell"></i>
                    <span id="badge-nav-bell" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light text-white <?= $qtd_pendentes > 0 ? '' : 'd-none' ?>" style="font-size: 0.65rem; padding: 0.25em 0.4em;">
                        <?= $qtd_pendentes ?>
                    </span>
                </div>
                <div class="me-3 d-flex align-items-center top-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" title="Abrir Menu"><i class="bi bi-grid-3x3-gap fs-5"></i></div>
                <img src="<?= htmlspecialchars($foto_atual) ?>" alt="Sua Foto" class="avatar-img-small ms-1" id="btnAlterarFotoNav" title="Clique para alterar sua foto">
            </div>
        </div>
    </nav>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="sidebarMenu">
        <div class="offcanvas-header bg-uniceplac text-white py-3 border-0">
            <h6 class="offcanvas-title fw-bold"><i class="bi bi-grid-1x2-fill me-2"></i>Menu Docente</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column bg-white">
            <div class="p-4 text-center border-bottom bg-light">
                <img src="<?= htmlspecialchars($foto_atual) ?>" alt="Foto" class="avatar-img-large shadow-sm">
                <h5 class="fw-bold mb-1 text-dark">Prof. <?= htmlspecialchars($_SESSION['nome']) ?></h5>
                <span class="badge bg-uniceplac text-uppercase px-3 py-1"><?= htmlspecialchars($_SESSION['perfil']) ?></span>
            </div>
            <div class="flex-grow-1 overflow-auto">
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-75">Meu Planejamento</div>
                <a href="#sessao-calendario" class="offcanvas-menu-link"><i class="bi bi-calendar3 text-primary me-2"></i> Meu Calendário de Aulas</a>
                
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-75 border-top mt-2">Laboratórios (Práticas)</div>
                <a href="#sessao-dashboard" class="offcanvas-menu-link"><i class="bi bi-geo-alt text-primary me-2"></i> Próximas Aulas e Chaves</a>
                <a href="#sessao-historico" class="offcanvas-menu-link">
                    <i class="bi bi-clock-history text-info me-2"></i> Histórico de Reservas
                    <span id="badge-menu-lateral" class="badge bg-warning text-dark ms-2 <?= $qtd_pendentes > 0 ? '' : 'd-none' ?>"><?= $qtd_pendentes ?> pendentes</span>
                </a>
                <a href="#sessao-solicitar" class="offcanvas-menu-link"><i class="bi bi-calendar-plus text-success me-2"></i> Solicitar Laboratório</a>
                
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-75 border-top mt-2">Grade Regular</div>
                <a href="#sessao-ensalamento" class="offcanvas-menu-link"><i class="bi bi-building text-primary me-2"></i> Meu Ensalamento Fixo</a>
            </div>
            <div class="p-3 border-top mt-auto bg-light"><a href="logout.php" class="btn btn-outline-danger w-100 fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Sair do Sistema</a></div>
        </div>
    </div>

    <div class="container pt-3 pb-5">
        <?= $mensagem ?>

        <div id="sessao-calendario" class="content-section">
            <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid var(--azul-google);">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-dark d-flex align-items-center"><i class="bi bi-calendar3 text-primary me-3 fs-4"></i> Meu Calendário Pessoal</h5>
                    <p class="text-muted small mb-0 mt-1">
                        <span class="badge me-2" style="background-color: rgba(66, 27, 113, 0.15); color: var(--roxo-uniceplac); border: 1px solid var(--roxo-uniceplac);">Aulas Fixas da Grade</span> 
                        <span class="badge me-2" style="background-color: rgba(240, 115, 60, 0.15); color: #c95b28; border: 1px solid var(--laranja-uniceplac);">Reservas Avulsas Aprovadas</span>
                        <span class="badge" style="background-color: rgba(220, 53, 69, 0.15); color: #dc3545; border: 1px solid #dc3545;">Feriados Nacionais</span>
                    </p>
                </div>
                <div class="card-body bg-white p-3 p-md-4">
                    <div id="calendarioProfessor"></div>
                </div>
            </div>
        </div>

        <div id="sessao-dashboard" class="content-section">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h4 class="text-uniceplac fw-bold mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Onde é minha aula prática?</h4>
                    <p class="text-muted mb-0 small">Próximas reservas de laboratório e controle de chaves</p>
                </div>
            </div>

            <div id="grid-proximas-aulas">
                <?php if ($total_proximas > 0): ?>

                    <?php if ($qtd_matutino > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-matutino"><i class="bi bi-sunrise-fill me-2"></i>Turno Matutino</span></div>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                            <?php foreach ($proximas_matutino as $aula): renderizarCardAulaProfessor($aula, $hoje, $chaves_retiradas, 'border-matutino', '<i class="bi bi-sunrise-fill text-warning"></i>'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($qtd_vespertino > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-vespertino"><i class="bi bi-sun-fill me-2" style="color: var(--tarde-cor);"></i>Turno Vespertino</span></div>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                            <?php foreach ($proximas_vespertino as $aula): renderizarCardAulaProfessor($aula, $hoje, $chaves_retiradas, 'border-vespertino', '<i class="bi bi-sun-fill" style="color: var(--tarde-cor);"></i>'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($qtd_noturno > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-noturno"><i class="bi bi-moon-stars-fill me-2" style="color: var(--noite-cor);"></i>Turno Noturno</span></div>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                            <?php foreach ($proximas_noturno as $aula): renderizarCardAulaProfessor($aula, $hoje, $chaves_retiradas, 'border-noturno', '<i class="bi bi-moon-stars-fill" style="color: var(--noite-cor);"></i>'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="apple-ticket text-center p-5 shadow-sm text-muted">
                        <i class="bi bi-calendar-x fs-1 opacity-50 mb-3 d-block"></i> Nenhuma aula prática futura aprovada.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="sessao-ensalamento" class="content-section">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div><h4 class="text-uniceplac fw-bold mb-0"><i class="bi bi-building me-2"></i>Meu Ensalamento Fixo</h4><p class="text-muted mb-0 small">Salas definidas pela coordenação</p></div>
            </div>
            
            <div id="grid-ensalamento">
                <?php if ($total_ensalamentos > 0): ?>

                    <?php if (count($ensalamento_matutino) > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-matutino"><i class="bi bi-sunrise-fill me-2"></i>Turno Matutino</span></div>
                        <div class="row row-cols-1 row-cols-lg-2 g-4 mb-4">
                            <?php foreach ($ensalamento_matutino as $e): renderizarCardEnsalamento($e, 'bg-warning text-dark', 'border-matutino'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($ensalamento_vespertino) > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-vespertino"><i class="bi bi-sun-fill me-2" style="color: var(--tarde-cor);"></i>Turno Vespertino</span></div>
                        <div class="row row-cols-1 row-cols-lg-2 g-4 mb-4">
                            <?php foreach ($ensalamento_vespertino as $e): renderizarCardEnsalamento($e, 'bg-orange text-white', 'border-vespertino'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (count($ensalamento_noturno) > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-noturno"><i class="bi bi-moon-stars-fill me-2" style="color: var(--noite-cor);"></i>Turno Noturno</span></div>
                        <div class="row row-cols-1 row-cols-lg-2 g-4 mb-4">
                            <?php foreach ($ensalamento_noturno as $e): renderizarCardEnsalamento($e, 'bg-primary text-white', 'border-noturno'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="apple-ticket text-center p-5 shadow-sm text-muted"><i class="bi bi-info-circle fs-1 opacity-50 mb-3 d-block"></i> Sem salas fixas definidas.</div>
                <?php endif; ?>
            </div>
        </div>

        <div id="sessao-solicitar" class="content-section">
            <div class="card shadow-sm mb-4 border-0" style="border-top: 4px solid var(--laranja-uniceplac) !important;">
                <div class="card-header bg-white py-3"><h5 class="mb-0 fw-bold text-laranja"><span class="header-icon bg-light text-laranja"><i class="bi bi-calendar-plus fs-4"></i></span>Solicitar Laboratório</h5></div>
                <div class="card-body bg-light p-4">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <form action="painel_professor.php" method="POST" class="bg-white p-4 p-md-5 border shadow-sm" style="border-radius: 20px;">
                                <div class="row mb-4">
                                    <div class="col-md-4 mb-3 mb-md-0"><label class="form-label fw-bold text-secondary">Data:</label><input type="date" class="form-control form-control-lg" name="data_reserva" min="<?= date('Y-m-d') ?>" required></div>
                                    <div class="col-md-4 mb-3 mb-md-0"><label class="form-label fw-bold text-secondary">Turno:</label><select class="form-select form-select-lg" name="turno" required><option value="">Selecione...</option><option>Matutino</option><option>Vespertino</option><option>Noturno</option></select></div>
                                    <div class="col-md-4"><label class="form-label fw-bold text-secondary">Horário:</label><select class="form-select form-select-lg" name="periodo" required><option>1º e 2º Horários</option><option>1º Horário</option><option>2º Horário</option></select></div>
                                </div>
                                <div class="row mb-5">
                                    <div class="col-md-6 mb-3 mb-md-0"><label class="form-label fw-bold text-secondary">Laboratório:</label><select class="form-select form-select-lg" name="id_laboratorio" required><option value="">Selecione...</option><?php foreach($laboratorios as $lab): ?><option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['nome']) ?> (Cap: <?= $lab['capacidade'] ?>)</option><?php endforeach; ?></select></div>
                                    <div class="col-md-6"><label class="form-label fw-bold text-secondary">Disciplina:</label><select class="form-select form-select-lg" name="id_disciplina" required><option value="">Selecione...</option><?php foreach($disciplinas as $disc): ?><option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option><?php endforeach; ?></select></div>
                                </div>
                                <div class="d-flex justify-content-end"><button type="submit" class="btn btn-uniceplac btn-lg px-5 w-100 w-md-auto rounded-pill"><i class="bi bi-send-check me-2"></i>Enviar Solicitação</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="sessao-historico" class="content-section">
            <div class="card shadow-sm border-0 mb-5" style="border-top: 4px solid var(--roxo-uniceplac) !important;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold d-flex align-items-center" style="color: var(--roxo-uniceplac);"><span class="header-icon bg-light" style="color: var(--roxo-uniceplac);"><i class="bi bi-clock-history fs-4"></i></span>Histórico de Solicitações</h5>
                </div>
                <div class="card-body p-0">
                    <div id="tabela-historico-container">
                        <?php if (count($minhas_alocacoes) > 0): ?>
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top"><tr><th class="ps-4 py-3">Data</th><th>Turno/Horário</th><th>Laboratório</th><th>Disciplina</th><th class="pe-4">Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($minhas_alocacoes as $linha): ?>
                                            <tr>
                                                <td class="ps-4"><strong><?= date('d/m/Y', strtotime($linha['data_reserva'])) ?></strong></td>
                                                <td><?= htmlspecialchars($linha['turno']) ?> <br><small class="text-muted"><?= htmlspecialchars($linha['periodo']) ?></small></td>
                                                <td class="fw-bold text-dark"><?= htmlspecialchars($linha['laboratorio']) ?></td>
                                                <td><?= htmlspecialchars($linha['disciplina']) ?></td>
                                                <td class="pe-4">
                                                    <?php 
                                                        if ($linha['status'] == 'aprovado') echo '<span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle me-1"></i>Aprovado</span>';
                                                        elseif ($linha['status'] == 'pendente') echo '<span class="badge bg-warning text-dark rounded-pill px-3"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>';
                                                        else echo '<span class="badge bg-danger rounded-pill px-3"><i class="bi bi-x-circle me-1"></i>Rejeitado</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5"><i class="bi bi-folder2-open fs-1 text-muted opacity-50 d-block mb-2"></i><p class="text-muted mb-0">Nenhum histórico.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let calendarioProfessorGlobal;

        function autoOcultarMensagens() {
            document.querySelectorAll('.alert-autohide').forEach(alerta => {
                setTimeout(() => { alerta.style.transition = "opacity 0.6s ease"; alerta.style.opacity = "0"; setTimeout(() => alerta.remove(), 600); }, 4000);
            });
        }

        function updateThemeElements(theme) {
            const themeIcon = document.getElementById('themeIcon');
            const navbarLogo = document.getElementById('navbarLogo');
            if(theme === 'dark') {
                if(themeIcon) { themeIcon.classList.remove('bi-moon-stars'); themeIcon.classList.add('bi-sun', 'text-warning'); }
                if (navbarLogo) navbarLogo.src = 'uniceplac.png'; 
            } else {
                if(themeIcon) { themeIcon.classList.remove('bi-sun', 'text-warning'); themeIcon.classList.add('bi-moon-stars'); }
                if (navbarLogo) navbarLogo.src = 'uniceplac2.png'; 
            }
        }

        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
            document.querySelectorAll('.offcanvas-menu-link').forEach(link => link.classList.remove('active-link'));
            let targetSection = document.getElementById(sectionId);
            if(targetSection) {
                targetSection.style.display = 'block';
                let activeLink = document.querySelector(`.offcanvas-menu-link[href="#${sectionId}"]`);
                if(activeLink) activeLink.classList.add('active-link');
                window.history.replaceState(null, null, '#' + sectionId);

                if (sectionId === 'sessao-calendario' && typeof calendarioProfessorGlobal !== 'undefined') {
                    setTimeout(() => { calendarioProfessorGlobal.updateSize(); }, 150);
                }
            }
        }

        let qtdPendentesAnterior = <?= $qtd_pendentes ?>;
        const somNotificacao = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3'); 

        function monitorarTempoReal() {
            const modalAberto = document.querySelector('.modal.show'); 
            if (!modalAberto) {
                fetch('painel_professor.php?_t=' + new Date().getTime(), { cache: "no-store" })
                    .then(res => res.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');

                        const novaGrid = doc.getElementById('grid-proximas-aulas');
                        const gridAtual = document.getElementById('grid-proximas-aulas');
                        if (novaGrid && gridAtual && gridAtual.innerHTML.trim() !== novaGrid.innerHTML.trim()) {
                            gridAtual.innerHTML = novaGrid.innerHTML;
                        }

                        const novoEnsalamento = doc.getElementById('grid-ensalamento');
                        const ensalamentoAtual = document.getElementById('grid-ensalamento');
                        if (novoEnsalamento && ensalamentoAtual && ensalamentoAtual.innerHTML.trim() !== novoEnsalamento.innerHTML.trim()) {
                            ensalamentoAtual.innerHTML = novoEnsalamento.innerHTML;
                        }

                        const novaTabela = doc.getElementById('tabela-historico-container');
                        const tabelaAtual = document.getElementById('tabela-historico-container');
                        if (novaTabela && tabelaAtual && tabelaAtual.innerHTML.trim() !== novaTabela.innerHTML.trim()) {
                            tabelaAtual.innerHTML = novaTabela.innerHTML;
                        }

                        const novoBadgeNav = doc.getElementById('badge-nav-bell');
                        const badgeNavAtual = document.getElementById('badge-nav-bell');
                        if(novoBadgeNav && badgeNavAtual) badgeNavAtual.outerHTML = novoBadgeNav.outerHTML;

                        const novoBadgeMenu = doc.getElementById('badge-menu-lateral');
                        const badgeMenuAtual = document.getElementById('badge-menu-lateral');
                        if(novoBadgeMenu && badgeMenuAtual) badgeMenuAtual.outerHTML = novoBadgeMenu.outerHTML;
                    })
                    .catch(err => console.log("Aguardando rede..."));
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            autoOcultarMensagens();
            updateThemeElements(savedTheme);

            let hashURL = window.location.hash.replace('#', '');
            let phpAbaAtiva = "<?= $aba_ativa ?>"; 
            let abaInicial = hashURL ? hashURL : phpAbaAtiva;
            if(document.getElementById(abaInicial)) showSection(abaInicial);
            else showSection('sessao-calendario'); 

            var calendarEl = document.getElementById('calendarioProfessor');
            if (calendarEl) {
                calendarioProfessorGlobal = new FullCalendar.Calendar(calendarEl, {
                    locale: 'pt-br',
                    initialView: 'dayGridMonth', 
                    navLinks: true,              
                    nowIndicator: true,          
                    dayMaxEvents: 3,             
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' 
                    },
                    buttonText: {
                        today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia', list: 'Lista' 
                    },
                    events: <?= $eventos_json ?>,
                    slotMinTime: '08:00:00', 
                    slotMaxTime: '23:30:00', 
                    allDaySlot: false,
                    expandRows: true,
                    eventContent: function(arg) {
                        let content = document.createElement('div');
                        
                        let timeStr = arg.timeText ? `<div style="font-size: 0.7rem; font-weight: bold; opacity: 0.75; margin-bottom: 2px;">${arg.timeText}</div>` : '';
                        
                        let titleEl = document.createElement('div');
                        titleEl.innerHTML = timeStr + '<div style="font-size: 0.8rem; font-weight: 700; line-height: 1.1;">' + arg.event.title + '</div>';
                        content.appendChild(titleEl);
                        
                        if (arg.view.type !== 'dayGridMonth') {
                            let localEl = document.createElement('div');
                            localEl.innerHTML = arg.event.extendedProps.local;
                            localEl.style.fontSize = '0.75rem';
                            localEl.style.marginTop = '4px';
                            localEl.style.lineHeight = '1.2';
                            content.appendChild(localEl);
                        }
                        
                        return { domNodes: [content] };
                    }
                });
                calendarioProfessorGlobal.render();
            }

            document.getElementById('themeToggleBtn').addEventListener('click', function() {
                let newTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('tema-uniceplac', newTheme);
                updateThemeElements(newTheme); 
            });

            document.querySelectorAll('.offcanvas-menu-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    let href = this.getAttribute('href');
                    if (href.startsWith('#')) {
                        e.preventDefault();
                        showSection(href.replace('#', ''));
                        
                        let btnClose = document.querySelector('#sidebarMenu .btn-close');
                        if (btnClose) {
                            btnClose.click();
                        }
                    }
                });
            });

            document.getElementById('navBell').addEventListener('click', () => showSection('sessao-historico'));

            const btnAlterarFoto = document.getElementById('btnAlterarFotoNav');
            const inputFoto = document.getElementById('nova_foto_input');
            if(btnAlterarFoto && inputFoto) {
                btnAlterarFoto.addEventListener('click', () => inputFoto.click());
                inputFoto.addEventListener('change', function() { if (this.value) document.getElementById('formFotoPerfil').submit(); });
            }

            setInterval(monitorarTempoReal, 5000);
        });
    </script>
</body>
</html>