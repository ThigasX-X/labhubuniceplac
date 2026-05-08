<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0 fw-bold d-flex align-items-center text-dark">
            <i class="bi bi-calendar3 text-primary me-3 fs-4"></i> Calendário de Reservas
        </h5>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge rounded-pill px-3 py-2" style="background:#6366f1;">Aula Fixa</span>
            <span class="badge rounded-pill px-3 py-2" style="background:#10b981;">Reserva Avulsa</span>
            <span class="badge rounded-pill px-3 py-2 bg-danger">Feriado</span>
        </div>
    </div>
    <div class="card-body p-3">
        <div id="calendar"></div>
    </div>
</div>

<!-- Modal Evento -->
<div class="modal fade" id="modalEvento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalEventTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-1"><i class="bi bi-clock me-2"></i><span id="modalEventTime"></span></p>
                <p class="mb-0" id="modalEventLocal"></p>
            </div>
        </div>
    </div>
</div>
