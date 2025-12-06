<?php
session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;

$google2fa = new Google2FA();
$errors = [];

// ==========================
// 🚀 PROCESSAMENTO DO FORM
// ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // --------------------------
    // 🔍 Validações básicas
    // --------------------------
    if (empty($nome_completo) || empty($email) || empty($password)) {
        $errors[] = "Todos os campos são obrigatórios.";
    }

    if ($password !== $password_confirm) {
        $errors[] = "As passwords não coincidem.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "O email inserido não é válido.";
    }

    // Verificar duplicado
    $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Este email já está registado.";
    }

    // --------------------------
    // ✅ Inserir novo utilizador
    // --------------------------
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Gerar chave secreta 2FA
            $secret_key = $google2fa->generateSecretKey();

            // Hash seguro da password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Inserir utilizador com conta inativa
            $stmt = $pdo->prepare("
                INSERT INTO utilizadores 
                (nome, email, password_hash, google_authenticator_secret, is_active, role_id)
                VALUES (?, ?, ?, ?, 0, NULL)
            ");
            $stmt->execute([$nome_completo, $email, $password_hash, $secret_key]);

            $pdo->commit();

            // Guardar sessão temporária para a próxima etapa
            $_SESSION['registration_success'] = true;
            $_SESSION['registration_secret_key'] = $secret_key;
            $_SESSION['registration_email'] = $email;

            header('Location: configurar_2fa.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Ocorreu um erro ao criar a sua conta. Por favor, tente novamente.";
            error_log("Erro no registo: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registo - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header_public.php'; ?>

    <main class="flex items-center justify-center py-12">
        <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
            <h1 class="text-3xl font-bold text-gray-800 text-center mb-2">Criar Conta</h1>
            <p class="text-gray-600 text-center mb-8">Registe-se para aceder ao CrewGest</p>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-6" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="registar.php" method="POST" class="space-y-4">
                <div>
                    <label for="nome_completo" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                    <input type="text" id="nome_completo" name="nome_completo"
                           class="w-full px-4 py-2 border rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email"
                           class="w-full px-4 py-2 border rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" name="password"
                           class="w-full px-4 py-2 border rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Password</label>
                    <input type="password" id="password_confirm" name="password_confirm"
                           class="w-full px-4 py-2 border rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                        Registar
                    </button>
                </div>

                <p class="text-center text-sm text-gray-600 mt-6">
                    Já tem conta?
                    <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">Faça login aqui.</a>
                </p>
            </form>
        </div>
    </main>
<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>
