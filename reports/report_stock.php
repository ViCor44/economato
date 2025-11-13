<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Buscar stock
$stmt = $pdo->query("
    SELECT 
        f.id,
        f.nome,
        c.nome AS cor,
        t.nome AS tamanho,
        f.quantidade,
        f.preco_unitario,
        (f.quantidade * f.preco_unitario) AS total_valor
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    ORDER BY f.nome, cor, tamanho
");

$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular total geral
$total_geral = array_sum(array_column($fardas, 'total_valor'));
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Stock de Fardas</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include '../src/templates/header.php'; ?>

<main class="max-w-6xl mx-auto bg-white shadow-md rounded-xl p-8 mt-6">

    <h1 class="text-3xl font-bold text-gray-800 mb-6 flex items-center gap-3">
        📦 Relatório de Stock de Fardas
    </h1>

    <!-- Exportação -->
    <div class="flex justify-end mb-4">
        <a href="stock_export_csv.php"
           class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow">
            📊 Exportar para Excel/CSV
        </a>
    </div>

    <!-- Tabela -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-200 text-left">
                    <th class="p-3">Peça</th>
                    <th class="p-3">Cor</th>
                    <th class="p-3">Tamanho</th>
                    <th class="p-3 text-center">Stock</th>
                    <th class="p-3 text-right">€ Unidade</th>
                    <th class="p-3 text-right">€ Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fardas as $f): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3"><?= htmlspecialchars($f['nome']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($f['cor']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($f['tamanho']) ?></td>
                    <td class="p-3 text-center"><?= $f['quantidade'] ?></td>
                    <td class="p-3 text-right"><?= number_format($f['preco_unitario'], 2, ',', '.') ?> €</td>
                    <td class="p-3 text-right font-semibold">
                        <?= number_format($f['total_valor'], 2, ',', '.') ?> €
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-gray-800 text-white font-bold">
                    <td colspan="5" class="p-3 text-right">TOTAL GERAL DO ARMAZÉM:</td>
                    <td class="p-3 text-right">
                        <?= number_format($total_geral, 2, ',', '.') ?> €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

</main>

</body>
</html>
