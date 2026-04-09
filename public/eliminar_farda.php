<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gerir_stock_farda.php');
    exit;
}

$farda_id = (int)($_POST['id'] ?? 0);
$pesquisa = trim($_POST['pesquisa'] ?? '');
$departamento_id = isset($_POST['departamento_id']) ? (int)$_POST['departamento_id'] : 0;

$redirect_params = [];
if ($pesquisa !== '') {
    $redirect_params['pesquisa'] = $pesquisa;
}
if ($departamento_id > 0) {
    $redirect_params['departamento_id'] = $departamento_id;
}

$redirect_url = 'gerir_stock_farda.php';
if ($redirect_params) {
    $redirect_url .= '?' . http_build_query($redirect_params);
}

if ($farda_id <= 0) {
    $_SESSION['error_message'] = '❌ Farda inválida.';
    header('Location: ' . $redirect_url);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, nome, ean FROM fardas WHERE id = ?');
    $stmt->execute([$farda_id]);
    $farda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$farda) {
        $_SESSION['error_message'] = '❌ Farda não encontrada.';
        header('Location: ' . $redirect_url);
        exit;
    }

    $dependencias = [
        'atribuições' => 'SELECT COUNT(*) FROM farda_atribuicoes WHERE farda_id = ?',
        'empréstimos' => 'SELECT COUNT(*) FROM farda_emprestimos WHERE farda_id = ?',
        'compras' => 'SELECT COUNT(*) FROM farda_compras WHERE farda_id = ?',
        'baixas de stock' => 'SELECT COUNT(*) FROM farda_baixas WHERE farda_id = ?',
        'devoluções' => 'SELECT COUNT(*) FROM farda_devolucoes WHERE farda_id = ?',
    ];

    $bloqueios = [];
    foreach ($dependencias as $label => $sql) {
        $check = $pdo->prepare($sql);
        $check->execute([$farda_id]);
        if ((int)$check->fetchColumn() > 0) {
            $bloqueios[] = $label;
        }
    }

    if ($bloqueios) {
        $_SESSION['error_message'] = "⚠️ Não é possível eliminar a farda '" . $farda['nome'] . "' porque já tem histórico em: " . implode(', ', $bloqueios) . '.';
        header('Location: ' . $redirect_url);
        exit;
    }

    $pdo->beginTransaction();

    $delete = $pdo->prepare('DELETE FROM fardas WHERE id = ?');
    $delete->execute([$farda_id]);

    $pdo->commit();

    $barcode_file = __DIR__ . '/../public/barcodes/' . ($farda['ean'] ?? '') . '.png';
    if (!empty($farda['ean']) && file_exists($barcode_file)) {
        @unlink($barcode_file);
    }

    adicionarLog(
        $pdo,
        'Eliminou farda',
        "Farda ID {$farda['id']} | Nome: {$farda['nome']}" . (!empty($farda['ean']) ? " | EAN: {$farda['ean']}" : '')
    );

    $_SESSION['success_message'] = "✅ Farda '" . $farda['nome'] . "' eliminada com sucesso.";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = '❌ Erro ao eliminar farda: ' . $e->getMessage();
}

header('Location: ' . $redirect_url);
exit;