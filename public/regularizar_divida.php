<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$atribuicao_id = (int)($_POST['atribuicao_id'] ?? 0);

if ($atribuicao_id <= 0) {
    die('ID inválido');
}

// 🔍 garantir que existe e está em dívida
$stmt = $pdo->prepare("
    SELECT id, colaborador_id
    FROM farda_atribuicoes
    WHERE id = ?
      AND estado = 'em_divida'
");
$stmt->execute([$atribuicao_id]);
$atr = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atr) {
    die('Atribuição não encontrada ou já regularizada.');
}

// ✅ regularizar
$stmt = $pdo->prepare("
    UPDATE farda_atribuicoes
    SET estado = 'divida_paga'
    WHERE id = ?
");
$stmt->execute([$atribuicao_id]);

adicionarLog(
    $pdo,
    'Regularização de dívida',
    "Atribuição {$atribuicao_id} regularizada"
);

// 🔁 voltar ao colaborador
header('Location: detalhes_colaborador.php?id=' . $atr['colaborador_id']);
exit;
