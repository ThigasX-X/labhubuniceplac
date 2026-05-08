<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --verde-uniceplac: #00734F; --roxo-uniceplac: #421B71; }
        body { background-color: #e9ecef; font-family: 'Segoe UI', sans-serif; }
        .card-cadastro { border: none; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,.1); overflow: hidden; max-width: 420px; width: 100%; margin: auto; }
        .bg-cadastro-header { background: linear-gradient(135deg, var(--verde-uniceplac), #009666); color: white; padding: 20px; text-align: center; }
        .btn-uniceplac { background-color: var(--verde-uniceplac); color: white; font-weight: 700; border: none; transition: .3s; }
        .btn-uniceplac:hover { background-color: #005a3e; color: white; }
        .form-label-nitido { font-size: .75rem; text-transform: uppercase; font-weight: 700; color: #495057; letter-spacing: .5px; margin-bottom: .25rem; display: block; }
        .password-toggle { cursor: pointer; color: #6c757d; }
    </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100 p-3">
<div class="d-flex flex-column align-items-center w-100">
    <div class="text-center mb-3">
        <img src="/assets/images/uniceplac2.png" alt="Logo UNICEPLAC" style="max-height:80px;">
    </div>
    <div class="card card-cadastro">
        <div class="bg-cadastro-header">
            <h4 class="fw-bold mb-1">Cadastro</h4>
            <p class="mb-0 opacity-85 small">Acesso à Central de Reservas</p>
        </div>
        <div class="card-body p-4">
            <?= $mensagem ?>
            <form action="/index.php?page=cadastro" method="POST" id="formCadastro">
                <div class="mb-2">
                    <label class="form-label-nitido">Nome Completo</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="nome" class="form-control" required placeholder="Ex: João Silva">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label-nitido">E-mail Institucional</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required placeholder="nome@uniceplac.edu.br">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label-nitido">Senha</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="senha" id="senha" class="form-control" required placeholder="Mínimo 8 caracteres">
                        <span class="input-group-text bg-white password-toggle" onclick="togglePassword('senha','iconSenha')">
                            <i class="bi bi-eye-slash" id="iconSenha"></i>
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-nitido">Confirmar Senha</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-control" required placeholder="••••••••">
                        <span class="input-group-text bg-white password-toggle" onclick="togglePassword('confirmar_senha','iconConfirmarSenha')">
                            <i class="bi bi-eye-slash" id="iconConfirmarSenha"></i>
                        </span>
                    </div>
                </div>
                <button type="submit" name="cadastrar_banco" class="btn btn-uniceplac w-100 py-2 mb-2">
                    <i class="bi bi-person-plus-fill me-2"></i>Finalizar Cadastro
                </button>
                <div class="text-center mt-3">
                    <span class="text-muted small">Já tem conta?</span>
                    <a href="/index.php?page=login" class="text-decoration-none small fw-bold" style="color:var(--roxo-uniceplac);">Faça Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') { input.type = 'text'; icon.classList.replace('bi-eye-slash','bi-eye'); }
    else { input.type = 'password'; icon.classList.replace('bi-eye','bi-eye-slash'); }
}
</script>
</body>
</html>
