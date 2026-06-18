<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #421B71;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-1 fw-bold d-flex align-items-center text-dark">
            <i class="bi bi-geo-alt-fill text-primary me-3 fs-4"></i> Mapa de Ensalamento
        </h5>
        <p class="text-muted small mb-0">Onde cada professor está dando aula — bloco, andar, sala e turma</p>
    </div>
    <div class="card-body">
        <?php
        $mostrarOrigem = true;
        $mostrarAcoes  = false;
        include VIEWS_PATH . '/shared/_mapa_ensalamento.php';
        ?>
    </div>
</div>
