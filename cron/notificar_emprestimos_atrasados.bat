@echo off
:: Notificar colaboradores com emprestimos de farda com mais de 15 dias.
:: Pode correr diariamente (ex: 08:00); o script limita para 1 aviso por emprestimo a cada 7 dias.
"C:\xampp\php\php.exe" "C:\xampp\htdocs\economato\cron\notificar_emprestimos_atrasados.php"
