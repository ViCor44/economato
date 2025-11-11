<?php
// 1. Iniciar a sessão para podermos aceder a ela.
session_start();

// 2. Limpar todas as variáveis da sessão.
$_SESSION = array();

// 3. Apagar o cookie da sessão. 
// Esta é uma boa prática para garantir que o logout é completo.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir a sessão do lado do servidor.
session_destroy();

// 5. Redirecionar o utilizador para a página de login.
header('Location: login.php');
exit;
?>