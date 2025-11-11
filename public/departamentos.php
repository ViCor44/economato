<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$mensagem = "";

// ADICIONAR NOVO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['novo_departamento'])) {
    $nome = trim($_POST['novo_departamento']);
    if (!empty($nome)) {
        $stmt = $pdo->prepare("INSERT INTO departamentos (nome) VALUES (:nome)");
        $stmt->execute(['nome' => $nome]);
        $mensagem = "Departamento adicionado com sucesso!";
    }
}

// ATUALIZAR EXISTENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_departamento'])) {
    $id = (int)$_POST['id'];
    $nome = trim($_POST['nome']);
    if (!empty($id) && !empty($nome)) {
        $stmt = $pdo->prepare("UPDATE departamentos SET nome = :nome WHERE id = :id");
        $stmt->execute(['nome' => $nome, 'id' => $id]);
        $mensagem = "Departamento atualizado com sucesso!";
    }
}

// ELIMINAR
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM departamentos WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $mensagem = "Departamento eliminado com sucesso!";
}

// LISTAR
$stmt = $pdo->query("SELECT * FROM departamentos ORDER BY id ASC");
$departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Departamentos - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
    <script>
        function enableEdit(id) {
            const nomeInput = document.getElementById('nome-' + id);
            const editarBtn = document.getElementById('editar-' + id);
            const guardarBtn = document.getElementById('guardar-' + id);
            const cancelarBtn = document.getElementById('cancelar-' + id);

            nomeInput.removeAttribute('readonly');
            nomeInput.classList.add('border-blue-500');
            editarBtn.classList.add('hidden');
            guardarBtn.classList.remove('hidden');
            cancelarBtn.classList.remove('hidden');
        }

        function cancelEdit(id) {
            const nomeInput = document.getElementById('nome-' + id);
            const editarBtn = document.getElementById('editar-' + id);
            const guardarBtn = document.getElementById('guardar-' + id);
            const cancelarBtn = document.getElementById('cancelar-' + id);

            nomeInput.value = nomeInput.dataset.original;
            nomeInput.setAttribute('readonly', true);
            nomeInput.classList.remove('border-blue-500');
            editarBtn.classList.remove('hidden');
            guardarBtn.classList.add('hidden');
            cancelarBtn.classList.add('hidden');
        }
    </script>
</head>
<body class="p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">

    <h1 class="text-2xl font-bold text-center mb-4">Gestão de Departamentos</h1>

    <?php if (!empty($mensagem)): ?>
        <p class="text-green-600 text-center mb-4 font-medium"><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>

    <!-- ADICIONAR -->
    <form method="POST" class="flex items-center gap-2 mb-6">
        <input type="text" name="novo_departamento" placeholder="Nome do departamento"
               class="flex-1 border px-4 py-2 rounded-md" required>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg">
            Adicionar
        </button>
    </form>

    <!-- LISTA -->
    <table class="min-w-full border border-gray-200 text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-4 py-2 border-b">ID</th>
                <th class="px-4 py-2 border-b">Nome</th>
                <th class="px-4 py-2 border-b">Criado em</th>
                <th class="px-4 py-2 border-b">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($departamentos as $d): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 border-b text-gray-700"><?= $d['id'] ?></td>
                <td class="px-4 py-2 border-b">
                    <form method="POST" class="flex items-center gap-2">
                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                        <input type="text" name="nome" id="nome-<?= $d['id'] ?>"
                               value="<?= htmlspecialchars($d['nome']) ?>"
                               data-original="<?= htmlspecialchars($d['nome']) ?>"
                               readonly
                               class="border rounded-md px-2 py-1 flex-1 focus:outline-none focus:ring-2 focus:ring-blue-300 transition">
                        
                        <!-- Botões -->
                        <button type="button" id="editar-<?= $d['id'] ?>"
                                class="text-blue-600 hover:text-blue-800"
                                onclick="enableEdit(<?= $d['id'] ?>)">
                            ✏️ Editar
                        </button>

                        <button type="submit" name="editar_departamento"
                                id="guardar-<?= $d['id'] ?>"
                                class="hidden bg-blue-600 text-white px-3 py-1 rounded-md font-medium hover:bg-blue-700">
                            💾 Guardar
                        </button>

                        <button type="button" id="cancelar-<?= $d['id'] ?>"
                                class="hidden text-gray-600 hover:text-red-600"
                                onclick="cancelEdit(<?= $d['id'] ?>)">
                            ✖️ Cancelar
                        </button>
                    </form>
                </td>
                <td class="px-4 py-2 border-b text-gray-600">
                    <?= date('Y-m-d H:i:s', strtotime($d['criado_em'] ?? 'now')) ?>
                </td>
                <td class="px-4 py-2 border-b">
                    <a href="?delete=<?= $d['id'] ?>" onclick="return confirm('Tem a certeza que deseja eliminar este departamento?');"
                       class="text-red-600 hover:text-red-800">
                        🗑️ Eliminar
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</main>

</body>
</html>
