<?php $diasSemana = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']; ?>

<div class="row g-4 mb-4">
    <!-- Painel lateral: gerenciar quadros -->
    <div class="col-lg-3">
        <div class="card shadow-sm border-0 mb-3" style="border-top:4px solid #6366f1;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-collection text-primary me-2"></i>Cenários de Quadro</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($listaQuadros as $q): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center <?= $q['id'] == $quadroSelecionado ? 'bg-primary bg-opacity-10 fw-semibold' : '' ?>">
                        <a href="/index.php?page=coordenador&q_id=<?= $q['id'] ?>#quadro" class="text-decoration-none text-dark flex-grow-1">
                            <div class="small fw-semibold"><?= htmlspecialchars($q['nome']) ?></div>
                            <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($q['periodo_letivo']) ?></div>
                        </a>
                        <form method="POST" action="/index.php?page=coordenador" class="d-inline"
                              onsubmit="return confirm('Excluir quadro?')">
                            <input type="hidden" name="excluir_quadro" value="1">
                            <input type="hidden" name="id_quadro" value="<?= $q['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($listaQuadros)): ?>
                        <div class="list-group-item text-muted text-center py-3 small">Nenhum quadro criado.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Novo Quadro -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2 text-success"></i>Novo Quadro</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="/index.php?page=coordenador">
                    <input type="hidden" name="criar_quadro" value="1">
                    <input type="text" name="nome_quadro" class="form-control form-control-sm rounded-3 mb-2" placeholder="Nome (ex: Grade 2025-2)" required>
                    <input type="text" name="periodo_letivo" class="form-control form-control-sm rounded-3 mb-2" placeholder="Período letivo" required>
                    <button type="submit" class="btn btn-success btn-sm w-100 rounded-pill fw-semibold">Criar</button>
                </form>
            </div>
        </div>

        <!-- Duplicar Quadro -->
        <?php if (!empty($listaQuadros)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-copy me-2 text-info"></i>Duplicar Cenário</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="/index.php?page=coordenador">
                    <input type="hidden" name="duplicar_quadro" value="1">
                    <select name="id_quadro_origem" class="form-select form-select-sm rounded-3 mb-2">
                        <?php foreach ($listaQuadros as $q): ?>
                            <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="novo_nome_quadro" class="form-control form-control-sm rounded-3 mb-2" placeholder="Novo nome" required>
                    <input type="text" name="novo_periodo_letivo" class="form-control form-control-sm rounded-3 mb-2" placeholder="Novo período" required>
                    <button type="submit" class="btn btn-info btn-sm w-100 rounded-pill fw-semibold text-white">Duplicar</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Grade de aulas -->
    <div class="col-lg-9">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-table text-primary me-2"></i>
                    <?php
                    $qSel = array_filter($listaQuadros, fn($q) => $q['id'] == $quadroSelecionado);
                    $qSel = reset($qSel);
                    echo $qSel ? htmlspecialchars($qSel['nome'] . ' — ' . $qSel['periodo_letivo']) : 'Selecione um quadro';
                    ?>
                </h6>
                <?php if ($quadroSelecionado): ?>
                <button class="btn btn-primary btn-sm rounded-pill fw-semibold" data-bs-toggle="modal" data-bs-target="#modalNovaAula">
                    <i class="bi bi-plus-lg me-1"></i>Adicionar Aula
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (!$quadroSelecionado): ?>
                    <div class="text-center py-5 text-muted"><i class="bi bi-table fs-1 d-block mb-2"></i>Selecione ou crie um quadro.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" style="min-width:700px;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 py-3" style="width:130px;">Horário</th>
                                <?php foreach ($diasSemana as $d): ?><th class="text-center"><?= $d ?></th><?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $turnosPeriodos = [
                                'Manhã'  => ['1º', '2º', '3º', '4º'],
                                'Tarde'  => ['1º', '2º', '3º', '4º'],
                                'Noite'  => ['1º', '2º', '3º'],
                            ];
                            foreach ($turnosPeriodos as $turno => $periodos): ?>
                            <tr class="table-secondary">
                                <td colspan="7" class="fw-bold ps-3 py-1 small text-uppercase"><?= $turno ?></td>
                            </tr>
                            <?php foreach ($periodos as $periodo): ?>
                            <tr>
                                <td class="ps-3 text-muted small fw-semibold"><?= $periodo ?> <?= $turno ?></td>
                                <?php foreach ($diasSemana as $dia): ?>
                                <td class="p-1">
                                    <?php
                                    $aulasSlot = array_filter($todasAulas, fn($a) => $a['dia_semana'] === $dia && $a['turno'] === $turno && $a['horario'] === $periodo);
                                    foreach ($aulasSlot as $aula): ?>
                                    <div class="apple-ticket-mini mb-1 p-2 rounded-3 shadow-sm position-relative" style="border-left:3px solid #6366f1;background:rgba(99,102,241,.06);">
                                        <div class="fw-semibold small text-truncate" style="max-width:130px;" title="<?= htmlspecialchars($aula['disc_nome'] ?? '') ?>">
                                            <?= htmlspecialchars(mb_substr($aula['disc_nome'] ?? '-', 0, 18)) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.7rem;">
                                            <?= htmlspecialchars(mb_substr($aula['prof_nome'] ?? 'EAD', 0, 16)) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.7rem;">
                                            <?php if (!empty($aula['lab_nome'])): ?>
                                                <i class="bi bi-pc-display"></i> <?= htmlspecialchars(mb_substr($aula['lab_nome'], 0, 14)) ?>
                                            <?php elseif (!empty($aula['sala'])): ?>
                                                <i class="bi bi-door-open"></i> <?= htmlspecialchars(mb_substr($aula['sala'], 0, 14)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="position-absolute top-0 end-0 p-1">
                                            <button class="btn btn-link btn-sm text-primary p-0 me-1" style="font-size:.7rem;"
                                                    onclick="abrirEditarAula(<?= htmlspecialchars(json_encode($aula)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="/index.php?page=coordenador" class="d-inline">
                                                <input type="hidden" name="excluir_aula_quadro" value="1">
                                                <input type="hidden" name="id_aula_q" value="<?= $aula['id'] ?>">
                                                <button type="submit" class="btn btn-link btn-sm text-danger p-0" style="font-size:.7rem;"
                                                        onclick="return confirm('Remover aula?')"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($aulasSlot)): ?>
                                        <div class="text-center" style="min-height:28px;"></div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nova Aula -->
<div class="modal fade" id="modalNovaAula" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nova Aula no Quadro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/index.php?page=coordenador" class="row g-3">
                    <input type="hidden" name="salvar_aula_quadro" value="1">
                    <input type="hidden" name="id_quadro" value="<?= $quadroSelecionado ?>">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Disciplina</label>
                        <select name="id_disciplina" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($disciplinas as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Professor</label>
                        <select name="id_professor" class="form-select rounded-3">
                            <option value="">EAD / Sem professor</option>
                            <?php foreach ($professores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Dia da Semana</label>
                        <select name="dia_semana" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($diasSemana as $d): ?><option><?= $d ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Turno</label>
                        <select name="turno" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <option>Manhã</option><option>Tarde</option><option>Noite</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Horário (Período)</label>
                        <select name="horario" class="form-select rounded-3" required>
                            <option value="">Selecione...</option>
                            <option>1º</option><option>2º</option><option>3º</option><option>4º</option><option>5º</option><option>6º</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Laboratório (opcional)</label>
                        <select name="id_laboratorio" class="form-select rounded-3">
                            <option value="">Sem laboratório</option>
                            <?php foreach ($laboratoriosCadastrados as $lab): ?>
                                <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Sala (opcional)</label>
                        <input type="text" name="sala" class="form-control rounded-3" placeholder="Ex: Sala 201">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold w-100">
                            <i class="bi bi-plus-lg me-2"></i>Adicionar Aula
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Aula -->
<div class="modal fade" id="modalEditarAula" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Editar Aula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/index.php?page=coordenador" class="row g-3" id="formEditarAula">
                    <input type="hidden" name="editar_aula_quadro" value="1">
                    <input type="hidden" name="id_aula_q" id="ea_id">
                    <input type="hidden" name="id_quadro" value="<?= $quadroSelecionado ?>">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Disciplina</label>
                        <select name="id_disciplina" id="ea_disc" class="form-select rounded-3" required>
                            <?php foreach ($disciplinas as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Professor</label>
                        <select name="id_professor" id="ea_prof" class="form-select rounded-3">
                            <option value="">EAD / Sem professor</option>
                            <?php foreach ($professores as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Dia da Semana</label>
                        <select name="dia_semana" id="ea_dia" class="form-select rounded-3" required>
                            <?php foreach ($diasSemana as $d): ?><option><?= $d ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Turno</label>
                        <select name="turno" id="ea_turno" class="form-select rounded-3" required>
                            <option>Manhã</option><option>Tarde</option><option>Noite</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Horário (Período)</label>
                        <select name="horario" id="ea_horario" class="form-select rounded-3" required>
                            <option>1º</option><option>2º</option><option>3º</option><option>4º</option><option>5º</option><option>6º</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Laboratório (opcional)</label>
                        <select name="id_laboratorio" id="ea_lab" class="form-select rounded-3">
                            <option value="">Sem laboratório</option>
                            <?php foreach ($laboratoriosCadastrados as $lab): ?>
                                <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Sala (opcional)</label>
                        <input type="text" name="sala" id="ea_sala" class="form-control rounded-3" placeholder="Ex: Sala 201">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold w-100">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function abrirEditarAula(aula) {
    document.getElementById('ea_id').value     = aula.id;
    document.getElementById('ea_disc').value   = aula.id_disciplina;
    document.getElementById('ea_prof').value   = aula.id_professor || '';
    document.getElementById('ea_dia').value    = aula.dia_semana;
    document.getElementById('ea_turno').value  = aula.turno;
    document.getElementById('ea_horario').value= aula.horario;
    document.getElementById('ea_lab').value    = aula.id_laboratorio || '';
    document.getElementById('ea_sala').value   = aula.sala || '';
    new bootstrap.Modal(document.getElementById('modalEditarAula')).show();
}
</script>
