<?php

date_default_timezone_set('Europe/Lisbon');

define('LOG_FILE', __DIR__ . '/../storage/system.log');

function log_event_file(string $level, string $action, string $message, $user_id = null): void
{
    $timestamp = date('Y-m-d H:i:s');

    // Obter IP real do utilizador
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

    // Se estiver atrás de proxy (opcional)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }

    $line = "[$timestamp] [$level] [$action] [IP:$ip]";

    if ($user_id !== null) {
        $line .= " [USER:$user_id]";
    }

    $line .= " - $message" . PHP_EOL;

    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}