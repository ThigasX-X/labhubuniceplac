<?php
session_start();
require 'conexao.php';

// Proteção: apenas Suporte e Coordenador podem consultar isso
if (!isset($_SESSION['usuario_id']) || ($_SESSION['perfil'] !== 'suporte' && $_SESSION['perfil'] !== 'coordenador')) {
    echo json_encode(['qtd_suporte' => 0, 'html_suporte' => '']);
    exit;
}

try {
    // Busca os chamados pendentes
    $stmt = $pdo->query("SELECT * FROM chamados_suporte WHERE status = 'pendente' ORDER BY data_hora DESC");
    $chamados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $qtd = count($chamados);
    
    $html = '';
    
    if ($qtd > 0) {
        foreach ($chamados as $c) {
            $data_f = date('d/m H:i', strtotime($c['data_hora']));
            $lab = htmlspecialchars($c['laboratorio']);
            $prof = htmlspecialchars($c['professor_nome']);
            
            // AQUI ESTÁ A CORREÇÃO: Capturando a descrição do problema
            $msg = htmlspecialchars($c['mensagem']); 
            $id = $c['id'];
            
            // Desenhando o alerta em estilo Apple Glassmorphism
            $html .= "
            <div class='alert shadow-sm mb-4' style='border-radius: 20px; border: 1px solid rgba(220, 53, 69, 0.3); background: rgba(220, 53, 69, 0.08); backdrop-filter: blur(12px);'>
                <div class='d-flex flex-column flex-md-row justify-content-between align-items-md-center'>
                    <div class='mb-3 mb-md-0'>
                        <h5 class='fw-bold text-danger mb-1'>
                            <i class='bi bi-exclamation-triangle-fill heartbeat me-2'></i> CHAMADO SOS: {$lab}
                        </h5>
                        <p class='mb-1 fw-bold text-dark' style='font-size: 0.95rem;'>
                            <i class='bi bi-person-badge text-secondary me-1'></i> Prof(a). {$prof} 
                            <span class='text-muted fw-normal ms-2 small'><i class='bi bi-clock me-1'></i>{$data_f}</span>
                        </p>
                        
                        <div class='bg-white p-3 mt-2 rounded-4 border shadow-sm'>
                            <p class='mb-0 text-dark small'><strong><i class='bi bi-chat-left-text me-2 text-danger'></i>Relato:</strong> {$msg}</p>
                        </div>
                    </div>
                    
                    <form method='POST' action='painel_suporte.php' class='text-end ms-md-3'>
                        <input type='hidden' name='id_chamado' value='{$id}'>
                        <button type='submit' name='resolver_chamado' class='btn btn-danger fw-bold rounded-pill shadow px-4 py-2' style='transition: 0.2s;'>
                            <i class='bi bi-check2-circle me-2'></i> Resolver SOS
                        </button>
                    </form>
                </div>
            </div>";
        }
    }
    
    // Retorna os dados em formato JSON para o JavaScript atualizar a tela
    echo json_encode(['qtd_suporte' => $qtd, 'html_suporte' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['qtd_suporte' => 0, 'html_suporte' => '']);
}