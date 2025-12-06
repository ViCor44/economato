<?php
// Inicia a sessão. É seguro chamar isto múltiplas vezes.
if (session_status() === PHP_SESSION_NONE) {
    require_once '../src/session_bootstrap.php';
}

// A verificação principal: o 'user_id' existe na sessão?
if (!isset($_SESSION['user_id'])) {
    // Se não existir, o utilizador não está logado.
    // Redireciona-o para a página de login e termina o script.
    header('Location: login.php');
    exit;
}

// Se o script continuar, significa que o utilizador está autenticado.
// Podemos também guardar os dados do utilizador numa variável para fácil acesso.
$utilizador_logado = [
    'id' => $_SESSION['user_id'],
    'email' => $_SESSION['user_email'],
    'role_id' => $_SESSION['user_role_id'],    
    'nome' => $_SESSION['user_name'] ?? null // <-- Adicionar esta linha
];

?>
