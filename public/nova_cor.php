<?php
require_once '../config/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nome'])) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO cores (nome) VALUES (:nome)");
    $stmt->execute(['nome' => trim($_POST['nome'])]);
}
header("Location: gerir_stock_farda.php");
exit;
