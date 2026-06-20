<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';
require_once '../src/mailer.php';
require_once '../src/log.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$colaborador_id = isset($_POST['colaborador_id']) ? (int)$_POST['colaborador_id'] : 0;
$assunto        = trim((string)($_POST['assunto'] ?? ''));
$mensagem       = trim((string)($_POST['mensagem'] ?? ''));

if ($colaborador_id <= 0 || $assunto === '' || $mensagem === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'Preencha o assunto e a mensagem.']);
    exit;
}

if (mb_strlen($assunto) > 200) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'O assunto não pode exceder 200 caracteres.']);
    exit;
}

if (mb_strlen($mensagem) > 5000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'A mensagem é demasiado longa (máx. 5000 caracteres).']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, email FROM colaboradores WHERE id = ? LIMIT 1");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$colaborador) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'erro' => 'Colaborador não encontrado.']);
        exit;
    }

    $emailDest = trim((string)$colaborador['email']);
    if ($emailDest === '' || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Este colaborador não tem um email válido.']);
        exit;
    }

    $remetenteNome  = $utilizador_logado['nome']  ?? 'CrewGest';
    $remetenteEmail = $utilizador_logado['email'] ?? SMTP_FROM;

    $mensagemHtml = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));

    $corpo = "
        <div style=\"font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1f2937;\">
            <p>Olá " . htmlspecialchars($colaborador['nome'], ENT_QUOTES, 'UTF-8') . ",</p>
            <div style=\"padding:12px 16px;border-left:4px solid #2563eb;background:#f3f4f6;border-radius:4px;\">
                {$mensagemHtml}
            </div>
            <p style=\"margin-top:16px;color:#6b7280;font-size:12px;\">
                Mensagem enviada por " . htmlspecialchars($remetenteNome, ENT_QUOTES, 'UTF-8') . " via CrewGest.
            </p>
        </div>
    ";

    $mail = new_mailer();
    $mail->addAddress($emailDest, $colaborador['nome']);
    if (filter_var($remetenteEmail, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($remetenteEmail, $remetenteNome);
    }
    $mail->Subject = $assunto;
    $mail->Body    = $corpo;
    $mail->AltBody = $mensagem;

    // 📎 Anexos
    $anexosInfo = [];
    if (!empty($_FILES['anexos']) && is_array($_FILES['anexos']['name'])) {

        $maxFicheiro   = 10 * 1024 * 1024;  // 10 MB por ficheiro
        $maxTotal      = 25 * 1024 * 1024;  // 25 MB total
        $maxQuantidade = 5;
        $tipoPermitido = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/zip',
            'application/x-zip-compressed',
        ];

        $totalBytes = 0;
        $files      = $_FILES['anexos'];
        $n          = count($files['name']);

        if ($n > $maxQuantidade) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => "Máximo de {$maxQuantidade} anexos por email."]);
            exit;
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

        for ($i = 0; $i < $n; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'erro' => "Falha ao receber o anexo \"{$files['name'][$i]}\"."]);
                exit;
            }

            $tmp  = $files['tmp_name'][$i];
            $nome = basename((string)$files['name'][$i]);
            $tam  = (int)$files['size'][$i];

            if (!is_uploaded_file($tmp)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'erro' => "Anexo inválido: {$nome}"]);
                exit;
            }

            if ($tam > $maxFicheiro) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'erro' => "O anexo \"{$nome}\" excede 10 MB."]);
                exit;
            }

            $totalBytes += $tam;
            if ($totalBytes > $maxTotal) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'erro' => 'Tamanho total dos anexos excede 25 MB.']);
                exit;
            }

            $mime = $finfo ? (finfo_file($finfo, $tmp) ?: '') : (string)$files['type'][$i];
            if (!in_array($mime, $tipoPermitido, true)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'erro' => "Tipo de ficheiro não permitido: {$nome} ({$mime})"]);
                exit;
            }

            $mail->addAttachment($tmp, $nome);
            $anexosInfo[] = $nome;
        }

        if ($finfo) {
            finfo_close($finfo);
        }
    }

    $mail->send();

    $detalhesLog = "Colaborador ID {$colaborador_id} ({$colaborador['nome']}) — Assunto: {$assunto}";
    if (!empty($anexosInfo)) {
        $detalhesLog .= ' — Anexos: ' . implode(', ', $anexosInfo);
    }

    adicionarLog(
        $pdo,
        'Envio de email a colaborador',
        $detalhesLog
    );

    echo json_encode(['ok' => true, 'mensagem' => 'Email enviado com sucesso.']);
} catch (Throwable $e) {
    error_log('enviar_email_colaborador: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Falha ao enviar o email. Tente novamente mais tarde.']);
}
