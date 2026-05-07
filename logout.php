<?php
session_start();
session_destroy(); // Apaga todas as variáveis de sessão
header("Location: index.php"); // Redireciona para o login
exit;
?>