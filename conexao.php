<?php
// 1. FORÇA O FUSO HORÁRIO DO PHP (Adicionado aqui!)
date_default_timezone_set('America/Sao_Paulo');

// Configurações do banco de dados (Ajuste se o seu usuário/senha do XAMPP/Wamp for diferente)
$host = 'localhost';
$dbname = 'sistema_labs';
$usuario = 'root'; // Usuário padrão do MySQL local
$senha = '';       // Geralmente vazio no XAMPP, ou 'root' no MAMP

try {
    // Cria a conexão PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usuario, $senha);

    // 2. FORÇA O FUSO HORÁRIO DO BANCO DE DADOS
    $pdo->exec("SET time_zone = '-03:00'");
    
    // Configura o PDO para lançar exceções em caso de erro (ótimo para debugar)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // Se der erro, para a execução e mostra o motivo
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}
?>