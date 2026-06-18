<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #dc3545;">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h5 class="mb-0 fw-bold d-flex align-items-center text-dark">
            <i class="bi bi-exclamation-circle-fill text-danger me-3 fs-4"></i>
            Chamados Pendentes
            <?php if ($qtdAlertas > 0): ?>
                <span class="badge bg-danger rounded-pill ms-2"><?= $qtdAlertas ?></span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">Data/Hora</th>
                        <th>Professor</th>
                        <th>Local</th>
                        <th>Problema</th>
                        <th class="pe-4 text-end">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alertasSuporte as $ch): ?>
                    <tr>
                        <td class="ps-4 small"><strong><?= date('d/m/Y H:i', strtotime($ch['data_hora'])) ?></strong></td>
                        <td class="fw-semibold"><?= htmlspecialchars($ch['professor_nome']) ?></td>
                        <td><?= htmlspecialchars($ch['laboratorio']) ?></td>
                        <td class="text-secondary small"><?= htmlspecialchars(mb_substr($ch['mensagem'], 0, 80)) ?><?= mb_strlen($ch['mensagem']) > 80 ? '...' : '' ?></td>
                        <td class="pe-4 text-end">
                            <form method="POST" action="/index.php?page=suporte" class="d-inline">
                                <input type="hidden" name="id_chamado" value="<?= $ch['id'] ?>">
                                <button type="submit" name="resolver_chamado" class="btn btn-sm btn-success rounded-pill fw-semibold">
                                    <i class="bi bi-check2 me-1"></i>Resolver
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($alertasSuporte)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-check-circle text-success me-1"></i> Nenhum chamado pendente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
