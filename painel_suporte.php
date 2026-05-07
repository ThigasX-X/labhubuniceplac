<?php
session_start();

if (!isset($_SESSION['usuario_id']) || ($_SESSION['perfil'] !== 'suporte' && $_SESSION['perfil'] !== 'coordenador')) {
    header("Location: index.php");
    exit;
}

require 'conexao.php';
require 'Agendamento.php';

$agendamento = new Agendamento($pdo);
$mensagem = '';
$id_usuario_logado = $_SESSION['usuario_id'];
date_default_timezone_set('America/Sao_Paulo'); 

// --- LÓGICA: DAR BAIXA NA CHAVE (COM HORA REAL) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['dar_baixa_chave'])) {
    try {
        $stmt = $pdo->prepare("UPDATE controle_chaves SET status = 'devolvido', funcionario_recebimento = :func, hora_devolucao_real = :hora_real WHERE id = :id");
        $stmt->execute([
            ':func' => $_POST['func_recebe'],
            ':hora_real' => $_POST['hora_devolucao_real'],
            ':id' => $_POST['id_chave']
        ]);
        $mensagem = '<div class="alert alert-primary alert-autohide rounded-0 border-0 border-start border-4 border-primary shadow-sm mb-4"><i class="bi bi-check-circle-fill me-2"></i>Chave recebida e baixada com sucesso!</div>';
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger alert-autohide rounded-0 mb-4">Erro ao dar baixa: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// --- LÓGICA DE UPLOAD DE FOTO ---
if (isset($_FILES['nova_foto']) && $_FILES['nova_foto']['error'] === UPLOAD_ERR_OK) {
    $extensao = strtolower(pathinfo($_FILES['nova_foto']['name'], PATHINFO_EXTENSION));
    $formatos_permitidos = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($extensao, $formatos_permitidos)) {
        $diretorio = 'uploads/';
        if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);
        $novo_nome = 'user_' . $id_usuario_logado . '_' . time() . '.' . $extensao;
        $destino = $diretorio . $novo_nome;
        if (move_uploaded_file($_FILES['nova_foto']['tmp_name'], $destino)) {
            try {
                if (!empty($_SESSION['foto_perfil']) && file_exists($_SESSION['foto_perfil'])) unlink($_SESSION['foto_perfil']);
                $stmt = $pdo->prepare("UPDATE usuarios SET foto_perfil = :foto WHERE id = :id");
                $stmt->execute([':foto' => $destino, ':id' => $id_usuario_logado]);
                $_SESSION['foto_perfil'] = $destino;
                $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-0 border-start border-4 border-success shadow-sm mb-4">Foto atualizada com sucesso!</div>';
            } catch (PDOException $e) { $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao salvar foto.</div>'; }
        }
    }
}

// --- RESOLVER SOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resolver_chamado'])) {
    try {
        $stmt = $pdo->prepare("UPDATE chamados_suporte SET status = 'resolvido' WHERE id = :id");
        $stmt->execute([':id' => $_POST['id_chamado']]);
        $mensagem = '<div class="alert alert-success alert-autohide rounded-0 border-0 border-start border-4 border-success shadow-sm mb-4">Chamado encerrado!</div>';
    } catch (PDOException $e) { 
        $mensagem = '<div class="alert alert-danger alert-autohide mb-4">Erro ao encerrar chamado.</div>'; 
    }
}

if (!isset($_SESSION['foto_perfil'])) {
    $stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $id_usuario_logado]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['foto_perfil'] = $user_data['foto_perfil'] ?? null;
}
$foto_atual = !empty($_SESSION['foto_perfil']) && file_exists($_SESSION['foto_perfil']) ? $_SESSION['foto_perfil'] : 'uploads/padrao-usuario.png';

// --- BUSCAS DE DADOS ---
$alertas_suporte = [];
try {
    $stmt_alertas = $pdo->query("SELECT * FROM chamados_suporte WHERE status = 'pendente' ORDER BY data_hora DESC");
    $alertas_suporte = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
$qtd_alertas = count($alertas_suporte);

$historico_chamados = [];
try {
    $stmt_hist_cham = $pdo->query("SELECT * FROM chamados_suporte WHERE status = 'resolvido' ORDER BY data_hora DESC");
    $historico_chamados = $stmt_hist_cham->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$chaves_em_uso_assoc = [];
try {
    $stmt_chaves = $pdo->query("SELECT * FROM controle_chaves WHERE status = 'em_uso'");
    $chaves_em_uso = $stmt_chaves->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chaves_em_uso as $chave) {
        $chaves_em_uso_assoc[$chave['id_agendamento']] = $chave; 
    }
} catch (PDOException $e) {}

$historico_chaves = [];
try {
    $stmt_hist_chaves = $pdo->query("SELECT * FROM controle_chaves ORDER BY data_uso DESC, hora_retirada DESC");
    $historico_chaves = $stmt_hist_chaves->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$lista_laboratorios = $pdo->query("SELECT * FROM laboratorios ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$data_filtro = isset($_GET['data_busca']) ? $_GET['data_busca'] : date('Y-m-d');

// --- 1. BUSCA DE AGENDAMENTOS AVULSOS ---
$stmt_aloc = $pdo->prepare("SELECT a.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina, a.turno, a.periodo 
        FROM agendamentos a
        INNER JOIN laboratorios l ON a.id_laboratorio = l.id
        INNER JOIN usuarios u ON a.id_professor = u.id
        INNER JOIN disciplinas d ON a.id_disciplina = d.id
        WHERE a.data_reserva = :data AND a.status = 'aprovado'
        ORDER BY FIELD(a.turno, 'Matutino', 'Vespertino', 'Noturno'), l.nome ASC");
$stmt_aloc->execute([':data' => $data_filtro]);
$alocacoes = $stmt_aloc->fetchAll(PDO::FETCH_ASSOC);

// --- 2. INTEGRAÇÃO COM QUADRO DE HORÁRIOS (NOVO) ---
$id_quadro_ativo = $pdo->query("SELECT id FROM quadros_horarios ORDER BY id DESC LIMIT 1")->fetchColumn();
$dias_map = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
$dia_semana_filtro = $dias_map[date('w', strtotime($data_filtro))];

if ($id_quadro_ativo) {
    $stmt_qa = $pdo->prepare("SELECT qa.id, l.nome as laboratorio, u.nome as professor, d.nome as disciplina, qa.turno, qa.horario as periodo 
        FROM quadro_aulas qa 
        INNER JOIN laboratorios l ON qa.id_laboratorio = l.id 
        INNER JOIN usuarios u ON qa.id_professor = u.id 
        INNER JOIN disciplinas d ON qa.id_disciplina = d.id
        WHERE qa.id_quadro = ? AND qa.dia_semana = ? AND qa.id_laboratorio IS NOT NULL");
    $stmt_qa->execute([$id_quadro_ativo, $dia_semana_filtro]);
    $aulas_fixas = $stmt_qa->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($aulas_fixas as $af) {
        $af['id'] = $af['id'] + 1000000; // Soma 1 milhão no ID para evitar conflito de chave com os avulsos
        $alocacoes[] = $af;
    }
}

// --- LÓGICA DE AGRUPAMENTO E CONTADORES ---
$total_reservas = count($alocacoes);
$alocacoes_matutino = [];
$alocacoes_vespertino = [];
$alocacoes_noturno = [];

foreach ($alocacoes as $linha) {
    $turno_limpo = trim($linha['turno']);
    if ($turno_limpo === 'Matutino' || $turno_limpo === 'Manhã') {
        $alocacoes_matutino[] = $linha;
    } elseif ($turno_limpo === 'Vespertino' || $turno_limpo === 'Tarde') {
        $alocacoes_vespertino[] = $linha;
    } elseif ($turno_limpo === 'Noturno' || $turno_limpo === 'Noite') {
        $alocacoes_noturno[] = $linha;
    }
}

$qtd_matutino = count($alocacoes_matutino);
$qtd_vespertino = count($alocacoes_vespertino);
$qtd_noturno = count($alocacoes_noturno);

// --- FUNÇÃO QUE RENDERIZA O CARD ---
function renderizarCardSuporte($l, $chaves_em_uso_assoc, $borda) {
    $nome_lab = $l['laboratorio'];
    $id_agendamento = $l['id']; 
    
    $chave_ativa = isset($chaves_em_uso_assoc[$id_agendamento]) ? $chaves_em_uso_assoc[$id_agendamento] : null;
    $modal_id = $id_agendamento . '_' . uniqid();
    
    $esta_atrasado = false;
    $link_wpp = "#";
    
    if($chave_ativa) {
        $hora_atual = date('H:i:s');
        $esta_atrasado = ($hora_atual > $chave_ativa['hora_devolucao_prevista']);
        
        $numero_limpo = preg_replace('/[^0-9]/', '', $chave_ativa['celular']);
        if(strlen($numero_limpo) == 10 || strlen($numero_limpo) == 11) { $numero_limpo = "55" . $numero_limpo; }
        $msg_wpp = urlencode("Olá, Prof. {$chave_ativa['professor_nome']}. Aqui é do Suporte TI da UNICEPLAC. Notamos que a chave do {$nome_lab} ainda consta com você. Já finalizou a aula?");
        $link_wpp = "https://wa.me/{$numero_limpo}?text={$msg_wpp}";
    }
    ?>
    <div class="col">
        <div class="apple-ticket card h-100 <?= $borda ?> <?= $esta_atrasado ? 'border border-danger border-2' : '' ?> p-3 position-relative">
            <div class="d-flex justify-content-between align-items-start">
                <h6 class="fw-bold <?= $esta_atrasado ? 'text-danger' : 'text-dark' ?> mb-2 text-truncate"><?= htmlspecialchars($l['laboratorio']) ?></h6>
                <?php if($esta_atrasado): ?>
                    <i class="bi bi-exclamation-triangle-fill text-danger heartbeat fs-5" title="Atrasado!"></i>
                <?php endif; ?>
            </div>
            
            <div class="small mb-1 text-primary fw-bold"><i class="bi bi-clock-history me-2"></i><?= htmlspecialchars($l['periodo']) ?></div>
            <div class="small mb-1"><i class="bi bi-person me-2 text-muted"></i><?= htmlspecialchars($l['professor']) ?></div>
            <div class="small text-secondary <?= $chave_ativa ? 'mb-3' : '' ?>"><i class="bi bi-book me-2 text-muted"></i><?= htmlspecialchars($l['disciplina']) ?></div>
            
            <?php if ($chave_ativa): ?>
                <hr class="my-2 opacity-25">
                <?php if($esta_atrasado): ?>
                    <div class="apple-tag late"><div class="apple-dot late"></div> ATRASADO</div>
                    <button class="apple-btn apple-btn-danger heartbeat mt-2" data-bs-toggle="modal" data-bs-target="#modalDetalheChave<?= $modal_id ?>"><i class="bi bi-key-fill me-2"></i> Cobrar Chave</button>
                <?php else: ?>
                    <div class="apple-tag in-use"><div class="apple-dot in-use"></div> EM USO</div>
                    <button class="apple-btn apple-btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalDetalheChave<?= $modal_id ?>"><i class="bi bi-key-fill me-2"></i> Receber Chave</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($chave_ativa): ?>
        <div class="modal fade" id="modalDetalheChave<?= $modal_id ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content <?= $esta_atrasado ? 'border-danger' : 'border-primary' ?>" style="border-width: 3px; border-radius: 20px;">
                    <div class="modal-header <?= $esta_atrasado ? 'bg-danger' : 'bg-primary' ?> text-white border-0" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                        <h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i> Detalhes da Retirada</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start p-4">
                        <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><?= htmlspecialchars($nome_lab) ?></h5>
                        <div class="mb-3">
                            <p class="mb-1 text-secondary small"><strong>Professor:</strong> <span class="text-dark"><?= htmlspecialchars($chave_ativa['professor_nome']) ?></span></p>
                            <p class="mb-1 text-secondary small"><strong>Entregue por:</strong> <span class="text-dark"><?= htmlspecialchars($chave_ativa['funcionario_entrega']) ?></span></p>
                            <p class="mb-1 text-secondary small"><strong>Hora da Retirada:</strong> <span class="text-dark"><?= date('H:i', strtotime($chave_ativa['hora_retirada'])) ?></span></p>
                        </div>
                        <div class="alert <?= $esta_atrasado ? 'alert-danger shadow-sm' : 'alert-warning' ?> py-3 mb-3 d-flex justify-content-between align-items-center" style="border-radius: 15px;">
                            <div><strong class="small opacity-75">PREVISÃO DE VOLTA</strong> <br><span class="fs-4 fw-bold"><?= date('H:i', strtotime($chave_ativa['hora_devolucao_prevista'])) ?></span></div>
                            <?php if($esta_atrasado): ?><span class="badge bg-danger fs-6 heartbeat py-2 px-3 rounded-pill"><i class="bi bi-exclamation-triangle-fill me-1"></i> ATRASADO</span><?php endif; ?>
                        </div>
                        
                        <a href="<?= $link_wpp ?>" target="_blank" class="apple-btn" style="background: rgba(25, 135, 84, 0.1); border: 1px solid rgba(25, 135, 84, 0.2); color: #198754; margin-bottom: 1.5rem;"><i class="bi bi-whatsapp fs-5 me-2"></i> Mandar WhatsApp</a>
                        
                        <form method="POST" action="painel_suporte.php" class="bg-light p-3 border rounded-4">
                            <input type="hidden" name="dar_baixa_chave" value="1">
                            <input type="hidden" name="id_chave" value="<?= $chave_ativa['id'] ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary">Hora da Devolução (Real):</label>
                                <input type="time" class="form-control rounded-pill px-3" name="hora_devolucao_real" value="<?= date('H:i') ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary">Recebido por (Seu Nome):</label>
                                <input type="text" class="form-control rounded-pill px-3" name="func_recebe" placeholder="Ex: Técnico João" required>
                            </div>
                            <button type="submit" class="btn <?= $esta_atrasado ? 'btn-danger' : 'btn-primary' ?> w-100 fw-bold py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i> Confirmar Devolução</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Suporte TI - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
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
        .card, .card-header, .form-control, .form-select, .btn, .badge, .alert, .offcanvas { border-radius: 0 !important; }
        .bg-uniceplac { background-color: var(--verde-uniceplac) !important; }
        .navbar { border-bottom: 1px solid rgba(0,0,0,0.05) !important; background: rgba(255, 255, 255, 0.9) !important; backdrop-filter: blur(10px); }
        
        .content-section { display: none; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .offcanvas-menu-link { padding: 12px 20px; color: #495057; text-decoration: none; display: block; border-bottom: 1px solid #f1f1f1; font-weight: 500; transition: background-color 0.2s; }
        .offcanvas-menu-link:hover, .offcanvas-menu-link.active-link { background-color: rgba(0, 115, 79, 0.05); color: var(--verde-uniceplac); border-right: 4px solid var(--verde-uniceplac); }
        
        .avatar-img-small { width: 40px; height: 40px; object-fit: cover; border-radius: 50% !important; border: 2px solid #dee2e6; cursor: pointer; }
        .top-icon-btn { color: #495057; font-size: 1.3rem; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; transition: 0.2s;}
        .top-icon-btn:hover { color: var(--verde-uniceplac); }
        
        @keyframes sos-pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
        .sos-active .bi-bell { color: #dc3545 !important; animation: sos-pulse 0.8s infinite; }
        
        @keyframes heartbeat { 0% { transform: scale(1); } 20% { transform: scale(1.05); } 40% { transform: scale(1); } 60% { transform: scale(1.05); } 80% { transform: scale(1); } 100% { transform: scale(1); } }
        .heartbeat { animation: heartbeat 1.5s infinite; }

        /* =========================================
           ESTILOS APPLE GLASSMORPHISM
           ========================================= */
        .apple-kpi { border-radius: 24px !important; padding: 24px 20px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.4) !important; box-shadow: 0 8px 32px rgba(0,0,0,0.06); position: relative; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .apple-kpi:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.1); }
        
        .kpi-total { background: linear-gradient(135deg, rgba(0, 115, 79, 0.05), rgba(0, 115, 79, 0.15)); border-color: rgba(0, 115, 79, 0.2) !important; }
        .kpi-matutino { background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 193, 7, 0.15)); border-color: rgba(255, 193, 7, 0.2) !important; }
        .kpi-vespertino { background: linear-gradient(135deg, rgba(253, 126, 20, 0.05), rgba(253, 126, 20, 0.15)); border-color: rgba(253, 126, 20, 0.2) !important; }
        .kpi-noturno { background: linear-gradient(135deg, rgba(66, 27, 113, 0.05), rgba(66, 27, 113, 0.15)); border-color: rgba(66, 27, 113, 0.2) !important; }

        .apple-ticket { border-radius: 20px !important; border: 1px solid rgba(0,0,0,0.08) !important; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.03); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .apple-ticket:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); transform: translateY(-3px); }
        
        .border-matutino { border-left: 6px solid var(--manha-cor) !important; }
        .border-vespertino { border-left: 6px solid var(--tarde-cor) !important; }
        .border-noturno { border-left: 6px solid var(--noite-cor) !important; }

        .apple-tag { display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border-radius: 30px; font-size: 0.70rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; backdrop-filter: blur(8px); width: 100%; margin-bottom: 8px; }
        .apple-tag.in-use { background: rgba(25, 135, 84, 0.1); border: 1px solid rgba(25, 135, 84, 0.2); color: #198754; }
        .apple-tag.late { background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.2); color: #dc3545; }
        
        .apple-dot { width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; }
        .apple-dot.in-use { background-color: #198754; box-shadow: 0 0 6px #198754; animation: pulse-dot-green 1.5s infinite; }
        .apple-dot.late { background-color: #dc3545; box-shadow: 0 0 6px #dc3545; animation: pulse-dot-red 1.5s infinite; }
        
        @keyframes pulse-dot-green { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(25, 135, 84, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); } }
        @keyframes pulse-dot-red { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }

        .apple-btn { display: flex; align-items: center; justify-content: center; width: 100%; padding: 8px 16px; border-radius: 30px; font-size: 0.85rem; font-weight: 600; backdrop-filter: blur(8px); transition: all 0.2s ease; cursor: pointer; text-decoration: none; border: none; }
        .apple-btn-primary { background: rgba(13, 110, 253, 0.1); border: 1px solid rgba(13, 110, 253, 0.2); color: #0d6efd; }
        .apple-btn-primary:hover { background: rgba(13, 110, 253, 0.2); transform: translateY(-1px); }
        .apple-btn-danger { background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.2); color: #dc3545; }
        .apple-btn-danger:hover { background: rgba(220, 53, 69, 0.2); transform: translateY(-1px); }

        /* Divisórias de Turno */
        .turn-divider { display: flex; align-items: center; margin: 2rem 0 1rem; color: #6c757d; }
        .turn-divider::before, .turn-divider::after { content: ""; flex: 1; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .turn-divider:not(:empty)::before { margin-right: 1em; }
        .turn-divider:not(:empty)::after { margin-left: 1em; }
        .turn-badge { display: inline-flex; align-items: center; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; backdrop-filter: blur(10px); }
        .badge-matutino { background: rgba(255, 193, 7, 0.15); color: #d39e00; border: 1px solid rgba(255, 193, 7, 0.3); }
        .badge-vespertino { background: rgba(253, 126, 20, 0.15); color: #e85e00; border: 1px solid rgba(253, 126, 20, 0.3); }
        .badge-noturno { background: rgba(66, 27, 113, 0.15); color: #421B71; border: 1px solid rgba(66, 27, 113, 0.3); }

        #nova_foto_input { display: none; }

        /* =========================================
           MODO ESCURO (DARK MODE APPLE)
           ========================================= */
        [data-bs-theme="dark"] body { background-color: #121212; color: #e0e0e0; }
        [data-bs-theme="dark"] .bg-white, [data-bs-theme="dark"] .bg-light { background-color: #1e1e1e !important; color: #e0e0e0 !important; }
        [data-bs-theme="dark"] .navbar { background: rgba(30, 30, 30, 0.9) !important; border-bottom-color: #333 !important; }
        [data-bs-theme="dark"] .card { background-color: #1e1e1e; border-color: #333 !important; }
        
        [data-bs-theme="dark"] .apple-kpi { background: rgba(40,40,40,0.6); border-color: rgba(255,255,255,0.1) !important; }
        [data-bs-theme="dark"] .kpi-total { background: linear-gradient(135deg, rgba(0, 115, 79, 0.15), rgba(0, 115, 79, 0.3)); color: #28c76f; border-color: rgba(40,199,111,0.3) !important; }
        [data-bs-theme="dark"] .kpi-matutino { background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 193, 7, 0.3)); color: #ffc107; border-color: rgba(255,193,7,0.3) !important; }
        [data-bs-theme="dark"] .kpi-vespertino { background: linear-gradient(135deg, rgba(253, 126, 20, 0.15), rgba(253, 126, 20, 0.3)); color: #fd7e14; border-color: rgba(253,126,20,0.3) !important; }
        [data-bs-theme="dark"] .kpi-noturno { background: linear-gradient(135deg, rgba(162, 106, 226, 0.15), rgba(162, 106, 226, 0.3)); color: #a26ae2; border-color: rgba(162,106,226,0.3) !important; }

        [data-bs-theme="dark"] .apple-ticket { background: rgba(35,35,35,0.7); border-color: rgba(255,255,255,0.08) !important; }
        [data-bs-theme="dark"] .text-dark { color: #f8f9fa !important; }
        [data-bs-theme="dark"] .text-secondary, [data-bs-theme="dark"] .text-muted { color: #adb5bd !important; }
        [data-bs-theme="dark"] .table { color: #e0e0e0; border-color: #444; }
        [data-bs-theme="dark"] .table-light th { background-color: #2a2a2a !important; color: #e0e0e0; border-color: #444; }
        [data-bs-theme="dark"] .table-hover tbody tr:hover { background-color: #2a2a2a !important; color: #fff; }
        [data-bs-theme="dark"] .offcanvas { background-color: #1e1e1e !important; }
        [data-bs-theme="dark"] .offcanvas-menu-link { color: #e0e0e0; border-bottom-color: #333; }
        [data-bs-theme="dark"] .offcanvas-menu-link:hover { background-color: rgba(255,255,255,0.05); }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select { background-color: #2a2a2a; color: #fff; border-color: #444; }
        [data-bs-theme="dark"] .form-control:focus, [data-bs-theme="dark"] .form-select:focus { background-color: #333; color: #fff; border-color: var(--verde-uniceplac); }
        [data-bs-theme="dark"] .modal-content { background-color: #1e1e1e; border-color: #444; border-radius: 20px !important; }
        [data-bs-theme="dark"] .top-icon-btn { color: #e0e0e0; }
        [data-bs-theme="dark"] .top-icon-btn:hover { color: var(--laranja-uniceplac); }
        
        [data-bs-theme="dark"] .apple-tag.in-use { background: rgba(40, 199, 111, 0.15); border-color: rgba(40, 199, 111, 0.3); color: #28c76f; }
        [data-bs-theme="dark"] .apple-dot.in-use { background-color: #28c76f; box-shadow: 0 0 6px #28c76f; }
        [data-bs-theme="dark"] .apple-tag.late { background: rgba(234, 84, 85, 0.15); border-color: rgba(234, 84, 85, 0.3); color: #ea5455; }
        [data-bs-theme="dark"] .apple-dot.late { background-color: #ea5455; box-shadow: 0 0 6px #ea5455; }
        
        [data-bs-theme="dark"] .apple-btn-primary { background: rgba(13, 110, 253, 0.15); border-color: rgba(13, 110, 253, 0.3); color: #6ea8fe; }
        [data-bs-theme="dark"] .apple-btn-danger { background: rgba(234, 84, 85, 0.12); border-color: rgba(234, 84, 85, 0.3); color: #ea5455; }
        
        [data-bs-theme="dark"] .turn-divider::before, [data-bs-theme="dark"] .turn-divider::after { border-bottom-color: rgba(255,255,255,0.1); }
        [data-bs-theme="dark"] .badge-matutino { background: rgba(255, 193, 7, 0.15); color: #ffc107; border-color: rgba(255, 193, 7, 0.3); }
        [data-bs-theme="dark"] .badge-vespertino { background: rgba(253, 126, 20, 0.15); color: #fd7e14; border-color: rgba(253, 126, 20, 0.3); }
        [data-bs-theme="dark"] .badge-noturno { background: rgba(162, 106, 226, 0.15); color: #a26ae2; border-color: rgba(162, 106, 226, 0.3); }
    </style>
</head>
<body>

    <form id="formFotoPerfil" action="painel_suporte.php" method="POST" enctype="multipart/form-data" class="d-none">
        <input type="file" name="nova_foto" id="nova_foto_input" accept="image/png, image/jpeg, image/webp">
    </form>

    <nav class="navbar navbar-light bg-white mb-4 shadow-sm sticky-top">
        <div class="container-fluid px-3 px-md-4">
            <span class="navbar-brand d-flex align-items-center">
                <img src="uniceplac.png" id="navbarLogo" alt="Logo" style="height: 70px; margin-right: 12px; transition: 0.3s;">
            </span>
            <div class="ms-auto d-flex align-items-center">
                <div class="me-4 top-icon-btn" id="themeToggleBtn" title="Alternar Tema"><i class="bi bi-moon-stars" id="themeIcon"></i></div>
                <div class="position-relative me-4 top-icon-btn" id="navBell" title="SOS">
                    <i class="bi bi-bell"></i>
                    <span id="badge-sos" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light text-white d-none">0</span>
                </div>
                <div class="me-3 top-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu"><i class="bi bi-grid-3x3-gap fs-5"></i></div>
                <img src="<?= htmlspecialchars($foto_atual) ?>" alt="Foto" class="avatar-img-small ms-1" id="btnAlterarFotoNav">
            </div>
        </div>
    </nav>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="sidebarMenu">
        <div class="offcanvas-header bg-uniceplac text-white py-3 border-0">
            <h6 class="offcanvas-title fw-bold">Menu do Suporte</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column bg-white">
            <div class="p-4 text-center border-bottom bg-light">
                <img src="<?= htmlspecialchars($foto_atual) ?>" alt="Foto" style="width: 80px; height: 80px; object-fit: cover; border-radius: 50% !important; border: 3px solid var(--laranja-uniceplac);" class="shadow-sm mb-2">
                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($_SESSION['nome']) ?></h5>
                <span class="badge bg-uniceplac text-uppercase mt-2 px-3 py-1">Suporte TI</span>
            </div>
            <div class="flex-grow-1 overflow-auto">
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50">Operacional</div>
                <a href="#sessao-mapa-diario" class="offcanvas-menu-link active-link"><i class="bi bi-speedometer2 text-primary me-2"></i> Mapa Diário</a>
                <a href="#sessao-labs" class="offcanvas-menu-link"><i class="bi bi-pc-display text-secondary me-2"></i> Laboratórios</a>
                
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50 border-top mt-2">Relatórios Gerenciais</div>
                <a href="#sessao-historico-chaves" class="offcanvas-menu-link"><i class="bi bi-key text-info me-2"></i> Histórico de Chaves</a>
                <a href="#sessao-historico-chamados" class="offcanvas-menu-link"><i class="bi bi-headset text-success me-2"></i> Chamados Atendidos</a>
                
                <?php if($_SESSION['perfil'] === 'coordenador'): ?>
                    <hr class="my-2 mx-3 border-secondary opacity-25">
                    <div class="p-3 text-muted small fw-bold text-uppercase opacity-50">Administração</div>
                    <a href="painel_coordenador.php" class="offcanvas-menu-link"><i class="bi bi-arrow-left-circle text-warning me-2"></i> Voltar Coordenação</a>
                <?php endif; ?>
            </div>
            <div class="p-3 border-top mt-auto"><a href="logout.php" class="btn btn-outline-danger w-100 fw-bold">Sair</a></div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">
        
        <div id="container-mensagens">
            <?= $mensagem ?>
        </div>

        <div id="area-chamados-dinamica"></div>
        
        <div id="sessao-mapa-diario" class="content-section">
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="apple-kpi kpi-total">
                        <span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Total Hoje</span>
                        <h2 class="fw-bold m-0"><?= $total_reservas ?></h2>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="apple-kpi kpi-matutino">
                        <span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Matutino</span>
                        <h2 class="fw-bold m-0"><?= $qtd_matutino ?></h2>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="apple-kpi kpi-vespertino">
                        <span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Vespertino</span>
                        <h2 class="fw-bold m-0"><?= $qtd_vespertino ?></h2>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="apple-kpi kpi-noturno">
                        <span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Noturno</span>
                        <h2 class="fw-bold m-0"><?= $qtd_noturno ?></h2>
                    </div>
                </div>
            </div>

            <div class="row align-items-end mb-4 g-3">
                <div class="col-md-6"><h4 class="fw-bold text-uniceplac mb-1">Mapa de Ocupação</h4><p class="text-muted small mb-0">Grade do dia: <?= date('d/m/Y', strtotime($data_filtro)) ?></p></div>
                <div class="col-md-6 text-md-end">
                    <form action="painel_suporte.php" method="GET" class="d-inline-block shadow-sm" style="border-radius: 20px; overflow: hidden;">
                        <div class="input-group input-group-sm"><span class="input-group-text bg-white border-end-0 text-muted">Data</span><input type="date" class="form-control border-start-0 text-secondary" name="data_busca" value="<?= htmlspecialchars($data_filtro) ?>" onchange="this.form.submit()"></div>
                    </form>
                </div>
            </div>

            <div id="grid-mapa-diario">
                <?php if ($total_reservas > 0): ?>

                    <?php if ($qtd_matutino > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-matutino"><i class="bi bi-sunrise-fill me-2"></i>Turno Matutino</span></div>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                            <?php foreach ($alocacoes_matutino as $l): renderizarCardSuporte($l, $chaves_em_uso_assoc, 'border-matutino'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($qtd_vespertino > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-vespertino"><i class="bi bi-sun-fill me-2"></i>Turno Vespertino</span></div>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                            <?php foreach ($alocacoes_vespertino as $l): renderizarCardSuporte($l, $chaves_em_uso_assoc, 'border-vespertino'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($qtd_noturno > 0): ?>
                        <div class="turn-divider"><span class="turn-badge badge-noturno"><i class="bi bi-moon-stars-fill me-2"></i>Turno Noturno</span></div>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                            <?php foreach ($alocacoes_noturno as $l): renderizarCardSuporte($l, $chaves_em_uso_assoc, 'border-noturno'); endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="apple-ticket text-center p-5 shadow-sm text-muted">
                        <i class="bi bi-calendar-x fs-1 opacity-50 mb-3 d-block"></i> Nenhuma reserva para a data selecionada.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="sessao-labs" class="content-section">
            <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid var(--roxo-uniceplac);">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold d-flex align-items-center text-dark"><i class="bi bi-pc-display text-secondary me-3 fs-4"></i> Relação de Laboratórios</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top"><tr><th class="ps-4 py-3">ID</th><th>Nome / Identificação</th><th>Capacidade</th></tr></thead>
                            <tbody>
                                <?php if (count($lista_laboratorios) > 0): ?>
                                    <?php foreach ($lista_laboratorios as $lab): ?>
                                        <tr>
                                            <td class="ps-4 text-muted">#<?= $lab['id'] ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($lab['nome']) ?></td>
                                            <td class="text-secondary"><i class="bi bi-people-fill me-2"></i><?= $lab['capacidade'] ?> lugares</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted">Nenhum laboratório cadastrado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="sessao-historico-chaves" class="content-section">
            <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid #0dcaf0;">
                <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <h5 class="mb-3 mb-md-0 fw-bold d-flex align-items-center text-dark"><i class="bi bi-key text-info me-3 fs-4"></i> Histórico / Log de Chaves</h5>
                    <button class="btn btn-outline-success fw-bold shadow-sm rounded-pill px-4" onclick="exportarTabelaParaCSV('tabela-historico-chaves', 'Relatorio_Chaves_Uniceplac.csv')">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Baixar Planilha (CSV)
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table id="tabela-historico-chaves" class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4 py-3">Data</th>
                                    <th>Laboratório</th>
                                    <th>Professor</th>
                                    <th>Hr. Retirada</th>
                                    <th>Hr. Prevista</th>
                                    <th>Hr. Real (Devolução)</th>
                                    <th>Téc. Entrega</th>
                                    <th>Téc. Recebeu</th>
                                    <th class="pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($historico_chaves) > 0): ?>
                                    <?php foreach ($historico_chaves as $log): ?>
                                        <tr>
                                            <td class="ps-4"><strong><?= date('d/m/Y', strtotime($log['data_uso'])) ?></strong></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($log['laboratorio']) ?></td>
                                            <td><?= htmlspecialchars($log['professor_nome']) ?> <br><small class="text-muted"><?= htmlspecialchars($log['celular']) ?></small></td>
                                            <td class="text-primary fw-bold"><?= date('H:i', strtotime($log['hora_retirada'])) ?></td>
                                            <td class="text-secondary"><?= date('H:i', strtotime($log['hora_devolucao_prevista'])) ?></td>
                                            <td class="text-primary fw-bold">
                                                <?= ($log['status'] == 'devolvido' && !empty($log['hora_devolucao_real'])) ? date('H:i', strtotime($log['hora_devolucao_real'])) : '-' ?>
                                            </td>
                                            <td><small><?= htmlspecialchars($log['funcionario_entrega']) ?></small></td>
                                            <td><small><?= $log['funcionario_recebimento'] ? htmlspecialchars($log['funcionario_recebimento']) : '-' ?></small></td>
                                            <td class="pe-4">
                                                <?php if($log['status'] == 'devolvido'): ?>
                                                    <span class="badge bg-success rounded-pill px-3">Devolvido</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark rounded-pill px-3">Em Uso</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center py-5 text-muted">Nenhum registro de chaves encontrado.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div id="sessao-historico-chamados" class="content-section">
            <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid #198754;">
                <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <h5 class="mb-3 mb-md-0 fw-bold d-flex align-items-center text-dark">
                        <i class="bi bi-headset text-success me-3 fs-4"></i> Chamados Atendidos (Resolvidos)
                    </h5>
                    <button class="btn btn-outline-success fw-bold shadow-sm rounded-pill px-4" onclick="exportarTabelaParaCSV('tabela-historico-chamados', 'Relatorio_Chamados_SOS_Uniceplac.csv')">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Baixar Planilha (CSV)
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table id="tabela-historico-chamados" class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-4 py-3">Data/Hora</th>
                                    <th>Laboratório</th>
                                    <th>Professor</th>
                                    <th>Problema Relatado</th>
                                    <th class="pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($historico_chamados) > 0): ?>
                                    <?php foreach ($historico_chamados as $chamado): ?>
                                        <tr>
                                            <td class="ps-4"><strong><?= date('d/m/Y H:i', strtotime($chamado['data_hora'])) ?></strong></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($chamado['laboratorio']) ?></td>
                                            <td><?= htmlspecialchars($chamado['professor_nome']) ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars($chamado['mensagem']) ?></td>
                                            <td class="pe-4">
                                                <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle me-1"></i>Resolvido</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">Nenhum chamado resolvido encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function exportarTabelaParaCSV(tableId, filename) {
            let csv = [];
            let rows = document.querySelectorAll("#" + tableId + " tr");
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll("td, th");
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " "); 
                    row.push('"' + text.replace(/"/g, '""') + '"');
                }
                csv.push(row.join(","));
            }
            let csvFile = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
            let downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        function updateThemeElements(theme) {
            const icon = document.getElementById('themeIcon');
            const logo = document.getElementById('navbarLogo');
            if(theme === 'dark') {
                icon.classList.remove('bi-moon-stars'); icon.classList.add('bi-sun', 'text-warning');
                if (logo) logo.src = 'uniceplac.png'; 
            } else {
                icon.classList.remove('bi-sun', 'text-warning'); icon.classList.add('bi-moon-stars');
                if (logo) logo.src = 'uniceplac2.png'; 
            }
        }

        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
            document.querySelectorAll('.offcanvas-menu-link').forEach(link => link.classList.remove('active-link'));
            const target = document.getElementById(sectionId);
            if(target) {
                target.style.display = 'block';
                const activeLink = document.querySelector(`.offcanvas-menu-link[href="#${sectionId}"]`);
                if(activeLink) activeLink.classList.add('active-link');
                window.history.replaceState(null, null, '#' + sectionId);
            }
        }

        let qtdAnterior = 0; 
        const somAlerta = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3'); 

        function monitorarTempoReal() {
            fetch('check_sos_status.php')
                .then(res => res.json())
                .then(data => {
                    if (data.qtd_suporte > qtdAnterior) { somAlerta.play().catch(e => console.log("Áudio aguardando interação.")); }
                    qtdAnterior = data.qtd_suporte;
                    document.getElementById('area-chamados-dinamica').innerHTML = data.html_suporte;
                    
                    const badge = document.getElementById('badge-sos');
                    const bell = document.getElementById('navBell');
                    
                    if (data.qtd_suporte > 0) {
                        badge.innerText = data.qtd_suporte;
                        badge.classList.remove('d-none');
                        bell.classList.add('sos-active');
                    } else {
                        badge.classList.add('d-none');
                        bell.classList.remove('sos-active');
                    }
                });

            const sessaoMapa = document.getElementById('sessao-mapa-diario');
            const modalAberto = document.querySelector('.modal.show'); 
            
            if (sessaoMapa && sessaoMapa.style.display !== 'none' && !modalAberto) {
                const dataFiltro = document.querySelector('input[name="data_busca"]').value;
                fetch('painel_suporte.php?data_busca=' + dataFiltro + '&_t=' + new Date().getTime(), { cache: "no-store" })
                    .then(res => res.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        
                        document.querySelector('.kpi-total h2').innerText = doc.querySelector('.kpi-total h2').innerText;
                        document.querySelector('.kpi-matutino h2').innerText = doc.querySelector('.kpi-matutino h2').innerText;
                        document.querySelector('.kpi-vespertino h2').innerText = doc.querySelector('.kpi-vespertino h2').innerText;
                        document.querySelector('.kpi-noturno h2').innerText = doc.querySelector('.kpi-noturno h2').innerText;
                        
                        const novaGrid = doc.getElementById('grid-mapa-diario').innerHTML;
                        const gridAtual = document.getElementById('grid-mapa-diario');
                        if (gridAtual.innerHTML.trim() !== novaGrid.trim()) {
                            gridAtual.innerHTML = novaGrid;
                        }
                    })
                    .catch(err => console.error("Erro ao atualizar a grade em tempo real:", err));
            }
        }

        function limparFeedbacks() {
            document.querySelectorAll('.alert-autohide').forEach(alerta => {
                setTimeout(() => { alerta.style.transition = "opacity 0.6s ease"; alerta.style.opacity = "0"; setTimeout(() => alerta.remove(), 600); }, 4000);
            });
        }

        document.addEventListener("DOMContentLoaded", () => {
            limparFeedbacks();
            updateThemeElements(savedTheme);

            let hashURL = window.location.hash.replace('#', '') || "sessao-mapa-diario";
            showSection(hashURL);

            document.querySelectorAll('.offcanvas-menu-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href.startsWith('#')) {
                        e.preventDefault();
                        showSection(href.replace('#', ''));
                        bootstrap.Offcanvas.getInstance(document.getElementById('sidebarMenu')).hide();
                    }
                });
            });

            document.getElementById('themeToggleBtn').addEventListener('click', function() {
                let newTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('tema-uniceplac', newTheme);
                updateThemeElements(newTheme); 
            });
            
            setInterval(monitorarTempoReal, 5000); 
            monitorarTempoReal(); 
            
            const btnFoto = document.getElementById('btnAlterarFotoNav');
            const inputFoto = document.getElementById('nova_foto_input');
            if(btnFoto && inputFoto) {
                btnFoto.addEventListener('click', () => inputFoto.click());
                inputFoto.addEventListener('change', function() {
                    if (this.value) document.getElementById('formFotoPerfil').submit();
                });
            }
        });
    </script>
</body>
</html>