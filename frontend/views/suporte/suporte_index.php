<?php
$nomeUsuario     = $_SESSION['nome'] ?? 'Suporte';
$painelTitulo    = 'Suporte TI | UNICEPLAC';
$painelSubtitulo = 'Painel Suporte';
$painelRota      = 'suporte';
$painelAbaAtiva  = 'sessao-mapa-diario';
$painelAlerta    = $qtdAlertas > 0
    ? ['secao' => 'sessao-chamados-pendentes', 'qtd' => $qtdAlertas, 'texto' => 'chamado' . ($qtdAlertas > 1 ? 's' : '')]
    : null;
$painelMenu = [
    ['titulo' => 'Operacional', 'itens' => [
        ['id' => 'sessao-mapa-diario',        'icone' => 'bi-speedometer2', 'label' => 'Mapa Diário'],
        ['id' => 'sessao-chamados-pendentes', 'icone' => 'bi-headset',      'label' => 'Chamados Pendentes', 'badge' => $qtdAlertas ?: null],
        ['id' => 'sessao-mapa-ensalamento',   'icone' => 'bi-geo-alt',      'label' => 'Mapa de Ensalamento'],
        ['id' => 'sessao-labs',               'icone' => 'bi-pc-display',   'label' => 'Laboratórios'],
    ]],
    ['titulo' => 'Relatórios', 'itens' => [
        ['id' => 'sessao-historico-chaves',   'icone' => 'bi-key',     'label' => 'Histórico de Chaves'],
        ['id' => 'sessao-historico-chamados', 'icone' => 'bi-check2-circle', 'label' => 'Chamados Atendidos'],
    ]],
];
$painelRodape = ($_SESSION['perfil'] ?? '') === 'coordenador'
    ? '<div class="px-3 py-2 mt-2"><a href="/index.php?page=coordenador" class="btn btn-outline-secondary w-100 rounded-pill btn-sm fw-semibold"><i class="bi bi-arrow-left-circle me-2"></i>Voltar Coordenação</a></div>'
    : '';

include VIEWS_PATH . '/layouts/painel_open.php';
?>

<div id="area-chamados-dinamica" class="mb-3"></div>

<div id="sessao-mapa-diario" class="content-section">
    <?php include VIEWS_PATH . '/suporte/_mapa_diario.php'; ?>
</div>
<div id="sessao-chamados-pendentes" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/suporte/_chamados_pendentes.php'; ?>
</div>
<div id="sessao-mapa-ensalamento" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/suporte/_mapa_ensalamento.php'; ?>
</div>
<div id="sessao-labs" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/suporte/_labs.php'; ?>
</div>
<div id="sessao-historico-chaves" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/suporte/_historico_chaves.php'; ?>
</div>
<div id="sessao-historico-chamados" class="content-section" style="display:none;">
    <?php include VIEWS_PATH . '/suporte/_historico_chamados.php'; ?>
</div>

<?php
$painelScriptsExtra = 'let qtdChamadosAnterior = null;
let audioLiberado = false;
const somAlerta = new Audio("assets/sounds/alerta-chamado.mp3");
somAlerta.preload = "auto";

// Navegadores bloqueiam áudio automático até o usuário interagir com a página.
// No primeiro clique/tecla, "destravamos" o som tocando-o mudo uma vez; depois
// disso o play() disparado pelo polling passa a funcionar.
function liberarAudio() {
    somAlerta.muted = true;
    somAlerta.play().then(() => {
        somAlerta.pause();
        somAlerta.currentTime = 0;
        somAlerta.muted = false;
        audioLiberado = true;
    }).catch(() => { somAlerta.muted = false; });
    document.removeEventListener("click", liberarAudio);
    document.removeEventListener("keydown", liberarAudio);
}
document.addEventListener("click", liberarAudio);
document.addEventListener("keydown", liberarAudio);

document.addEventListener("DOMContentLoaded", function () {
    initLabHubPanel({ defaultSection: "sessao-mapa-diario" });
    verificarSOS();                 // estabelece a contagem inicial sem alertar
    setInterval(verificarSOS, 8000);
});

function verificarSOS() {
    fetch("index.php?page=api/check-sos-status")
        .then(r => r.json())
        .then(data => {
            // Alerta sonoro só quando CHEGA um chamado novo (não no 1º carregamento).
            if (qtdChamadosAnterior !== null && data.qtd_suporte > qtdChamadosAnterior) {
                somAlerta.currentTime = 0;
                somAlerta.play().catch(() => {}); // ainda sem gesto do usuário: ignora
            }
            qtdChamadosAnterior = data.qtd_suporte;

            const area = document.getElementById("area-chamados-dinamica");
            if (area) {
                area.innerHTML = data.qtd_suporte > 0 ? data.html_suporte : "";
            }
        }).catch(() => {});
}';

include VIEWS_PATH . '/layouts/painel_close.php';
