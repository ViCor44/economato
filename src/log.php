<?php
function adicionarLog($pdo, $acao, $detalhes = null) {
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';

    $stmt = $pdo->prepare("
        INSERT INTO logs (user_id, acao, detalhes, ip)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$user_id, $acao, $detalhes, $ip]);
}