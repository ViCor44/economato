<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// 🚫 Apenas administradores
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN) {
    header('Location: acesso_negado.php');
    exit;
}

// 🧩 Validar ID do utilizador
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: gerir_utilizadores.php");
    exit;
}
$user_id = (int)$_GET['id'];
$is_self = ($user_id === (int)$utilizador_logado['id']);

$success = $_GET['sucesso'] ?? false;
$new_password = null;
$error = null;

// ⚙️ Atualização geral
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if ($is_self) {
        $error = "❌ Não pode alterar o seu próprio perfil de administrador.";
    } else {
        $novo_role_id = $_POST['role_id'] ?? null;
        $novo_estado = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE utilizadores SET role_id = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$novo_role_id, $novo_estado, $user_id]);
            header("Location: editar_utilizador.php?id={$user_id}&sucesso=1");
            exit;
        } catch (PDOException $e) {
            $error = "Erro ao atualizar o utilizador: " . $e->getMessage();
        }
    }
}

// 🔑 Repor password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if ($is_self) {
        $error = "❌ Não pode repor a sua própria password.";
    } else {
        $new_password = bin2hex(random_bytes(4)); // 8 caracteres
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE id = ?")->execute([$hash, $user_id]);
        $success = true;
    }
}

// 🔍 Buscar dados atuais
try {
    $stmt = $pdo->prepare("SELECT id, nome, email, role_id, is_active FROM utilizadores WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: gerir_utilizadores.php");
        exit;
    }

    $roles = $pdo->query("SELECT id, nome_role FROM roles ORDER BY nome_role ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar os dados do utilizador.");
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Editar Utilizador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<main class="p-8">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-lg">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Editar Utilizador</h1>
        <p class="text-gray-600 mb-6">A gerir o utilizador <strong><?= htmlspecialchars($user['nome']) ?></strong>.</p>

        <?php if ($success && !$new_password): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">
                ✅ Utilizador atualizado com sucesso.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($new_password): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-6">
                🔑 Nova password temporária: 
                <strong><?= htmlspecialchars($new_password) ?></strong><br>
                Peça ao utilizador para alterá-la após o login.
            </div>
        <?php endif; ?>

        <form action="editar_utilizador.php?id=<?= $user_id ?>" method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" value="<?= htmlspecialchars($user['nome']) ?>" disabled
                    class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                    class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>

            <div>
                <label for="role_id" class="block text-sm font-medium text-gray-700 mb-1">Função (Role)</label>
                <select name="role_id" id="role_id"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                    <?= $is_self ? 'disabled' : '' ?>>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= ($user['role_id'] == $role['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['nome_role']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       <?= $user['is_active'] ? 'checked' : '' ?> <?= $is_self ? 'disabled' : '' ?>
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label for="is_active" class="ml-2 text-sm text-gray-700">Conta Ativa</label>
            </div>

            <div class="flex justify-between items-center pt-6 w-full">
                <a href="gerir_utilizadores.php"
                class="inline-block bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300 transition">
                    Voltar
                </a>

                <?php if (!$is_self): ?>
                    <div class="flex gap-4 items-center">
                        <form action="editar_utilizador.php?id=<?= $user_id ?>" method="POST"
                            onsubmit="return confirm('Gerar uma nova password temporária para este utilizador?');"
                            class="inline-block">
                            <input type="hidden" name="action" value="reset_password">
                            <button type="submit"
                                    class="inline-block bg-yellow-500 text-black font-bold py-2 px-6 rounded-lg 
                                        hover:bg-yellow-400 border border-yellow-600 shadow-lg">
                                🔑 Repor Password
                            </button>
                        </form>

                        <form action="editar_utilizador.php?id=<?= $user_id ?>" method="POST" class="inline-block">
                            <input type="hidden" name="action" value="update">
                            <button type="submit"
                                    class="inline-block bg-blue-600 text-white font-bold py-2 px-6 rounded-lg 
                                        hover:bg-blue-700 border border-blue-800 shadow-lg">
                                💾 Guardar Alterações
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</main>

</body>
</html>
