<?php
// nova_cor.php
require_once '../config/db.php';
require_once '../src/auth_guard.php'; // se tens autenticação

header('Content-Type: application/json; charset=utf-8');

$nome = trim($_POST['nome'] ?? '');
if ($nome === '') {
    echo json_encode(['erro' => 'Nome inválido']);
    exit;
}

try {
    // Normalizar (opcional): capitalizar, remover espaços duplicados, etc.
    $nome_normalizado = preg_replace('/\s+/', ' ', $nome);

    // Verifica se já existe (case-insensitive)
    $stmt = $pdo->prepare("SELECT id, nome FROM cores WHERE LOWER(nome) = LOWER(?) LIMIT 1");
    $stmt->execute([$nome_normalizado]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['id' => (int)$row['id'], 'nome' => $row['nome']]);
        exit;
    }

    // Inserir nova cor
    $ins = $pdo->prepare("INSERT INTO cores (nome) VALUES (:nome)");
    $ins->execute(['nome' => $nome_normalizado]);
    $id = (int)$pdo->lastInsertId();

    echo json_encode(['id' => $id, 'nome' => $nome_normalizado]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de BD: ' . $e->getMessage()]);
    exit;
}
