/* =====================================================================
   LabHub UNICEPLAC — Global JavaScript
   ===================================================================== */

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
