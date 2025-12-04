<?php

// Caminho do ficheiro — ajusta conforme preferires
define('LOG_FILE', __DIR__ . '/../storage/system.log');

/**
 * Escreve um log num ficheiro em vez da BD.
 * $level    EX: INFO, WARNING, ERROR
 * $action   EX: LOGIN_SUCCESS
 * $message  Texto detalhado
 */
function log_event_file(string $level, string $action, string $message, $user_id = null): void
{
    $timestamp = date('Y-m-d H:i:s');

    // construir linha
    $line = "[$timestamp] [$level] [$action]";

    if ($user_id) {
        $line .= " [USER:$user_id]";
    }

    $line .= " - $message" . PHP_EOL;

    // garantir que a pasta existe
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}
