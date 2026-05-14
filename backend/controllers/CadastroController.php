<?php

// Importamos a Base para herdar funções úteis
require_once __DIR__ . '/BaseController.php'; 
// Importamos o Service (O Cérebro)
require_once __DIR__ . '/../services/CadastroRestService.php';

class CadastroController extends BaseController {

    private $cadastroService;

    public function __construct($pdo) {
        parent::__construct($pdo);
        // Instanciamos o Service que vai processar a lógica
        $this->cadastroService = new CadastroRestService($pdo);
    }

    /**
     * Método principal chamado pela rota 'cadastro'
     */
    public function cadastro() {
        // Se não for POST, apenas exibe a página (View) ou dá erro
        if (!$this->isPost()) {
            // Se você quiser que o Insomnia veja erro quando for GET:
            if ($this->isApiRequest()) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Método não permitido'], 405);
            }
            // Caso contrário, carrega a view normal do formulário
            return $this->view('cadastro_view');
        }

        // --- CAMADA DE SEGURANÇA 1: RECEBIMENTO E SANITIZAÇÃO ---
        $dadosBrutos = $this->getPostData();
        
        $dadosLimpos = [
            'nome'   => filter_var(trim($dadosBrutos['nome'] ?? ''), FILTER_SANITIZE_SPECIAL_CHARS),
            'email'  => filter_var(trim($dadosBrutos['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'senha'  => $dadosBrutos['senha'] ?? '', // Senha não sanitiza (Service vai dar hash)
            'perfil' => $dadosBrutos['perfil'] ?? 'professor'
        ];

        // --- CAMADA DE SEGURANÇA 2: CHAMADA DO SERVICE (DELEGATE) ---
        try {
            // O Controller não sabe COMO salvar, ele só pede para o Service salvar
            $resultado = $this->cadastroService->salvar($dadosLimpos);

            if ($resultado['status'] === 'success') {
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Cadastro realizado com sucesso!',
                    'data' => $resultado['data'] ?? []
                ], 201);
            } else {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => $resultado['message']
                ], 400);
            }

        } catch (Exception $e) {
            // Log do erro real no servidor e resposta genérica para o usuário
            error_log($e->getMessage());
            return $this->jsonResponse(['status' => 'error', 'message' => 'Erro interno ao processar cadastro.'], 500);
        }
    }

    /**
     * Função auxiliar para pegar dados de POST (Formulário ou JSON)
     */
    private function getPostData() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        return $data ?: $_POST;
    }

    /**
     * Detecta se a requisição espera um JSON (como o Insomnia)
     */
    private function isApiRequest() {
        $headers = getallheaders();
        return (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') 
               || isset($_GET['api']);
    }
}