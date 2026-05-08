<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #198754;">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h5 class="mb-3 mb-md-0 fw-bold d-flex align-items-center text-dark"><i class="bi bi-headset text-success me-3 fs-4"></i> Chamados Atendidos</h5>
        <button class="btn btn-outline-success fw-bold shadow-sm rounded-pill px-4" onclick="exportarTabelaParaCSV('tabela-historico-chamados','Relatorio_SOS_Uniceplac.csv')">
            <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Baixar CSV
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
            <table id="tabela-historico-chamados" class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr><th class="ps-4 py-3">Data/Hora</th><th>Laboratório</th><th>Professor</th><th class="pe-4">Problema Relatado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($historicoChamados as $cham): ?>
                        <tr>
                            <td class="ps-4"><strong><?= date('d/m/Y H:i', strtotime($cham['data_hora'])) ?></strong></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($cham['laboratorio']) ?></td>
                            <td><?= htmlspecialchars($cham['professor_nome']) ?></td>
                            <td class="pe-4 text-secondary"><?= htmlspecialchars(mb_substr($cham['mensagem'], 0, 80)) ?><?= mb_strlen($cham['mensagem']) > 80 ? '...' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$historicoChamados): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Nenhum chamado resolvido ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
