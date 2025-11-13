<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cor_id = $_POST['cor_id'] ?? null;
    $tamanho_id = $_POST['tamanho_id'] ?? null;
    $departamentos = $_POST['departamentos'] ?? [];
    $preco_unitario = str_replace(',', '.', $_POST['preco_unitario'] ?? '0');
    $quantidade = (int)($_POST['quantidade'] ?? 0);

    if (empty($nome) || empty($cor_id) || empty($tamanho_id) || empty($departamentos)) {
        $errors[] = "Todos os campos obrigatórios devem ser preenchidos.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Inserir nova farda
            $stmt = $pdo->prepare("
                INSERT INTO fardas (nome, cor_id, tamanho_id, preco_unitario, quantidade)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $cor_id, $tamanho_id, $preco_unitario, $quantidade]);
            $farda_id = $pdo->lastInsertId();

            // Inserir relação com departamentos
            $stmtDep = $pdo->prepare("INSERT INTO farda_departamentos (farda_id, departamento_id) VALUES (?, ?)");
            foreach ($departamentos as $dep_id) {
                $stmtDep->execute([$farda_id, $dep_id]);
            }

            $pdo->commit();
            $success = "✅ Farda adicionada com sucesso!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao adicionar farda: " . $e->getMessage();
        }
    }
}

// Carregar opções
$cores = $pdo->query("SELECT id, nome FROM cores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = $pdo->query("SELECT id, nome FROM tamanhos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>

    <main class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">➕ Adicionar Farda</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Peça</label>
                <input type="text" name="nome" required
                       class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                    <select name="cor_id" class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500" required>
                        <option value="">-- Escolha uma cor --</option>
                        <?php foreach ($cores as $cor): ?>
                            <option value="<?= $cor['id'] ?>"><?= htmlspecialchars($cor['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho</label>
                    <select name="tamanho_id" class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500" required>
                        <option value="">-- Escolha um tamanho --</option>
                        <?php foreach ($tamanhos as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Departamentos</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($departamentos as $d): ?>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="departamentos[]" value="<?= $d['id'] ?>"
                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="text-gray-700"><?= htmlspecialchars($d['nome']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Preço Unitário (€)</label>
                    <input type="number" step="0.01" min="0" name="preco_unitario" required
                           class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade Inicial</label>
                    <input type="number" name="quantidade" min="0" required
                           class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
                    Guardar Farda
                </button>
            </div>
        </form>
    </main>
</body>
</html>
