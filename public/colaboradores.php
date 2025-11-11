<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Buscar todos os colaboradores com o nome do departamento
$stmt = $pdo->query("
    SELECT c.id, c.nome, c.cartao, c.ativo, d.nome AS departamento_nome
    FROM colaboradores c
    LEFT JOIN departamentos d ON c.departamento_id = d.id
    ORDER BY c.nome ASC
");
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colaboradores - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-6xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Gestão de Colaboradores</h1>
        <a href="adicionar_colaborador.php"
           class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition-all duration-300 active:scale-95 focus:ring-2 focus:ring-blue-400 focus:ring-offset-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Novo Colaborador
        </a>
    </div>

    <table class="min-w-full border border-gray-200 text-sm">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-4 py-2 border-b text-left">ID</th>
                <th class="px-4 py-2 border-b text-left">Nome</th>
                <th class="px-4 py-2 border-b text-left">Cartão</th>
                <th class="px-4 py-2 border-b text-left">Departamento</th>
                <th class="px-4 py-2 border-b text-left">Estado</th>
                <th class="px-4 py-2 border-b text-left">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($colaboradores as $c): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 border-b"><?= $c['id'] ?></td>
                <td class="px-4 py-2 border-b">
                    <a href="detalhes_colaborador.php?id=<?= $c['id'] ?>"
                       class="text-blue-600 hover:underline font-medium">
                       <?= htmlspecialchars($c['nome']) ?>
                    </a>
                </td>
                <td class="px-4 py-2 border-b"><?= htmlspecialchars($c['cartao']) ?></td>
                <td class="px-4 py-2 border-b"><?= htmlspecialchars($c['departamento_nome'] ?? '—') ?></td>
                <td class="px-4 py-2 border-b">
                    <?php if ($c['ativo']): ?>
                        <span class="text-green-600 font-medium">Ativo</span>
                    <?php else: ?>
                        <span class="text-red-600 font-medium">Inativo</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-2 border-b flex gap-3">
                    <a href="editar_colaborador.php?id=<?= $c['id'] ?>"
                       class="text-blue-600 hover:text-blue-800">✏️ Editar</a>
                    <a href="eliminar_colaborador.php?id=<?= $c['id'] ?>"
                       onclick="return confirm('Tem a certeza que deseja eliminar este colaborador?');"
                       class="text-red-600 hover:text-red-800">🗑️ Eliminar</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

</body>
</html>
