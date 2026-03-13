<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT fa.*, f.nome AS farda_nome, f.quantidade AS stock_atual
    FROM farda_atribuicoes fa
    JOIN fardas f ON f.id = fa.farda_id
    WHERE fa.id = ?
");
$stmt->execute([$id]);
$atribuicao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atribuicao) {
    die("Atribuição não encontrada.");
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nova_qtd = (int)($_POST['quantidade'] ?? 0);

    if ($nova_qtd <= 0) {
        $errors[] = "Quantidade inválida.";
    }

    if (!$errors) {

        try {

            $pdo->beginTransaction();

            $dif = $nova_qtd - $atribuicao['quantidade'];

            if ($dif > 0 && $atribuicao['stock_atual'] < $dif) {
                throw new Exception("Stock insuficiente.");
            }

            // ajustar stock
            $stmt = $pdo->prepare("
                UPDATE fardas
                SET quantidade = quantidade - ?
                WHERE id = ?
            ");
            $stmt->execute([$dif, $atribuicao['farda_id']]);

            // atualizar atribuição
            $stmt = $pdo->prepare("
                UPDATE farda_atribuicoes
                SET quantidade = ?
                WHERE id = ?
            ");
            $stmt->execute([$nova_qtd, $id]);

            $pdo->commit();

            adicionarLog(
                $pdo,
                "Editar atribuição",
                "Atribuição ID {$id} alterada para {$nova_qtd}"
            );

            header("Location: detalhes_colaborador.php?id=".$atribuicao['colaborador_id']);
            exit;

        } catch (Exception $e) {

            $pdo->rollBack();
            $errors[] = $e->getMessage();

        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
<meta charset="UTF-8">
<title>Editar Atribuição - CrewGest</title>
<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8 mb-4">

<div class="flex items-center justify-between mb-4">

<h1 class="text-2xl font-bold text-gray-800">
✏️ Editar Atribuição
</h1>

<a href="<?= BASE_URL ?>/public/detalhes_colaborador.php?id=<?= $atribuicao['colaborador_id'] ?>"
class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">
← Voltar ao colaborador
</a>

</div>

<p class="text-gray-700 mb-6">

Editar atribuição de farda ao colaborador<br>

<strong>ID colaborador:</strong> <?= $atribuicao['colaborador_id'] ?><br>

<strong>Peça:</strong> <?= htmlspecialchars($atribuicao['farda_nome']) ?>

</p>

<?php if ($errors): ?>

<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
<ul class="list-disc pl-5">
<?php foreach ($errors as $e): ?>
<li><?= htmlspecialchars($e) ?></li>
<?php endforeach; ?>
</ul>
</div>

<?php endif; ?>

<form method="POST" class="space-y-6">

<div>

<label class="block text-sm font-medium text-gray-700 mb-1">
Quantidade
</label>

<input
type="number"
name="quantidade"
value="<?= $atribuicao['quantidade'] ?>"
min="1"
class="w-full px-4 py-2 border rounded-md"
required>

</div>

<div class="flex justify-end">

<button type="submit"
class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
Guardar Alteração
</button>

</div>

</form>

</main>

<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>