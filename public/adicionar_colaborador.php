<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$errors = [];
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $numero_funcionario = trim($_POST['numero_funcionario'] ?? '');
    $cartao = trim($_POST['cartao'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $departamento_id = $_POST['departamento_id'] ?? null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $foto_nome = null;

    $sector = trim($_POST['sector'] ?? null);
    if ($sector === '') {
        $sector = null;
    }

    // Validação
    if (empty($nome)) $errors[] = "O nome é obrigatório.";
    if (empty($numero_funcionario)) $errors[] = "O número de funcionário é obrigatório.";
    if (empty($cartao)) $errors[] = "O número do cartão é obrigatório.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "O email inserido não é válido.";
    }
    if (empty($departamento_id)) $errors[] = "Selecione um departamento.";

    // Verificar duplicados
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT id FROM colaboradores 
            WHERE cartao = ? OR numero_funcionario = ?
        ");
        $stmt->execute([$cartao, $numero_funcionario]);

        if ($stmt->fetch()) {
            $errors[] = "O cartão ou número de funcionário já existe.";
        }
    }

    // Upload da foto
    if (!empty($_FILES['foto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $permitidas)) {
            $errors[] = "Formato de imagem inválido (jpg, png, webp).";
        } else {
            $foto_nome = uniqid('colab_') . '.' . $ext;
            $destino = __DIR__ . '/../public/uploads/colaboradores/' . $foto_nome;

            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $errors[] = "Erro ao fazer upload da foto.";
            }
        }
    }

    // Inserir colaborador
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO colaboradores
                (nome, numero_funcionario, cartao, telefone, email, departamento_id, sector, ativo, foto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nome,
                $numero_funcionario,
                $cartao,
                $telefone,
                $email,
                $departamento_id,
                $sector,
                $ativo,
                $foto_nome
            ]);

            $success = "✅ Colaborador adicionado com sucesso!";
        } catch (PDOException $e) {
            $errors[] = "Erro ao adicionar colaborador.";
        }
    }
}

// Buscar departamentos
$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Colaborador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include_once '../src/templates/header.php'; ?>

<main class="p-8">
    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-lg p-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">👤 Adicionar Colaborador</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
                <ul class="list-disc pl-5"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">

            <!-- Nome -->
            <div>
                <label class="block text-gray-700 font-medium mb-1">Nome Completo</label>
                <input type="text" name="nome" class="w-full px-4 py-2 border rounded-md" required>
            </div>

            <!-- Número de Funcionário e Cartão -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Número de Funcionário</label>
                    <input type="text" name="numero_funcionario"
                        class="w-full px-4 py-2 border rounded-md"
                        required>
                </div>                
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Número do Cartão</label>
                    <input type="text" name="cartao" class="w-full px-4 py-2 border rounded-md" placeholder="Aproxime ou digite o número do cartão">
                </div>
            </div>            

            <!-- Telefone e Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Telefone</label>
                    <input type="text" name="telefone" class="w-full px-4 py-2 border rounded-md" placeholder="+351 912 345 678">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-2 border rounded-md" placeholder="exemplo@email.com">
                </div>
            </div>

            <!-- Foto -->
             <div>
                <label class="block text-gray-700 font-medium mb-1">Foto do Colaborador</label>
                <input type="file" name="foto"
                    accept="image/*"
                    class="w-full px-4 py-2 border rounded-md bg-white">
                <p class="text-sm text-gray-500 mt-1">Opcional (JPG, PNG, WEBP)</p>
            </div>

            <!-- Departamento -->
            <div>
                <label class="block text-gray-700 font-medium mb-1">Departamento</label>
                <select name="departamento_id" class="w-full px-4 py-2 border rounded-md" required>
                    <option value="">-- Selecionar --</option>
                    <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sector (Piscinas) -->
            <div id="boxSector" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Sector (Piscinas)
                </label>
                <input type="text"
                    name="sector"
                    id="sector"
                    class="w-full px-4 py-2 border rounded-md">
            </div>

            <!-- Ativo -->
            <div class="flex items-center">
                <input type="checkbox" name="ativo" id="ativo" class="mr-2" checked>
                <label for="ativo" class="text-gray-700">Colaborador Ativo</label>
            </div>

            <!-- Botões -->
            <div class="text-right pt-4">
                <a href="colaboradores.php" class="bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700">Guardar</button>
            </div>
        </form>
    </div>
</main>
<?php include_once '../src/templates/footer.php'; ?>
<script>
    const departamentoSelect = document.querySelector('select[name="departamento_id"]');
    const boxSector = document.getElementById('boxSector');

    function toggleSector() {
        const selectedText =
            departamentoSelect.options[departamentoSelect.selectedIndex]?.text.toLowerCase();

        if (selectedText && (selectedText.includes('vigilantes') || selectedText.includes('supervisores'))) {
            boxSector.classList.remove('hidden');
        } else {
            boxSector.classList.add('hidden');
            document.getElementById('sector').value = '';
        }
    }

    departamentoSelect.addEventListener('change', toggleSector);
    toggleSector(); // correr ao carregar a página
</script>
</body>
</html>
