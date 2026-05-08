<?php
$secoesCadastro = [
    ['id' => 'labs',       'titulo' => 'Laboratórios',  'icon' => 'bi-pc-display',     'cor' => '#6366f1', 'campo' => 'nome_lab',        'post' => 'salvar_lab',     'excluir' => 'excluir_lab',     'items' => $laboratoriosCadastrados, 'extra' => true],
    ['id' => 'discs',      'titulo' => 'Disciplinas',   'icon' => 'bi-book',           'cor' => '#10b981', 'campo' => 'nome_disciplina', 'post' => 'salvar_disciplina','excluir' => 'excluir_disciplina','items' => $disciplinas,             'extra' => false],
    ['id' => 'cursos',     'titulo' => 'Cursos',        'icon' => 'bi-mortarboard',    'cor' => '#f59e0b', 'campo' => 'nome_curso',      'post' => 'salvar_curso',   'excluir' => 'excluir_curso',   'items' => $cursosCadastrados,        'extra' => false],
    ['id' => 'semestres',  'titulo' => 'Semestres',     'icon' => 'bi-calendar2-week', 'cor' => '#3b82f6', 'campo' => 'nome_semestre',   'post' => 'salvar_semestre','excluir' => 'excluir_semestre','items' => $semestres,               'extra' => false],
    ['id' => 'blocos',     'titulo' => 'Blocos',        'icon' => 'bi-building',       'cor' => '#8b5cf6', 'campo' => 'nome_bloco',      'post' => 'salvar_bloco',   'excluir' => 'excluir_bloco',   'items' => $blocosCadastrados,        'extra' => false],
    ['id' => 'andares',    'titulo' => 'Andares',       'icon' => 'bi-layers',         'cor' => '#ec4899', 'campo' => 'nome_andar',      'post' => 'salvar_andar',   'excluir' => 'excluir_andar',   'items' => $andaresCadastrados,       'extra' => false],
    ['id' => 'salas',      'titulo' => 'Salas',         'icon' => 'bi-door-closed',    'cor' => '#ef4444', 'campo' => 'nome_sala',       'post' => 'salvar_sala',    'excluir' => 'excluir_sala',    'items' => $salasCadastradas,         'extra' => false],
];
?>

<h4 class="fw-bold mb-4"><i class="bi bi-database text-primary me-2"></i>Cadastros Base</h4>

<div class="row g-4">
<?php foreach ($secoesCadastro as $sec): ?>
<div class="col-12 col-md-6">
    <div class="card shadow-sm border-0 h-100" style="border-top:4px solid <?= $sec['cor'] ?>;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <i class="<?= $sec['icon'] ?> me-2" style="color:<?= $sec['cor'] ?>;"></i><?= $sec['titulo'] ?>
                <span class="badge bg-secondary rounded-pill ms-1 fw-normal"><?= count($sec['items']) ?></span>
            </h6>
            <button class="btn btn-sm btn-primary rounded-pill" data-bs-toggle="collapse" data-bs-target="#form-<?= $sec['id'] ?>">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>

        <!-- Formulário colapsável -->
        <div class="collapse" id="form-<?= $sec['id'] ?>">
            <div class="card-body border-bottom">
                <form method="POST" action="/index.php?page=coordenador" class="row g-2 align-items-end">
                    <input type="hidden" name="<?= $sec['post'] ?>" value="1">
                    <div class="col">
                        <input type="text" name="<?= $sec['campo'] ?>" class="form-control form-control-sm rounded-3"
                               placeholder="Nome..." required>
                    </div>
                    <?php if ($sec['extra']): ?>
                    <div class="col-auto">
                        <input type="number" name="capacidade" class="form-control form-control-sm rounded-3"
                               placeholder="Capacidade" min="1" style="width:120px;">
                    </div>
                    <?php endif; ?>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-semibold">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <ul class="list-group list-group-flush" style="max-height:280px;overflow-y:auto;">
                <?php foreach ($sec['items'] as $item): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="fw-semibold small"><?= htmlspecialchars($item['nome']) ?></span>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($sec['extra'] && !empty($item['capacidade'])): ?>
                            <span class="badge bg-light text-muted border"><?= $item['capacidade'] ?> lugares</span>
                        <?php endif; ?>
                        <form method="POST" action="/index.php?page=coordenador" class="d-inline"
                              onsubmit="return confirm('Excluir?')">
                            <input type="hidden" name="<?= $sec['excluir'] ?>" value="1">
                            <input type="hidden" name="id_item" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (empty($sec['items'])): ?>
                <li class="list-group-item text-center text-muted py-3 small">Nenhum registro.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
