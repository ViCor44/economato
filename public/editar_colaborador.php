<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: colaboradores.php");
    exit;
}

// Buscar dados do colaborador
$stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = :id");
$stmt->execute(['id' => $id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// Buscar departamentos
$departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

// Atualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $cartao = trim($_POST['cartao']);
    $departamento_id = (int)$_POST['departamento_id'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE colaboradores 
                           SET nome = :nome, cartao = :cartao, departamento_id = :departamento_id, ativo = :ativo 
                           WHERE id = :id");
    $stmt->execute([
        'nome' => $nome,
        'cartao' => $cartao,
        'departamento_id' => $departamento_id,
        'ativo' => $ativo,
        'id' => $id
    ]);

    header("Location: colaboradores.php?atualizado=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Colaborador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">✏️ Editar Colaborador</h1>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-gray-700 mb-1 font-medium">Nome</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($colaborador['nome']) ?>"
                   class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-gray-700 mb-1 font-medium">Cartão</label>
            <input type="text" name="cartao" value="<?= htmlspecialchars($colaborador['cartao']) ?>"
                   class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-gray-700 mb-1 font-medium">Departamento</label>
            <select name="departamento_id" class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value="">-- Selecione --</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($d['id'] == $colaborador['departamento_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="ativo" id="ativo" <?= $colaborador['ativo'] ? 'checked' : '' ?>>
            <label for="ativo" class="text-gray-700">Ativo</label>
        </div>

        <div class="flex justify-between items-center mt-6">
            <a href="colaboradores.php" class="text-gray-600 hover:underline">← Voltar</a>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow-md transition-all duration-300 active:scale-95">
                💾 Guardar Alterações
            </button>
        </div>
    </form>
</main>

</body>
</html>
