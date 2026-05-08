<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #0dcaf0;">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h5 class="mb-3 mb-md-0 fw-bold d-flex align-items-center text-dark"><i class="bi bi-key text-info me-3 fs-4"></i> Histórico / Log de Chaves</h5>
        <button class="btn btn-outline-success fw-bold shadow-sm rounded-pill px-4" onclick="exportarTabelaParaCSV('tabela-historico-chaves','Relatorio_Chaves_Uniceplac.csv')">
            <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Baixar CSV
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
            <table id="tabela-historico-chaves" class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr><th class="ps-4 py-3">Data</th><th>Laboratório</th><th>Professor</th><th>Hr. Retirada</th><th>Hr. Prevista</th><th>Hr. Real</th><th>Téc. Entregou</th><th>Téc. Recebeu</th><th class="pe-4">Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($historicoChaves as $log): ?>
                        <tr>
                            <td class="ps-4"><strong><?= date('d/m/Y', strtotime($log['data_uso'])) ?></strong></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($log['laboratorio']) ?></td>
                            <td><?= htmlspecialchars($log['professor_nome']) ?><br><small class="text-muted"><?= htmlspecialchars($log['celular']) ?></small></td>
                            <td class="text-primary fw-bold"><?= date('H:i', strtotime($log['hora_retirada'])) ?></td>
                            <td><?= date('H:i', strtotime($log['hora_devolucao_prevista'])) ?></td>
                            <td class="text-primary fw-bold"><?= ($log['status'] === 'devolvido' && !empty($log['hora_devolucao_real'])) ? date('H:i', strtotime($log['hora_devolucao_real'])) : '-' ?></td>
                            <td><small><?= htmlspecialchars($log['funcionario_entrega']) ?></small></td>
                            <td><small><?= $log['funcionario_recebimento'] ? htmlspecialchars($log['funcionario_recebimento']) : '-' ?></small></td>
                            <td class="pe-4">
                                <?php if ($log['status'] === 'devolvido'): ?>
                                    <span class="badge bg-success rounded-pill px-3">Devolvido</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-3">Em Uso</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$historicoChaves): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">Nenhum registro.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
