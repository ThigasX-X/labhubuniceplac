<?php
$host = '127.0.0.1';
$usuario = 'root'; 
// Tente com senha vazia. Se der erro, mude para 'root' na linha de baixo.
$senha = ''; 

try {
    // Conecta no MySQL geral, sem especificar o dbname ainda
    $pdo = new PDO("mysql:host=$host;charset=utf8", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Cria o banco de dados
    $pdo->exec("CREATE DATABASE IF NOT EXISTS sistema_labs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 2. Entra no banco de dados
    $pdo->exec("USE sistema_labs");
    
    // 3. Cria a tabela
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        perfil VARCHAR(50) DEFAULT 'professor',
        email_verificado TINYINT(1) DEFAULT 0
    )");
    
    echo "✅ SUCESSO! Banco de dados 'sistema_labs' e tabela 'usuarios' criados com sucesso!";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage();
}
?>