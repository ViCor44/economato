<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$colaborador_id = $_GET['colaborador_id'] ?? 0;

// Obter colaborador e departamento
$stmt = $pdo->prepare("
    SELECT c.id, c.nome, d.id AS departamento_id, d.nome AS departamento_nome
    FROM colaboradores c
    LEFT JOIN departamentos d ON c.departamento_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// Buscar fardas compatíveis (associadas ao departamento do colaborador)
$stmtFardas = $pdo->prepare("
    SELECT DISTINCT f.id, f.nome, c.nome AS cor, t.nome AS tamanho
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    JOIN farda_departamentos fd ON fd.farda_id = f.id
    WHERE fd.departamento_id = :dep_id
    ORDER BY f.nome ASC
");
$stmtFardas->execute(['dep_id' => $colaborador['departamento_id']]);
$fardas = $stmtFardas->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farda_id = $_POST['farda_id'] ?? 0;
    $quantidade = (int)($_POST['quantidade'] ?? 0);

    if ($farda_id && $quantidade > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO farda_atribuicoes (colaborador_id, farda_id, quantidade, data_atribuicao)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$colaborador_id, $farda_id, $quantidade]);
            $success = "Farda atribuída com sucesso!";

            adicionarLog(
                $pdo,
                "Atribuição de farda",
                "Colaborador ID $colaborador_id recebeu farda ID $farda_id x$quantidade"
            );    

        } catch (PDOException $e) {
            $errors[] = "Erro ao atribuir farda: " . $e->getMessage();
        }
    } else {
        $errors[] = "Selecione uma farda e uma quantidade válida.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Atribuir Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>

    <main class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">👕 Atribuir Farda</h1>
        <p class="text-gray-700 mb-6">
            Atribuir farda ao colaborador <strong><?= htmlspecialchars($colaborador['nome']) ?></strong><br>
            <span class="text-sm text-gray-500">(Departamento: <?= htmlspecialchars($colaborador['departamento_nome'] ?? '—') ?>)</span>
        </p>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Peça de Farda</label>
                <select name="farda_id" class="w-full px-4 py-2 border rounded-md" required>
                    <option value="">-- Selecione uma farda --</option>
                    <?php foreach ($fardas as $f): ?>
                        <option value="<?= $f['id'] ?>">
                            <?= htmlspecialchars("{$f['nome']} ({$f['cor']} - {$f['tamanho']})") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
                <input type="number" name="quantidade" min="1"
                       class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500" required>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
                    Atribuir
                </button>                
            </div>
        </form>
    </main>
</body>
</html>
