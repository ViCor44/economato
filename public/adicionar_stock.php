<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

// Carregar lista de fardas existentes
$stmt = $pdo->query("
    SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    ORDER BY f.nome ASC
");
$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farda_id = $_POST['farda_id'];
    $quantidade = (int)$_POST['quantidade'];
    $preco_compra = $_POST['preco_compra'] ? (float)$_POST['preco_compra'] : null;

    if ($farda_id && $quantidade > 0) {
        try {
            $pdo->beginTransaction();

            // Atualiza o stock
            $stmt = $pdo->prepare("UPDATE fardas SET quantidade = quantidade + ? WHERE id = ?");
            $stmt->execute([$quantidade, $farda_id]);

            // Regista o movimento de compra
            $stmt = $pdo->prepare("
                INSERT INTO farda_compras (farda_id, quantidade_adicionada, preco_compra, criado_por)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$farda_id, $quantidade, $preco_compra, $utilizador_logado['id'] ?? null]);

            $pdo->commit();

            adicionarLog(
                $pdo,
                "Adicionou stock",
                "Farda ID $farda_id | Quantidade: $quantidade"
            );

            header('Location: gerir_stock_farda.php?sucesso=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao adicionar stock: " . $e->getMessage();
        }
    } else {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Stock - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">
<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-lg mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">📦 Adicionar Stock de Farda</h1>

    <?php if (!empty($erro)): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-md mb-4"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label for="farda_id" class="block font-medium text-gray-700">Farda</label>
            <select name="farda_id" id="farda_id" required class="w-full border rounded-md p-2">
                <option value="">-- Selecione a farda --</option>
                <?php foreach ($fardas as $f): ?>
                    <option value="<?= $f['id'] ?>">
                        <?= htmlspecialchars($f['nome'] . " (" . $f['cor'] . " - " . $f['tamanho'] . ")") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="quantidade" class="block font-medium text-gray-700">Quantidade a adicionar</label>
            <input type="number" name="quantidade" id="quantidade" required min="1" class="w-full border rounded-md p-2">
        </div>

        <div>
            <label for="preco_compra" class="block font-medium text-gray-700">Preço de compra (opcional, €)</label>
            <input type="number" step="0.01" name="preco_compra" id="preco_compra" class="w-full border rounded-md p-2">
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="gerir_stock_farda.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Cancelar</a>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">Guardar</button>
        </div>
    </form>
</main>
</body>
</html>
