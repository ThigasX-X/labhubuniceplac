<?php
// Inclui a conexão com o banco de dados
require 'conexao.php';

$mensagem = "";
$tipo_alerta = "";

// Verifica se existe um 'token' na URL (ex: verificar.php?token=123456...)
if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
    $token = trim($_GET['token']);

    try {
        // 1. Busca no banco se existe algum usuário com este token e que AINDA NÃO foi verificado (0)
        $sql = "SELECT id, nome FROM usuarios WHERE token_verificacao = :token AND email_verificado = 0 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        // Se encontrou 1 linha, significa que o token é válido e pertence a alguém
        if ($stmt->rowCount() > 0) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 2. Atualiza o status do e-mail para verificado (1) e "limpa" o token por segurança
            // Limpar o token impede que o link seja reutilizado no futuro
            $sql_update = "UPDATE usuarios SET email_verificado = 1, token_verificacao = NULL WHERE id = :id";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':id', $usuario['id']);
            $stmt_update->execute();

            $mensagem = "Excelente, " . htmlspecialchars($usuario['nome']) . "! Seu e-mail foi verificado com sucesso. Seu acesso aos laboratórios está liberado.";
            $tipo_alerta = "success"; // Classe verde do Bootstrap

        } else {
            // Se não encontrou, ou o token está errado, ou o usuário já clicou nesse link antes
            $mensagem = "Link de verificação inválido ou sua conta já foi verificada anteriormente.";
            $tipo_alerta = "danger"; // Classe vermelha do Bootstrap
        }

    } catch (PDOException $e) {
        $mensagem = "Erro ao conectar com o banco de dados: " . $e->getMessage();
        $tipo_alerta = "danger";
    }

} else {
    // Se a pessoa tentar acessar verificar.php direto, sem o token na URL
    $mensagem = "Nenhum código de verificação foi fornecido. Por favor, acesse o link enviado para o seu e-mail.";
    $tipo_alerta = "warning"; // Classe amarela do Bootstrap
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação de Conta - Sistema de Laboratórios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow text-center" style="width: 30rem;">
            <div class="card-body p-5">
                
                <?php if ($tipo_alerta == 'success'): ?>
                    <h1 class="display-1 text-success mb-3">✓</h1>
                    <h3 class="card-title mb-4">Conta Ativada!</h3>
                <?php elseif ($tipo_alerta == 'warning'): ?>
                    <h1 class="display-1 text-warning mb-3">!</h1>
                    <h3 class="card-title mb-4">Atenção</h3>
                <?php else: ?>
                    <h1 class="display-1 text-danger mb-3">✗</h1>
                    <h3 class="card-title mb-4">Erro na Verificação</h3>
                <?php endif; ?>

                <div class="alert alert-<?= $tipo_alerta ?>" role="alert">
                    <?= $mensagem ?>
                </div>

                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary w-100">Ir para a página de Login</a>
                </div>
                
            </div>
        </div>
    </div>
</body>
</html>