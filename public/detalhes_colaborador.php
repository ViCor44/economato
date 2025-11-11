<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Verifica se o ID foi passado
$colaborador_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($colaborador_id <= 0) {
    header("Location: colaboradores.php");
    exit;
}

try {
    // 🔹 Buscar dados do colaborador
    $stmt = $pdo->prepare("
        SELECT c.*, d.nome AS departamento_nome
        FROM colaboradores c
        LEFT JOIN departamentos d ON c.departamento_id = d.id
        WHERE c.id = :id
    ");
    $stmt->execute(['id' => $colaborador_id]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$colaborador) {
        die("Colaborador não encontrado.");
    }

    // 🔹 Buscar cacifos atribuídos
    $stmtCacifos = $pdo->prepare("
        SELECT numero, avariado
        FROM cacifos
        WHERE colaborador_id = :id
        ORDER BY numero ASC
    ");
    $stmtCacifos->execute(['id' => $colaborador_id]);
    $cacifos = $stmtCacifos->fetchAll(PDO::FETCH_ASSOC);

    // 🔹 (Futuro) Buscar fardas
    $fardas = []; // Placeholder por agora

} catch (PDOException $e) {
    die("Erro ao carregar detalhes do colaborador: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Colaborador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-5xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            👤 <?= htmlspecialchars($colaborador['nome']) ?>
        </h1>
        <a href="colaboradores.php" class="text-blue-600 hover:underline">← Voltar</a>
    </div>

    <section class="mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Informações Pessoais</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">
            <p><strong>ID:</strong> <?= $colaborador['id'] ?></p>
            <p><strong>Cartão:</strong> <?= htmlspecialchars($colaborador['cartao']) ?></p>
            <p><strong>Departamento:</strong> <?= htmlspecialchars($colaborador['departamento_nome'] ?? '—') ?></p>
            <p><strong>Status:</strong>
                <?php if ($colaborador['ativo']): ?>
                    <span class="text-green-600 font-medium">Ativo</span>
                <?php else: ?>
                    <span class="text-red-600 font-medium">Inativo</span>
                <?php endif; ?>
            </p>
            <p><strong>Data de criação:</strong>
                <?= date('d/m/Y H:i', strtotime($colaborador['criado_em'])) ?>
            </p>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">🔒 Cacifos Atribuídos</h2>
        <?php if (count($cacifos) > 0): ?>
            <table class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="text-left px-4 py-2 border-b">Número</th>
                        <th class="text-left px-4 py-2 border-b">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cacifos as $c): ?>
                        <tr>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($c['numero']) ?></td>
                            <td class="px-4 py-2 border-b">
                                <?= $c['avariado'] ? '<span class="text-red-600">Avariado</span>' : '<span class="text-green-600">OK</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-600">Nenhum cacifo atribuído.</p>
        <?php endif; ?>
    </section>

    <section class="mt-8">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold text-gray-800">🧥 Fardas Atribuídas</h2>

        <a href="atribuir_farda.php?colaborador_id=<?= $colaborador['id'] ?>"
           class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-300 active:scale-95">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Atribuir Farda
        </a>
    </div>

    <?php
    // Buscar fardas atribuídas ao colaborador
    $stmt = $pdo->prepare("
        SELECT fa.id, f.nome, c.nome AS cor, t.nome AS tamanho, fa.quantidade, 
               f.preco_unitario, fa.data_atribuicao
        FROM farda_atribuicoes fa
        JOIN fardas f ON fa.farda_id = f.id
        JOIN cores c ON f.cor_id = c.id
        JOIN tamanhos t ON f.tamanho_id = t.id
        WHERE fa.colaborador_id = :id
        ORDER BY fa.data_atribuicao DESC
    ");
    $stmt->execute(['id' => $colaborador['id']]);
    $fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_geral = 0;
    ?>

    <?php if ($fardas_atribuidas): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200 text-sm mt-2">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 border-b text-left">Peça</th>
                        <th class="px-4 py-2 border-b text-left">Cor</th>
                        <th class="px-4 py-2 border-b text-left">Tamanho</th>
                        <th class="px-4 py-2 border-b text-center">Qtd</th>
                        <th class="px-4 py-2 border-b text-right">Preço (€)</th>
                        <th class="px-4 py-2 border-b text-right">Total (€)</th>
                        <th class="px-4 py-2 border-b text-center">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fardas_atribuidas as $f): 
                        $total_item = $f['quantidade'] * $f['preco_unitario'];
                        $total_geral += $total_item;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['nome']) ?></td>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['cor']) ?></td>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['tamanho']) ?></td>
                        <td class="px-4 py-2 border-b text-center"><?= $f['quantidade'] ?></td>
                        <td class="px-4 py-2 border-b text-right"><?= number_format($f['preco_unitario'], 2, ',', '.') ?></td>
                        <td class="px-4 py-2 border-b text-right font-semibold">
                            <?= number_format($total_item, 2, ',', '.') ?>
                        </td>
                        <td class="px-4 py-2 border-b text-center text-gray-600">
                            <?= date('d/m/Y H:i', strtotime($f['data_atribuicao'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-right font-semibold">💰 Total Geral:</td>
                        <td class="px-4 py-3 text-right font-bold text-green-700">
                            <?= number_format($total_geral, 2, ',', '.') ?> €
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <p class="text-gray-500 italic">Nenhuma farda atribuída a este colaborador.</p>
    <?php endif; ?>
</section>


</main>

</body>
</html>
