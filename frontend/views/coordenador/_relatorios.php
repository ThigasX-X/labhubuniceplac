<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="apple-kpi-card card border-0 shadow-sm p-3 h-100 text-center">
            <div class="fs-1 fw-black" style="color:#6366f1;"><?= number_format($taxaOcupacaoGlobal, 0) ?>%</div>
            <div class="text-muted small fw-semibold mt-1">Taxa de Ocupação</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="apple-kpi-card card border-0 shadow-sm p-3 h-100 text-center">
            <div class="fs-1 fw-black" style="color:#10b981;"><?= number_format($usoGlobal, 0) ?>h</div>
            <div class="text-muted small fw-semibold mt-1">Horas Totais de Uso</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="apple-kpi-card card border-0 shadow-sm p-3 h-100 text-center">
            <div class="fs-6 fw-bold text-truncate mt-2" style="color:#f59e0b;" title="<?= htmlspecialchars($labMaisUsado['nome']) ?>">
                <?= htmlspecialchars(mb_substr($labMaisUsado['nome'], 0, 18)) ?>
            </div>
            <div class="text-muted small fw-semibold"><?= number_format($labMaisUsado['horas'], 0) ?>h — Lab Mais Usado</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="apple-kpi-card card border-0 shadow-sm p-3 h-100 text-center">
            <div class="fs-6 fw-bold text-truncate mt-2 text-secondary" title="<?= htmlspecialchars($labMaisOcioso['nome']) ?>">
                <?= htmlspecialchars(mb_substr($labMaisOcioso['nome'], 0, 18)) ?>
            </div>
            <div class="text-muted small fw-semibold"><?= number_format($labMaisOcioso['horas'], 0) ?>h — Lab Mais Ocioso</div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-4 mb-4">
    <div class="col-12 col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-workspace me-2 text-primary"></i>Carga por Professor (h/semana)</h6>
            </div>
            <div class="card-body">
                <canvas id="graficoProfHoras" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart me-2 text-success"></i>Horas por Curso</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="graficoCursos" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2 text-info"></i>Uso de Laboratórios (horas)</h6>
            </div>
            <div class="card-body">
                <canvas id="graficoLabUso" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tabelas Detalhadas -->
<div class="row g-4">
    <div class="col-12 col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Relatório por Professor</h6>
                <button class="btn btn-sm btn-outline-secondary rounded-pill"
                        onclick="exportarTabelaParaCSV('tabela-rel-prof','Relatorio_Professores.csv')">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i>CSV
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table id="tabela-rel-prof" class="table table-hover align-middle mb-0 small">
                        <thead class="table-light sticky-top">
                            <tr><th class="ps-3 py-2">Professor</th><th>Horas/sem</th><th>Lab Principal</th><th class="pe-3">Sala</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relatorioProfessores as $rp): ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?= htmlspecialchars($rp['professor'] ?? '-') ?></td>
                                <td><span class="badge bg-primary rounded-pill"><?= number_format($rp['horas'] ?? 0, 1) ?>h</span></td>
                                <td class="text-muted"><?= htmlspecialchars($rp['lab'] ?? '-') ?></td>
                                <td class="pe-3 text-muted"><?= htmlspecialchars($rp['sala'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($relatorioProfessores)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">Sem dados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pc-display me-2 text-success"></i>Relatório por Laboratório</h6>
                <button class="btn btn-sm btn-outline-secondary rounded-pill"
                        onclick="exportarTabelaParaCSV('tabela-rel-lab','Relatorio_Laboratorios.csv')">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i>CSV
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                    <table id="tabela-rel-lab" class="table table-hover align-middle mb-0 small">
                        <thead class="table-light sticky-top">
                            <tr><th class="ps-3 py-2">Laboratório</th><th>Horas Uso</th><th>Horas Ociosas</th><th class="pe-3">Ocupação</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relatorioLabs as $rl): ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?= htmlspecialchars($rl['laboratorio'] ?? '-') ?></td>
                                <td><span class="badge bg-success rounded-pill"><?= number_format($rl['horas_uso'] ?? 0, 1) ?>h</span></td>
                                <td class="text-muted"><?= number_format($rl['horas_ocioso'] ?? 0, 1) ?>h</td>
                                <td class="pe-3">
                                    <?php $taxa = $rl['taxa_ocupacao'] ?? 0; ?>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-success" style="width:<?= min(100, $taxa) ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= number_format($taxa, 0) ?>%</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($relatorioLabs)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">Sem dados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportarTabelaParaCSV(tabelaId, nomeArquivo) {
    const tabela = document.getElementById(tabelaId);
    if (!tabela) return;
    const linhas = [...tabela.querySelectorAll('tr')];
    const csv = linhas.map(l => [...l.querySelectorAll('th,td')].map(c => '"' + c.innerText.replace(/"/g, '""') + '"').join(',')).join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = nomeArquivo; a.click();
    URL.revokeObjectURL(url);
}
</script>
