<?php
/**
 * Configuração do modem TRB145 (RutOS 7.x) para envio de SMS.
 *
 * A password NUNCA deve ficar no ficheiro — deve ser exposta na variável
 * de ambiente GSM_APP_PASSWORD (ex.: definida no Windows Task Scheduler
 * ou nas variáveis de sistema).
 *
 * Variáveis de ambiente suportadas (com fallback):
 *   MODEM_HOST          (default: 192.168.2.1)
 *   MODEM_SCHEME        (default: https)
 *   MODEM_USER          (default: admin)
 *   GSM_APP_PASSWORD    (obrigatória — password do utilizador do modem)
 *   MODEM_ID            (default: 3-1)   ← identificador do modem no RutOS
 *   MODEM_VERIFY_SSL    (default: 0)     ← certificado do router é self-signed
 *   MODEM_TIMEOUT       (default: 15)    ← timeout em segundos por request
 *   SMS_COUNTRY_CODE    (default: +351)  ← prefixo aplicado a números locais
 */

return [
    'host'         => getenv('MODEM_HOST')       ?: '192.168.2.1',
    'scheme'       => getenv('MODEM_SCHEME')     ?: 'https',
    'user'         => getenv('MODEM_USER')       ?: 'admin',
    'password'     => getenv('GSM_APP_PASSWORD') ?: '',
    'modem_id'     => getenv('MODEM_ID')         ?: '3-1',
    'verify_ssl'   => (bool)(getenv('MODEM_VERIFY_SSL') ?: false),
    'timeout'      => (int)(getenv('MODEM_TIMEOUT') ?: 15),
    'country_code' => getenv('SMS_COUNTRY_CODE') ?: '+351',
];
