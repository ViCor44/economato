<?php
// Função central para registar eventos na base de dados
function log_event(
    PDO $pdo,
    string $level,
    string $event_type,
    string $message,
    ?int $user_id = null,
    ?array $context = null
) {
    // Se o user_id não for passado, tenta obtê-lo da sessão
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $json_context = $context ? json_encode($context) : null;

    try {
        $sql = "INSERT INTO logs (level, event_type, message, user_id, ip_address, context) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$level, $event_type, $message, $user_id, $ip_address, $json_context]);
    } catch (PDOException $e) {
        // Em caso de falha no log, não queremos que a aplicação pare.
        // O ideal seria registar este erro num ficheiro de texto.
        error_log("Falha ao registar no log da BD: " . $e->getMessage());
    }
}