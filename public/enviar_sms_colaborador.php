<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/sms_trb145.php';
require_once '../src/log.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$colaborador_id = isset($_POST['colaborador_id']) ? (int)$_POST['colaborador_id'] : 0;
$mensagem       = trim((string)($_POST['mensagem'] ?? ''));

if ($colaborador_id <= 0 || $mensagem === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'A mensagem não pode estar vazia.']);
    exit;
}

if (mb_strlen($mensagem) > 320) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'A mensagem não pode exceder 320 caracteres.']);
    exit;
}

$smsCfg = require __DIR__ . '/../config/sms.php';

if (empty($smsCfg['password'])) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'erro' => 'Serviço de SMS não configurado (GSM_APP_PASSWORD em falta).']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, telefone FROM colaboradores WHERE id = ? LIMIT 1");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$colaborador) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'erro' => 'Colaborador não encontrado.']);
        exit;
    }

    $telefone = trim((string)($colaborador['telefone'] ?? ''));
    if ($telefone === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Este colaborador não tem número de telefone registado.']);
        exit;
    }

    $numero = Trb145SmsClient::normalizeNumber($telefone, (string)$smsCfg['country_code']);
    if ($numero === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => "Número de telefone inválido: {$telefone}"]);
        exit;
    }

    $client = new Trb145SmsClient($smsCfg);
    $errSms = null;

    $emissorNome = trim((string)($_SESSION['user_name'] ?? ''));
    if ($emissorNome === '') {
        $emissorNome = 'Utilizador #' . (int)($_SESSION['user_id'] ?? 0);
    }
    $receptorLabel = trim((string)$colaborador['nome']) !== ''
        ? sprintf('%s <%s>', $colaborador['nome'], $numero)
        : $numero;

    // Prefixo automático a identificar o sistema e o utilizador emissor.
    // Usa apenas o primeiro e último nome para manter a SMS curta.
    $partesNome = preg_split('/\s+/', $emissorNome, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($partesNome) >= 2) {
        $nomeCurto = $partesNome[0] . ' ' . end($partesNome);
    } else {
        $nomeCurto = $emissorNome;
    }
    $prefixo         = sprintf('[CrewGest - %s] ', $nomeCurto);
    $mensagemFinal   = $prefixo . $mensagem;

    if (!$client->sendSms($numero, $mensagemFinal, $errSms)) {
        error_log('enviar_sms_colaborador: ' . ($errSms ?? 'erro desconhecido'));
        logSms($pdo, $emissorNome, $receptorLabel, $mensagemFinal, 'erro', $errSms);
        http_response_code(500);
        echo json_encode(['ok' => false, 'erro' => 'Falha ao enviar SMS. Tente novamente mais tarde.']);
        exit;
    }

    logSms($pdo, $emissorNome, $receptorLabel, $mensagemFinal, 'enviado');

    adicionarLog(
        $pdo,
        'Envio de SMS a colaborador',
        "Colaborador ID {$colaborador_id} ({$colaborador['nome']}) — Número: {$numero}"
    );

    echo json_encode(['ok' => true, 'mensagem' => "SMS enviado para {$numero}."]);

} catch (Throwable $e) {
    error_log('enviar_sms_colaborador: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro interno. Tente novamente mais tarde.']);
}
