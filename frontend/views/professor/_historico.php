<div class="card shadow-sm border-0 mb-5" style="border-top:4px solid var(--roxo-uniceplac)!important;">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold d-flex align-items-center" style="color:var(--roxo-uniceplac);">
            <span class="header-icon bg-light" style="color:var(--roxo-uniceplac);"><i class="bi bi-clock-history fs-4"></i></span>Histórico de Solicitações
        </h5>
    </div>
    <div class="card-body p-0">
        <div id="tabela-historico-container">
            <?php if (count($minhasAlocacoes) > 0): ?>
                <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr><th class="ps-4 py-3">Data</th><th>Turno/Horário</th><th>Laboratório</th><th>Disciplina</th><th class="pe-4">Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($minhasAlocacoes as $linha): ?>
                                <tr>
                                    <td class="ps-4"><strong><?= date('d/m/Y', strtotime($linha['data_reserva'])) ?></strong></td>
                                    <td><?= htmlspecialchars($linha['turno']) ?><br><small class="text-muted"><?= htmlspecialchars($linha['periodo']) ?></small></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($linha['laboratorio']) ?></td>
                                    <td><?= htmlspecialchars($linha['disciplina']) ?></td>
                                    <td class="pe-4">
                                        <?php if ($linha['status'] === 'aprovado'): ?>
                                            <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle me-1"></i>Aprovado</span>
                                        <?php elseif ($linha['status'] === 'pendente'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3"><i class="bi bi-x-circle me-1"></i>Rejeitado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><i class="bi bi-folder2-open fs-1 text-muted opacity-50 d-block mb-2"></i><p class="text-muted mb-0">Nenhum histórico.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
