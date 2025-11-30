<?php
// public/process_reset.php
require_once __DIR__ . '/../config/db.php';

$token = $_POST['token'] ?? '';
$pass = $_POST['password'] ?? '';
$pass2 = $_POST['password_confirm'] ?? '';

if (!$token || !$pass || $pass !== $pass2) {
    die('Dados inválidos.');
}

// buscar token activo
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at >= NOW() LIMIT 1");
$stmt->execute([$token]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$reset) {
    die('Token inválido ou expirado.');
}

// se tivermos user_id actualiza; caso contrário tentamos procurar utilizador por email
$user_id = $reset['user_id'];
if (!$user_id) {
    $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE email = ? LIMIT 1");
    $stmt->execute([$reset['email']]);
    $user_id = $stmt->fetchColumn();
    if (!$user_id) {
        die('Utilizador não encontrado.');
    }
}

// actualizar password (assumindo campo password na tabela colaboradores)
$hashed = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE colaboradores SET password = ? WHERE id = ?");
$stmt->execute([$hashed, $user_id]);

// marcar token como usado
$stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
$stmt->execute([$reset['id']]);

// opcional: gravar log, destruir sessões, etc.
header('Location: login.php?reset=1');
exit;
