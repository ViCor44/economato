<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Obter o ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: gerir_stock_farda.php");
    exit;
}

// Buscar listas auxiliares
$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$cores = $pdo->query("SELECT id, nome FROM cores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = $pdo->query("SELECT id, nome FROM tamanhos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Buscar item atual
$stmt = $pdo->prepare("
    SELECT id, nome, cor_id, tamanho_id, departamento_id, quantidade, preco_unitario
    FROM fardas WHERE id = :id
");
$stmt->execute(['id' => $id]);
$farda = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farda) {
    die("Item não encontrado.");
}

// Atualizar item
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $cor_id = (int)$_POST['cor_id'];
    $tamanho_id = (int)$_POST['tamanho_id'];
    $departamento_id = (int)$_POST['departamento_id'];
    $quantidade = (int)$_POST['quantidade'];
    $preco_unitario = (float)$_POST['preco_unitario'];

    if ($nome && $cor_id && $tamanho_id && $departamento_id && $quantidade >= 0) {
        $stmt = $pdo->prepare("
            UPDATE fardas 
            SET nome = :nome, cor_id = :cor_id, tamanho_id = :tamanho_id,
                departamento_id = :departamento_id, quantidade = :quantidade,
                preco_unitario = :preco_unitario
            WHERE id = :id
        ");
        $stmt->execute([
            'nome' => $nome,
            'cor_id' => $cor_id,
            'tamanho_id' => $tamanho_id,
            'departamento_id' => $departamento_id,
            'quantidade' => $quantidade,
            'preco_unitario' => $preco_unitario,
            'id' => $id
        ]);
        header("Location: gerir_stock_farda.php?atualizado=1");
        exit;
    } else {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">✏️ Editar Item de Farda</h1>

    <?php if (!empty($erro)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-gray-700 font-medium mb-1">Nome *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($farda['nome']) ?>"
                   class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-1">Cor *</label>
            <select name="cor_id" class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
                <?php foreach ($cores as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $farda['cor_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-1">Tamanho *</label>
            <select name="tamanho_id" class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
                <?php foreach ($tamanhos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $t['id'] == $farda['tamanho_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-1">Departamento *</label>
            <select name="departamento_id" class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $farda['departamento_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-1">Quantidade em stock *</label>
            <input type="number" name="quantidade" min="0" value="<?= htmlspecialchars($farda['quantidade']) ?>"
                   class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-gray-700 font-medium mb-1">Preço unitário (€) *</label>
            <input type="number" name="preco_unitario" step="0.01" min="0"
                   value="<?= htmlspecialchars(number_format($farda['preco_unitario'], 2, '.', '')) ?>"
                   class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div class="flex justify-between items-center mt-6">
            <a href="gerir_stock_farda.php" class="text-gray-600 hover:underline">← Voltar</a>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow-md transition-all duration-300 active:scale-95">
                💾 Guardar Alterações
            </button>
        </div>
    </form>
</main>
</body>
</html>
