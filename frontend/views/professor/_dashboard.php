<?php
function renderizarCardAulaProfessor($aula, $hoje, $chavesRetiradas, $bordaClasse, $iconeTurno) {
    $badgeHoje = ($aula['data_reserva'] == $hoje) ? '<span class="badge bg-danger float-end">HOJE</span>' : '';
    $jaRetirou = isset($chavesRetiradas[$aula['id']]);

    $labDet = [];
    if (!empty($aula['lab_local'])) $labDet[] = htmlspecialchars($aula['lab_local']);
    if (!empty($aula['lab_andar'])) $labDet[] = 'Andar ' . htmlspecialchars($aula['lab_andar']);
    $labStr = $labDet ? "<div class='text-muted mb-1' style='font-size:.8rem;'><i class='bi bi-geo-alt me-1'></i>" . implode(' - ', $labDet) . "</div>" : '';

    $salaStr = '';
    if (!empty($aula['sala']) || !empty($aula['bloco'])) {
        $salaStr = "<div class='text-success mb-3' style='font-size:.8rem;'><i class='bi bi-door-open me-1'></i>Sala " . htmlspecialchars($aula['sala'] ?? '-') . " (Bl " . htmlspecialchars($aula['bloco'] ?? '-') . ")</div>";
    }
    ?>
    <div class="col">
        <div class="card h-100 apple-ticket <?= $bordaClasse ?>">
            <div class="card-body p-3">
                <?= $badgeHoje ?>
                <h5 class="mb-1 text-dark fw-bold text-truncate" title="<?= htmlspecialchars($aula['laboratorio']) ?>">
                    <i class="bi bi-pc-display me-2 text-primary"></i><?= htmlspecialchars($aula['laboratorio']) ?>
                </h5>
                <?= $labStr . $salaStr ?>
                <div class="mb-2 text-primary fw-bold d-flex align-items-center small">
                    <i class="bi bi-calendar-event me-2"></i><?= date('d/m/Y', strtotime($aula['data_reserva'])) ?> | <?= $iconeTurno ?> <?= htmlspecialchars($aula['periodo']) ?>
                </div>
                <div class="text-truncate d-flex align-items-center mb-3 small text-secondary">
                    <i class="bi bi-book-half me-2"></i><?= htmlspecialchars($aula['disciplina']) ?>
                </div>
                <?php if ($aula['data_reserva'] == $hoje): ?>
                    <hr class="my-3 opacity-25">
                    <?php if ($jaRetirou): ?>
                        <div class="apple-tag mb-2"><div class="apple-dot"></div> EM USO (Sua Aula)</div>
                        <button type="button" class="apple-btn apple-btn-danger heartbeat" data-bs-toggle="modal" data-bs-target="#modalSOS<?= $aula['id'] ?>">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Chamado SOS
                        </button>
                    <?php else: ?>
                        <button type="button" class="apple-btn apple-btn-success mb-2" data-bs-toggle="modal" data-bs-target="#modalChave<?= $aula['id'] ?>">
                            <i class="bi bi-key-fill me-2"></i> Retirar Chave
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($aula['data_reserva'] == $hoje && !$jaRetirou): ?>
    <div class="modal fade" id="modalChave<?= $aula['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-success" style="border-width:3px;border-radius:20px!important;">
                <div class="modal-header bg-success text-white border-0" style="border-top-left-radius:16px;border-top-right-radius:16px;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i> Retirar Chave</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" action="/index.php?page=professor">
                        <input type="hidden" name="registrar_retirada" value="1">
                        <input type="hidden" name="id_agendamento" value="<?= $aula['id'] ?>">
                        <input type="hidden" name="laboratorio_chave" value="<?= htmlspecialchars($aula['laboratorio']) ?>">
                        <input type="hidden" name="turno_aula" value="<?= htmlspecialchars($aula['turno']) ?>">
                        <p class="mb-1 text-secondary">Lab: <strong class="text-dark"><?= htmlspecialchars($aula['laboratorio']) ?></strong></p>
                        <div class="mb-3 mt-3"><label class="form-label fw-bold small">Seu Celular:</label><input type="text" class="form-control rounded-pill px-3" name="celular" placeholder="(61) 90000-0000" required></div>
                        <div class="mb-3"><label class="form-label fw-bold small">Hora Prevista Devolução:</label><input type="time" class="form-control rounded-pill px-3" name="hora_devolucao_prevista" required></div>
                        <div class="mb-4"><label class="form-label fw-bold small">Técnico que entregou:</label><input type="text" class="form-control rounded-pill px-3" name="funcionario_entrega" required></div>
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i> Confirmar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($aula['data_reserva'] == $hoje && $jaRetirou): ?>
    <div class="modal fade" id="modalSOS<?= $aula['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger" style="border-width:3px;border-radius:20px!important;">
                <div class="modal-header bg-danger text-white border-0" style="border-top-left-radius:16px;border-top-right-radius:16px;">
                    <h5 class="modal-title fw-bold"><i class="bi bi-headset me-2"></i> Chamado SOS</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" action="/index.php?page=professor">
                        <input type="hidden" name="acao_sos" value="1">
                        <input type="hidden" name="laboratorio_sos" value="<?= htmlspecialchars($aula['laboratorio']) ?>">
                        <p class="text-secondary small mb-3">Lab: <strong class="text-dark"><?= htmlspecialchars($aula['laboratorio']) ?></strong></p>
                        <div class="mb-4"><label class="form-label fw-bold">Problema:</label><textarea class="form-control" style="border-radius:15px;" name="mensagem_sos" rows="4" placeholder="O PC não liga..." required></textarea></div>
                        <button type="submit" class="btn btn-danger w-100 fw-bold py-2 rounded-pill"><i class="bi bi-send me-1"></i> Enviar Alerta</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h4 class="text-uniceplac fw-bold mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Onde é minha aula prática?</h4>
        <p class="text-muted mb-0 small">Próximas reservas de laboratório e controle de chaves</p>
    </div>
</div>

<div id="grid-proximas-aulas">
    <?php $totalProximas = count($proximasMatutino) + count($proximasVespertino) + count($proximasNoturno); ?>
    <?php if ($totalProximas > 0): ?>
        <?php if ($proximasMatutino): ?>
            <div class="turn-divider"><span class="turn-badge badge-matutino"><i class="bi bi-sunrise-fill me-2"></i>Turno Matutino</span></div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                <?php foreach ($proximasMatutino as $aula): renderizarCardAulaProfessor($aula, $hoje, $chavesRetiradas, 'border-matutino', '<i class="bi bi-sunrise-fill text-warning"></i>'); endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($proximasVespertino): ?>
            <div class="turn-divider"><span class="turn-badge badge-vespertino"><i class="bi bi-sun-fill me-2" style="color:var(--tarde-cor);"></i>Turno Vespertino</span></div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                <?php foreach ($proximasVespertino as $aula): renderizarCardAulaProfessor($aula, $hoje, $chavesRetiradas, 'border-vespertino', '<i class="bi bi-sun-fill" style="color:var(--tarde-cor);"></i>'); endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($proximasNoturno): ?>
            <div class="turn-divider"><span class="turn-badge badge-noturno"><i class="bi bi-moon-stars-fill me-2" style="color:var(--noite-cor);"></i>Turno Noturno</span></div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
                <?php foreach ($proximasNoturno as $aula): renderizarCardAulaProfessor($aula, $hoje, $chavesRetiradas, 'border-noturno', '<i class="bi bi-moon-stars-fill" style="color:var(--noite-cor);"></i>'); endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="apple-ticket text-center p-5 shadow-sm text-muted">
            <i class="bi bi-calendar-x fs-1 opacity-50 mb-3 d-block"></i> Nenhuma aula prática futura aprovada.
        </div>
    <?php endif; ?>
</div>
