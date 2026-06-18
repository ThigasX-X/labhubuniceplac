<?php
function renderizarCardEnsalamento($e, $badgeCor, $bordaClasse) { ?>
    <div class="col">
        <div class="card h-100 apple-ticket <?= $bordaClasse ?> p-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-bold text-dark text-truncate mb-0" style="max-width:80%;" title="<?= htmlspecialchars($e['disciplina']) ?>"><?= htmlspecialchars($e['disciplina']) ?></h5>
                <span class="badge <?= $badgeCor ?>"><?= $e['turno'] ?></span>
            </div>
            <p class="text-muted small mb-4">
                <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($e['curso'] ?? '') ?>
                <?php if (!empty($e['turma'])): ?> · Turma <strong><?= htmlspecialchars($e['turma']) ?></strong><?php endif; ?>
                <?php if (!empty($e['categoria'])): ?> · <?= htmlspecialchars($e['categoria']) ?><?php endif; ?>
            </p>
            <div class="d-flex align-items-center bg-light p-3 rounded border">
                <div class="flex-fill text-center"><span class="d-block small text-secondary fw-bold text-uppercase mb-1">Bloco</span><span class="fs-5 fw-bold text-dark"><?= htmlspecialchars($e['bloco'] ?? '-') ?></span></div>
                <div class="flex-fill text-center border-start"><span class="d-block small text-secondary fw-bold text-uppercase mb-1">Andar</span><span class="fs-5 fw-bold text-dark"><?= htmlspecialchars($e['andar'] ?? '-') ?></span></div>
                <div class="flex-fill text-center border-start"><span class="d-block small text-secondary fw-bold text-uppercase mb-1">Sala</span><span class="fs-4 fw-bold text-uniceplac"><?= htmlspecialchars($e['sala'] ?? '-') ?></span></div>
            </div>
        </div>
    </div>
<?php }
?>
<div class="d-flex justify-content-between align-items-end mb-4">
    <div><h4 class="text-uniceplac fw-bold mb-0"><i class="bi bi-building me-2"></i>Meu Ensalamento Fixo</h4><p class="text-muted mb-0 small">Salas definidas pela coordenação</p></div>
</div>
<div id="grid-ensalamento">
    <?php $totalEnsalamentos = count($ensalamentoMatutino) + count($ensalamentoVespertino) + count($ensalamentoNoturno); ?>
    <?php if ($totalEnsalamentos > 0): ?>
        <?php if ($ensalamentoMatutino): ?>
            <div class="turn-divider"><span class="turn-badge badge-matutino"><i class="bi bi-sunrise-fill me-2"></i>Turno Matutino</span></div>
            <div class="row row-cols-1 row-cols-lg-2 g-4 mb-4">
                <?php foreach ($ensalamentoMatutino as $e): renderizarCardEnsalamento($e, 'bg-warning text-dark', 'border-matutino'); endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($ensalamentoVespertino): ?>
            <div class="turn-divider"><span class="turn-badge badge-vespertino"><i class="bi bi-sun-fill me-2" style="color:var(--tarde-cor);"></i>Turno Vespertino</span></div>
            <div class="row row-cols-1 row-cols-lg-2 g-4 mb-4">
                <?php foreach ($ensalamentoVespertino as $e): renderizarCardEnsalamento($e, 'bg-warning text-dark', 'border-vespertino'); endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($ensalamentoNoturno): ?>
            <div class="turn-divider"><span class="turn-badge badge-noturno"><i class="bi bi-moon-stars-fill me-2" style="color:var(--noite-cor);"></i>Turno Noturno</span></div>
            <div class="row row-cols-1 row-cols-lg-2 g-4 mb-4">
                <?php foreach ($ensalamentoNoturno as $e): renderizarCardEnsalamento($e, 'bg-primary text-white', 'border-noturno'); endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="apple-ticket text-center p-5 shadow-sm text-muted"><i class="bi bi-info-circle fs-1 opacity-50 mb-3 d-block"></i> Sem salas fixas definidas.</div>
    <?php endif; ?>
</div>
