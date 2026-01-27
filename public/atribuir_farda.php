<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$colaborador_id = $_GET['colaborador_id'] ?? 0;

// 🔍 Obter colaborador e departamento
$stmt = $pdo->prepare("
    SELECT c.id, c.nome, d.id AS departamento_id, d.nome AS departamento_nome
    FROM colaboradores c
    LEFT JOIN departamentos d ON c.departamento_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// 🔍 Buscar fardas compatíveis (com stock visível)
$stmtFardas = $pdo->prepare("
    SELECT DISTINCT 
        f.id,
        f.ean,
        f.nome,
        c.nome AS cor,
        t.nome AS tamanho,
        f.quantidade
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

/* =========================
   PROCESSAR ATRIBUIÇÃO
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $farda_id   = (int)($_POST['farda_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade'] ?? 0);

    if ($farda_id <= 0 || $quantidade <= 0) {
        $errors[] = "Selecione uma farda e uma quantidade válida.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 🔒 Buscar stock com lock
            $stmt = $pdo->prepare("
                SELECT quantidade
                FROM fardas
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$farda_id]);
            $farda = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$farda) {
                throw new Exception("Farda não encontrada.");
            }

            if ($farda['quantidade'] < $quantidade) {
                throw new Exception(
                    "Stock insuficiente. Disponível: {$farda['quantidade']}."
                );
            }

            // 🔽 Atualizar stock
            $stmt = $pdo->prepare("
                UPDATE fardas
                SET quantidade = quantidade - ?
                WHERE id = ?
            ");
            $stmt->execute([$quantidade, $farda_id]);

            // ➕ Criar atribuição
            $stmt = $pdo->prepare("
                INSERT INTO farda_atribuicoes
                (colaborador_id, farda_id, quantidade, estado, data_atribuicao)
                VALUES (?, ?, ?, 'atribuida', NOW())
            ");
            $stmt->execute([$colaborador_id, $farda_id, $quantidade]);

            $pdo->commit();

            $success = "Farda atribuída com sucesso!";

            adicionarLog(
                $pdo,
                "Atribuição de farda",
                "Colaborador ID {$colaborador_id} recebeu farda ID {$farda_id} x{$quantidade}"
            );

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
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

<main class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8 mb-4">
    <h1 class="text-2xl font-bold text-gray-800 mb-4">👕 Atribuir Farda</h1>

    <p class="text-gray-700 mb-6">
        Atribuir farda ao colaborador <strong><?= htmlspecialchars($colaborador['nome']) ?></strong><br>
        <span class="text-sm text-gray-500">
            (Departamento: <?= htmlspecialchars($colaborador['departamento_nome'] ?? '—') ?>)
        </span>
    </p>

    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Peça de Farda
            </label>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    🔍 Procurar por EAN
                </label>
                <input type="text"
                    id="eanSearch"
                    placeholder="Passe o código de barras..."
                    class="w-full px-4 py-2 border rounded-md">
            </div>

            <select name="farda_id" id="fardaSelect" class="w-full px-4 py-2 border rounded-md" required>
                <option value="">-- Selecione uma farda --</option>
                <?php foreach ($fardas as $f): ?>
                    <option value="<?= $f['id'] ?>"
                        data-ean="<?= htmlspecialchars($f['ean']) ?>">
                    <?= htmlspecialchars(
                        "{$f['nome']} ({$f['cor']} - {$f['tamanho']}) — Stock: {$f['quantidade']}"
                    ) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Quantidade
            </label>
            <input type="number" name="quantidade" min="1"
                   class="w-full px-4 py-2 border rounded-md" required>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
                Atribuir
            </button>
        </div>

    </form>
</main>
<script>
const eanInput = document.getElementById('eanSearch');
const select   = document.getElementById('fardaSelect');

eanInput.addEventListener('input', () => {

    const term = eanInput.value.trim();

    let found = false;

    [...select.options].forEach(opt => {

        if (!opt.value) return;

        const ean = opt.dataset.ean;

        if (ean && ean.startsWith(term)) {
            opt.hidden = false;

            if (ean === term) {
                select.value = opt.value;
                found = true;
            }

        } else {
            opt.hidden = true;
        }
    });

    if (!term) {
        [...select.options].forEach(opt => opt.hidden = false);
    }
});
</script>

<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>
