<?php
use PHPMailer\PHPMailer\PHPMailer;

defined('BASE_URL') or define('BASE_URL', 'http://127.0.0.1/economato');

// Carrega configurações SMTP a partir de config/mail.php
$__mailConfig = require __DIR__ . '/../config/mail.php';

define('SMTP_HOST',      $__mailConfig['host']);
define('SMTP_USER',      $__mailConfig['username']);
define('SMTP_PASS',      $__mailConfig['password']);
define('SMTP_PORT',      (int)$__mailConfig['port']);
define('SMTP_SECURE',    $__mailConfig['secure']);
define('SMTP_FROM',      $__mailConfig['from_email']);
define('SMTP_FROM_NAME', $__mailConfig['from_name']);

unset($__mailConfig);

function new_mailer($debug = false) {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = (SMTP_PORT === 465)
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    if ($debug) {
        $mail->SMTPDebug   = 2;
        $mail->Debugoutput = function($str){
            file_put_contents(__DIR__.'/../storage/mail_debug.log', $str."\n", FILE_APPEND);
        };
    }

    return $mail;
}
