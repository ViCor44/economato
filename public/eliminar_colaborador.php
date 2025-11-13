<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: colaboradores.php");
    exit;
}

$colaborador_id = (int)($_POST['id'] ?? 0);

if ($colaborador_id <= 0) {
    header("Location: colaboradores.php");
    exit;
}

try {
    // 🔍 Verificar se o colaborador existe
    $stmt = $pdo->prepare("SELECT nome FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$colaborador) {
        $_SESSION['error_message'] = "❌ Colaborador não encontrado.";
        header("Location: colaboradores.php");
        exit;
    }

    // 🧱 Verificar se tem cacifos atribuídos
    $stmtCacifos = $pdo->prepare("SELECT COUNT(*) FROM cacifos WHERE colaborador_id = ?");
    $stmtCacifos->execute([$colaborador_id]);
    $temCacifos = $stmtCacifos->fetchColumn() > 0;

    // 🧥 Verificar se tem fardas atribuídas
    $stmtFardas = $pdo->prepare("SELECT COUNT(*) FROM farda_atribuicoes WHERE colaborador_id = ?");
    $stmtFardas->execute([$colaborador_id]);
    $temFardas = $stmtFardas->fetchColumn() > 0;

    if ($temCacifos || $temFardas) {
        $msg = "⚠️ Não é possível eliminar este colaborador porque ainda tem ";
        if ($temCacifos) $msg .= "cacifos atribuídos";
        if ($temCacifos && $temFardas) $msg .= " e ";
        if ($temFardas) $msg .= "fardas associadas";
        $msg .= ".";

        $_SESSION['error_message'] = $msg;
        header("Location: colaboradores.php");
        exit;
    }

    // ✅ Eliminar colaborador
    $stmt = $pdo->prepare("DELETE FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);

    $_SESSION['success_message'] = "✅ Colaborador '" . htmlspecialchars($colaborador['nome']) . "' foi eliminado com sucesso.";
    header("Location: colaboradores.php");
    exit;

} catch (PDOException $e) {
    $_SESSION['error_message'] = "❌ Erro ao eliminar colaborador: " . $e->getMessage();
    header("Location: colaboradores.php");
    exit;
}
