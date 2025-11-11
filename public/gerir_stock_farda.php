<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// =====================
// 🔍 FILTRO DE PESQUISA
// =====================
$pesquisa = trim($_GET['pesquisa'] ?? '');
$params = [];

// =====================
// 🔹 QUERY PRINCIPAL
// =====================
$query = "
    SELECT 
        f.id,
        f.nome,
        f.quantidade,
        f.preco_unitario,
        c.nome AS cor,
        t.nome AS tamanho,
        d.nome AS departamento
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    JOIN departamentos d ON f.departamento_id = d.id
";

if ($pesquisa !== '') {
    $query .= " WHERE f.nome LIKE :pesquisa ";
    $params['pesquisa'] = "%$pesquisa%";
}

$query .= " ORDER BY f.nome ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================
// 💰 CÁLCULO TOTAL DE STOCK
// =====================
$total_stock = 0;
foreach ($fardas as $f) {
    $total_stock += $f['quantidade'] * $f['preco_unitario'];
}
?>

<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerir Stock de Fardas - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">
    <?php include_once '../src/templates/header.php'; ?>

    <main class="max-w-6xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">👕 Gerir Stock de Fardas</h1>

            <div class="text-right text-green-700 font-semibold">
                💰 Valor Total em Stock: <?= number_format($total_stock, 2, ',', '.') ?> €
            </div>
        </div>

        <!-- 🔍 Barra de Pesquisa -->
        <div class="flex justify-between items-center mb-6">
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="pesquisa" placeholder="🔍 Procurar item..."
                       value="<?= htmlspecialchars($pesquisa ?? '') ?>"
                       class="border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500"
                       style="min-width: 220px;">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-md transition">
                    Pesquisar
                </button>
                <?php if (!empty($pesquisa)): ?>
                    <a href="gerir_stock_farda.php" class="text-gray-600 hover:underline ml-2">Limpar</a>
                <?php endif; ?>
            </form>

            <a href="adicionar_farda.php"
                    class="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 active:scale-95 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Novo Item
            </a>
        </div>

        <!-- 🧾 Tabela de Stock -->
        <?php if ($fardas): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 border-b text-left">Nome</th>
                            <th class="px-4 py-2 border-b text-left">Cor</th>
                            <th class="px-4 py-2 border-b text-left">Tamanho</th>
                            <th class="px-4 py-2 border-b text-left">Departamento</th>
                            <th class="px-4 py-2 border-b text-center">Qtd</th>
                            <th class="px-4 py-2 border-b text-right">Preço (€)</th>
                            <th class="px-4 py-2 border-b text-right">Valor Total (€)</th>
                            <th class="px-4 py-2 border-b text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fardas as $f): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['nome']) ?></td>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['cor']) ?></td>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['tamanho']) ?></td>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['departamento']) ?></td>
                            <td class="px-4 py-2 border-b text-center font-semibold"><?= $f['quantidade'] ?></td>
                            <td class="px-4 py-2 border-b text-right"><?= number_format($f['preco_unitario'], 2, ',', '.') ?></td>
                            <td class="px-4 py-2 border-b text-right font-semibold">
                                <?= number_format($f['quantidade'] * $f['preco_unitario'], 2, ',', '.') ?>
                            </td>
                            <td class="px-4 py-2 border-b text-center">
                                <a href="editar_farda.php?id=<?= $f['id'] ?>" 
                                   class="text-blue-600 hover:text-blue-800">✏️ Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-600 italic mt-4">Nenhum item encontrado.</p>
        <?php endif; ?>
    </main>
</body>
</html>
