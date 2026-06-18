<?php
$gruposCadastro = [
    'academico' => [
        'label' => 'Acadêmico',
        'icon'  => 'bi-mortarboard-fill',
        'cor'   => '#00734F',
        'secoes' => [
            ['id' => 'discs',     'titulo' => 'Disciplinas', 'icon' => 'bi-book',           'cor' => '#10b981', 'campo' => 'nome_disciplina', 'idField' => 'id_disciplina', 'post' => 'salvar_disciplina', 'excluir' => 'excluir_disciplina', 'items' => $disciplinas,       'parent' => null],
            ['id' => 'cursos',    'titulo' => 'Cursos',      'icon' => 'bi-mortarboard',    'cor' => '#f59e0b', 'campo' => 'nome_curso',      'idField' => 'id_curso',      'post' => 'salvar_curso',      'excluir' => 'excluir_curso',      'items' => $cursosCadastrados, 'parent' => null],
            ['id' => 'semestres', 'titulo' => 'Semestres',   'icon' => 'bi-calendar2-week', 'cor' => '#3b82f6', 'campo' => 'nome_semestre',   'idField' => 'id_semestre',   'post' => 'salvar_semestre',   'excluir' => 'excluir_semestre',   'items' => $semestres,         'parent' => null],
        ],
    ],
    'infra' => [
        'label' => 'Estrutura',
        'icon'  => 'bi-buildings-fill',
        'cor'   => '#421B71',
        'secoes' => [
            ['id' => 'blocos',  'titulo' => 'Blocos',  'icon' => 'bi-building',    'cor' => '#8b5cf6', 'campo' => 'nome_bloco', 'idField' => 'id_bloco', 'post' => 'salvar_bloco',  'excluir' => 'excluir_bloco',  'items' => $blocosCadastrados,  'parent' => null],
            ['id' => 'andares', 'titulo' => 'Andares', 'icon' => 'bi-layers',      'cor' => '#ec4899', 'campo' => 'nome_andar', 'idField' => 'id_andar', 'post' => 'salvar_andar',  'excluir' => 'excluir_andar',  'items' => $andaresCadastrados, 'parent' => ['name' => 'id_bloco', 'label' => 'Bloco', 'items' => $blocosCadastrados, 'displayKey' => 'bloco_nome']],
            ['id' => 'salas',   'titulo' => 'Salas',   'icon' => 'bi-door-closed', 'cor' => '#ef4444', 'campo' => 'nome_sala',  'idField' => 'id_sala',  'post' => 'salvar_sala',   'excluir' => 'excluir_sala',   'items' => $salasCadastradas,   'parent' => ['name' => 'id_andar', 'label' => 'Andar', 'items' => $andaresCadastrados, 'displayKey' => 'andar_nome']],
        ],
    ],
    'labs' => [
        'label' => 'Laboratórios',
        'icon'  => 'bi-pc-display-horizontal',
        'cor'   => '#6366f1',
        'secoes' => [
            ['id' => 'labs', 'titulo' => 'Laboratórios', 'icon' => 'bi-pc-display', 'cor' => '#6366f1', 'campo' => 'nome_lab', 'idField' => 'id_lab', 'post' => 'salvar_lab', 'excluir' => 'excluir_lab', 'items' => $laboratoriosCadastrados, 'parent' => null, 'tipo' => 'lab'],
        ],
    ],
];

$totalCadastros = count($disciplinas) + count($cursosCadastrados) + count($semestres)
    + count($blocosCadastrados) + count($andaresCadastrados) + count($salasCadastradas)
    + count($laboratoriosCadastrados);
?>

<div class="cadastro-hub mb-4">
    <div class="cadastro-hub-header">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-database-fill-gear text-uniceplac me-2"></i>Cadastros Base</h4>
            <p class="text-muted mb-0 small">Gerencie cursos, blocos, salas e laboratórios da instituição</p>
        </div>
        <div class="cadastro-hub-stats">
            <span class="cadastro-stat"><strong><?= $totalCadastros ?></strong> registros</span>
            <span class="cadastro-stat"><strong><?= count($blocosCadastrados) ?></strong> blocos</span>
            <span class="cadastro-stat"><strong><?= count($laboratoriosCadastrados) ?></strong> labs</span>
        </div>
    </div>

    <ul class="nav cadastro-tabs" id="cadastroTabs" role="tablist">
        <?php $first = true; foreach ($gruposCadastro as $gid => $grupo): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link cadastro-tab <?= $first ? 'active' : '' ?>" id="tab-<?= $gid ?>"
                    data-bs-toggle="tab" data-bs-target="#pane-<?= $gid ?>" type="button" role="tab">
                <i class="<?= $grupo['icon'] ?> me-2"></i><?= $grupo['label'] ?>
            </button>
        </li>
        <?php $first = false; endforeach; ?>
    </ul>

    <div class="tab-content cadastro-tab-content">
        <?php $first = true; foreach ($gruposCadastro as $gid => $grupo): ?>
        <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" id="pane-<?= $gid ?>" role="tabpanel">
            <div class="row g-4">
                <?php foreach ($grupo['secoes'] as $sec): ?>
                <div class="col-12 <?= ($gid === 'labs') ? '' : 'col-xl-4' ?>">
                    <div class="cadastro-card h-100" style="--cad-cor: <?= $sec['cor'] ?>;">
                        <div class="cadastro-card-head">
                            <div class="cadastro-card-icon"><i class="<?= $sec['icon'] ?>"></i></div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-0"><?= $sec['titulo'] ?></h6>
                                <span class="small text-muted"><?= count($sec['items']) ?> cadastrado(s)</span>
                            </div>
                            <button type="button" class="btn btn-sm cadastro-add-btn" data-bs-toggle="collapse" data-bs-target="#form-<?= $sec['id'] ?>">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>

                        <div class="collapse" id="form-<?= $sec['id'] ?>">
                            <div class="cadastro-form-wrap">
                                <form method="POST" action="/index.php?page=coordenador" class="cadastro-form">
                                    <input type="hidden" name="<?= $sec['post'] ?>" value="1">
                                    <?php if ($sec['parent']): ?>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold mb-1"><?= $sec['parent']['label'] ?></label>
                                        <select name="<?= $sec['parent']['name'] ?>" class="form-select form-select-sm" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($sec['parent']['items'] as $p): ?>
                                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mb-2">
                                        <label class="form-label small fw-semibold mb-1">Nome</label>
                                        <input type="text" name="<?= $sec['campo'] ?>" class="form-control form-control-sm" placeholder="Digite o nome..." required>
                                    </div>
                                    <?php if (!empty($sec['tipo']) && $sec['tipo'] === 'lab'): ?>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Capacidade</label>
                                            <input type="number" name="capacidade_lab" class="form-control form-control-sm" min="1" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Andar</label>
                                            <input type="text" name="andar_lab" class="form-control form-control-sm" placeholder="Opcional">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold mb-1">Localização</label>
                                            <input type="text" name="localizacao_lab" class="form-control form-control-sm" placeholder="Ex: Bloco A">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-uniceplac btn-sm w-100 fw-semibold">
                                        <i class="bi bi-check2 me-1"></i>Salvar
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="cadastro-list">
                            <?php if (empty($sec['items'])): ?>
                                <div class="cadastro-empty"><i class="bi bi-inbox"></i> Nenhum registro</div>
                            <?php else: ?>
                                <?php foreach ($sec['items'] as $item): ?>
                                <div class="cadastro-item">
                                    <div class="cadastro-item-info">
                                        <?php if ($sec['parent'] && !empty($item[$sec['parent']['displayKey']])): ?>
                                            <span class="cadastro-item-meta"><?= htmlspecialchars($item[$sec['parent']['displayKey']]) ?></span>
                                        <?php endif; ?>
                                        <span class="cadastro-item-nome"><?= htmlspecialchars($item['nome']) ?></span>
                                        <?php if ($sec['id'] === 'labs' && !empty($item['capacidade'])): ?>
                                            <span class="cadastro-item-badge"><?= $item['capacidade'] ?> lug.</span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="/index.php?page=coordenador" class="d-inline" onsubmit="return confirm('Excluir este registro?')">
                                        <input type="hidden" name="<?= $sec['excluir'] ?>" value="1">
                                        <input type="hidden" name="<?= $sec['idField'] ?>" value="<?= $item['id'] ?>">
                                        <button type="submit" class="cadastro-del-btn" title="Excluir"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php $first = false; endforeach; ?>
    </div>
</div>
