<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: colaboradores.php");
    exit;
}

$id = (int)$_GET['id'];
$errors = [];
$success = '';

// Obter dados do colaborador
$stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
$stmt->execute([$id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    header("Location: colaboradores.php");
    exit;
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cartao = trim($_POST['cartao'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $departamento_id = $_POST['departamento_id'] ?? null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if (empty($nome)) $errors[] = "O nome é obrigatório.";
    if (empty($cartao)) $errors[] = "O número do cartão é obrigatório.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "O email inserido não é válido.";

    // Evitar cartões duplicados
    $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE cartao = ? AND id <> ?");
    $stmt->execute([$cartao, $id]);
    if ($stmt->fetch()) {
        $errors[] = "O cartão já está associado a outro colaborador.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE colaboradores 
                SET nome = ?, cartao = ?, telefone = ?, email = ?, departamento_id = ?, ativo = ? 
                WHERE id = ?
            ");
            $stmt->execute([$nome, $cartao, $telefone, $email, $departamento_id, $ativo, $id]);
            $success = "✅ Dados do colaborador atualizados com sucesso!";
        } catch (PDOException $e) {
            $errors[] = "Erro ao atualizar colaborador: " . $e->getMessage();
        }
    }
}

$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Editar Colaborador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include_once '../src/templates/header.php'; ?>

<main class="p-8">
    <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-lg p-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">✏️ Editar Colaborador</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
                <ul class="list-disc pl-5"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-gray-700 font-medium mb-1">Nome Completo</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($colaborador['nome']) ?>" class="w-full px-4 py-2 border rounded-md" required>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-1">Número do Cartão</label>
                <input type="text" name="cartao" value="<?= htmlspecialchars($colaborador['cartao']) ?>" class="w-full px-4 py-2 border rounded-md" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Telefone</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($colaborador['telefone']) ?>" class="w-full px-4 py-2 border rounded-md">
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($colaborador['email']) ?>" class="w-full px-4 py-2 border rounded-md">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-1">Departamento</label>
                <select name="departamento_id" class="w-full px-4 py-2 border rounded-md" required>
                    <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($d['id'] == $colaborador['departamento_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center">
                <input type="checkbox" name="ativo" id="ativo"
                        style="margin-right:10px;" <?= $colaborador['ativo'] ? 'checked' : '' ?>>
                <label for="ativo" class="text-gray-700">Colaborador Ativo</label>
            </div>

            <div class="text-right pt-4">
                <a href="colaboradores.php" class="bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300">Voltar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700">Guardar Alterações</button>
            </div>
        </form>
    </div>
</main>
<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
