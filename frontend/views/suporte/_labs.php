<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid var(--roxo-uniceplac);">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold d-flex align-items-center text-dark"><i class="bi bi-pc-display text-secondary me-3 fs-4"></i> Relação de Laboratórios</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light sticky-top"><tr><th class="ps-4 py-3">ID</th><th>Nome</th><th>Capacidade</th></tr></thead>
                <tbody>
                    <?php foreach ($listaLaboratorios as $lab): ?>
                        <tr>
                            <td class="ps-4 text-muted">#<?= $lab['id'] ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($lab['nome']) ?></td>
                            <td class="text-secondary"><i class="bi bi-people-fill me-2"></i><?= $lab['capacidade'] ?> lugares</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$listaLaboratorios): ?>
                        <tr><td colspan="3" class="text-center py-5 text-muted">Nenhum laboratório cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
