/* =====================================================================
   LabHub UNICEPLAC — Global JavaScript
   ===================================================================== */

const LabHubPanel = {
    onSectionShow: null,
    defaultSection: '',
};

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

// Injeta o token CSRF em qualquer form POST no momento do submit. Usar o evento
// (em vez de varrer no DOMContentLoaded) garante que formulários inseridos
// dinamicamente — ex.: o "Resolver Chamado" trazido pelo polling — também recebam o token.
document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f instanceof HTMLFormElement
        && (f.method || '').toLowerCase() === 'post'
        && !f.querySelector('input[name="_csrf"]')) {
        const i = document.createElement('input');
        i.type = 'hidden';
        i.name = '_csrf';
        i.value = CSRF_TOKEN;
        f.appendChild(i);
    }
}, true);

function autoOcultarMensagens() {
    document.querySelectorAll('.alert-autohide').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .6s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 600);
        }, 4000);
    });
}

function getLabHubTheme() {
    return localStorage.getItem('labhub-theme')
        || localStorage.getItem('tema-uniceplac')
        || 'light';
}

function setLabHubTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('labhub-theme', theme);
    updateThemeElements(theme);
}

function updateThemeElements(theme) {
    const icon = document.getElementById('themeIcon');
    const logo = document.getElementById('navbarLogo');
    if (theme === 'dark') {
        icon?.classList.replace('bi-moon-stars', 'bi-sun');
        icon?.classList.add('text-warning');
        if (logo) logo.src = 'assets/images/uniceplac.png';
    } else {
        icon?.classList.replace('bi-sun', 'bi-moon-stars');
        icon?.classList.remove('text-warning');
        if (logo) logo.src = 'assets/images/uniceplac2.png';
    }
}

function showSection(id) {
    document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.offcanvas-menu-link').forEach(l => l.classList.remove('active-link'));
    const sec  = document.getElementById(id);
    const link = document.querySelector(`.offcanvas-menu-link[href="#${id}"]`);
    if (sec) sec.style.display = 'block';
    if (link) link.classList.add('active-link');
    window.history.replaceState(null, null, '#' + id);
    if (typeof LabHubPanel.onSectionShow === 'function') {
        LabHubPanel.onSectionShow(id);
    }
}

function initLabHubPanel(options = {}) {
    LabHubPanel.onSectionShow = options.onSectionShow || null;
    LabHubPanel.defaultSection = options.defaultSection || '';

    autoOcultarMensagens();
    setLabHubTheme(getLabHubTheme());

    const themeBtn = document.getElementById('themeToggle');
    themeBtn?.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        setLabHubTheme(next);
    });

    document.querySelectorAll('#menuOffcanvas .offcanvas-menu-link').forEach(l => {
        l.addEventListener('click', () => {
            bootstrap.Offcanvas.getInstance(document.getElementById('menuOffcanvas'))?.hide();
        });
    });

    const hash = window.location.hash.replace('#', '');
    const initial = hash && document.getElementById(hash) ? hash : LabHubPanel.defaultSection;
    if (initial) showSection(initial);
}

function exportarTabelaParaCSV(idTabela, nomeArquivo) {
    const rows = document.querySelectorAll('#' + idTabela + ' tr');
    const csv  = [...rows].map(r =>
        [...r.querySelectorAll('th,td')].map(c => '"' + c.innerText.replace(/"/g, '""') + '"').join(',')
    ).join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = nomeArquivo;
    a.click();
    URL.revokeObjectURL(url);
}
