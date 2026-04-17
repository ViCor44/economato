@echo off
:: Notificar colaboradores com emprestimos de farda com mais de 15 dias.
:: Configurar no Windows Task Scheduler para correr diariamente (ex: 08:00).
"C:\xampp\php\php.exe" "C:\xampp\economato\cron\notificar_emprestimos_atrasados.php"
