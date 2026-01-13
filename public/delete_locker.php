<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Só aceitar GET com número válido
$numero = isset($_GET['numero']) ? (int)$_GET['numero'] : 0;

if ($numero <= 0) {
    $_SESSION['error_message'] = 'Número de cacifo inválido.';
    header('Location: list_lockers.php');
    exit;
}

try {
    // Verificar se o cacifo existe
    $stmt = $pdo->prepare("SELECT numero FROM cacifos WHERE numero = ?");
    $stmt->execute([$numero]);
    $cacifo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cacifo) {
        $_SESSION['error_message'] = "O cacifo #{$numero} não existe.";
        header('Location: list_lockers.php');
        exit;
    }

    // Apagar cacifo
    $del = $pdo->prepare("DELETE FROM cacifos WHERE numero = ?");
    $del->execute([$numero]);

    $_SESSION['success_message'] = "✅ Cacifo #{$numero} eliminado com sucesso.";

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erro ao eliminar cacifo: " . $e->getMessage();
}

// Voltar à lista
header('Location: list_lockers.php');
exit;
