<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Buscar departamentos existentes
$stmt = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC");
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inserir colaborador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $cartao = trim($_POST['cartao']);
    $departamento_id = (int)$_POST['departamento_id'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (!empty($nome) && !empty($cartao) && $departamento_id > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO colaboradores (nome, cartao, departamento_id, ativo)
            VALUES (:nome, :cartao, :departamento_id, :ativo)
        ");
        $stmt->execute([
            'nome' => $nome,
            'cartao' => $cartao,
            'departamento_id' => $departamento_id,
            'ativo' => $ativo
        ]);

        header("Location: colaboradores.php?adicionado=1");
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
    <title>Adicionar Colaborador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">➕ Adicionar Colaborador</h1>

    <?php if (!empty($erro)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

        <div>
            <label class="block text-gray-700 mb-1 font-medium">Nome do colaborador *</label>
            <input type="text" name="nome" placeholder="Ex: João Silva"
                   class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
        </div>

        <div>
            <label class="block text-gray-700 mb-1 font-medium">Número do Cartão *</label>
            <input type="text" name="cartao" placeholder="Ex: 123456789"
                   class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
            <p class="text-sm text-gray-500 mt-1">Passe o cartão do colaborador no leitor RFID (ou insira manualmente).</p>
        </div>

        <div>
            <label class="block text-gray-700 mb-1 font-medium">Departamento *</label>
            <select name="departamento_id" class="w-full border rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
                <option value="">-- Selecione --</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="ativo" id="ativo" checked>
            <label for="ativo" class="text-gray-700">Ativo</label>
        </div>

        <div class="flex justify-between items-center mt-6">
            <a href="colaboradores.php" class="text-gray-600 hover:underline">← Voltar</a>
            <button type="submit"
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow-md transition-all duration-300 active:scale-95">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Adicionar
            </button>
        </div>

    </form>

</main>

</body>
</html>
