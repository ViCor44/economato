<?php
session_start();

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';

// verificar autoload do composer
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    error_log("Erro: autoload do Composer não encontrado.");
    die("Ocorreu um erro no sistema.");
}
require_once '../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;

$google2fa = new Google2FA();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $one_time_password = trim($_POST['one_time_password'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $one_time_password)) {
        $error = "Código inválido. Insira 6 dígitos.";
    } else {

        try {
            // Buscar utilizador SOMENTE da tabela utilizadores
            $stmt = $pdo->prepare("
                SELECT id, email, nome, google_authenticator_secret, role_id
                FROM utilizadores
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['2fa_user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                session_unset();
                session_destroy();
                header('Location: login.php');
                exit;
            }

            // Verificar se o utilizador tem secret configurado
            if (empty($user['google_authenticator_secret'])) {
                error_log("User {$user['id']} não tem secret configurado.");
                $error = "A sua conta não tem 2FA configurado. Contacte o administrador.";
            } else {

                try {
                    $valid = $google2fa->verifyKey($user['google_authenticator_secret'], $one_time_password);

                    if ($valid) {
                        // Sucesso → inicia sessão normal
                        session_regenerate_id(true);
                        unset($_SESSION['2fa_user_id']);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role_id'] = $user['role_id'];
                        $_SESSION['user_name'] = $user['nome'];

                        header('Location: index.php');
                        exit;
                    } else {
                        $error = "Código inválido. Tente novamente.";
                    }

                } catch (Exception $e) {
                    error_log("Erro verifyKey: " . $e->getMessage());
                    $error = "Ocorreu um erro ao validar o código.";
                }
            }

        } catch (Exception $e) {
            error_log("Erro verificar_2fa: " . $e->getMessage());
            $error = "Ocorreu um erro no sistema.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Dois Fatores</title>
    <link href="/economato/public/css/style.css" rel="stylesheet">
</head>

<body class="min-h-screen flex items-center justify-center">

<div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
    <h1 class="text-3xl font-bold text-gray-800 text-center mb-2">Verificação de Segurança</h1>
    <p class="text-gray-600 text-center mb-8">Insira o código de 6 dígitos da sua aplicação de autenticação.</p>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md mb-6">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Código de Verificação</label>
            <input type="text"
                   name="one_time_password"
                   maxlength="6"
                   required
                   inputmode="numeric"
                   pattern="[0-9]{6}"
                   class="w-full text-center text-2xl tracking-[.4em] px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg">
            Verificar
        </button>
    </form>
</div>

</body>
</html>
