<?php
/**
 * Mapa centralizado de ensalamento.
 * Variáveis: $mapaEnsalamento (array), $mostrarOrigem (bool, default true), $mostrarAcoes (bool, default false)
 */
$mostrarOrigem = $mostrarOrigem ?? true;
$mostrarAcoes  = $mostrarAcoes ?? false;
$turnoClass = fn($t) => match (trim($t)) {
    'Matutino'   => 'turno-matutino',
    'Vespertino' => 'turno-vespertino',
    'Noturno'    => 'turno-noturno',
    default      => '',
};
?>

<div class="mapa-ensalamento-wrap">
    <div class="mapa-ensalamento-filtros">
        <input type="text" id="filtroMapaEnsal" class="form-control form-control-sm" style="max-width:280px;border-radius:20px!important;" placeholder="Buscar professor, matéria, bloco...">
        <select id="filtroTurnoEnsal" class="form-select form-select-sm" style="max-width:160px;border-radius:20px!important;">
            <option value="">Todos os turnos</option>
            <option>Matutino</option>
            <option>Vespertino</option>
            <option>Noturno</option>
        </select>
        <select id="filtroBlocoEnsal" class="form-select form-select-sm" style="max-width:160px;border-radius:20px!important;">
            <option value="">Todos os blocos</option>
            <?php foreach (array_unique(array_column($mapaEnsalamento, 'bloco')) as $bl): if ($bl): ?>
                <option><?= htmlspecialchars($bl) ?></option>
            <?php endif; endforeach; ?>
        </select>
    </div>

    <div class="row g-3" id="gridMapaEnsal">
        <?php foreach ($mapaEnsalamento as $item):
            $searchData = strtolower(implode(' ', [
                $item['professor'] ?? '', $item['disciplina'] ?? '', $item['curso'] ?? '',
                $item['turma'] ?? '', $item['bloco'] ?? '', $item['andar'] ?? '', $item['sala'] ?? '',
                $item['turno'] ?? '', $item['categoria'] ?? '',
            ]));
        ?>
        <div class="col-12 col-md-6 col-xl-4 mapa-ensal-item"
             data-search="<?= htmlspecialchars($searchData) ?>"
             data-turno="<?= htmlspecialchars($item['turno'] ?? '') ?>"
             data-bloco="<?= htmlspecialchars($item['bloco'] ?? '') ?>">
            <div class="mapa-ensal-card <?= $turnoClass($item['turno'] ?? '') ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($item['disciplina'] ?? '-') ?></h6>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['turno'] ?? '-') ?></span>
                        <?php if ($mostrarOrigem): ?>
                            <span class="badge <?= ($item['origem'] ?? '') === 'quadro' ? 'bg-info' : 'bg-uniceplac' ?> ms-1">
                                <?= ($item['origem'] ?? '') === 'quadro' ? 'Grade' : 'Ensalamento' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($mostrarAcoes && ($item['origem'] ?? '') === 'ensalamento' && !empty($item['id'])): ?>
                    <form method="POST" action="/index.php?page=coordenador" onsubmit="return confirm('Remover?')">
                        <input type="hidden" name="excluir_ensalamento" value="1">
                        <input type="hidden" name="id_ensalamento" value="<?= (int) $item['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mb-1"><i class="bi bi-person-badge me-1"></i><strong><?= htmlspecialchars($item['professor'] ?? '-') ?></strong></p>
                <p class="small text-muted mb-0">
                    <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($item['curso'] ?? '-') ?>
                    <?php if (!empty($item['turma'])): ?>
                        · Turma <strong><?= htmlspecialchars($item['turma']) ?></strong>
                    <?php endif; ?>
                </p>
                <?php if (!empty($item['categoria'])): ?>
                    <p class="small text-secondary mb-0 mt-1"><i class="bi bi-tag me-1"></i><?= htmlspecialchars($item['categoria']) ?></p>
                <?php endif; ?>
                <div class="mapa-ensal-loc">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span><strong><?= htmlspecialchars($item['bloco'] ?? '-') ?></strong> · <?= htmlspecialchars($item['andar'] ?? '-') ?> · Sala <strong><?= htmlspecialchars($item['sala'] ?? '-') ?></strong></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($mapaEnsalamento)): ?>
        <div class="col-12">
            <div class="apple-ticket text-center p-5 text-muted">
                <i class="bi bi-building fs-1 opacity-50 d-block mb-2"></i>
                Nenhum ensalamento cadastrado.
            </div>
        </div>
        <?php endif; ?>
    </div>
    <p id="mapaEnsalVazio" class="text-center text-muted py-4 d-none"><i class="bi bi-search me-1"></i>Nenhum resultado para os filtros.</p>
</div>

<script>
(function () {
    const busca  = document.getElementById('filtroMapaEnsal');
    const turno  = document.getElementById('filtroTurnoEnsal');
    const bloco  = document.getElementById('filtroBlocoEnsal');
    const items  = document.querySelectorAll('.mapa-ensal-item');
    const vazio  = document.getElementById('mapaEnsalVazio');

    function filtrar() {
        const q = (busca?.value || '').toLowerCase();
        const t = turno?.value || '';
        const b = bloco?.value || '';
        let vis = 0;
        items.forEach(el => {
            const ok = (!q || el.dataset.search.includes(q))
                && (!t || el.dataset.turno === t)
                && (!b || el.dataset.bloco === b);
            el.classList.toggle('d-none', !ok);
            if (ok) vis++;
        });
        if (vazio) vazio.classList.toggle('d-none', vis > 0 || items.length === 0);
    }
    [busca, turno, bloco].forEach(el => el?.addEventListener('input', filtrar));
    [turno, bloco].forEach(el => el?.addEventListener('change', filtrar));
})();
</script>
