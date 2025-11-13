<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Pesquisa opcional
$pesquisa = trim($_GET['pesquisa'] ?? '');

try {
    $query = "
        SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho,
               f.preco_unitario, f.quantidade,
               GROUP_CONCAT(DISTINCT d.nome ORDER BY d.nome SEPARATOR ', ') AS departamentos
        FROM fardas f
        JOIN cores c ON f.cor_id = c.id
        JOIN tamanhos t ON f.tamanho_id = t.id
        LEFT JOIN farda_departamentos fd ON f.id = fd.farda_id
        LEFT JOIN departamentos d ON fd.departamento_id = d.id
    ";

    // Se houver termo de pesquisa, filtra por nome ou cor
    if ($pesquisa) {
        $query .= " WHERE f.nome LIKE :pesq OR c.nome LIKE :pesq ";
    }

    $query .= " GROUP BY f.id ORDER BY f.nome ASC";

    $stmt = $pdo->prepare($query);

    if ($pesquisa) {
        $stmt->execute(['pesq' => "%$pesquisa%"]);
    } else {
        $stmt->execute();
    }

    $fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar stock de fardas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Stock de Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="max-w-6xl mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8">
        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Gestão de Stock de Farda</h1>

            <div class="flex flex-col md:flex-row gap-3 md:items-center">
                <!-- 🔍 Pesquisa -->
                <form method="GET" class="flex gap-2">
                    <input 
                        type="text" 
                        name="pesquisa" 
                        value="<?= htmlspecialchars($pesquisa) ?>" 
                        placeholder="Pesquisar por nome ou cor..." 
                        style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;"
                    >
                    <button type="submit"
                        style="background-color:#2563eb; color:#fff; font-weight:600; padding:8px 18px; border:none; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                        onmouseover="this.style.backgroundColor='#1d4ed8'"
                        onmouseout="this.style.backgroundColor='#2563eb'">
                        🔍 Pesquisar
                    </button>
                </form>

                <!-- ➕ Adicionar Farda -->
                <a href="adicionar_farda.php"
                style="background-color:#16a34a; color:#fff; font-weight:600; padding:8px 18px; border-radius:8px; text-decoration:none; display:flex; align-items:center; gap:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                onmouseover="this.style.backgroundColor='#15803d'"
                onmouseout="this.style.backgroundColor='#16a34a'">
                ➕ Adicionar Farda
                </a>
                <a href="adicionar_stock.php"
                style="background-color:#16a34a; color:#fff; font-weight:600; padding:8px 18px; border-radius:8px; text-decoration:none; display:flex; align-items:center; gap:6px; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                onmouseover="this.style.backgroundColor='#ee7321ff'"
                onmouseout="this.style.backgroundColor='#a37416ff'">
                ➕ Adicionar Stock
                </a>
            </div>
        </div>


        <?php if (empty($fardas)): ?>
            <p class="text-gray-600 italic">Nenhuma farda encontrada.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left">Nome</th>
                            <th class="px-4 py-2 text-left">Cor</th>
                            <th class="px-4 py-2 text-left">Tamanho</th>
                            <th class="px-4 py-2 text-left">Departamentos</th>
                            <th class="px-4 py-2 text-right">Preço (€)</th>
                            <th class="px-4 py-2 text-right">Quantidade</th>
                            <th class="px-4 py-2 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fardas as $f): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2"><?= htmlspecialchars($f['nome']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($f['cor']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($f['tamanho']) ?></td>
                                <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($f['departamentos'] ?? '—') ?></td>
                                <td class="px-4 py-2 text-right"><?= number_format($f['preco_unitario'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2 text-right"><?= (int)$f['quantidade'] ?></td>
                                <td class="px-4 py-2 text-center">
                                    <a href="editar_farda.php?id=<?= $f['id'] ?>" class="text-blue-600 hover:underline">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
