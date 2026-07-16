<?php
// src/mail_config.php
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'slide.rocketchat@gmail.com',
    'password' => getenv('GMAIL_APP_PASSWORD') ?: '',
    'from_email' => 'slide.rocketchat@gmail.com',
    'from_name' => 'CrewGest',
    'secure' => 'ssl', // or 'ssl'
];
