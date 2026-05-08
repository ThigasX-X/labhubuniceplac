<?php
require_once BACKEND_PATH . '/models/Usuario.php';
require_once BACKEND_PATH . '/helpers/Auth.php';

class AuthController extends BaseController
{
    public function login(): void
    {
        $erro   = '';
        $sucesso = '';

        if (isset($_GET['msg'])) {
            $sucesso = match ($_GET['msg']) {
                'cadastro_ok'     => 'Cadastro realizado! Aguarde ativação ou use o login institucional.',
                'email_confirmado' => 'E-mail confirmado! Você já pode fazer login.',
                default           => '',
            };
        }

        if ($this->isPost()) {
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            $model   = new Usuario($this->pdo);
            $usuario = $model->findByEmail($email);

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
            $nome           = trim($_POST['nome'] ?? '');
            $email          = trim($_POST['email'] ?? '');
            $senha          = $_POST['senha'] ?? '';
            $confirmarSenha = $_POST['confirmar_senha'] ?? '';

            if (!str_ends_with($email, '@uniceplac.edu.br')) {
                $mensagem = '<div class="alert alert-danger py-2 small">Use apenas seu e-mail institucional (@uniceplac.edu.br).</div>';
            } elseif ($senha !== $confirmarSenha) {
                $mensagem = '<div class="alert alert-danger py-2 small">As senhas não coincidem.</div>';
            } elseif (strlen($senha) < 8) {
                $mensagem = '<div class="alert alert-danger py-2 small">A senha deve ter pelo menos 8 caracteres.</div>';
            } else {
                $model = new Usuario($this->pdo);

                if ($model->findByEmail($email)) {
                    $mensagem = '<div class="alert alert-warning py-2 small">Este e-mail já está cadastrado. <a href="/index.php?page=login">Faça login</a>.</div>';
                } else {
                    try {
                        $model->create($nome, $email, password_hash($senha, PASSWORD_DEFAULT));
                        $mensagem = '<div class="alert alert-success py-2 small">✅ Cadastro realizado! Aguarde a ativação ou tente o login institucional.</div>';
                    } catch (PDOException $e) {
                        $mensagem = '<div class="alert alert-danger py-2 small">Erro técnico: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            }
        }

        $this->render('auth/cadastro', compact('mensagem'));
    }

    public function verificar(): void
    {
        $mensagem  = '';
        $tipoAlerta = '';

        $token = trim($_GET['token'] ?? '');

        if (empty($token)) {
            $mensagem  = 'Nenhum código de verificação foi fornecido.';
            $tipoAlerta = 'warning';
        } else {
            $model = new Usuario($this->pdo);
            if ($model->verificarEmail($token)) {
                $mensagem  = 'E-mail verificado com sucesso! Acesso liberado.';
                $tipoAlerta = 'success';
            } else {
                $mensagem  = 'Link inválido ou conta já verificada anteriormente.';
                $tipoAlerta = 'danger';
            }
        }

        $this->render('auth/verificar', compact('mensagem', 'tipoAlerta'));
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

            $model   = new Usuario($this->pdo);
            $usuario = $model->upsertGoogle($info->email, $info->name, $info->id, $info->picture);

            Auth::login($usuario);
            $_SESSION['foto'] = $info->picture;

            header('Location: ' . Auth::destinoAposLogin($usuario['perfil']));
            exit;
        } catch (Exception $e) {
            die('Erro Crítico: ' . htmlspecialchars($e->getMessage()));
        }
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /index.php?page=login');
        exit;
    }
}
