<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$pesquisa = trim($_GET['pesquisa'] ?? '');
$colaboradores = [];

try {
    // 🔍 Buscar colaboradores (por nome OU cartão)
    if (!empty($pesquisa)) {
        $stmt = $pdo->prepare("
            SELECT c.*, d.nome AS departamento_nome
            FROM colaboradores c
            LEFT JOIN departamentos d ON c.departamento_id = d.id
            WHERE c.nome LIKE :pesquisa OR c.cartao LIKE :pesquisa
            ORDER BY c.nome ASC
        ");
        $stmt->execute(['pesquisa' => "%$pesquisa%"]);
    } else {
        $stmt = $pdo->query("
            SELECT c.*, d.nome AS departamento_nome
            FROM colaboradores c
            LEFT JOIN departamentos d ON c.departamento_id = d.id
            ORDER BY c.nome ASC
        ");
    }

    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar colaboradores: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colaboradores - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<main class="p-8 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">👥 Colaboradores</h1>
        <a href="adicionar_colaborador.php" 
           class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-200 active:scale-95">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Adicionar Colaborador
        </a>
    </div>

    <!-- 🔍 Campo de Pesquisa -->
    <form method="GET" class="mb-6 flex items-center gap-2">
        <input type="text" name="pesquisa" placeholder="🔍 Pesquisar por nome ou cartão..."
               value="<?= htmlspecialchars($pesquisa) ?>"
               class="flex-1 px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-md">
            Pesquisar
        </button>
    </form>

    <!-- 🔔 Mensagens de Sucesso / Erro -->
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- 📋 Tabela de Colaboradores -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <table class="min-w-full text-sm text-gray-700">
            <thead class="bg-gray-100">
                <tr>
                    <th class="text-left px-6 py-3 border-b">Nome</th>
                    <th class="text-left px-6 py-3 border-b">Cartão</th>
                    <th class="text-left px-6 py-3 border-b">Telefone</th>
                    <th class="text-left px-6 py-3 border-b">Email</th>
                    <th class="text-left px-6 py-3 border-b">Departamento</th>
                    <th class="text-center px-6 py-3 border-b">Estado</th>
                    <th class="text-right px-6 py-3 border-b">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($colaboradores)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-6 text-gray-500">
                            Nenhum colaborador encontrado.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($colaboradores as $c): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-3 border-b">
                                <a href="detalhes_colaborador.php?id=<?= $c['id'] ?>" 
                                   class="text-blue-600 hover:underline font-medium">
                                    <?= htmlspecialchars($c['nome']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['cartao'] ?: '—') ?></td>
                            <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['telefone'] ?: '—') ?></td>
                            <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['email'] ?: '—') ?></td>
                            <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['departamento_nome'] ?: '—') ?></td>
                            <td class="px-6 py-3 border-b text-center">
                                <?php if ($c['ativo']): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Ativo</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 border-b text-right">
                                <a href="editar_colaborador.php?id=<?= $c['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-medium mr-3">Editar</a>
                                <form action="eliminar_colaborador.php" method="POST" class="inline" 
                                      onsubmit="return confirm('Tem certeza que deseja eliminar este colaborador?');">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>
