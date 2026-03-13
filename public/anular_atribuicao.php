<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT fa.*, f.nome AS farda_nome
    FROM farda_atribuicoes fa
    JOIN fardas f ON f.id = fa.farda_id
    WHERE fa.id = ?
");
$stmt->execute([$id]);
$atribuicao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atribuicao) {
    die("Atribuição não encontrada.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $pdo->beginTransaction();

        // devolver stock
        $stmt = $pdo->prepare("
            UPDATE fardas
            SET quantidade = quantidade + ?
            WHERE id = ?
        ");
        $stmt->execute([
            $atribuicao['quantidade'],
            $atribuicao['farda_id']
        ]);

        // apagar atribuição
        $stmt = $pdo->prepare("
            DELETE FROM farda_atribuicoes
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        $pdo->commit();

        adicionarLog(
            $pdo,
            "Anular atribuição",
            "Atribuição ID {$id} anulada"
        );

        header("Location: detalhes_colaborador.php?id=".$atribuicao['colaborador_id']);
        exit;

    } catch (Exception $e) {

        $pdo->rollBack();
        die("Erro ao anular atribuição.");

    }

}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
<meta charset="UTF-8">
<title>Anular Atribuição - CrewGest</title>
<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8 mb-4">

<div class="flex items-center justify-between mb-6">

<h1 class="text-2xl font-bold text-red-700">
⚠️ Anular Atribuição
</h1>

<a href="<?= BASE_URL ?>/public/detalhes_colaborador.php?id=<?= $atribuicao['colaborador_id'] ?>"
class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">
← Voltar ao colaborador
</a>

</div>

<div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-md mb-6">

<p class="text-red-700 font-medium">
Tem a certeza que deseja anular esta atribuição?
</p>

<p class="mt-2 text-sm text-gray-700">

<strong>Peça:</strong> <?= htmlspecialchars($atribuicao['farda_nome']) ?><br>
<strong>Quantidade:</strong> <?= $atribuicao['quantidade'] ?><br>

</p>

</div>

<form method="POST" class="flex justify-end gap-3">

<a href="<?= BASE_URL ?>/public/detalhes_colaborador.php?id=<?= $atribuicao['colaborador_id'] ?>"
class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300">
Cancelar
</a>

<button type="submit"
class="px-6 py-2 rounded-lg bg-red-200 hover:bg-red-300 text-white font-semibold shadow">
❌ Confirmar Anulação
</button>

</form>

</main>

<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>