<?php
/**
 * Teste manual de envio de SMS pelo modem TRB145.
 *
 * Uso (CLI):
 *   php cron\testar_sms.php +351912345678 "Mensagem de teste"
 *
 * Requer a variável de ambiente GSM_APP_PASSWORD definida.
 * Não escreve nada na base de dados.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI apenas.\n");
}

require_once __DIR__ . '/../src/sms_trb145.php';

$cfg = require __DIR__ . '/../config/sms.php';

$numeroArg = $argv[1] ?? null;
$mensagem  = $argv[2] ?? 'Teste CrewGest — ' . date('Y-m-d H:i:s');

if (!$numeroArg) {
    fwrite(STDERR, "Uso: php cron\\testar_sms.php <numero> [mensagem]\n");
    exit(2);
}

if (empty($cfg['password'])) {
    fwrite(STDERR, "ERRO: variável de ambiente GSM_APP_PASSWORD não definida.\n");
    exit(2);
}

$numero = Trb145SmsClient::normalizeNumber($numeroArg, (string)$cfg['country_code']);
if ($numero === null) {
    fwrite(STDERR, "ERRO: número inválido: '{$numeroArg}'\n");
    exit(2);
}

echo "Modem : {$cfg['scheme']}://{$cfg['host']}  (modem_id={$cfg['modem_id']})\n";
echo "Para  : {$numero}\n";
echo "Texto : {$mensagem}\n";
echo "A enviar...\n";

$client = new Trb145SmsClient($cfg);
$err = null;

if ($client->sendSms($numero, $mensagem, $err)) {
    echo "OK — SMS aceite pelo modem.\n";
    exit(0);
}

fwrite(STDERR, "FALHA: " . ($err ?? 'desconhecido') . "\n");
exit(1);
