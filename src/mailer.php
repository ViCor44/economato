<?php
use PHPMailer\PHPMailer\PHPMailer;

defined('BASE_URL') or define('BASE_URL', 'http://127.0.0.1/economato');

// CONFIGURAÇÕES SMTP — PREENCHER!
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'slide.rocketchat@gmail.com'); 
define('SMTP_PASS', '');   // NÃO a password normal
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');

// ESTE É O ENDEREÇO QUE APARECE NO "FROM" — TEM DE SER UM EMAIL VÁLIDO!
define('SMTP_FROM', 'slide.rocketchat@gmail.com');
define('SMTP_FROM_NAME', 'CrewGest');

function new_mailer($debug = false) {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;

    // ESTA LINHA ESTAVA A FALHAR NO TEU LOG
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    if ($debug) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str){
            file_put_contents(__DIR__.'/../storage/mail_debug.log', $str."\n", FILE_APPEND);
        };
    }

    return $mail;
}
