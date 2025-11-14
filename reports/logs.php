<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Buscar logs + nome do utilizador
$stmt = $pdo->query("
    SELECT l.*, u.nome AS nome_utilizador
    FROM logs l
    LEFT JOIN utilizadores u ON u.id = l.user_id
    ORDER BY l.id DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Logs do Sistema</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="p-8 bg-gray-100">
<?php include '../src/templates/header.php'; ?>

<main class="max-w-5xl mx-auto bg-white p-8 rounded-xl shadow mt-8">

    <h1 class="text-3xl font-bold mb-6">📜 Logs do Sistema</h1>

    <table class="min-w-full border">
        <thead>
            <tr class="bg-gray-200 text-left">
                <th class="p-2">Data</th>
                <th class="p-2">Utilizador</th>
                <th class="p-2">Ação</th>
                <th class="p-2">Detalhes</th>
                <th class="p-2">IP</th>
            </tr>
        </thead>
        <tbody>

        <?php foreach ($logs as $log): ?>
    <?php
    // Inicializar variáveis
    $nome_eliminado = 'Desconhecido';
    $nome_admin = 'Desconhecido';

    // Extrair IDs
    preg_match('/Utilizador ID (\d+)/', $log['detalhes'], $m1);
    preg_match('/admin ID (\d+)/', $log['detalhes'], $m2);
    $id_eliminado = $m1[1] ?? null;
    $id_admin = $m2[1] ?? null;

    // Buscar nomes (só se ID existir)
    if ($id_eliminado) {
        $stmt = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
        $stmt->execute([$id_eliminado]);
        $result = $stmt->fetchColumn();
        $nome_eliminado = $result !== false ? $result : "ID {$id_eliminado} (não encontrado)";
    }

    if ($id_admin) {
        $stmt = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
        $stmt->execute([$id_admin]);
        $result = $stmt->fetchColumn();
        $nome_admin = $result !== false ? $result : "Admin ID {$id_admin} (não encontrado)";
    }

    // Construir nova frase SEMPRE
    $novo_detalhe = "Utilizador {$nome_eliminado} eliminado pelo administrador {$nome_admin}";

    // Se não houver admin, ajustar frase
    if (!$id_admin) {
        $novo_detalhe = "Utilizador {$nome_eliminado} eliminado";
    }

    // Se não houver utilizador eliminado, ajustar
    if (!$id_eliminado) {
        $novo_detalhe = "Ação por administrador {$nome_admin}";
    }

    // Atualizar detalhes
    $log['detalhes'] = $novo_detalhe;
    ?>
    <tr class="border-b">
        <td class="p-2"><?= htmlspecialchars($log['criado_em']) ?></td>
        <td class="p-2"><?= htmlspecialchars($log['nome_utilizador'] ?? 'N/A') ?></td>
        <td class="p-2"><?= htmlspecialchars($log['acao']) ?></td>
        <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($log['detalhes']) ?></td>
        <td class="p-2"><?= htmlspecialchars($log['ip']) ?></td>
    </tr>
<?php endforeach; ?>

        </tbody>
    </table>

</main>
</body>
</html>
