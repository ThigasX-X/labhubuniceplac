<?php
$nomeUsuario     = $_SESSION['nome'] ?? 'Coordenador';
$painelTitulo    = 'Coordenador | UNICEPLAC';
$painelSubtitulo = 'Painel Coordenação';
$painelRota      = 'coordenador';
$painelAbaAtiva  = $abaAtiva ?? 'calendario';
$painelAlerta    = $qtdPendentes > 0
    ? ['secao' => 'solicitacoes', 'qtd' => $qtdPendentes, 'texto' => 'pendente' . ($qtdPendentes > 1 ? 's' : '')]
    : null;
$painelMenu = [
    ['titulo' => null, 'itens' => [
        ['id' => 'calendario',   'icone' => 'bi-calendar3',       'label' => 'Calendário'],
        ['id' => 'kanban',       'icone' => 'bi-kanban',          'label' => 'Kanban de Aulas'],
        ['id' => 'solicitacoes', 'icone' => 'bi-inbox',           'label' => 'Solicitações', 'badge' => $qtdPendentes ?: null],
        ['id' => 'reservas',     'icone' => 'bi-calendar-check',  'label' => 'Reservas Aprovadas'],
        ['id' => 'quadro',       'icone' => 'bi-table',           'label' => 'Quadro de Horários'],
        ['id' => 'ensalamento',  'icone' => 'bi-door-open',       'label' => 'Ensalamento'],
        ['id' => 'cadastros',    'icone' => 'bi-database',        'label' => 'Cadastros'],
        ['id' => 'relatorios',   'icone' => 'bi-bar-chart-line',  'label' => 'Relatórios BI'],
    ]],
];
$painelRodape = '<div class="px-3 py-2 mt-2"><a href="/index.php?page=suporte" class="btn btn-outline-secondary w-100 rounded-pill btn-sm fw-semibold"><i class="bi bi-headset me-2"></i>Ir para Suporte</a></div>';
$painelHeadExtra = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
HTML;

include VIEWS_PATH . '/layouts/painel_open.php';
?>

<div class="content-section" id="calendario">
    <?php include __DIR__ . '/_calendario.php'; ?>
</div>
<div class="content-section" id="kanban" style="display:none;">
    <?php include __DIR__ . '/_kanban.php'; ?>
</div>
<div class="content-section" id="solicitacoes" style="display:none;">
    <?php include __DIR__ . '/_pendentes.php'; ?>
</div>
<div class="content-section" id="reservas" style="display:none;">
    <?php include __DIR__ . '/_reservas.php'; ?>
</div>
<div class="content-section" id="quadro" style="display:none;">
    <?php include __DIR__ . '/_quadro.php'; ?>
</div>
<div class="content-section" id="ensalamento" style="display:none;">
    <?php include __DIR__ . '/_ensalamento.php'; ?>
</div>
<div class="content-section" id="cadastros" style="display:none;">
    <?php include __DIR__ . '/_cadastros.php'; ?>
</div>
<div class="content-section" id="relatorios" style="display:none;">
    <?php include __DIR__ . '/_relatorios.php'; ?>
</div>

<?php
$painelScriptsExtra = 'let calendarCoord;

document.addEventListener("DOMContentLoaded", function () {
    initLabHubPanel({
        defaultSection: ' . json_encode($painelAbaAtiva) . ',
        onSectionShow(id) {
            if (id === "calendario" && calendarCoord) {
                setTimeout(() => calendarCoord.updateSize(), 100);
            }
        }
    });

    const calEl = document.getElementById("calendar");
    if (calEl) {
        calendarCoord = new FullCalendar.Calendar(calEl, {
            locale: "pt-br",
            initialView: "dayGridMonth",
            height: "auto",
            headerToolbar: { left: "prev,next today", center: "title", right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek" },
            events: ' . $eventosJson . ',
            eventClick(info) {
                document.getElementById("modalEventTitle").textContent = info.event.title;
                document.getElementById("modalEventTime").textContent = info.event.startStr || "";
                document.getElementById("modalEventLocal").innerHTML = info.event.extendedProps.local || "";
                new bootstrap.Modal(document.getElementById("modalEvento")).show();
            },
        });
        calendarCoord.render();
        if (document.getElementById("calendario")?.style.display !== "none") {
            setTimeout(() => calendarCoord.updateSize(), 100);
        }
    }

    document.querySelectorAll(".kanban-card").forEach(card => {
        card.addEventListener("dragstart", e => {
            e.dataTransfer.setData("id_aula", card.dataset.id);
            card.classList.add("opacity-50");
        });
        card.addEventListener("dragend", () => card.classList.remove("opacity-50"));
    });
    document.querySelectorAll(".kanban-col").forEach(col => {
        col.addEventListener("dragover", e => { e.preventDefault(); col.classList.add("kanban-dragover"); });
        col.addEventListener("dragleave", () => col.classList.remove("kanban-dragover"));
        col.addEventListener("drop", e => {
            e.preventDefault();
            col.classList.remove("kanban-dragover");
            const idAula = e.dataTransfer.getData("id_aula");
            const novoDia = col.dataset.dia;
            fetch("index.php?page=coordenador", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded", "X-CSRF-Token": CSRF_TOKEN },
                body: "action=mover_aula&id_aula=" + idAula + "&novo_dia=" + encodeURIComponent(novoDia) + "&_csrf=" + CSRF_TOKEN
            }).then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.error || "Erro ao mover."); });
        });
    });

    iniciarGraficos();
});

function iniciarGraficos() {
    const makeChart = (id, type, labels, data, label, color) => {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type,
            data: { labels, datasets: [{ label, data, backgroundColor: color, borderColor: color, borderWidth: 2, tension: 0.4, fill: type === "line" }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: type !== "pie" ? { y: { beginAtZero: true } } : {} }
        });
    };
    makeChart("graficoProfHoras", "bar", ' . json_encode($graficoProfNomes) . ', ' . json_encode($graficoProfHoras) . ', "Horas/Semana", "#6366f1");
    makeChart("graficoLabUso", "bar", ' . json_encode($graficoLabNomes) . ', ' . json_encode($graficoLabUso) . ', "Horas Usadas", "#10b981");
    makeChart("graficoCursos", "pie", ' . json_encode($graficoNomeCursos) . ', ' . json_encode($graficoHorasCursos) . ', "Horas por Curso", ["#6366f1","#10b981","#f59e0b","#ef4444","#3b82f6","#8b5cf6","#ec4899"]);
}';

include VIEWS_PATH . '/layouts/painel_close.php';
