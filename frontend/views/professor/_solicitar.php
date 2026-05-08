<div class="card shadow-sm mb-4 border-0" style="border-top:4px solid var(--laranja-uniceplac)!important;">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold"><span class="header-icon bg-light"><i class="bi bi-calendar-plus fs-4"></i></span>Solicitar Laboratório</h5>
    </div>
    <div class="card-body bg-light p-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <form action="/index.php?page=professor" method="POST" class="bg-white p-4 p-md-5 border shadow-sm" style="border-radius:20px;">
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label class="form-label fw-bold text-secondary">Data:</label>
                            <input type="date" class="form-control form-control-lg" name="data_reserva" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label class="form-label fw-bold text-secondary">Turno:</label>
                            <select class="form-select form-select-lg" name="turno" required>
                                <option value="">Selecione...</option>
                                <option>Matutino</option><option>Vespertino</option><option>Noturno</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary">Horário:</label>
                            <select class="form-select form-select-lg" name="periodo" required>
                                <option>1º e 2º Horários</option><option>1º Horário</option><option>2º Horário</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-5">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold text-secondary">Laboratório:</label>
                            <select class="form-select form-select-lg" name="id_laboratorio" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($laboratorios as $lab): ?>
                                    <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['nome']) ?> (Cap: <?= $lab['capacidade'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Disciplina:</label>
                            <select class="form-select form-select-lg" name="id_disciplina" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($disciplinas as $disc): ?>
                                    <option value="<?= $disc['id'] ?>"><?= htmlspecialchars($disc['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-uniceplac btn-lg px-5 w-100 rounded-pill">
                            <i class="bi bi-send-check me-2"></i>Enviar Solicitação
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
