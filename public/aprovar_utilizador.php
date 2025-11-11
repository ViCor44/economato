<?php
require_once '../src/auth_guard.php';
// NOTA FUTURA: Adicionar verificação de Admin aqui também.

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $role_id = $_POST['role_id'] ?? null;

    // Validação simples
    if (empty($user_id) || empty($role_id)) {
        // Idealmente, usar sessões para mensagens de erro
        header('Location: gerir_utilizadores.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE utilizadores SET is_active = 1, role_id = ? WHERE id = ?");
        $stmt->execute([$role_id, $user_id]);
        
        // (Opcional) Guardar mensagem de sucesso na sessão
        
    } catch (PDOException $e) {
        // (Opcional) Guardar mensagem de erro na sessão
    }
}

// Redirecionar de volta para a página de gestão
header('Location: gerir_utilizadores.php');
exit;
?>