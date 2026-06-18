<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h4 class="text-uniceplac fw-bold mb-0"><i class="bi bi-headset me-2"></i>Chamados ao Suporte</h4>
        <p class="text-muted mb-0 small">Abra solicitações técnicas e acompanhe o andamento</p>
    </div>
    <?php if ($qtdChamadosPendentes > 0): ?>
        <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><?= $qtdChamadosPendentes ?> aguardando suporte</span>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-5">
        <div class="card apple-ticket border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Novo Chamado</h5>
                <form method="POST" action="/index.php?page=professor">
                    <input type="hidden" name="abrir_chamado" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Local / Laboratório</label>
                        <select name="local_chamado" class="form-select rounded-3" required>
                            <option value="">Selecione ou descreva abaixo...</option>
                            <optgroup label="Laboratórios">
                                <?php foreach ($laboratorios as $lab): ?>
                                    <option value="Lab: <?= htmlspecialchars($lab['nome']) ?>"><?= htmlspecialchars($lab['nome']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php if (!empty($locaisEnsalamento)): ?>
                            <optgroup label="Minhas Salas">
                                <?php foreach ($locaisEnsalamento as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <option value="Outro local">Outro local (descreva na mensagem)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Descrição do problema</label>
                        <textarea name="mensagem_chamado" class="form-control rounded-3" rows="4" required
                                  placeholder="Ex: Projetor não liga, computadores sem internet, ar-condicionado..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-uniceplac w-100 rounded-pill fw-semibold py-2">
                        <i class="bi bi-send me-2"></i>Enviar Chamado
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="bi bi-list-check me-2"></i>Meus Chamados</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                    <table class="table table-hover align-middle mb-0" id="tabela-meus-chamados">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Data</th>
                                <th>Local</th>
                                <th>Status</th>
                                <th class="pe-3">Problema</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meusChamados as $ch): ?>
                            <tr>
                                <td class="ps-3 small"><?= date('d/m/Y H:i', strtotime($ch['data_hora'])) ?></td>
                                <td class="small fw-semibold"><?= htmlspecialchars($ch['laboratorio']) ?></td>
                                <td>
                                    <?php if ($ch['status'] === 'pendente'): ?>
                                        <span class="badge bg-warning text-dark">Pendente</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Resolvido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 small text-muted"><?= htmlspecialchars(mb_substr($ch['mensagem'], 0, 60)) ?><?= mb_strlen($ch['mensagem']) > 60 ? '...' : '' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($meusChamados)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">Nenhum chamado aberto ainda.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
