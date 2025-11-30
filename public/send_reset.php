<?php
// public/send_reset.php
require_once '../config/db.php';
require_once '../vendor/autoload.php'; // PHPMailer + demais libs
require_once '../src/mailer.php';       // define new_mailer()

use PHPMailer\PHPMailer\PHPMailer;

$email = trim($_POST['email'] ?? '');
if (!$email) {
    header('Location: forgot_password.php');
    exit;
}

// procurar utilizador (colaboradores tabela)
$stmt = $pdo->prepare("SELECT id, nome, email FROM utilizadores WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$token = bin2hex(random_bytes(32));
$token_hash = password_hash($token, PASSWORD_DEFAULT);
$expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
$user_id = $user ? $user['id'] : null;

$stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->execute([$user_id, $email, $token_hash, $expires]);

// construir link (ajusta conforme base URL)
$resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=$token";

// enviar email
$mail = new_mailer(); // função que retorna PHPMailer já configurado
try {
    $mail->addAddress($email, $user['nome'] ?? '');
    $mail->Subject = 'Recuperação de password — CrewGest';
    $mail->Body = "<p>Olá " . htmlspecialchars($user['nome'] ?? '') . ",</p>
        <p>Recebemos um pedido para redefinir a password. Clique no link abaixo:</p>
        <p><a href=\"" . htmlspecialchars($resetUrl) . "\">Redefinir password</a></p>
        <p>O link expira em 1 hora. Se não pediste, ignora este email.</p>";
    $mail->AltBody = "Redefinir password: $resetUrl";

    $mail->send();
} catch (Exception $e) {
    error_log("send_reset: falha envio mail para $email: " . $mail->ErrorInfo);
    // continua: não exponha erro ao utilizador
}

header('Location: forgot_password.php?ok=1');
exit;
