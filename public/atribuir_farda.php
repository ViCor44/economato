<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// 🔹 Buscar lista de colaboradores
$colaboradores = $pdo->query("
    SELECT c.id, c.nome, d.nome AS departamento, c.departamento_id
    FROM colaboradores c
    JOIN departamentos d ON c.departamento_id = d.id
    WHERE c.ativo = 1
    ORDER BY c.nome
")->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Se um colaborador for selecionado
$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
$fardas_disponiveis = [];

if ($colaborador_id > 0) {
    $stmt = $pdo->prepare("
        SELECT departamento_id FROM colaboradores WHERE id = :id
    ");
    $stmt->execute(['id' => $colaborador_id]);
    $colab = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($colab) {
        $departamento_id = $colab['departamento_id'];
        $stmt = $pdo->prepare("
            SELECT f.id, f.nome, f.quantidade, c.nome AS cor, t.nome AS tamanho
            FROM fardas f
            JOIN cores c ON f.cor_id = c.id
            JOIN tamanhos t ON f.tamanho_id = t.id
            WHERE f.departamento_id = :dep AND f.quantidade > 0
            ORDER BY f.nome
        ");
        $stmt->execute(['dep' => $departamento_id]);
        $fardas_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 🔹 Processar atribuição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = (int)$_POST['colaborador_id'];
    $farda_id = (int)$_POST['farda_id'];
    $quantidade = (int)$_POST['quantidade'];

    try {
        $pdo->beginTransaction();

        // Verificar stock
        $stmt = $pdo->prepare("SELECT quantidade FROM fardas WHERE id = :id");
        $stmt->execute(['id' => $farda_id]);
        $farda = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$farda || $farda['quantidade'] < $quantidade) {
            throw new Exception("Stock insuficiente para esta peça de farda.");
        }

        // Registar atribuição
        $stmt = $pdo->prepare("
            INSERT INTO farda_atribuicoes (colaborador_id, farda_id, quantidade)
            VALUES (:colaborador_id, :farda_id, :quantidade)
        ");
        $stmt->execute([
            'colaborador_id' => $colaborador_id,
            'farda_id' => $farda_id,
            'quantidade' => $quantidade
        ]);

        // Atualizar stock
        $stmt = $pdo->prepare("
            UPDATE fardas SET quantidade = quantidade - :qtd WHERE id = :id
        ");
        $stmt->execute(['qtd' => $quantidade, 'id' => $farda_id]);

        $pdo->commit();
        $mensagem = "Peça atribuída com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = "Erro ao atribuir farda: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atribuir Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
    <script>
        function selecionarColaborador() {
            const id = document.getElementById('colaborador_id').value;
            window.location = 'atribuir_farda.php?colaborador_id=' + id;
        }
    </script>
</head>
<body class="p-8">
<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-4xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">👕 Atribuir Peça de Farda</h1>

    <?php if (!empty($mensagem)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php elseif (!empty($erro)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <!-- Selecionar colaborador -->
    <div class="mb-6">
        <label for="colaborador_id" class="block text-gray-700 mb-1 font-medium">Selecionar colaborador</label>
        <select id="colaborador_id" name="colaborador_id"
                onchange="selecionarColaborador()"
                class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
            <option value="">-- Escolha o colaborador --</option>
            <?php foreach ($colaboradores as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $colaborador_id == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['departamento']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($colaborador_id && $fardas_disponiveis): ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="colaborador_id" value="<?= $colaborador_id ?>">

            <div>
                <label class="block text-gray-700 mb-1 font-medium">Peça de farda</label>
                <select name="farda_id" required class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($fardas_disponiveis as $f): ?>
                        <option value="<?= $f['id'] ?>">
                            <?= htmlspecialchars($f['nome']) ?> - <?= htmlspecialchars($f['cor']) ?> - <?= htmlspecialchars($f['tamanho']) ?> (Stock: <?= $f['quantidade'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 mb-1 font-medium">Quantidade</label>
                <input type="number" name="quantidade" min="1" value="1"
                       class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex justify-between items-center mt-6">
                <a href="gerir_stock_farda.php" class="text-gray-600 hover:underline">← Voltar</a>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow-md transition-all duration-300 active:scale-95">
                    💾 Atribuir
                </button>
            </div>
        </form>
    <?php elseif ($colaborador_id): ?>
        <p class="text-gray-600">⚠️ Nenhuma farda disponível para o departamento deste colaborador.</p>
    <?php endif; ?>

</main>
</body>
</html>
