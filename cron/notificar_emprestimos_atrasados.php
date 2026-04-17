<?php
/**
 * CRON: Notificar colaboradores com empréstimos de roupa com mais de 15 dias por devolver.
 *
 * Execução recomendada (Windows Task Scheduler):
 *   php C:\xampp\htdocs\economato\cron\notificar_emprestimos_atrasados.php
 *
 * Recomendação: correr 1x por dia (ex: todos os dias às 08:00).
 * Envia no máximo 1 email por empréstimo a cada 7 dias (controlado por `ultimo_aviso_email`).
 */

declare(strict_types=1);

// Garantir que só corre em CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via linha de comandos.\n");
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

$smtp = require __DIR__ . '/../config/mail.php';

$hoje = date('Y-m-d');
$limiteAviso = date('Y-m-d', strtotime('-7 days'));
$logFile = __DIR__ . '/notificacoes_emprestimos.log';

function cron_log(string $msg, string $logFile): void
{
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $linha, FILE_APPEND | LOCK_EX);
    echo $linha;
}

// Garantir que a coluna de tracking existe
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
} catch (PDOException $e) {
    cron_log("ERRO ao verificar/criar coluna: " . $e->getMessage(), $logFile);
    exit(1);
}

// Buscar empréstimos com 15+ dias e email do colaborador
// Exclui: já devolvidos, colaboradores sem email, aviso enviado nos últimos 7 dias
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
          AND c.email IS NOT NULL
          AND c.email != ''
                    AND (fe.ultimo_aviso_email IS NULL OR fe.ultimo_aviso_email < :limite_aviso)
        ORDER BY dias_em_aberto DESC
    ");
        $stmt->execute(['limite_aviso' => $limiteAviso]);
    $emprestimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    cron_log("ERRO ao consultar empréstimos: " . $e->getMessage(), $logFile);
    exit(1);
}

if (empty($emprestimos)) {
    cron_log("Sem empréstimos a notificar nesta execução (janela de 7 dias).", $logFile);
    exit(0);
}

cron_log("Empréstimos a notificar: " . count($emprestimos), $logFile);

$enviados = 0;
$erros    = 0;

// Agrupar por colaborador para enviar apenas 1 email com todos os itens em atraso
$porColaborador = [];
foreach ($emprestimos as $row) {
    $porColaborador[$row['colaborador_id']][] = $row;
}

foreach ($porColaborador as $colaboradorId => $itens) {
    $colaboradorNome  = $itens[0]['colaborador_nome'];
    $colaboradorEmail = $itens[0]['colaborador_email'];

    // Construir lista de itens em atraso
    $listaHtml = '';
    $listaTxt  = '';
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
    }

    $assunto = 'Aviso: Farda(s) por devolver — CrewGest';

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
        $mail->addAddress((string)$colaboradorEmail, (string)$colaboradorNome);

        $mail->Subject = $assunto;
        $mail->isHTML(true);
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyTxt;

        $mail->send();

        // Marcar todos os empréstimos deste colaborador como notificados hoje
        $ids = array_column($itens, 'emprestimo_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $upd = $pdo->prepare("
            UPDATE farda_emprestimos
            SET ultimo_aviso_email = ?
            WHERE id IN ($placeholders)
        ");
        $upd->execute(array_merge([$hoje], $ids));

        cron_log("Email enviado → {$colaboradorNome} <{$colaboradorEmail}> (" . count($itens) . " item(ns))", $logFile);
        $enviados++;

    } catch (MailerException $e) {
        cron_log("ERRO ao enviar para {$colaboradorNome} <{$colaboradorEmail}>: " . $mail->ErrorInfo, $logFile);
        $erros++;
    }
}

cron_log("Concluído. Enviados: {$enviados} | Erros: {$erros}", $logFile);
exit($erros > 0 ? 1 : 0);
