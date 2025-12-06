<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php'; // <-- INCLUÍDO AQUI, ANTES DE SER USADO

// AGORA A VERIFICAÇÃO JÁ FUNCIONA
if ((int)$utilizador_logado['role_id'] !== ROLE_ADMIN) {
    http_response_code(403);
    echo "<h1>Acesso Negado</h1>";
    exit;
}
// A partir daqui, usamos a lógica que PROVÁMOS que funciona.
try {
    // Ir buscar todos os roles para preencher o dropdown
    $roles_stmt = $pdo->query("SELECT * FROM roles ORDER BY nome_role ASC");
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ir buscar utilizadores pendentes (is_active = 0)
    $stmt_pendentes = $pdo->query("
        SELECT u.id, u.email, u.nome
        FROM utilizadores u        
        WHERE u.is_active = 0 
        ORDER BY u.created_at ASC
    ");
    $utilizadores_pendentes = $stmt_pendentes->fetchAll(PDO::FETCH_ASSOC);

    // Ir buscar utilizadores ativos (is_active = 1)
    $stmt_ativos = $pdo->query("
        SELECT 
            u.id, 
            u.email, 
            u.nome AS nome_utilizador, 
            r.nome_role 
        FROM utilizadores u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.is_active = 1
        ORDER BY u.nome ASC
    ");
    $utilizadores_ativos = $stmt_ativos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erro ao carregar os dados dos utilizadores: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Utilizadores - Slide RH</title>
    <link href="/economato/public/css/style.css" rel="stylesheet">
</head>
<body>

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">

    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                    <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
                </svg>
                Voltar ao Dashboard
            </a>
        </div>
        <h1 class="text-4xl font-bold text-gray-800 mb-8">Gestão de Utilizadores</h1>

        <div class="mb-12">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Registos Pendentes de Aprovação</h2>
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilizador</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Atribuir Função</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($utilizadores_pendentes)): ?>
                            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Nenhum utilizador pendente.</td></tr>
                        <?php else: ?>
                            <?php foreach ($utilizadores_pendentes as $user): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($user['nome_completo'] ?? 'Registo Incompleto') ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                                    </td>
                                    <form action="aprovar_utilizador.php" method="POST">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <td class="px-6 py-4">
                                            <select name="role_id" class="w-full p-2 border border-gray-300 rounded-md" required>
                                                <option value="">-- Selecione uma função --</option>
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['nome_role']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4">
                                            <button type="submit" class="bg-green-500 text-white font-bold py-2 px-4 rounded-lg hover:bg-green-600">Ativar</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Utilizadores Ativos</h2>
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilizador</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Função (Role)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($utilizadores_ativos)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">Nenhum utilizador ativo encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($utilizadores_ativos as $user): ?>
    <tr>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="font-medium text-gray-900"><?= htmlspecialchars($user['nome_utilizador'] ?? 'Sem Nome') ?></div>
            <div class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                <?= htmlspecialchars($user['nome_role'] ?? 'Sem Função') ?>
            </span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <a href="editar_utilizador.php?id=<?= $user['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-4">Editar</a>

            <?php if ((int)$user['id'] !== (int)$utilizador_logado['id']): ?>
                <form action="eliminar_utilizador.php" method="POST" class="inline" onsubmit="return confirm('Tem a certeza que deseja eliminar este utilizador?');">
                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                    <button type="submit" class="text-red-600 hover:text-red-900">Eliminar</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </main>
<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>