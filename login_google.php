<?php
// login_google.php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/conexao.php';

use Google\Client;
use Google\Service\Oauth2;
use GuzzleHttp\Client as GuzzleClient;

// 1. CONFIGURAÇÕES
// Configure as variáveis no seu ambiente (.env ou no servidor)
$clientID     = getenv('GOOGLE_CLIENT_ID')     ?: $_ENV['GOOGLE_CLIENT_ID']     ?? '';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
$redirectUri  = getenv('GOOGLE_REDIRECT_URI')  ?: $_ENV['GOOGLE_REDIRECT_URI']  ?? 'http://localhost/labs/login_google.php';

$client = new Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

// Pula verificação SSL no Localhost (Essencial para XAMPP)
$httpClient = new GuzzleClient(['verify' => false]);
$client->setHttpClient($httpClient);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (!isset($token['error'])) {
            $client->setAccessToken($token['access_token']);

            $google_oauth = new Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();
            
            $email     = $google_account_info->email;
            $nome      = $google_account_info->name;
            $google_id = $google_account_info->id;
            $foto      = $google_account_info->picture;

            // --- LÓGICA DE PERSISTÊNCIA NO BANCO ---
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT id, perfil, nome FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                // ATUALIZA usuário existente
                $update = $pdo->prepare("UPDATE usuarios SET google_id = ?, foto_perfil = ? WHERE email = ?");
                $update->execute([$google_id, $foto, $email]);
                
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['perfil']     = $usuario['perfil'];
                $_SESSION['nome']       = $usuario['nome'];
            } else {
                // INSERE usuário novo
                $sql = "INSERT INTO usuarios (nome, email, google_id, foto_perfil, perfil, email_verificado) 
                        VALUES (?, ?, ?, ?, 'professor', 1)";
                $ins = $pdo->prepare($sql);
                $ins->execute([$nome, $email, $google_id, $foto]);
                
                $_SESSION['usuario_id'] = $pdo->lastInsertId();
                $_SESSION['perfil']     = 'professor';
                $_SESSION['nome']       = $nome;
            }

            $_SESSION['foto'] = $foto;

            // REDIRECIONAMENTO POR PERFIL
            if ($_SESSION['perfil'] == 'coordenador') {
                header("Location: dashboard_bi.php");
            } else {
                header("Location: painel_professor.php");
            }
            exit;
            
        }
    } catch (Exception $e) {
        die("Erro Crítico: " . $e->getMessage());
    }
} else {
    $authUrl = $client->createAuthUrl();
    header("Location: " . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}