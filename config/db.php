<?php
// Usar sempre nomes genéricos para não expor a tecnologia (slide_rh)
define('DB_HOST', 'localhost'); // ou 127.0.0.1
define('DB_NAME', 'econo_app');
define('DB_USER', 'root'); // O teu user da BD
define('DB_PASS', '');     // A tua password da BD

define('BASE_URL', '/economato');

define('ROLE_ADMIN', 1);
define('ROLE_GESTOR', 2);
define('ROLE_USER', 3);
try {
    // Usar PDO é a prática mais segura (evita SQL Injection)
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    // Configurar o PDO para lançar exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Em produção, nunca mostrar o erro detalhado. Guardar num log.
    die("Erro: Não foi possível ligar à base de dados. " . $e->getMessage());
}