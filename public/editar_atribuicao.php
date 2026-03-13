<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT fa.*, f.nome, f.quantidade AS stock_atual
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nova_qtd = (int)$_POST['quantidade'];

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

            // atualizar stock
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
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Editar Atribuição</title>
<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

<main class="max-w-xl mx-auto bg-white p-6 rounded-xl shadow mt-10">

<h1 class="text-xl font-bold mb-4">✏️ Editar Atribuição</h1>

<p class="mb-4">
<strong>Peça:</strong> <?= htmlspecialchars($atribuicao['nome']) ?>
</p>

<?php foreach ($errors as $e): ?>
<div class="text-red-600 mb-2"><?= $e ?></div>
<?php endforeach; ?>

<form method="POST">

<label class="block mb-2">Quantidade</label>

<input type="number"
       name="quantidade"
       value="<?= $atribuicao['quantidade'] ?>"
       min="1"
       class="border p-2 rounded w-full mb-4">

<button class="bg-blue-600 text-white px-4 py-2 rounded">
Guardar
</button>

<a href="detalhes_colaborador.php?id=<?= $atribuicao['colaborador_id'] ?>"
   class="ml-3 text-gray-600">
Cancelar
</a>

</form>

</main>

</body>
</html>