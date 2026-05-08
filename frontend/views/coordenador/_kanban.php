<?php
$diasSemana = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$turnosCores = ['Manhã' => 'text-warning', 'Tarde' => 'text-primary', 'Noite' => 'text-info'];
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-kanban text-primary me-2"></i>Kanban de Aulas</h4>
        <p class="text-muted small mb-0">Arraste os cards para reorganizar o dia de uma aula.</p>
    </div>
    <!-- Seletor de Quadro -->
    <form method="GET" action="/index.php" class="d-flex gap-2 align-items-center">
        <input type="hidden" name="page" value="coordenador">
        <input type="hidden" name="#" value="kanban">
        <select name="q_id" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()" style="min-width:200px;">
            <?php foreach ($listaQuadros as $q): ?>
                <option value="<?= $q['id'] ?>" <?= $q['id'] == $quadroSelecionado ? 'selected' : '' ?>>
                    <?= htmlspecialchars($q['nome']) ?> — <?= htmlspecialchars($q['periodo_letivo']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!$quadroSelecionado || empty($todasAulas)): ?>
    <div class="alert alert-info rounded-3"><i class="bi bi-info-circle me-2"></i>Nenhuma aula cadastrada no quadro. Acesse <a href="#quadro" onclick="showSection('quadro')" class="alert-link">Quadro de Horários</a> para adicionar.</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($diasSemana as $dia): ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-2">
                <i class="bi bi-calendar-day me-2 text-primary"></i><?= $dia ?>
                <span class="badge bg-secondary rounded-pill ms-2"><?= count($aulasDiaSemana[$dia] ?? []) ?></span>
            </div>
            <div class="card-body p-2 kanban-col" data-dia="<?= $dia ?>" style="min-height:200px;">
                <?php foreach (($aulasDiaSemana[$dia] ?? []) as $aula): ?>
                <div class="kanban-card card border-0 shadow-sm mb-2 p-2 rounded-3" draggable="true" data-id="<?= $aula['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="fw-semibold small"><?= htmlspecialchars($aula['disc_nome'] ?? 'Disciplina') ?></span>
                        <span class="badge rounded-pill <?= $turnosCores[$aula['turno']] ?? 'text-muted' ?> bg-light small"><?= htmlspecialchars($aula['turno']) ?></span>
                    </div>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($aula['prof_nome'] ?? 'EAD') ?>
                    </div>
                    <div class="text-muted small">
                        <?php if (!empty($aula['lab_nome'])): ?>
                            <i class="bi bi-pc-display me-1"></i><?= htmlspecialchars($aula['lab_nome']) ?>
                        <?php elseif (!empty($aula['sala'])): ?>
                            <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($aula['sala']) ?>
                        <?php endif; ?>
                        <span class="ms-2 text-secondary"><?= htmlspecialchars($aula['horario'] ?? '') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($aulasDiaSemana[$dia])): ?>
                    <div class="text-center text-muted py-4 small"><i class="bi bi-inbox fs-4 d-block mb-1"></i>Sem aulas</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
