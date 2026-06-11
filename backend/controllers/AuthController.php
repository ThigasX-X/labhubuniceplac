<?php
require_once __DIR__ . '/BaseController.php';
require_once dirname(__DIR__) . '/DAOImpl/UsuarioDAOImpl.php';
require_once __DIR__ . '/../services/CadastroRestService.php';
require_once __DIR__ . '/../helpers/Auth.php';

class AuthController extends BaseController
{
    private UsuarioDAOImpl $usuarioDAO;
    private CadastroRestService $cadastroService;

    public function __construct($pdo)
    {
        parent::__construct($pdo);
        // Injetamos as novas camadas
        $this->usuarioDAO = new UsuarioDAOImpl($this->pdo);
        $this->cadastroService = new CadastroRestService($this->pdo);
    }

    public function login(): void
    {
        $erro = '';
        $sucesso = '';

        // Mantemos suas mensagens de feedback via GET
        if (isset($_GET['msg'])) {
            $sucesso = match ($_GET['msg']) {
                'cadastro_ok'      => 'Cadastro realizado! Você já pode fazer login.',
                'email_confirmado' => 'E-mail confirmado! Você já pode fazer login.',
                default            => '',
            };
        }

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            // Usamos o DAO agora
            $usuario = $this->usuarioDAO->findByEmail($email);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                if ($usuario['email_verificado'] == 0) {
                    $erro = 'Seu acesso está bloqueado. Confirme seu e-mail.';
                } else {
                    Auth::login($usuario);
                    header('Location: ' . Auth::destinoAposLogin($usuario['perfil']));
                    exit;
                }
            } else {
                $erro = 'E-mail ou senha incorretos!';
            }
        }

        $this->render('auth/login', compact('erro', 'sucesso'));
    }

    public function cadastro(): void
    {
        $mensagem = '';

        if ($this->isPost() && isset($_POST['cadastrar_banco'])) {
            // Preparamos os dados para o Service
            $dados = [
                'nome'            => trim($_POST['nome'] ?? ''),
                'email'           => trim($_POST['email'] ?? ''),
                'senha'           => $_POST['senha'] ?? '',
                'confirmar_senha' => $_POST['confirmar_senha'] ?? '',
                'perfil'          => 'professor'
            ];

            // --- VALIDAÇÕES RÁPIDAS DE INTERFACE ---
            if (!str_ends_with($dados['email'], '@uniceplac.edu.br')) {
                $mensagem = '<div class="alert alert-danger py-2 small">Use apenas seu e-mail institucional.</div>';
            } elseif (strlen($dados['senha']) < 8) {
                $mensagem = '<div class="alert alert-danger py-2 small">A senha deve ter pelo menos 8 caracteres.</div>';
            } elseif ($dados['senha'] !== $dados['confirmar_senha']) {
                $mensagem = '<div class="alert alert-danger py-2 small">As senhas não coincidem.</div>';
            } else {
                // --- DELEGAMOS PARA O SERVICE (O Cérebro) ---
                $resultado = $this->cadastroService->salvar($dados);

                if ($resultado['status'] === 'success') {
                    $this->redirect('login', ['msg' => 'cadastro_ok']);
                } else {
                    $mensagem = '<div class="alert alert-warning py-2 small">' . $resultado['message'] . '</div>';
                }
            }
        }

        $this->render('auth/cadastro', compact('mensagem'));
    }

    public function google(): void
    {
        require_once ROOT_PATH . '/vendor/autoload.php';

        $clientID     = getenv('GOOGLE_CLIENT_ID')     ?: ($_ENV['GOOGLE_CLIENT_ID']     ?? '');
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: ($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
        $redirectUri  = getenv('GOOGLE_REDIRECT_URI')  ?: ($_ENV['GOOGLE_REDIRECT_URI']  ?? 'http://localhost/index.php?page=google');

        $client = new Google\Client();
        $client->setClientId($clientID);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->addScope('email');
        $client->addScope('profile');

        $http = new GuzzleHttp\Client(['verify' => false]);
        $client->setHttpClient($http);

        if (!isset($_GET['code'])) {
            header('Location: ' . filter_var($client->createAuthUrl(), FILTER_SANITIZE_URL));
            exit;
        }

        try {
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

            if (isset($token['error'])) {
                die('Erro OAuth: ' . htmlspecialchars($token['error']));
            }

            $client->setAccessToken($token['access_token']);
            $info = (new Google\Service\Oauth2($client))->userinfo->get();

            // Restringe a um domínio (ex.: uniceplac.edu.br) se configurado no .env
            $dominioPermitido = getenv('GOOGLE_ALLOWED_DOMAIN') ?: ($_ENV['GOOGLE_ALLOWED_DOMAIN'] ?? '');
            if ($dominioPermitido !== '' && !str_ends_with($info->email, '@' . ltrim($dominioPermitido, '@'))) {
                die('Acesso negado: use seu e-mail institucional (@' . htmlspecialchars(ltrim($dominioPermitido, '@')) . ').');
            }

            $usuario = $this->usuarioDAO->upsertGoogle($info->email, $info->name, $info->picture);

            Auth::login($usuario);
            $_SESSION['foto'] = $info->picture;

            header('Location: ' . Auth::destinoAposLogin($usuario['perfil']));
            exit;
        } catch (Exception $e) {
            die('Erro Crítico: ' . htmlspecialchars($e->getMessage()));
        }
    }

    public function verificar(): void
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            $this->render('auth/verificar', [
                'tipoAlerta' => 'warning',
                'mensagem'   => 'Nenhum código de verificação foi fornecido. Acesse o link enviado para o seu e-mail.',
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nome FROM usuarios WHERE token_verificacao = ? AND email_verificado = 0 LIMIT 1"
            );
            $stmt->execute([$token]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $this->pdo->prepare("UPDATE usuarios SET email_verificado = 1, token_verificacao = NULL WHERE id = ?")
                    ->execute([$usuario['id']]);
                $tipoAlerta = 'success';
                $mensagem   = 'Excelente, ' . $usuario['nome'] . '! Seu e-mail foi verificado com sucesso. Acesso liberado.';
            } else {
                $tipoAlerta = 'danger';
                $mensagem   = 'Link de verificação inválido ou sua conta já foi verificada anteriormente.';
            }
        } catch (PDOException) {
            // Banco sem a coluna token_verificacao (cadastro atual já ativa a conta direto)
            $tipoAlerta = 'danger';
            $mensagem   = 'Link de verificação inválido ou sua conta já foi verificada anteriormente.';
        }

        $this->render('auth/verificar', compact('tipoAlerta', 'mensagem'));
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /index.php?page=login');
        exit;
    }
}
