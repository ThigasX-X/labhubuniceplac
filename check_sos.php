<?php
// check_sos.php
require 'conexao.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM chamados_suporte WHERE status = 'pendente'");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Retorna o número de chamados em formato JSON
    echo json_encode(['qtd' => (int)$resultado['total']]);
} catch (PDOException $e) {
    echo json_encode(['qtd' => 0]);
}