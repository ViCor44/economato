<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$errors = [];
$successMessage = '';

/* ==========================
   🧩 PROCESSAMENTO POST
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 🔐 Alterar Password
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = 'Todos os campos são obrigatórios.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'A nova password e a confirmação não coincidem.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM utilizadores WHERE id = ?");
                $stmt->execute([$utilizador_logado['id']]);
                $user = $stmt->fetch();

                if ($user && password_verify($current_password, $user['password_hash'])) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE id = ?");
                    $update->execute([$new_hash, $utilizador_logado['id']]);
                    $successMessage = '✅ Password alterada com sucesso!';
                } else {
                    $errors[] = 'A password atual está incorreta.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Erro ao alterar a password. Tente novamente.';
            }
        }
    }

    // 🔓 Desativar 2FA
    if ($action === 'disable_2fa') {
        try {
            $stmt = $pdo->prepare("UPDATE utilizadores SET google_authenticator_secret = NULL WHERE id = ?");
            $stmt->execute([$utilizador_logado['id']]);
            $successMessage = '🔒 Autenticação de dois fatores desativada com sucesso.';
        } catch (PDOException $e) {
            $errors[] = 'Erro ao desativar o 2FA.';
        }
    }
}

/* ==========================
   🧠 OBTER DADOS DO UTILIZADOR
========================== */
try {
    $stmt = $pdo->prepare("SELECT nome, email, google_authenticator_secret FROM utilizadores WHERE id = ?");
    $stmt->execute([$utilizador_logado['id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 🧠 Avaliar estado do 2FA com mais precisão
    $is_2fa_enabled = isset($user_data['google_authenticator_secret'])
        && $user_data['google_authenticator_secret'] !== null
        && trim($user_data['google_authenticator_secret']) !== '';
} catch (PDOException $e) {
    $errors[] = "Erro ao carregar os dados do perfil.";
    $is_2fa_enabled = false;
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O Meu Perfil - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<main class="p-8">
    <div class="max-w-4xl mx-auto space-y-8">

        <h1 class="text-3xl font-bold text-gray-800 mb-4">👤 O Meu Perfil</h1>

        <!-- Mensagens -->
        <?php if ($successMessage): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md">
                <ul class="list-disc pl-5 space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Dados básicos -->
        <div class="bg-white p-8 rounded-2xl shadow-md">
            <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">👥 Informações do Utilizador</h2>
            <p><strong>Nome:</strong> <?= htmlspecialchars($user_data['nome']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user_data['email']) ?></p>
        </div>

        <!-- Secção 2FA -->
        <div class="bg-white p-8 rounded-2xl shadow-md">
            <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">🔐 Autenticação de Dois Fatores (2FA)</h2>

            <?php if ($is_2fa_enabled): ?>
                <!-- 2FA Ativo -->
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-green-600">Estado: <strong>Ativo ✅</strong></p>
                        <p class="text-sm text-gray-600">A sua conta está protegida com 2FA.</p>
                    </div>
                    <form action="perfil.php" method="POST">
                        <input type="hidden" name="action" value="disable_2fa">
                        <button type="submit"
                                class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-5 rounded-lg transition">
                            Desativar 2FA
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- 2FA Inativo -->
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-600">Estado: <strong>Inativo ⚠️</strong></p>
                        <p class="text-sm text-gray-600">Ative o 2FA para reforçar a segurança da sua conta.</p>
                    </div>
                    <a href="ativar_2fa.php"
   style="
      display: inline-block;
      background-color: #22c55e;
      color: #fff;
      font-weight: 600;
      padding: 10px 18px;
      border-radius: 8px;
      text-decoration: none;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
   "
   onmouseover="this.style.backgroundColor='#16a34a'; this.style.transform='scale(1.03)';"
   onmouseout="this.style.backgroundColor='#22c55e'; this.style.transform='scale(1)';">
   Ativar 2FA
</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Alterar Password -->
        <div class="bg-white p-8 rounded-2xl shadow-md">
            <h2 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">🔑 Alterar Password</h2>
            <form action="perfil.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="current_password">Password Atual</label>
                    <input type="password" id="current_password" name="current_password"
                           class="w-full px-4 py-2 border rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="new_password">Nova Password</label>
                    <input type="password" id="new_password" name="new_password"
                           class="w-full px-4 py-2 border rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="confirm_password">Confirmar Nova Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="w-full px-4 py-2 border rounded-md border-gray-300 focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div class="text-right pt-2">
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition">
                        Guardar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

</body>
</html>
