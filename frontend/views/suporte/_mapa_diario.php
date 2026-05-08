<?php
function renderizarCardSuporte($l, $chavesEmUso, $borda) {
    $nomeLab     = $l['laboratorio'];
    $idAgend     = $l['id'];
    $chaveAtiva  = $chavesEmUso[$idAgend] ?? null;
    $modalId     = $idAgend . '_' . uniqid();
    $estaAtrasado = false;
    $linkWpp      = '#';

    if ($chaveAtiva) {
        $estaAtrasado = (date('H:i:s') > $chaveAtiva['hora_devolucao_prevista']);
        $numero = preg_replace('/[^0-9]/', '', $chaveAtiva['celular']);
        if (strlen($numero) <= 11) $numero = '55' . $numero;
        $msgWpp  = urlencode("Olá, Prof. {$chaveAtiva['professor_nome']}. Aqui é do Suporte TI UNICEPLAC. A chave do {$nomeLab} ainda consta com você. Já finalizou a aula?");
        $linkWpp = "https://wa.me/{$numero}?text={$msgWpp}";
    }
    ?>
    <div class="col">
        <div class="apple-ticket card h-100 <?= $borda ?> <?= $estaAtrasado ? 'border border-danger border-2' : '' ?> p-3 position-relative">
            <div class="d-flex justify-content-between align-items-start">
                <h6 class="fw-bold <?= $estaAtrasado ? 'text-danger' : 'text-dark' ?> mb-2 text-truncate"><?= htmlspecialchars($nomeLab) ?></h6>
                <?php if ($estaAtrasado): ?><i class="bi bi-exclamation-triangle-fill text-danger heartbeat fs-5"></i><?php endif; ?>
            </div>
            <div class="small mb-1 text-primary fw-bold"><i class="bi bi-clock-history me-2"></i><?= htmlspecialchars($l['periodo']) ?></div>
            <div class="small mb-1"><i class="bi bi-person me-2 text-muted"></i><?= htmlspecialchars($l['professor']) ?></div>
            <div class="small text-secondary <?= $chaveAtiva ? 'mb-3' : '' ?>"><i class="bi bi-book me-2 text-muted"></i><?= htmlspecialchars($l['disciplina']) ?></div>

            <?php if ($chaveAtiva): ?>
                <hr class="my-2 opacity-25">
                <?php if ($estaAtrasado): ?>
                    <div class="apple-tag late"><div class="apple-dot late"></div> ATRASADO</div>
                    <button class="apple-btn apple-btn-danger heartbeat mt-2" data-bs-toggle="modal" data-bs-target="#modalChave<?= $modalId ?>"><i class="bi bi-key-fill me-2"></i> Cobrar Chave</button>
                <?php else: ?>
                    <div class="apple-tag in-use"><div class="apple-dot in-use"></div> EM USO</div>
                    <button class="apple-btn apple-btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalChave<?= $modalId ?>"><i class="bi bi-key-fill me-2"></i> Receber Chave</button>
                <?php endif; ?>

                <div class="modal fade" id="modalChave<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content <?= $estaAtrasado ? 'border-danger' : 'border-primary' ?>" style="border-width:3px;border-radius:20px;">
                            <div class="modal-header <?= $estaAtrasado ? 'bg-danger' : 'bg-primary' ?> text-white border-0" style="border-top-left-radius:16px;border-top-right-radius:16px;">
                                <h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i> Detalhes da Retirada</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <h5 class="fw-bold text-dark mb-3 border-bottom pb-2"><?= htmlspecialchars($nomeLab) ?></h5>
                                <p class="mb-1 text-secondary small"><strong>Professor:</strong> <span class="text-dark"><?= htmlspecialchars($chaveAtiva['professor_nome']) ?></span></p>
                                <p class="mb-1 text-secondary small"><strong>Entregue por:</strong> <span class="text-dark"><?= htmlspecialchars($chaveAtiva['funcionario_entrega']) ?></span></p>
                                <p class="mb-3 text-secondary small"><strong>Hora Retirada:</strong> <span class="text-dark"><?= date('H:i', strtotime($chaveAtiva['hora_retirada'])) ?></span></p>
                                <div class="alert <?= $estaAtrasado ? 'alert-danger' : 'alert-warning' ?> py-3 mb-3 d-flex justify-content-between align-items-center" style="border-radius:15px;">
                                    <div><strong class="small opacity-75">PREVISÃO DE VOLTA</strong><br><span class="fs-4 fw-bold"><?= date('H:i', strtotime($chaveAtiva['hora_devolucao_prevista'])) ?></span></div>
                                    <?php if ($estaAtrasado): ?><span class="badge bg-danger fs-6 heartbeat py-2 px-3 rounded-pill">ATRASADO</span><?php endif; ?>
                                </div>
                                <a href="<?= $linkWpp ?>" target="_blank" class="apple-btn mb-4" style="background:rgba(25,135,84,.1);border:1px solid rgba(25,135,84,.2);color:#198754;"><i class="bi bi-whatsapp fs-5 me-2"></i> WhatsApp</a>
                                <form method="POST" action="/index.php?page=suporte" class="bg-light p-3 border rounded-4">
                                    <input type="hidden" name="dar_baixa_chave" value="1">
                                    <input type="hidden" name="id_chave" value="<?= $chaveAtiva['id'] ?>">
                                    <div class="mb-3"><label class="form-label fw-bold small text-secondary">Hora Real Devolução:</label><input type="time" class="form-control rounded-pill px-3" name="hora_devolucao_real" value="<?= date('H:i') ?>" required></div>
                                    <div class="mb-4"><label class="form-label fw-bold small text-secondary">Recebido por:</label><input type="text" class="form-control rounded-pill px-3" name="func_recebe" placeholder="Ex: Técnico João" required></div>
                                    <button type="submit" class="btn <?= $estaAtrasado ? 'btn-danger' : 'btn-primary' ?> w-100 fw-bold py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i> Confirmar Devolução</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="apple-kpi kpi-total"><span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Total Hoje</span><h2 class="fw-bold m-0"><?= $totalReservas ?></h2></div></div>
    <div class="col-6 col-md-3"><div class="apple-kpi kpi-matutino"><span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Matutino</span><h2 class="fw-bold m-0"><?= $qtdMatutino ?></h2></div></div>
    <div class="col-6 col-md-3"><div class="apple-kpi kpi-vespertino"><span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Vespertino</span><h2 class="fw-bold m-0"><?= $qtdVespertino ?></h2></div></div>
    <div class="col-6 col-md-3"><div class="apple-kpi kpi-noturno"><span class="d-block small fw-bold text-uppercase mb-1 opacity-75">Noturno</span><h2 class="fw-bold m-0"><?= $qtdNoturno ?></h2></div></div>
</div>

<div class="row align-items-end mb-4 g-3">
    <div class="col-md-6"><h4 class="fw-bold text-uniceplac mb-1">Mapa de Ocupação</h4><p class="text-muted small mb-0">Grade do dia: <?= date('d/m/Y', strtotime($dataFiltro)) ?></p></div>
    <div class="col-md-6 text-md-end">
        <form action="/index.php?page=suporte" method="GET" class="d-inline-block shadow-sm" style="border-radius:20px;overflow:hidden;">
            <div class="input-group input-group-sm"><span class="input-group-text bg-white border-end-0 text-muted">Data</span><input type="date" class="form-control border-start-0" name="data_busca" value="<?= htmlspecialchars($dataFiltro) ?>" onchange="this.form.submit()"></div>
        </form>
    </div>
</div>

<div id="grid-mapa-diario">
    <?php if ($totalReservas > 0): ?>
        <?php if ($alocacoesMatutino): ?>
            <div class="turn-divider"><span class="turn-badge badge-matutino"><i class="bi bi-sunrise-fill me-2"></i>Turno Matutino</span></div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                <?php foreach ($alocacoesMatutino as $l): renderizarCardSuporte($l, $chavesEmUso, 'border-matutino'); endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($alocacoesVespertino): ?>
            <div class="turn-divider"><span class="turn-badge badge-vespertino"><i class="bi bi-sun-fill me-2"></i>Turno Vespertino</span></div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                <?php foreach ($alocacoesVespertino as $l): renderizarCardSuporte($l, $chavesEmUso, 'border-vespertino'); endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($alocacoesNoturno): ?>
            <div class="turn-divider"><span class="turn-badge badge-noturno"><i class="bi bi-moon-stars-fill me-2"></i>Turno Noturno</span></div>
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                <?php foreach ($alocacoesNoturno as $l): renderizarCardSuporte($l, $chavesEmUso, 'border-noturno'); endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="apple-ticket text-center p-5 shadow-sm text-muted">
            <i class="bi bi-calendar-x fs-1 opacity-50 mb-3 d-block"></i> Nenhuma reserva para a data selecionada.
        </div>
    <?php endif; ?>
</div>
