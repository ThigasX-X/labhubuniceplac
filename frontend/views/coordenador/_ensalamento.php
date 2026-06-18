<div class="card shadow-sm border-0 mb-4" style="border-top:4px solid #f59e0b;">
    <div class="card-header bg-white py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h5 class="mb-1 fw-bold d-flex align-items-center text-dark">
                <i class="bi bi-geo-alt-fill text-warning me-3 fs-4"></i> Mapa Central de Ensalamento
            </h5>
            <p class="text-muted small mb-0">Professor · Matéria · Turma · Bloco · Andar · Sala</p>
        </div>
        <button class="btn btn-primary rounded-pill fw-semibold px-4 shadow-sm mt-3 mt-md-0" data-bs-toggle="modal" data-bs-target="#modalEnsalamento">
            <i class="bi bi-plus-lg me-2"></i>Novo Ensalamento
        </button>
    </div>
    <div class="card-body">
        <?php
        $mostrarOrigem = true;
        $mostrarAcoes  = true;
        include VIEWS_PATH . '/shared/_mapa_ensalamento.php';
        ?>
    </div>
</div>

<!-- Modal Novo Ensalamento -->
<div class="modal fade" id="modalEnsalamento" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-door-open me-2"></i>Novo Ensalamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/index.php?page=coordenador" class="row g-3">
                    <input type="hidden" name="salvar_ensalamento" value="1">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Professor</label>
                        <select name="id_professor" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($professores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Disciplina (Matéria)</label>
                        <select name="id_disciplina" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($disciplinas as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Curso</label>
                        <select name="id_curso" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($cursosCadastrados as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Turma (Semestre)</label>
                        <select name="id_semestre" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($semestres as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Turno</label>
                        <select name="turno" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <option>Matutino</option><option>Vespertino</option><option>Noturno</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold small">Categoria (opcional)</label>
                        <input type="text" name="categoria" class="form-control rounded-3" placeholder="Ex: Presencial, EAD híbrido">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Bloco</label>
                        <select name="id_bloco" class="form-select rounded-3 ensal-bloco" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($blocosCadastrados as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Andar</label>
                        <select name="id_andar" class="form-select rounded-3 ensal-andar" required disabled>
                            <option value="">Selecione um bloco</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Sala</label>
                        <select name="id_sala" class="form-select rounded-3 ensal-sala" required disabled>
                            <option value="">Selecione um andar</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">Salvar Ensalamento</button>
                    </div>
                </form>
                <script>
                (function () {
                    const bloco = document.querySelector('#modalEnsalamento .ensal-bloco');
                    const andar = document.querySelector('#modalEnsalamento .ensal-andar');
                    const sala  = document.querySelector('#modalEnsalamento .ensal-sala');
                    const fill = (sel, items, placeholder) => {
                        sel.innerHTML = `<option value="">${placeholder}</option>` +
                            items.map(i => `<option value="${i.id}">${i.nome}</option>`).join('');
                        sel.disabled = items.length === 0;
                    };
                    bloco.addEventListener('change', async () => {
                        fill(andar, [], 'Carregando...');
                        fill(sala,  [], 'Selecione um andar');
                        if (!bloco.value) return fill(andar, [], 'Selecione um bloco');
                        const r = await fetch('/index.php?page=api/andares&id_bloco=' + bloco.value);
                        fill(andar, await r.json(), 'Selecione...');
                    });
                    andar.addEventListener('change', async () => {
                        fill(sala, [], 'Carregando...');
                        if (!andar.value) return fill(sala, [], 'Selecione um andar');
                        const r = await fetch('/index.php?page=api/salas&id_andar=' + andar.value);
                        fill(sala, await r.json(), 'Selecione...');
                    });
                })();
                </script>
            </div>
        </div>
    </div>
</div>
