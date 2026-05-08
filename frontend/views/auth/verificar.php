<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação de Conta - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow text-center" style="width:30rem;">
            <div class="card-body p-5">
                <?php if ($tipoAlerta === 'success'): ?>
                    <h1 class="display-1 text-success mb-3">✓</h1>
                    <h3 class="card-title mb-4">Conta Ativada!</h3>
                <?php elseif ($tipoAlerta === 'warning'): ?>
                    <h1 class="display-1 text-warning mb-3">!</h1>
                    <h3 class="card-title mb-4">Atenção</h3>
                <?php else: ?>
                    <h1 class="display-1 text-danger mb-3">✗</h1>
                    <h3 class="card-title mb-4">Erro na Verificação</h3>
                <?php endif; ?>
                <div class="alert alert-<?= $tipoAlerta ?>"><?= htmlspecialchars($mensagem) ?></div>
                <a href="/index.php?page=login" class="btn btn-primary w-100 mt-3">Ir para o Login</a>
            </div>
        </div>
    </div>
</body>
</html>
