<?php
// public/forgot_password.php
//require_once __DIR__ . '/../src/auth_guard.php'; // se tiveres; senão remove
require_once __DIR__ . '/../config/db.php';
?>
<!doctype html>
<html lang="pt-PT">
<head><meta charset="utf-8"><title>Recuperar password</title>
<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8 bg-gray-100">
<?php include __DIR__ . '/../src/templates/header.php'; ?>

<main class="max-w-md mx-auto bg-white p-8 rounded shadow mt-8">
    <h1 class="text-2xl font-bold mb-4">Recuperar password</h1>

    <?php if(!empty($_GET['ok'])): ?>
        <div class="bg-green-100 p-3 rounded">Se o email existir, enviámos instruções.</div>
    <?php endif; ?>

    <form action="send_reset.php" method="post" class="space-y-4">
        <div>
            <label>Email</label>
            <input type="email" name="email" required class="w-full border p-2 rounded">
        </div>
        <div class="text-right">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Enviar link</button>
        </div>
    </form>
</main>
</body>
</html>
