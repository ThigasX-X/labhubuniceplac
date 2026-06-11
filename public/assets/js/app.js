/* =====================================================================
   LabHub UNICEPLAC — Global JavaScript
   ===================================================================== */

/* CSRF: token vem da <meta name="csrf-token">; injetado em todo form POST */
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(f => {
        if ((f.method || '').toLowerCase() === 'post' && !f.querySelector('input[name="_csrf"]')) {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = '_csrf'; i.value = CSRF_TOKEN;
            f.appendChild(i);
        }
    });
});

function autoOcultarMensagens() {
    document.querySelectorAll('.alert-autohide').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .6s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 600);
        }, 4000);
    });
}

function updateThemeElements(theme) {
    const icon = document.getElementById('themeIcon');
    const logo = document.getElementById('navbarLogo');
    if (theme === 'dark') {
        icon?.classList.replace('bi-moon-stars', 'bi-sun');
        icon?.classList.add('text-warning');
        if (logo) logo.src = '/assets/images/uniceplac.png';
    } else {
        icon?.classList.replace('bi-sun', 'bi-moon-stars');
        icon?.classList.remove('text-warning');
        if (logo) logo.src = '/assets/images/uniceplac2.png';
    }
}

function showSection(id) {
    document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.offcanvas-menu-link').forEach(l => l.classList.remove('active-link'));
    const sec  = document.getElementById(id);
    const link = document.querySelector(`.offcanvas-menu-link[href="#${id}"]`);
    if (sec)  sec.style.display = 'block';
    if (link) link.classList.add('active-link');
    window.history.replaceState(null, null, '#' + id);
}
