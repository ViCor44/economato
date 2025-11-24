<?php
require_once '../config/db.php';
require_once '../src/auth_guard.php'; // opcional se quiser proteger

header('Content-Type: application/json; charset=utf-8');

$ean = trim($_GET['ean'] ?? '');
if ($ean === '') {
    echo json_encode(['error' => 'EAN não fornecido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, ean, preco_unitario FROM fardas WHERE ean = ? LIMIT 1");
    $stmt->execute([$ean]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$f) {
        echo json_encode(['error' => 'EAN não encontrado.']);
        exit;
    }

    echo json_encode([
        'id' => (int)$f['id'],
        'nome' => $f['nome'],
        'ean' => $f['ean'],
        'preco_unitario' => $f['preco_unitario'] !== null ? number_format($f['preco_unitario'], 2, '.', '') : null
    ]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['error' => 'Erro na BD: ' . $e->getMessage()]);
    exit;
}
