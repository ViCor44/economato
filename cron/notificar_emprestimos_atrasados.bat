@echo off
:: Notificar colaboradores com emprestimos de farda com mais de 15 dias.
:: Envia EMAIL + SMS (via modem Teltonika TRB145).
:: Configurar no Windows Task Scheduler para correr diariamente (ex: 08:00).
::
:: A password do modem DEVE estar na variavel de ambiente GSM_APP_PASSWORD
:: (Sistema > Propriedades > Variaveis de Ambiente). Opcionalmente podem
:: ser definidas: MODEM_HOST, MODEM_USER, MODEM_ID, MODEM_SCHEME,
:: MODEM_VERIFY_SSL, SMS_COUNTRY_CODE. Ver config\sms.php.
"C:\xampp\php\php.exe" "C:\xampp\htdocs\economato\cron\notificar_emprestimos_atrasados.php"
