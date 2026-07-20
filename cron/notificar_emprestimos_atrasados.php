<?php
/**
 * CRON: Notificar colaboradores com empréstimos de roupa com mais de 15 dias por devolver.
 *
 * Execução recomendada (Windows Task Scheduler):
 *   php C:\xampp\htdocs\economato\cron\notificar_emprestimos_atrasados.php
 *
 * Recomendação: correr 1x por dia (ex: todos os dias às 08:00).
 * Envia no máximo 1 email por empréstimo por dia (controlado por `ultimo_aviso_email`).
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Lisbon');

// Garantir que só corre em CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via linha de comandos.\n");
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/sms_trb145.php';
require_once __DIR__ . '/../src/log.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

$smtp     = require __DIR__ . '/../config/mail.php';
$smsCfg   = require __DIR__ . '/../config/sms.php';

$hoje = date('Y-m-d');
$logFile = __DIR__ . '/notificacoes_emprestimos.log';

function cron_log(string $msg, string $logFile): void
{
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $linha, FILE_APPEND | LOCK_EX);
    echo $linha;
}

// Garantir que as colunas de tracking existem (email + SMS)
try {
    $check = $pdo->query("SHOW COLUMNS FROM farda_emprestimos LIKE 'ultimo_aviso_email'");
    if (!$check->fetch()) {
        $pdo->exec("
            ALTER TABLE farda_emprestimos
            ADD COLUMN ultimo_aviso_email DATE NULL DEFAULT NULL
                COMMENT 'Data do último email de aviso de atraso enviado'
        ");
        cron_log("Coluna 'ultimo_aviso_email' criada na tabela farda_emprestimos.", $logFile);
    }

    $check = $pdo->query("SHOW COLUMNS FROM farda_emprestimos LIKE 'ultimo_aviso_sms'");
    if (!$check->fetch()) {
        $pdo->exec("
            ALTER TABLE farda_emprestimos
            ADD COLUMN ultimo_aviso_sms DATE NULL DEFAULT NULL
                COMMENT 'Data do último SMS de aviso de atraso enviado'
        ");
        cron_log("Coluna 'ultimo_aviso_sms' criada na tabela farda_emprestimos.", $logFile);
    }
} catch (PDOException $e) {
    cron_log("ERRO ao verificar/criar coluna: " . $e->getMessage(), $logFile);
    exit(1);
}

// Buscar empréstimos com 15+ dias e email do colaborador
// Exclui: já devolvidos, colaboradores sem email, aviso já enviado hoje
try {
    $stmt = $pdo->prepare("
        SELECT
            fe.id            AS emprestimo_id,
            fe.quantidade,
            fe.data_emprestimo,
            DATEDIFF(CURDATE(), DATE(fe.data_emprestimo)) AS dias_em_aberto,
            c.id             AS colaborador_id,
            c.nome           AS colaborador_nome,
            c.email          AS colaborador_email,
            c.telefone       AS colaborador_telefone,
            fe.ultimo_aviso_email,
            fe.ultimo_aviso_sms,
            f.nome           AS farda_nome,
            co.nome          AS cor_nome,
            t.nome           AS tamanho_nome
        FROM farda_emprestimos fe
        JOIN colaboradores c  ON fe.colaborador_id = c.id
        JOIN fardas f         ON fe.farda_id = f.id
        JOIN cores co         ON f.cor_id = co.id
        JOIN tamanhos t       ON f.tamanho_id = t.id
        WHERE fe.devolvido = 0
          AND DATEDIFF(CURDATE(), DATE(fe.data_emprestimo)) >= 15
          AND (
                (c.email    IS NOT NULL AND c.email    != '' AND (fe.ultimo_aviso_email IS NULL OR fe.ultimo_aviso_email < :hoje))
             OR (c.telefone IS NOT NULL AND c.telefone != '' AND (fe.ultimo_aviso_sms   IS NULL OR fe.ultimo_aviso_sms   < :hoje2))
          )
        ORDER BY dias_em_aberto DESC
    ");
    $stmt->execute(['hoje' => $hoje, 'hoje2' => $hoje]);
    $emprestimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    cron_log("ERRO ao consultar empréstimos: " . $e->getMessage(), $logFile);
    exit(1);
}

if (empty($emprestimos)) {
    cron_log("Sem empréstimos a notificar hoje.", $logFile);
    exit(0);
}

cron_log("Empréstimos a notificar: " . count($emprestimos), $logFile);

$enviados     = 0;
$erros        = 0;
$smsEnviados  = 0;
$smsErros     = 0;

// Instanciar cliente SMS (lazy — só falha se realmente for usado sem password)
$smsClient = null;
try {
    if (!empty($smsCfg['password'])) {
        $smsClient = new Trb145SmsClient($smsCfg);
    } else {
        cron_log("Aviso: GSM_APP_PASSWORD não definida — SMS desativado.", $logFile);
    }
} catch (Throwable $e) {
    cron_log("Aviso: cliente SMS indisponível: " . $e->getMessage(), $logFile);
    $smsClient = null;
}

// Agrupar por colaborador para enviar apenas 1 email + 1 SMS com todos os itens em atraso
$porColaborador = [];
foreach ($emprestimos as $row) {
    $porColaborador[$row['colaborador_id']][] = $row;
}

foreach ($porColaborador as $colaboradorId => $itens) {
    $colaboradorNome     = $itens[0]['colaborador_nome'];
    $colaboradorEmail    = (string)($itens[0]['colaborador_email'] ?? '');
    $colaboradorTelefone = (string)($itens[0]['colaborador_telefone'] ?? '');

    // Construir lista de itens em atraso
    $listaHtml = '';
    $listaTxt  = '';
    $totalPecas = 0;
    $maxDias    = 0;
    foreach ($itens as $item) {
        $dataEmp = date('d/m/Y', strtotime((string)$item['data_emprestimo']));
        $listaHtml .= sprintf(
            '<tr>
                <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">%s</td>
                <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">%s</td>
                <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">%s</td>
                <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:center;">%d</td>
                <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:center;color:#dc2626;font-weight:bold;">%d dias</td>
            </tr>',
            htmlspecialchars((string)$item['farda_nome']),
            htmlspecialchars((string)$item['cor_nome']),
            htmlspecialchars((string)$item['tamanho_nome']),
            (int)$item['quantidade'],
            (int)$item['dias_em_aberto']
        );
        $listaTxt .= sprintf(
            "- %s (%s, %s) x%d — emprestado em %s (%d dias em aberto)\n",
            $item['farda_nome'],
            $item['cor_nome'],
            $item['tamanho_nome'],
            (int)$item['quantidade'],
            $dataEmp,
            (int)$item['dias_em_aberto']
        );
        $totalPecas += (int)$item['quantidade'];
        if ((int)$item['dias_em_aberto'] > $maxDias) {
            $maxDias = (int)$item['dias_em_aberto'];
        }
    }

    $assunto  = 'Aviso: Farda(s) por devolver — CrewGest';
    $ids      = array_column($itens, 'emprestimo_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // ----------------------------- EMAIL -----------------------------
    $precisaEmail = $colaboradorEmail !== '' && array_reduce(
        $itens,
        static fn(bool $carry, array $it) => $carry || empty($it['ultimo_aviso_email']) || $it['ultimo_aviso_email'] < $GLOBALS['hoje'],
        false
    );

    if ($precisaEmail) {
        $bodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#374151;margin:0;padding:0;">
  <div style="max-width:600px;margin:32px auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
    <div style="background:#1d4ed8;padding:24px 32px;">
      <h1 style="margin:0;color:#fff;font-size:20px;">CrewGest — Aviso de Empréstimo</h1>
    </div>
    <div style="padding:32px;">
      <p>Olá <strong>{$colaboradorNome}</strong>,</p>
      <p>Verificámos que tem farda(s) emprestada(s) há <strong>mais de 15 dias</strong> por devolver:</p>
      <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">
        <thead>
          <tr style="background:#f3f4f6;">
            <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e5e7eb;">Peça</th>
            <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e5e7eb;">Cor</th>
            <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #e5e7eb;">Tamanho</th>
            <th style="padding:8px 12px;text-align:center;border-bottom:2px solid #e5e7eb;">Qtd</th>
            <th style="padding:8px 12px;text-align:center;border-bottom:2px solid #e5e7eb;">Atraso</th>
          </tr>
        </thead>
        <tbody>
          {$listaHtml}
        </tbody>
      </table>
      <p>Por favor, proceda à devolução o mais brevemente possível junto do responsável do economato.</p>
      <p style="margin-top:32px;font-size:12px;color:#9ca3af;">
        Esta é uma mensagem automática do sistema CrewGest. Não responda a este email.
      </p>
    </div>
  </div>
</body>
</html>
HTML;

        $bodyTxt = "Olá {$colaboradorNome},\n\n"
            . "Tem farda(s) emprestada(s) há mais de 15 dias por devolver:\n\n"
            . $listaTxt
            . "\nPor favor, proceda à devolução junto do responsável do economato.\n\n"
            . "-- CrewGest (mensagem automática)";

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$smtp['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom((string)$smtp['from_email'], (string)$smtp['from_name']);
            $mail->addAddress($colaboradorEmail, (string)$colaboradorNome);

            $mail->Subject = $assunto;
            $mail->isHTML(true);
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyTxt;

            $mail->send();

            $upd = $pdo->prepare("
                UPDATE farda_emprestimos
                SET ultimo_aviso_email = ?
                WHERE id IN ($placeholders)
            ");
            $upd->execute(array_merge([$hoje], $ids));

            cron_log("Email enviado → {$colaboradorNome} <{$colaboradorEmail}> (" . count($itens) . " item(ns))", $logFile);
            $enviados++;

        } catch (MailerException $e) {
            cron_log("ERRO email para {$colaboradorNome} <{$colaboradorEmail}>: " . $mail->ErrorInfo, $logFile);
            $erros++;
        }
    }

    // ----------------------------- SMS -----------------------------
    $precisaSms = $smsClient !== null
        && $colaboradorTelefone !== ''
        && array_reduce(
            $itens,
            static fn(bool $carry, array $it) => $carry || empty($it['ultimo_aviso_sms']) || $it['ultimo_aviso_sms'] < $GLOBALS['hoje'],
            false
        );

    if ($precisaSms) {
        $numero = Trb145SmsClient::normalizeNumber(
            $colaboradorTelefone,
            (string)$smsCfg['country_code']
        );

        if ($numero === null) {
            cron_log("SMS ignorado — número inválido para {$colaboradorNome}: '{$colaboradorTelefone}'", $logFile);
        } else {
            // SMS curto (limite prático ~160 chars GSM-7 / 70 UCS-2).
            // Evitamos acentos para caber num único SMS.
            $primeiroNome = strtok($colaboradorNome, ' ') ?: $colaboradorNome;
            $texto = sprintf(
                "CrewGest: Ola %s, tem %d peca(s) de farda por devolver ha %d dias. Por favor entregue no economato.",
                $primeiroNome,
                $totalPecas,
                $maxDias
            );
            // Remover acentos para maximizar compatibilidade GSM-7
            $texto = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;

            $errSms = null;
            if ($smsClient->sendSms($numero, $texto, $errSms)) {
                $upd = $pdo->prepare("
                    UPDATE farda_emprestimos
                    SET ultimo_aviso_sms = ?
                    WHERE id IN ($placeholders)
                ");
                $upd->execute(array_merge([$hoje], $ids));

                cron_log("SMS enviado → {$colaboradorNome} <{$numero}> (" . count($itens) . " item(ns))", $logFile);
                logSms($pdo, 'Sistema (Cron)', "{$colaboradorNome} <{$numero}>", $texto, 'enviado');
                $smsEnviados++;
            } else {
                cron_log("ERRO SMS para {$colaboradorNome} <{$numero}>: " . ($errSms ?? 'desconhecido'), $logFile);
                logSms($pdo, 'Sistema (Cron)', "{$colaboradorNome} <{$numero}>", $texto, 'erro', $errSms);
                $smsErros++;
            }
        }
    }
}

cron_log("Concluído. Email: {$enviados} ok / {$erros} erro | SMS: {$smsEnviados} ok / {$smsErros} erro", $logFile);
exit(($erros > 0 || $smsErros > 0) ? 1 : 0);
