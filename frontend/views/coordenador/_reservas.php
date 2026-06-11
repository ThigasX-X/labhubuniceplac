<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #10b981;">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h5 class="mb-3 mb-md-0 fw-bold d-flex align-items-center text-dark">
            <i class="bi bi-calendar-check text-success me-3 fs-4"></i> Reservas Aprovadas
        </h5>
        <button class="btn btn-outline-success fw-bold shadow-sm rounded-pill px-4"
                onclick="exportarTabelaParaCSV('tabela-reservas-coord','Reservas_Aprovadas.csv')">
            <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Baixar CSV
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
            <table id="tabela-reservas-coord" class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="ps-4 py-3">Data</th>
                        <th>Laboratório</th>
                        <th>Professor</th>
                        <th>Disciplina</th>
                        <th>Turno</th>
                        <th>Período</th>
                        <th class="pe-4 text-center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agendamentosAprovados as $ag): ?>
                    <tr>
                        <td class="ps-4"><strong><?= date('d/m/Y', strtotime($ag['data_reserva'])) ?></strong></td>
                        <td class="fw-semibold"><?= htmlspecialchars($ag['laboratorio'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($ag['professor'] ?? '-') ?></td>
                        <td class="text-muted"><?= htmlspecialchars($ag['disciplina'] ?? '-') ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ag['turno']) ?></span></td>
                        <td><?= htmlspecialchars($ag['periodo']) ?></td>
                        <td class="pe-4 text-center">
                            <button class="btn btn-sm btn-outline-primary rounded-pill me-1"
                                    onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($ag)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="/index.php?page=coordenador" class="d-inline"
                                  onsubmit="return confirm('Cancelar esta reserva?')">
                                <input type="hidden" name="id_agendamento" value="<?= $ag['id'] ?>">
                                <input type="hidden" name="cancelar_agendamento" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($agendamentosAprovados)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">Nenhuma reserva aprovada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Histórico completo -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h5 class="mb-3 mb-md-0 fw-bold d-flex align-items-center text-dark">
            <i class="bi bi-clock-history text-secondary me-3 fs-4"></i> Histórico Completo
        </h5>
        <button class="btn btn-outline-secondary fw-bold shadow-sm rounded-pill px-4"
                onclick="exportarTabelaParaCSV('tabela-historico-coord','Historico_Reservas.csv')">
            <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Baixar CSV
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:500px;overflow-y:auto;">
            <table id="tabela-historico-coord" class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr><th class="ps-4 py-3">Data</th><th>Laboratório</th><th>Professor</th><th>Turno</th><th class="pe-4">Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($historicoCompleto as $h): ?>
                    <tr>
                        <td class="ps-4"><strong><?= date('d/m/Y', strtotime($h['data_reserva'])) ?></strong></td>
                        <td class="fw-semibold"><?= htmlspecialchars($h['laboratorio'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($h['professor'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($h['turno']) ?></td>
                        <td class="pe-4">
                            <?php
                            $badgeMap = ['aprovado' => 'success', 'pendente' => 'warning text-dark', 'rejeitado' => 'danger', 'cancelado' => 'secondary'];
                            $badge = $badgeMap[$h['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $badge ?> rounded-pill px-3"><?= ucfirst($h['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($historicoCompleto)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Nenhum registro no histórico.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Editar Reserva -->
<div class="modal fade" id="modalEditarReserva" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Editar Reserva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/index.php?page=coordenador" id="formEditarReserva">
                    <input type="hidden" name="editar_agendamento_coord" value="1">
                    <input type="hidden" name="id_agendamento" id="editId">
                    <input type="hidden" name="id_professor" id="editProf">
                    <input type="hidden" name="id_disciplina" id="editDisc">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Data</label>
                        <input type="date" name="data_reserva" id="editData" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Turno</label>
                        <select name="turno" id="editTurno" class="form-select rounded-3" required>
                            <option>Matutino</option><option>Vespertino</option><option>Noturno</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Período</label>
                        <select name="periodo" id="editPeriodo" class="form-select rounded-3" required>
                            <option>1º e 2º Horários</option><option>1º Horário</option><option>2º Horário</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Laboratório</label>
                        <select name="id_laboratorio" id="editLab" class="form-select rounded-3" required>
                            <?php foreach ($laboratoriosCadastrados as $lab): ?>
                                <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">Salvar Alterações</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalEditar(ag) {
    document.getElementById('editId').value      = ag.id;
    document.getElementById('editProf').value    = ag.id_professor;
    document.getElementById('editDisc').value    = ag.id_disciplina;
    document.getElementById('editData').value    = ag.data_reserva;
    document.getElementById('editTurno').value   = ag.turno;
    document.getElementById('editPeriodo').value = ag.periodo;
    document.getElementById('editLab').value     = ag.id_laboratorio;
    new bootstrap.Modal(document.getElementById('modalEditarReserva')).show();
}

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
