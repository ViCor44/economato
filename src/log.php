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

/**
 * Regista uma SMS enviada (ou uma tentativa falhada) na tabela `sms_logs`.
 *
 * @param PDO         $pdo
 * @param string      $emissor   Nome do utilizador (ou "Sistema (Cron)") que originou a SMS.
 * @param string      $receptor  Número em E.164, opcionalmente com nome (ex.: "Ana Silva <+3519...>").
 * @param string      $mensagem  Texto enviado.
 * @param string      $estado    'enviado' ou 'erro'.
 * @param string|null $erro      Detalhe do erro, se aplicável.
 */
function logSms(PDO $pdo, string $emissor, string $receptor, string $mensagem, string $estado = 'enviado', ?string $erro = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (emissor, receptor, mensagem, estado, erro)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$emissor, $receptor, $mensagem, $estado, $erro]);
    } catch (Throwable $e) {
        // Nunca deixar a falha do log quebrar o fluxo de envio.
        error_log('logSms falhou: ' . $e->getMessage());
    }
}