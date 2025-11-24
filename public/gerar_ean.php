<?php
// public/gerar_ean.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/ean_functions.php';
require_once __DIR__ . '/../src/auth_guard.php'; // se necessário (permite apenas user logado)

header('Content-Type: application/json');

try {
    $prefix = '200'; // ajusta se desejares outro prefixo
    $ean = generate_unique_ean($pdo, $prefix);
    echo json_encode(['ean' => $ean]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
