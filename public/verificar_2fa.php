<?php
session_start();

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;

$google2fa = new Google2FA();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $one_time_password = $_POST['one_time_password'] ?? '';

    try {
        // QUERY CORRIGIDA: Ir buscar também o nome do utilizador
        $stmt = $pdo->prepare("
            SELECT u.*, COALESCE(f.nome_completo, u.nome) AS nome_final
            FROM utilizadores u
            LEFT JOIN funcionarios f ON u.funcionario_id = f.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['2fa_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $valid = $google2fa->verifyKey($user['google_authenticator_secret'], $one_time_password);

            if ($valid) {
                session_regenerate_id(true);
                unset($_SESSION['2fa_user_id']);
                
                // SESSÃO CRIADA COM O NOME
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role_id'] = $user['role_id'];
                $_SESSION['user_name'] = $user['nome_final']; // <-- A linha crucial

                header('Location: index.php');
                exit;
            } else {
                $error = "Código inválido. Por favor, tente novamente.";
            }
        } else {
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } catch (Exception $e) {
        $error = "Ocorreu um erro no sistema.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Dois Fatores - Slide RH</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
        <h1 class="text-3xl font-bold text-gray-800 text-center mb-2">Verificação de Segurança</h1>
        <p class="text-gray-600 text-center mb-8">Insira o código de 6 dígitos da sua aplicação de autenticação.</p>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-6" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="verificar_2fa.php" method="POST">
            <div class="mb-6">
                <label for="one_time_password" class="block text-sm font-medium text-gray-700 mb-1">Código de Verificação</label>
                <input type="text" id="one_time_password" name="one_time_password" 
                       class="w-full text-center text-2xl tracking-[1em] px-4 py-2 border rounded-md border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                       required maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code">
            </div>
            <div class="text-center">
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors">
                    Verificar
                </button>
            </div>
        </form>
    </div>

</body>
</html>