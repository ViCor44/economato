<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Buscar logs + nome do utilizador (actor)
$stmt = $pdo->query("
    SELECT l.*, u.nome AS nome_utilizador
    FROM logs l
    LEFT JOIN utilizadores u ON u.id = l.user_id
    ORDER BY l.id DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Preparar caches para reduzir queries ---
$cacheUtilizadores = [];   // id => nome
$cacheColaboradores = [];  // id => nome
$cacheFardas = [];         // id => nome

function fetchNomeUtilizador($pdo, &$cache, $id) {
    $id = (int)$id;
    if ($id <= 0) return null;
    if (isset($cache[$id])) return $cache[$id];
    $s = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
    $s->execute([$id]);
    $nome = $s->fetchColumn();
    $cache[$id] = $nome ?: null;
    return $cache[$id];
}

function fetchNomeColaborador($pdo, &$cache, $id) {
    $id = (int)$id;
    if ($id <= 0) return null;
    if (isset($cache[$id])) return $cache[$id];
    $s = $pdo->prepare("SELECT nome FROM colaboradores WHERE id = ?");
    $s->execute([$id]);
    $nome = $s->fetchColumn();
    $cache[$id] = $nome ?: null;
    return $cache[$id];
}

function fetchNomeFarda($pdo, &$cache, $id) {
    $id = (int)$id;
    if ($id <= 0) return null;
    if (isset($cache[$id])) return $cache[$id];
    $s = $pdo->prepare("SELECT nome FROM fardas WHERE id = ?");
    $s->execute([$id]);
    $nome = $s->fetchColumn();
    $cache[$id] = $nome ?: null;
    return $cache[$id];
}

// Processar cada log e substituir padrões "ID" por nomes quando encontradas
foreach ($logs as &$log) {
    $det = $log['detalhes'] ?? '';

    if (!$det) {
        $log['detalhes'] = $det;
        continue;
    }

    // 1) Utilizador ID / user ID / admin ID
    if (preg_match_all('/\b(?:Utilizador|user|admin)\s+ID\s+(\d+)\b/i', $det, $m)) {
        foreach ($m[1] as $id) {
            $nome = fetchNomeUtilizador($pdo, $cacheUtilizadores, $id);
            if ($nome) {
                // Substituir "Utilizador ID X" ou "user ID X" ou "admin ID X" por nome
                $det = preg_replace('/\b(Utilizador|user|admin)\s+ID\s+' . preg_quote($id, '/') . '\b/i', $nome, $det, 1);
            } else {
                // substituir por um texto mais legível se não existir
                $det = preg_replace('/\b(Utilizador|user|admin)\s+ID\s+' . preg_quote($id, '/') . '\b/i', "Utilizador ID {$id} (apagado)", $det, 1);
            }
        }
    }

    // 2) Colaborador ID
    if (preg_match_all('/\bColaborador\s+ID\s+(\d+)\b/i', $det, $m)) {
        foreach ($m[1] as $id) {
            $nome = fetchNomeColaborador($pdo, $cacheColaboradores, $id);
            if ($nome) {
                $det = preg_replace('/\bColaborador\s+ID\s+' . preg_quote($id, '/') . '\b/i', $nome, $det, 1);
            } else {
                $det = preg_replace('/\bColaborador\s+ID\s+' . preg_quote($id, '/') . '\b/i', "Colaborador ID {$id} (apagado)", $det, 1);
            }
        }
    }

    // 3) Farda ID
    if (preg_match_all('/\bFarda\s+ID\s+(\d+)\b/i', $det, $m)) {
        foreach ($m[1] as $id) {
            $nome = fetchNomeFarda($pdo, $cacheFardas, $id);
            if ($nome) {
                $det = preg_replace('/\bFarda\s+ID\s+' . preg_quote($id, '/') . '\b/i', $nome, $det, 1);
            } else {
                $det = preg_replace('/\bFarda\s+ID\s+' . preg_quote($id, '/') . '\b/i', "Farda ID {$id} (apagada)", $det, 1);
            }
        }
    }

    // 4) EAN: se houver "EAN: 12345" podemos formatar (opcional)
    // Exemplo: manter como está ou colocar "EAN: 12345"
    // (não alteramos a menos que queiras uma substituição)

    // Atualiza detalhes
    $log['detalhes'] = $det;
}
unset($log); // evita referência acidental

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Logs do Sistema</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body>
<?php include '../src/templates/header.php'; ?>

<main class="max-w-5xl mx-auto bg-white p-8 rounded-xl shadow mt-8">
    <h1 class="text-3xl font-bold mb-6">📜 Logs do Sistema</h1>

    <table class="min-w-full border">
        <thead>
            <tr class="bg-gray-200 text-left">
                <th class="p-2">Data</th>
                <th class="p-2">Utilizador (actor)</th>
                <th class="p-2">Ação</th>
                <th class="p-2">Detalhes</th>
                <th class="p-2">IP</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr class="border-b">
                <td class="p-2"><?= htmlspecialchars($log['criado_em']) ?></td>
                <td class="p-2"><?= htmlspecialchars($log['nome_utilizador'] ?? 'N/A') ?></td>
                <td class="p-2"><?= htmlspecialchars($log['acao']) ?></td>
                <td class="p-2 text-sm text-gray-600"><?= nl2br(htmlspecialchars($log['detalhes'])) ?></td>
                <td class="p-2"><?= htmlspecialchars($log['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</main>
<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
