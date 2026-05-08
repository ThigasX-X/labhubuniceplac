<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #f59e0b;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold d-flex align-items-center text-dark">
            <i class="bi bi-inbox text-warning me-3 fs-4"></i> Solicitações Pendentes
            <?php if ($qtdPendentes > 0): ?>
                <span class="badge bg-danger rounded-pill ms-2"><?= $qtdPendentes ?></span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($reservasPendentes)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
                Nenhuma solicitação pendente.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">Data</th>
                        <th>Professor</th>
                        <th>Laboratório</th>
                        <th>Turno / Período</th>
                        <th>Disciplina</th>
                        <th class="pe-4 text-center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservasPendentes as $r): ?>
                    <tr>
                        <td class="ps-4"><strong><?= date('d/m/Y', strtotime($r['data_reserva'])) ?></strong></td>
                        <td><?= htmlspecialchars($r['professor'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($r['laboratorio'] ?? '-') ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['turno']) ?></span> <small class="text-muted"><?= htmlspecialchars($r['periodo']) ?></small></td>
                        <td class="text-muted"><?= htmlspecialchars($r['disciplina'] ?? '-') ?></td>
                        <td class="pe-4 text-center">
                            <form method="POST" action="/index.php?page=coordenador" class="d-inline">
                                <input type="hidden" name="id_agendamento" value="<?= $r['id'] ?>">
                                <input type="hidden" name="acao_reserva" value="aprovar">
                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 me-1 fw-semibold">
                                    <i class="bi bi-check-lg me-1"></i>Aprovar
                                </button>
                            </form>
                            <form method="POST" action="/index.php?page=coordenador" class="d-inline">
                                <input type="hidden" name="id_agendamento" value="<?= $r['id'] ?>">
                                <input type="hidden" name="acao_reserva" value="rejeitar">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                    <i class="bi bi-x-lg me-1"></i>Rejeitar
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Nova Reserva Avulsa pelo Coordenador -->
<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #6366f1;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold d-flex align-items-center text-dark">
            <i class="bi bi-plus-circle text-primary me-3 fs-4"></i> Nova Reserva Avulsa
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" action="/index.php?page=coordenador" class="row g-3">
            <input type="hidden" name="agendar_lab_coord" value="1">
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Professor</label>
                <select name="id_professor" class="form-select rounded-3" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($professores as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Laboratório</label>
                <select name="id_laboratorio" class="form-select rounded-3" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($laboratoriosCadastrados as $lab): ?>
                        <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Disciplina</label>
                <select name="id_disciplina" class="form-select rounded-3" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($disciplinas as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Data</label>
                <input type="date" name="data_reserva" class="form-control rounded-3" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Turno</label>
                <select name="turno" class="form-select rounded-3" required>
                    <option value="">Selecione...</option>
                    <option>Manhã</option><option>Tarde</option><option>Noite</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Período</label>
                <select name="periodo" class="form-select rounded-3" required>
                    <option value="">Selecione...</option>
                    <option>1º</option><option>2º</option><option>3º</option><option>4º</option>
                    <option>5º</option><option>6º</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Observações (opcional)</label>
                <textarea name="observacoes" class="form-control rounded-3" rows="2" placeholder="Contexto da reserva..."></textarea>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="bi bi-check2-circle me-2"></i>Criar Reserva Aprovada
                </button>
            </div>
        </form>
    </div>
</div>
