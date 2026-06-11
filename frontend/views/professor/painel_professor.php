<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <title>Portal Docente - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales-all.global.min.js"></script>
    <script>const savedTheme = localStorage.getItem('tema-uniceplac') || 'light'; document.documentElement.setAttribute('data-bs-theme', savedTheme);</script>
    
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <form id="formFotoPerfil" action="index.php?page=professor" method="POST" enctype="multipart/form-data" class="d-none">
        <input type="file" name="nova_foto" id="nova_foto_input" accept="image/png,image/jpeg,image/webp">
    </form>

    <nav class="navbar navbar-light bg-white mb-4 border-bottom shadow-sm sticky-top">
        <div class="container-fluid px-3 px-md-4">
            <span class="navbar-brand mb-0 h1 d-flex align-items-center">
                <img src="assets/images/uniceplac.png" id="navbarLogo" alt="Logo" style="height:70px;margin-right:12px;transition:.3s;">
            </span>
            <div class="d-flex align-items-center">
                <div class="me-4 top-icon-btn" id="themeToggleBtn" title="Alternar Tema"><i class="bi bi-moon-stars" id="themeIcon"></i></div>
                <div class="position-relative me-4 top-icon-btn" id="navBell">
                    <i class="bi bi-bell"></i>
                    <span id="badge-nav-bell" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light <?= $qtdPendentes > 0 ? '' : 'd-none' ?>"><?= $qtdPendentes ?></span>
                </div>
                <div class="me-3 top-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu"><i class="bi bi-grid-3x3-gap fs-5"></i></div>
                <img src="<?= htmlspecialchars($fotoAtual) ?>" alt="Foto" class="avatar-img-small ms-1" id="btnAlterarFotoNav">
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
                <img src="<?= htmlspecialchars($fotoAtual) ?>" alt="Foto" class="avatar-img-large shadow-sm">
                <h5 class="fw-bold mb-1 text-dark">Prof. <?= htmlspecialchars($_SESSION['nome']) ?></h5>
                <span class="badge bg-uniceplac text-uppercase px-3 py-1"><?= htmlspecialchars($_SESSION['perfil']) ?></span>
            </div>
            <div class="flex-grow-1 overflow-auto">
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-75">Meu Planejamento</div>
                <a href="#sessao-calendario" class="offcanvas-menu-link"><i class="bi bi-calendar3 text-primary me-2"></i> Meu Calendário</a>
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-75 border-top mt-2">Laboratórios</div>
                <a href="#sessao-dashboard" class="offcanvas-menu-link"><i class="bi bi-geo-alt text-primary me-2"></i> Próximas Aulas e Chaves</a>
                <a href="#sessao-historico" class="offcanvas-menu-link">
                    <i class="bi bi-clock-history text-info me-2"></i> Histórico de Reservas
                    <span id="badge-menu-lateral" class="badge bg-warning text-dark ms-2 <?= $qtdPendentes > 0 ? '' : 'd-none' ?>"><?= $qtdPendentes ?> pendentes</span>
                </a>
                <a href="#sessao-solicitar" class="offcanvas-menu-link"><i class="bi bi-calendar-plus text-success me-2"></i> Solicitar Laboratório</a>
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-75 border-top mt-2">Grade Regular</div>
                <a href="#sessao-ensalamento" class="offcanvas-menu-link"><i class="bi bi-building text-primary me-2"></i> Ensalamento Fixo</a>
            </div>
            <div class="p-3 border-top mt-auto bg-light">
                <a href="/index.php?page=logout" class="btn btn-outline-danger w-100 fw-bold"><i class="bi bi-box-arrow-right me-2"></i> Sair</a>
            </div>
        </div>
    </div>

    <div class="container pt-3 pb-5">
        <?= $mensagem ?>

        <!-- Seção Calendário -->
        <div id="sessao-calendario" class="content-section">
            <?php include VIEWS_PATH . '/professor/_calendario.php'; ?>
        </div>

        <!-- Seção Dashboard (Próximas Aulas) -->
        <div id="sessao-dashboard" class="content-section">
            <?php include VIEWS_PATH . '/professor/_dashboard.php'; ?>
        </div>

        <!-- Seção Ensalamento -->
        <div id="sessao-ensalamento" class="content-section">
            <?php include VIEWS_PATH . '/professor/_ensalamento.php'; ?>
        </div>

        <!-- Seção Solicitar -->
        <div id="sessao-solicitar" class="content-section">
            <?php include VIEWS_PATH . '/professor/_solicitar.php'; ?>
        </div>

        <!-- Seção Histórico -->
        <div id="sessao-historico" class="content-section">
            <?php include VIEWS_PATH . '/professor/_historico.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
    let calendarioProfessorGlobal;

    function showSection(id) {
        document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
        document.querySelectorAll('.offcanvas-menu-link').forEach(l => l.classList.remove('active-link'));
        const sec = document.getElementById(id);
        if (sec) {
            sec.style.display = 'block';
            const link = document.querySelector(`.offcanvas-menu-link[href="#${id}"]`);
            if (link) link.classList.add('active-link');
            window.history.replaceState(null, null, '#' + id);
            if (id === 'sessao-calendario' && calendarioProfessorGlobal) {
                setTimeout(() => calendarioProfessorGlobal.updateSize(), 150);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        autoOcultarMensagens();
        updateThemeElements(savedTheme);

        const hash = window.location.hash.replace('#', '');
        showSection(hash && document.getElementById(hash) ? hash : '<?= $abaAtiva ?>');

        // Calendário
        const calEl = document.getElementById('calendarioProfessor');
        if (calEl) {
            calendarioProfessorGlobal = new FullCalendar.Calendar(calEl, {
                locale: 'pt-br', initialView: 'dayGridMonth', navLinks: true,
                nowIndicator: true, dayMaxEvents: 3,
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
                buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia', list: 'Lista' },
                events: <?= $eventosJson ?>,
                slotMinTime: '08:00:00', slotMaxTime: '23:30:00', allDaySlot: false, expandRows: true,
                eventContent: function (arg) {
                    let c = document.createElement('div');
                    let t = document.createElement('div');
                    t.innerHTML = (arg.timeText ? `<div style="font-size:.7rem;font-weight:bold;opacity:.75;margin-bottom:2px;">${arg.timeText}</div>` : '')
                        + `<div style="font-size:.8rem;font-weight:700;line-height:1.1;">${arg.event.title}</div>`;
                    c.appendChild(t);
                    if (arg.view.type !== 'dayGridMonth') {
                        let l = document.createElement('div');
                        l.innerHTML = arg.event.extendedProps.local;
                        l.style.cssText = 'font-size:.75rem;margin-top:4px;line-height:1.2;';
                        c.appendChild(l);
                    }
                    return { domNodes: [c] };
                }
            });
            calendarioProfessorGlobal.render();
        }

        document.getElementById('themeToggleBtn').addEventListener('click', function () {
            const t = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', t);
            localStorage.setItem('tema-uniceplac', t);
            updateThemeElements(t);
        });

        document.querySelectorAll('.offcanvas-menu-link').forEach(l => {
            l.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href.startsWith('#')) {
                    e.preventDefault();
                    showSection(href.slice(1));
                    document.querySelector('#sidebarMenu .btn-close')?.click();
                }
            });
        });

        document.getElementById('navBell').addEventListener('click', () => showSection('sessao-historico'));
        document.getElementById('btnAlterarFotoNav').addEventListener('click', () => document.getElementById('nova_foto_input').click());
        document.getElementById('nova_foto_input').addEventListener('change', function () { if (this.value) document.getElementById('formFotoPerfil').submit(); });

        setInterval(monitorarTempoReal, 5000);
    });

    function monitorarTempoReal() {
        if (document.querySelector('.modal.show')) return;
        fetch('/index.php?page=professor&_t=' + Date.now(), { cache: 'no-store' })
            .then(r => r.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                ['grid-proximas-aulas', 'grid-ensalamento', 'tabela-historico-container', 'badge-nav-bell', 'badge-menu-lateral'].forEach(id => {
                    const n = doc.getElementById(id), c = document.getElementById(id);
                    if (n && c && c.innerHTML.trim() !== n.innerHTML.trim()) c.innerHTML = n.innerHTML;
                });
            })
            .catch(() => {});
    }
    </script>
</body>
</html>
