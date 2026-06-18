<?php
/**
 * Layout unificado dos painéis (coordenador, professor, suporte).
 *
 * Variáveis esperadas:
 * - $painelTitulo, $painelSubtitulo, $painelRota, $painelAbaAtiva
 * - $painelMenu: array de grupos ['titulo' => ?string, 'itens' => [...]]
 * - $painelAlerta: ?array ['secao','qtd','texto'] — pill na navbar
 * - $painelRodape: ?string HTML extra no offcanvas
 * - $painelHeadExtra: ?string scripts/links extras no <head>
 * - $fotoAtual, $nomeUsuario
 */
$painelTitulo    = $painelTitulo    ?? 'LabHub | UNICEPLAC';
$painelSubtitulo = $painelSubtitulo ?? 'Painel';
$painelRota      = $painelRota      ?? 'login';
$painelAbaAtiva  = $painelAbaAtiva  ?? '';
$painelMenu      = $painelMenu      ?? [];
$painelAlerta    = $painelAlerta    ?? null;
$painelRodape    = $painelRodape    ?? '';
$painelHeadExtra = $painelHeadExtra ?? '';
$nomeUsuario     = $nomeUsuario     ?? ($_SESSION['nome'] ?? 'Usuário');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($painelTitulo) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <?= $painelHeadExtra ?>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top shadow-sm" id="mainNavbar">
    <div class="container-fluid px-3 px-md-4">
        <button class="btn btn-sm me-2 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuOffcanvas" title="Menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#<?= htmlspecialchars($painelAbaAtiva) ?>" onclick="showSection('<?= htmlspecialchars($painelAbaAtiva) ?>'); return false;">
            <img src="assets/images/uniceplac2.png" alt="Logo" height="32" id="navbarLogo">
            <span class="d-none d-md-inline text-muted fw-normal fs-6"><?= htmlspecialchars($painelSubtitulo) ?></span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <?php if ($painelAlerta && ($painelAlerta['qtd'] ?? 0) > 0): ?>
                <button type="button" class="btn btn-sm btn-warning rounded-pill fw-bold px-3 shadow-sm"
                        onclick="showSection('<?= htmlspecialchars($painelAlerta['secao']) ?>')">
                    <i class="bi bi-bell-fill me-1"></i><?= (int) $painelAlerta['qtd'] ?> <?= htmlspecialchars($painelAlerta['texto']) ?>
                </button>
            <?php endif; ?>
            <button class="btn btn-sm border-0 rounded-circle" id="themeToggle" type="button" title="Alternar tema">
                <i class="bi bi-moon-stars fs-5" id="themeIcon"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-sm border-0 d-flex align-items-center gap-2 rounded-pill px-2" type="button" data-bs-toggle="dropdown">
                    <img src="<?= htmlspecialchars($fotoAtual) ?>" class="rounded-circle object-fit-cover border" width="34" height="34" alt="Foto">
                    <span class="d-none d-md-inline fw-semibold small"><?= htmlspecialchars($nomeUsuario) ?></span>
                    <i class="bi bi-chevron-down small opacity-50"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#modalFoto"><i class="bi bi-camera me-2"></i>Alterar Foto</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="index.php?page=logout"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-start" tabindex="-1" id="menuOffcanvas" style="width:280px;">
    <div class="offcanvas-header border-bottom py-3">
        <h6 class="offcanvas-title fw-bold d-flex align-items-center gap-2">
            <i class="bi bi-grid-3x3-gap text-primary"></i> Navegação
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="py-2">
            <?php foreach ($painelMenu as $grupo): ?>
                <?php if (!empty($grupo['titulo'])): ?>
                    <div class="offcanvas-menu-group-label"><?= htmlspecialchars($grupo['titulo']) ?></div>
                <?php endif; ?>
                <?php foreach ($grupo['itens'] as $item): ?>
                    <a href="#<?= htmlspecialchars($item['id']) ?>" class="offcanvas-menu-link" onclick="showSection('<?= htmlspecialchars($item['id']) ?>')">
                        <i class="<?= htmlspecialchars($item['icone']) ?>"></i>
                        <?= htmlspecialchars($item['label']) ?>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="badge bg-danger rounded-pill ms-auto"><?= htmlspecialchars((string) $item['badge']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?= $painelRodape ?>
        </nav>
    </div>
</div>

<div class="container-fluid px-3 px-md-4 py-4">
    <div id="container-mensagens"><?= $mensagem ?? '' ?></div>
