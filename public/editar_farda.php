<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: gerir_stock_farda.php');
    exit;
}

$errors = [];
$success = '';

try {
    // Carregar farda
    $stmt = $pdo->prepare("SELECT * FROM fardas WHERE id = ?");
    $stmt->execute([$id]);
    $farda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$farda) {
        throw new Exception("Farda não encontrada.");
    }

    // Carregar cores, tamanhos e departamentos
    $cores = $pdo->query("SELECT id, nome FROM cores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $tamanhos = $pdo->query("SELECT id, nome FROM tamanhos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
    $departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Carregar departamentos associados atualmente
    $stmtDeps = $pdo->prepare("SELECT departamento_id FROM farda_departamentos WHERE farda_id = ?");
    $stmtDeps->execute([$id]);
    $deps_assoc = $stmtDeps->fetchAll(PDO::FETCH_COLUMN, 0); // array de ids

} catch (Exception $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

// Processar submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cor_id = $_POST['cor_id'] ?? null;
    $tamanho_id = $_POST['tamanho_id'] ?? null;
    $departamentos_sel = $_POST['departamentos'] ?? []; // array de ids
    $preco_unitario_raw = $_POST['preco_unitario'] ?? '';
    $quantidade_raw = $_POST['quantidade'] ?? '';

    // Normalizar preço (aceitar vírgula)
    $preco_unitario = str_replace(',', '.', trim($preco_unitario_raw));
    $preco_unitario = $preco_unitario === '' ? null : (float)$preco_unitario;
    $quantidade = is_numeric($quantidade_raw) ? (int)$quantidade_raw : null;

    // Validações
    if ($nome === '') $errors[] = "O nome da peça é obrigatório.";
    if (empty($cor_id)) $errors[] = "Selecione uma cor.";
    if (empty($tamanho_id)) $errors[] = "Selecione um tamanho.";
    if (empty($departamentos_sel) || !is_array($departamentos_sel)) $errors[] = "Selecione pelo menos um departamento.";
    if ($preco_unitario === null || $preco_unitario < 0) $errors[] = "Preço unitário inválido.";
    if ($quantidade === null || $quantidade < 0) $errors[] = "Quantidade inválida.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Atualizar tabela fardas
            $update = $pdo->prepare("
                UPDATE fardas
                SET nome = ?, cor_id = ?, tamanho_id = ?, preco_unitario = ?, quantidade = ?
                WHERE id = ?
            ");
            $update->execute([$nome, $cor_id, $tamanho_id, $preco_unitario, $quantidade, $id]);

            // Sincronizar departamentos:
            // Opção simples: apagar associações antigas e inserir as novas (rápido e seguro)
            $del = $pdo->prepare("DELETE FROM farda_departamentos WHERE farda_id = ?");
            $del->execute([$id]);

            $ins = $pdo->prepare("INSERT INTO farda_departamentos (farda_id, departamento_id) VALUES (?, ?)");
            foreach ($departamentos_sel as $dep_id) {
                // garantir que dep_id é inteiro
                $dep_id = (int)$dep_id;
                if ($dep_id > 0) {
                    $ins->execute([$id, $dep_id]);
                }
            }

            $pdo->commit();
            $success = "✅ Farda atualizada com sucesso.";
            // atualizar variáveis locais para manter o formulário preenchido após submit
            $farda['nome'] = $nome;
            $farda['cor_id'] = $cor_id;
            $farda['tamanho_id'] = $tamanho_id;
            $farda['preco_unitario'] = $preco_unitario;
            $farda['quantidade'] = $quantidade;
            $deps_assoc = array_map('intval', $departamentos_sel);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao atualizar farda: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="utf-8">
    <title>Editar Farda - CrewGest</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">✏️ Editar Farda</h1>
        <a href="gerir_stock_farda.php" class="text-blue-600 hover:underline">← Voltar ao stock</a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Peça</label>
            <input type="text" name="nome" required
                   class="w-full px-4 py-2 border rounded-md"
                   value="<?= htmlspecialchars($farda['nome']) ?>">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                <select name="cor_id" required class="w-full px-4 py-2 border rounded-md">
                    <option value="">-- Escolha uma cor --</option>
                    <?php foreach ($cores as $cor): ?>
                        <option value="<?= $cor['id'] ?>" <?= ((int)$farda['cor_id'] === (int)$cor['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cor['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho</label>
                <select name="tamanho_id" required class="w-full px-4 py-2 border rounded-md">
                    <option value="">-- Escolha um tamanho --</option>
                    <?php foreach ($tamanhos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ((int)$farda['tamanho_id'] === (int)$t['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Departamentos (associar esta peça)</label>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                <?php foreach ($departamentos as $d): 
                    $checked = in_array((int)$d['id'], array_map('intval', $deps_assoc));
                ?>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="departamentos[]" value="<?= $d['id'] ?>"
                               class="h-4 w-4" <?= $checked ? 'checked' : '' ?>>
                        <span class="text-gray-700"><?= htmlspecialchars($d['nome']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Preço Unitário (€)</label>
                <input type="number" name="preco_unitario" step="0.01" min="0" required
                       class="w-full px-4 py-2 border rounded-md"
                       value="<?= htmlspecialchars(number_format((float)$farda['preco_unitario'], 2, '.', '')) ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
                <input type="number" name="quantidade" min="0" required
                       class="w-full px-4 py-2 border rounded-md"
                       value="<?= (int)$farda['quantidade'] ?>">
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="gerir_stock_farda.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md">Cancelar</a>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Guardar Alterações</button>
        </div>
    </form>
</main>

</body>
</html>
