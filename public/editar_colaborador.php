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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $numero_funcionario = trim($_POST['numero_funcionario'] ?? '');
    $cartao = trim($_POST['cartao'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $departamento_id = $_POST['departamento_id'] ?? null;
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $cartao_entregue = isset($_POST['cartao_entregue']) ? 1 : 0;

    $foto_nome = $colaborador['foto']; // manter foto atual

    $sector = trim($_POST['sector'] ?? null);
    if ($sector === '') {
        $sector = null;
    }

    // Se nao existir cartao no momento da edicao, guardar nota padrao.
    if ($cartao === '') {
        $cartao = 'SEM CARTAO (Aguardando atribuicao)';
    }

    // Validações
    if (empty($nome)) $errors[] = "O nome é obrigatório.";
    if (empty($numero_funcionario)) $errors[] = "O número de funcionário é obrigatório.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "O email inserido não é válido.";
    }

    // Duplicados (cartão ou nº funcionário)
    $cartao_tem_numero_real = ($cartao !== 'SEM CARTAO (Aguardando atribuicao)');

    if ($cartao_tem_numero_real) {
        $stmt = $pdo->prepare("
            SELECT id FROM colaboradores
            WHERE (cartao = ? OR numero_funcionario = ?) AND id <> ?
        ");
        $stmt->execute([$cartao, $numero_funcionario, $id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id FROM colaboradores
            WHERE numero_funcionario = ? AND id <> ?
        ");
        $stmt->execute([$numero_funcionario, $id]);
    }

    if ($stmt->fetch()) {
        $errors[] = "O cartão ou número de funcionário já pertence a outro colaborador.";
    }

    // Upload de nova foto (se existir)
    if (!empty($_FILES['foto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $permitidas)) {
            $errors[] = "Formato de imagem inválido.";
        } else {
            $foto_nome = uniqid('colab_') . '.' . $ext;
            $destino = __DIR__ . '/../public/uploads/colaboradores/' . $foto_nome;

            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                $errors[] = "Erro ao fazer upload da foto.";
            }
        }
    }

    // Atualizar colaborador
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE colaboradores SET
                    nome = ?,
                    numero_funcionario = ?,
                    cartao = ?,
                    telefone = ?,
                    email = ?,
                    departamento_id = ?,
                    sector = ?,
                    ativo = ?,
                    cartao_entregue = ?,
                    foto = ?
                WHERE id = ?
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
                $cartao_entregue,
                $foto_nome,
                $id
            ]);

            $success = "✅ Dados do colaborador atualizados com sucesso!";
            // refrescar dados
            $colaborador = array_merge($colaborador, $_POST, ['foto' => $foto_nome]);
        } catch (PDOException $e) {
            $errors[] = "Erro ao atualizar colaborador.";
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

        <form method="POST" enctype="multipart/form-data" class="space-y-6">

            <div>
                <label class="block text-gray-700 font-medium mb-1">Nome Completo</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($colaborador['nome']) ?>" class="w-full px-4 py-2 border rounded-md" required>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-1">Número de Funcionário</label>
                <input type="text"
                    name="numero_funcionario"
                    value="<?= htmlspecialchars($colaborador['numero_funcionario']) ?>"
                    class="w-full px-4 py-2 border rounded-md"
                    required>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-1">Número do Cartão</label>
                <input type="text" name="cartao" value="<?= htmlspecialchars($colaborador['cartao']) ?>" class="w-full px-4 py-2 border rounded-md" placeholder="Opcional: aproxime ou digite o número do cartão">
                <p class="text-sm text-gray-500 mt-1">Se ficar vazio, será guardado como "SEM CARTÃO (Aguardando atribuição)".</p>
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
                <label class="block text-gray-700 font-medium mb-1">Foto do Colaborador</label>

                <?php if (!empty($colaborador['foto'])): ?>
                    <img src="<?= BASE_URL ?>/public/uploads/colaboradores/<?= htmlspecialchars($colaborador['foto']) ?>"
                        class="h-24 w-24 object-cover rounded-full mb-3 border">
                <?php else: ?>
                    <p class="text-sm text-gray-500 mb-2">Sem foto associada</p>
                <?php endif; ?>

                <input type="file"
                    name="foto"
                    accept="image/*"
                    class="w-full px-4 py-2 border rounded-md bg-white">

                <p class="text-sm text-gray-500 mt-1">
                    Enviar nova foto apenas se quiser substituir a atual
                </p>
            </div>

            <div>
                <label class="block text-gray-700 font-medium mb-1">Departamento</label>
                <select name="departamento_id" class="w-full px-4 py-2 border rounded-md mb-4" required>
                    <?php foreach ($departamentos as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($d['id'] == $colaborador['departamento_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="boxSector" class="<?= (
                    stripos($colaborador['departamento_nome'] ?? '', 'piscinas') === false &&
                    stripos($colaborador['departamento_nome'] ?? '', 'vigilantes') === false &&
                    stripos($colaborador['departamento_nome'] ?? '', 'supervisores') === false
                ) ? 'hidden' : '' ?>">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Sector (Piscinas)
                    </label>
                    <input type="text"
                        name="sector"
                        id="sector"
                        value="<?= htmlspecialchars($colaborador['sector'] ?? '') ?>"
                        class="w-full px-4 py-2 border rounded-md">
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="ativo" id="ativo" class="mr-2" <?= $colaborador['ativo'] ? 'checked' : '' ?>>
                    <span class="text-gray-700">Colaborador Ativo</span>
                </label>

                <label class="inline-flex items-center">
                    <input type="checkbox" name="cartao_entregue" id="cartao_entregue" class="mr-2" <?= !empty($colaborador['cartao_entregue']) ? 'checked' : '' ?>>
                    <span class="text-gray-700">Cartão Entregue</span>
                </label>
            </div>

            <div class="text-right pt-4">
                <a href="colaboradores.php" class="bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300">Voltar</a>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700">Guardar Alterações</button>
            </div>
        </form>
    </div>
</main>
<?php include_once '../src/templates/footer.php'; ?>
<script>
    const departamentoSelect = document.querySelector('select[name="departamento_id"]');
    const boxSector = document.getElementById('boxSector');
    const sectorInput = document.getElementById('sector');

    function toggleSector() {
        const text =
            departamentoSelect.options[departamentoSelect.selectedIndex]?.text.toLowerCase();

        if (text && (text.includes('piscinas') || text.includes('vigilantes') || text.includes('supervisores'))) {
            boxSector.classList.remove('hidden');
        } else {
            boxSector.classList.add('hidden');
            sectorInput.value = '';
        }
    }

    departamentoSelect.addEventListener('change', toggleSector);
    toggleSector();
</script>

</body>
</html>
