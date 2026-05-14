<?php
require_once __DIR__ . '/../DAOImpl/UsuarioDAOImpl.php';

class CadastroRestService {
    private $usuarioDAO;

    public function __construct($pdo) {
        $this->usuarioDAO = new UsuarioDAOImpl($pdo);
    }

    public function salvar(array $dados) {
        if ($this->usuarioDAO->findByEmail($dados['email'])) {
            return ['status' => 'error', 'message' => 'E-mail já cadastrado.'];
        }

        $hash = password_hash($dados['senha'], PASSWORD_DEFAULT);

        $id = $this->usuarioDAO->create($dados['nome'], $dados['email'], $hash, $dados['perfil']);

        return ['status' => 'success', 'id' => $id];
    }
}