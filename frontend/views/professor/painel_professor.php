<?php
$nomeUsuario     = $_SESSION['nome'] ?? 'Professor';
$painelTitulo    = 'Portal Docente | UNICEPLAC';
$painelSubtitulo = 'Portal Docente';
$painelRota      = 'professor';
$painelAbaAtiva  = $abaAtiva ?? 'sessao-calendario';
$painelAlerta    = $qtdPendentes > 0
    ? ['secao' => 'sessao-historico', 'qtd' => $qtdPendentes, 'texto' => 'pendente' . ($qtdPendentes > 1 ? 's' : '')]
    : ($qtdChamadosPendentes > 0
        ? ['secao' => 'sessao-chamados', 'qtd' => $qtdChamadosPendentes, 'texto' => 'chamado' . ($qtdChamadosPendentes > 1 ? 's' : '')]
        : null);
$painelMenu = [
    ['titulo' => 'Meu Planejamento', 'itens' => [
        ['id' => 'sessao-calendario', 'icone' => 'bi-calendar3',      'label' => 'Meu Calendário'],
    ]],
    ['titulo' => 'Laboratórios', 'itens' => [
        ['id' => 'sessao-dashboard',  'icone' => 'bi-geo-alt',         'label' => 'Próximas Aulas e Chaves'],
        ['id' => 'sessao-historico',  'icone' => 'bi-clock-history',   'label' => 'Histórico de Reservas', 'badge' => $qtdPendentes ?: null],
        ['id' => 'sessao-solicitar',  'icone' => 'bi-calendar-plus',   'label' => 'Solicitar Laboratório'],
        ['id' => 'sessao-chamados',   'icone' => 'bi-headset',         'label' => 'Chamados ao Suporte', 'badge' => $qtdChamadosPendentes ?: null],
    ]],
    ['titulo' => 'Grade Regular', 'itens' => [
        ['id' => 'sessao-ensalamento', 'icone' => 'bi-building', 'label' => 'Ensalamento Fixo'],
    ]],
];
$painelHeadExtra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales-all.global.min.js"></script>
HTML;

include VIEWS_PATH . '/layouts/painel_open.php';
?>

<div id="sessao-calendario" class="content-section">
    <?php include VIEWS_PATH . '/professor/_calendario.php'; ?>
</div>
<div id="sessao-dashboard" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/professor/_dashboard.php'; ?>
</div>
<div id="sessao-ensalamento" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/professor/_ensalamento.php'; ?>
</div>
<div id="sessao-solicitar" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/professor/_solicitar.php'; ?>
</div>
<div id="sessao-historico" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/professor/_historico.php'; ?>
</div>
<div id="sessao-chamados" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/professor/_chamados.php'; ?>
</div>

<?php
$painelScriptsExtra = 'let calendarioProfessorGlobal;

document.addEventListener("DOMContentLoaded", function () {
    initLabHubPanel({
        defaultSection: ' . json_encode($painelAbaAtiva) . ',
        onSectionShow(id) {
            if (id === "sessao-calendario" && calendarioProfessorGlobal) {
                setTimeout(() => calendarioProfessorGlobal.updateSize(), 150);
            }
        }
    });

    const calEl = document.getElementById("calendarioProfessor");
    if (calEl) {
        calendarioProfessorGlobal = new FullCalendar.Calendar(calEl, {
            locale: "pt-br", initialView: "dayGridMonth", navLinks: true,
            nowIndicator: true, dayMaxEvents: 3,
            headerToolbar: { left: "prev,next today", center: "title", right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek" },
            buttonText: { today: "Hoje", month: "Mês", week: "Semana", day: "Dia", list: "Lista" },
            events: ' . $eventosJson . ',
            slotMinTime: "08:00:00", slotMaxTime: "23:30:00", allDaySlot: false, expandRows: true,
            eventContent(arg) {
                const c = document.createElement("div");
                const t = document.createElement("div");
                t.innerHTML = (arg.timeText ? \'<div style="font-size:.7rem;font-weight:bold;opacity:.75;margin-bottom:2px;">\' + arg.timeText + \'</div>\' : \'\')
                    + \'<div style="font-size:.8rem;font-weight:700;line-height:1.1;">\' + arg.event.title + \'</div>\';
                c.appendChild(t);
                if (arg.view.type !== "dayGridMonth") {
                    const l = document.createElement("div");
                    l.innerHTML = arg.event.extendedProps.local;
                    l.style.cssText = "font-size:.75rem;margin-top:4px;line-height:1.2;";
                    c.appendChild(l);
                }
                return { domNodes: [c] };
            }
        });
        calendarioProfessorGlobal.render();
    }

    setInterval(monitorarTempoReal, 5000);
});

function monitorarTempoReal() {
    if (document.querySelector(".modal.show")) return;
    fetch("/index.php?page=professor&_t=" + Date.now(), { cache: "no-store" })
        .then(r => r.text())
        .then(html => {
            const doc = new DOMParser().parseFromString(html, "text/html");
            ["grid-proximas-aulas", "grid-ensalamento", "tabela-historico-container", "tabela-meus-chamados"].forEach(id => {
                const n = doc.getElementById(id), c = document.getElementById(id);
                if (n && c && c.innerHTML.trim() !== n.innerHTML.trim()) c.innerHTML = n.innerHTML;
            });
        })
        .catch(() => {});
}';

include VIEWS_PATH . '/layouts/painel_close.php';
