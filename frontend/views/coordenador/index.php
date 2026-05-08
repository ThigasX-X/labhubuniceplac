<?php
$nomeUsuario = $_SESSION['nome']     ?? 'Coordenador';
$perfil      = $_SESSION['perfil']   ?? 'coordenador';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordenador | UNICEPLAC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg sticky-top shadow-sm" id="mainNavbar">
    <div class="container-fluid px-3 px-md-4">
        <button class="btn btn-sm me-2 border-0" id="offcanvasToggle" data-bs-toggle="offcanvas" data-bs-target="#menuOffcanvas" title="Menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#calendario" onclick="showSection('calendario')">
            <img src="/assets/images/uniceplac2.png" alt="Logo" height="32" id="navbarLogo">
            <span class="d-none d-md-inline text-muted fw-normal fs-6">Painel Coordenação</span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <?php if ($qtdPendentes > 0): ?>
                <button class="btn btn-sm btn-warning rounded-pill fw-bold px-3 shadow-sm" onclick="showSection('solicitacoes')">
                    <i class="bi bi-bell-fill me-1"></i><?= $qtdPendentes ?> pendente<?= $qtdPendentes > 1 ? 's' : '' ?>
                </button>
            <?php endif; ?>
            <button class="btn btn-sm border-0 rounded-circle" id="themeToggle" title="Alternar tema">
                <i class="bi bi-moon-stars fs-5" id="themeIcon"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-sm border-0 d-flex align-items-center gap-2 rounded-pill px-2" data-bs-toggle="dropdown">
                    <img src="<?= htmlspecialchars($fotoAtual) ?>" class="rounded-circle object-fit-cover border" width="34" height="34" alt="Foto">
                    <span class="d-none d-md-inline fw-semibold small"><?= htmlspecialchars($nomeUsuario) ?></span>
                    <i class="bi bi-chevron-down small opacity-50"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalFoto"><i class="bi bi-camera me-2"></i>Alterar Foto</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="/index.php?page=logout"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- OFFCANVAS MENU -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="menuOffcanvas" style="width:280px;">
    <div class="offcanvas-header border-bottom py-3">
        <h6 class="offcanvas-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-grid-3x3-gap text-primary"></i> Navegação
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="py-2">
            <a href="#calendario"   class="offcanvas-menu-link" onclick="showSection('calendario')"><i class="bi bi-calendar3"></i> Calendário</a>
            <a href="#kanban"       class="offcanvas-menu-link" onclick="showSection('kanban')"><i class="bi bi-kanban"></i> Kanban de Aulas</a>
            <a href="#solicitacoes" class="offcanvas-menu-link" onclick="showSection('solicitacoes')">
                <i class="bi bi-inbox"></i> Solicitações
                <?php if ($qtdPendentes > 0): ?><span class="badge bg-danger rounded-pill ms-auto"><?= $qtdPendentes ?></span><?php endif; ?>
            </a>
            <a href="#reservas"     class="offcanvas-menu-link" onclick="showSection('reservas')"><i class="bi bi-calendar-check"></i> Reservas Aprovadas</a>
            <a href="#quadro"       class="offcanvas-menu-link" onclick="showSection('quadro')"><i class="bi bi-table"></i> Quadro de Horários</a>
            <a href="#ensalamento"  class="offcanvas-menu-link" onclick="showSection('ensalamento')"><i class="bi bi-door-open"></i> Ensalamento</a>
            <a href="#cadastros"    class="offcanvas-menu-link" onclick="showSection('cadastros')"><i class="bi bi-database"></i> Cadastros</a>
            <a href="#relatorios"   class="offcanvas-menu-link" onclick="showSection('relatorios')"><i class="bi bi-bar-chart-line"></i> Relatórios BI</a>
            <div class="px-3 py-2 mt-2">
                <a href="/index.php?page=suporte" class="btn btn-outline-secondary w-100 rounded-pill btn-sm fw-semibold">
                    <i class="bi bi-headset me-2"></i>Ir para Suporte
                </a>
            </div>
        </nav>
    </div>
</div>

<!-- CONTEÚDO PRINCIPAL -->
<div class="container-fluid px-3 px-md-4 py-4">

    <?= $mensagem ?>

    <!-- CALENDÁRIO -->
    <div class="content-section" id="calendario">
        <?php include __DIR__ . '/_calendario.php'; ?>
    </div>

    <!-- KANBAN -->
    <div class="content-section" id="kanban" style="display:none;">
        <?php include __DIR__ . '/_kanban.php'; ?>
    </div>

    <!-- SOLICITAÇÕES PENDENTES -->
    <div class="content-section" id="solicitacoes" style="display:none;">
        <?php include __DIR__ . '/_pendentes.php'; ?>
    </div>

    <!-- RESERVAS APROVADAS -->
    <div class="content-section" id="reservas" style="display:none;">
        <?php include __DIR__ . '/_reservas.php'; ?>
    </div>

    <!-- QUADRO DE HORÁRIOS -->
    <div class="content-section" id="quadro" style="display:none;">
        <?php include __DIR__ . '/_quadro.php'; ?>
    </div>

    <!-- ENSALAMENTO -->
    <div class="content-section" id="ensalamento" style="display:none;">
        <?php include __DIR__ . '/_ensalamento.php'; ?>
    </div>

    <!-- CADASTROS -->
    <div class="content-section" id="cadastros" style="display:none;">
        <?php include __DIR__ . '/_cadastros.php'; ?>
    </div>

    <!-- RELATÓRIOS BI -->
    <div class="content-section" id="relatorios" style="display:none;">
        <?php include __DIR__ . '/_relatorios.php'; ?>
    </div>

</div>

<!-- MODAL: Alterar Foto -->
<div class="modal fade" id="modalFoto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-camera me-2"></i>Alterar Foto de Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="<?= htmlspecialchars($fotoAtual) ?>" class="rounded-circle mb-3 border object-fit-cover" width="100" height="100" alt="Foto atual">
                <form method="POST" enctype="multipart/form-data" action="/index.php?page=coordenador">
                    <input type="file" class="form-control mb-3" name="nova_foto" accept="image/jpeg,image/png,image/webp" required>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">Salvar Foto</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    autoOcultarMensagens();

    // Seção inicial via hash
    const hash = window.location.hash.replace('#', '') || 'calendario';
    showSection(hash);

    // Tema
    const savedTheme = localStorage.getItem('labhub-theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
    updateThemeElements(savedTheme);
    document.getElementById('themeToggle').addEventListener('click', function () {
        const current = document.documentElement.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('labhub-theme', next);
        updateThemeElements(next);
    });

    // FullCalendar
    const calEl = document.getElementById('calendar');
    if (calEl) {
        const eventos = <?= $eventosJson ?>;
        const calendar = new FullCalendar.Calendar(calEl, {
            locale: 'pt-br',
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
            events: eventos,
            eventClick: function (info) {
                const local = info.event.extendedProps.local || '';
                document.getElementById('modalEventTitle').textContent = info.event.title;
                document.getElementById('modalEventTime').textContent = info.event.startStr || '';
                document.getElementById('modalEventLocal').innerHTML = local;
                new bootstrap.Modal(document.getElementById('modalEvento')).show();
            },
        });
        calendar.render();
    }

    // Drag & Drop Kanban
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', e => {
            e.dataTransfer.setData('id_aula', card.dataset.id);
            card.classList.add('opacity-50');
        });
        card.addEventListener('dragend', () => card.classList.remove('opacity-50'));
    });
    document.querySelectorAll('.kanban-col').forEach(col => {
        col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('kanban-dragover'); });
        col.addEventListener('dragleave', () => col.classList.remove('kanban-dragover'));
        col.addEventListener('drop', e => {
            e.preventDefault();
            col.classList.remove('kanban-dragover');
            const idAula = e.dataTransfer.getData('id_aula');
            const novoDia = col.dataset.dia;
            fetch('/index.php?page=coordenador', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mover_aula&id_aula=${idAula}&novo_dia=${encodeURIComponent(novoDia)}`
            }).then(r => r.json()).then(d => { if (d.ok) location.reload(); else alert(d.erro || 'Erro ao mover.'); });
        });
    });

    // Gráficos BI
    iniciarGraficos();
});

function iniciarGraficos() {
    const makeChart = (id, type, labels, data, label, color) => {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type,
            data: {
                labels,
                datasets: [{ label, data, backgroundColor: color, borderColor: color, borderWidth: 2, tension: 0.4, fill: type === 'line' }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: type !== 'pie' ? { y: { beginAtZero: true } } : {} }
        });
    };

    makeChart('graficoProfHoras', 'bar',
        <?= json_encode($graficoProfNomes) ?>,
        <?= json_encode($graficoProfHoras) ?>,
        'Horas/Semana', '#6366f1');

    makeChart('graficoLabUso', 'bar',
        <?= json_encode($graficoLabNomes) ?>,
        <?= json_encode($graficoLabUso) ?>,
        'Horas Usadas', '#10b981');

    makeChart('graficoCursos', 'pie',
        <?= json_encode($graficoNomeCursos) ?>,
        <?= json_encode($graficoHorasCursos) ?>,
        'Horas por Curso',
        ['#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899']);
}
</script>
</body>
</html>
