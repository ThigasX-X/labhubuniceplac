<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Suporte TI - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>const savedTheme = localStorage.getItem('tema-uniceplac') || 'light'; document.documentElement.setAttribute('data-bs-theme', savedTheme);</script>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <form id="formFotoPerfil" action="index.php?page=suporte" method="POST" enctype="multipart/form-data" class="d-none">
        <input type="file" name="nova_foto" id="nova_foto_input" accept="image/png,image/jpeg,image/webp">
    </form>

    <nav class="navbar navbar-light bg-white mb-4 shadow-sm sticky-top">
        <div class="container-fluid px-3 px-md-4">
            <span class="navbar-brand d-flex align-items-center">
                <img src="assets/images/uniceplac.png" id="navbarLogo" alt="Logo" style="height:70px;margin-right:12px;">
            </span>
            <div class="ms-auto d-flex align-items-center">
                <div class="me-4 top-icon-btn" id="themeToggleBtn"><i class="bi bi-moon-stars" id="themeIcon"></i></div>
                <div class="position-relative me-4 top-icon-btn" id="navBell">
                    <i class="bi bi-bell"></i>
                    <span id="badge-sos" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light d-none">0</span>
                </div>
                <div class="me-3 top-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu"><i class="bi bi-grid-3x3-gap fs-5"></i></div>
                <img src="<?= htmlspecialchars($fotoAtual) ?>" alt="Foto" class="avatar-img-small ms-1" id="btnAlterarFotoNav">
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
                <img src="<?= htmlspecialchars($fotoAtual) ?>" alt="Foto" style="width:80px;height:80px;object-fit:cover;border-radius:50%!important;border:3px solid var(--laranja-uniceplac);" class="shadow-sm mb-2">
                <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($_SESSION['nome']) ?></h5>
                <span class="badge bg-uniceplac text-uppercase mt-2 px-3 py-1">Suporte TI</span>
            </div>
            <div class="flex-grow-1 overflow-auto">
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50">Operacional</div>
                <a href="#sessao-mapa-diario" class="offcanvas-menu-link active-link"><i class="bi bi-speedometer2 text-primary me-2"></i> Mapa Diário</a>
                <a href="#sessao-labs" class="offcanvas-menu-link"><i class="bi bi-pc-display text-secondary me-2"></i> Laboratórios</a>
                <div class="p-3 text-muted small fw-bold text-uppercase opacity-50 border-top mt-2">Relatórios</div>
                <a href="#sessao-historico-chaves" class="offcanvas-menu-link"><i class="bi bi-key text-info me-2"></i> Histórico de Chaves</a>
                <a href="#sessao-historico-chamados" class="offcanvas-menu-link"><i class="bi bi-headset text-success me-2"></i> Chamados Atendidos</a>
                <?php if ($_SESSION['perfil'] === 'coordenador'): ?>
                    <hr class="my-2 mx-3">
                    <a href="/index.php?page=coordenador" class="offcanvas-menu-link"><i class="bi bi-arrow-left-circle text-warning me-2"></i> Voltar Coordenação</a>
                <?php endif; ?>
            </div>
            <div class="p-3 border-top mt-auto">
                <a href="/index.php?page=logout" class="btn btn-outline-danger w-100 fw-bold">Sair</a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 pb-5">
        <div id="container-mensagens"><?= $mensagem ?></div>
        <div id="area-chamados-dinamica"></div>

        <!-- Mapa Diário -->
        <div id="sessao-mapa-diario" class="content-section">
            <?php include VIEWS_PATH . '/suporte/_mapa_diario.php'; ?>
        </div>

        <!-- Labs -->
        <div id="sessao-labs" class="content-section">
            <?php include VIEWS_PATH . '/suporte/_labs.php'; ?>
        </div>

        <!-- Histórico de Chaves -->
        <div id="sessao-historico-chaves" class="content-section">
            <?php include VIEWS_PATH . '/suporte/_historico_chaves.php'; ?>
        </div>

        <!-- Histórico de Chamados -->
        <div id="sessao-historico-chamados" class="content-section">
            <?php include VIEWS_PATH . '/suporte/_historico_chamados.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        autoOcultarMensagens();
        updateThemeElements(savedTheme);
        const hash = window.location.hash.replace('#', '');
        showSection(hash && document.getElementById(hash) ? hash : 'sessao-mapa-diario');

        document.getElementById('themeToggleBtn').addEventListener('click', function () {
            const t = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', t);
            localStorage.setItem('tema-uniceplac', t);
            updateThemeElements(t);
        });

        document.querySelectorAll('.offcanvas-menu-link').forEach(l => {
            l.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href && href.startsWith('#')) {
                    e.preventDefault();
                    showSection(href.slice(1));
                    document.querySelector('#sidebarMenu .btn-close')?.click();
                }
            });
        });

        document.getElementById('btnAlterarFotoNav').addEventListener('click', () => document.getElementById('nova_foto_input').click());
        document.getElementById('nova_foto_input').addEventListener('change', function () { if (this.value) document.getElementById('formFotoPerfil').submit(); });

        setInterval(verificarSOS, 8000);
    });

    function verificarSOS() {
        fetch('index.php?page=api/check-sos-status')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('badge-sos');
                const area  = document.getElementById('area-chamados-dinamica');
                if (data.qtd_suporte > 0) {
                    badge.textContent = data.qtd_suporte;
                    badge.classList.remove('d-none');
                    document.getElementById('navBell')?.classList.add('sos-active');
                    if (area) area.innerHTML = data.html_suporte;
                } else {
                    badge.classList.add('d-none');
                    document.getElementById('navBell')?.classList.remove('sos-active');
                    if (area) area.innerHTML = '';
                }
            }).catch(() => {});
    }

    function exportarTabelaParaCSV(idTabela, nomeArquivo) {
        const rows = document.querySelectorAll('#' + idTabela + ' tr');
        const csv  = [...rows].map(r => [...r.querySelectorAll('th,td')].map(c => '"' + c.innerText.replace(/"/g, '""') + '"').join(',')).join('\n');
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a'); a.href = url; a.download = nomeArquivo; a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>
