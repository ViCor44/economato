<?php
require_once '../config/db.php';
require_once '../src/auth_guard.php'; // se quiseres que apenas anonimos acedam podes condicionar
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <title>Recuperar Password</title>
  <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
<?php include '../src/templates/header.php'; ?>

<main class="max-w-md mx-auto bg-white p-8 rounded shadow">
  <h1 class="text-xl font-bold mb-4">Recuperar Password</h1>
  <p class="text-sm text-gray-600 mb-4">Indique o email associado à sua conta. Irá receber um link para repor a password.</p>

  <?php if (isset($_GET['sent'])): ?>
    <div class="bg-green-100 p-3 rounded mb-4">Se o email existir, enviámos um link de recuperação.</div>
  <?php endif; ?>

  <form action="password_reset_handle.php" method="POST" class="space-y-4">
    <div>
      <label class="block text-sm font-medium">Email</label>
      <input type="email" name="email" required class="w-full border rounded p-2">
    </div>
    <div class="flex justify-end">
      <button class="bg-blue-600 text-white px-4 py-2 rounded" type="submit">Enviar link</button>
    </div>
  </form>
</main>
</body>
</html>
