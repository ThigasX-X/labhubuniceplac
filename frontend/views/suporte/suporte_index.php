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
$painelScriptsExtra = 'document.addEventListener("DOMContentLoaded", function () {
    initLabHubPanel({ defaultSection: "sessao-mapa-diario" });
    setInterval(verificarSOS, 8000);
});

function verificarSOS() {
    fetch("index.php?page=api/check-sos-status")
        .then(r => r.json())
        .then(data => {
            const area = document.getElementById("area-chamados-dinamica");
            if (data.qtd_suporte > 0 && area) {
                area.innerHTML = data.html_suporte;
            } else if (area) {
                area.innerHTML = "";
            }
        }).catch(() => {});
}';

include VIEWS_PATH . '/layouts/painel_close.php';
