<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$erro = null;
$sucesso = null;

// Buscar lista de fardas
$stmt = $pdo->query("
    SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.quantidade
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    ORDER BY f.nome ASC
");
$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $farda_id = (int)$_POST['farda_id'];
    $quantidade = (int)$_POST['quantidade'];
    $motivo = trim($_POST['motivo']);
    $motivo_extra = trim($_POST['motivo_extra']);

    if ($motivo === "Outro" && $motivo_extra !== "") {
        $motivo = $motivo_extra;
    }

    // Buscar item selecionado
    $stmt = $pdo->prepare("SELECT * FROM fardas WHERE id = ?");
    $stmt->execute([$farda_id]);
    $farda = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$farda) {
        $erro = "Item de farda não encontrado.";
    } elseif ($quantidade <= 0) {
        $erro = "A quantidade deve ser positiva.";
    } elseif ($quantidade > $farda['quantidade']) {
        $erro = "Stock insuficiente para dar baixa dessa quantidade.";
    } elseif ($motivo === "") {
        $erro = "Indique o motivo da baixa.";
    }

    if (!$erro) {
        try {
            $pdo->beginTransaction();

            // Registrar baixa
            $stmt = $pdo->prepare("
                INSERT INTO farda_baixas (farda_id, quantidade, motivo, criado_por)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$farda_id, $quantidade, $motivo, $utilizador_logado['id'] ?? null]);

            // Atualizar stock
            $stmt = $pdo->prepare("UPDATE fardas SET quantidade = quantidade - ? WHERE id = ?");
            $stmt->execute([$quantidade, $farda_id]);            

            $pdo->commit();
            $sucesso = "Baixa registada com sucesso!";
            
            adicionarLog(
                $pdo,
                "Baixa de stock",
                "Farda ID $farda_id | Quantidade: $quantidade | Motivo: $motivo"
            );

        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "Erro ao registar baixa.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Dar Baixa de Farda</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="p-8">
<?php include '../src/templates/header.php'; ?>

<main class="max-w-lg mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">🗑️ Dar Baixa de Farda</h1>

    <?php if (!empty($erro)): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-md mb-4"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if (!empty($sucesso)): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded-md mb-4"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">

        <div>
            <label class="block font-medium text-gray-700">Item</label>
            <select name="farda_id" required class="w-full border rounded-md p-2">
                <option value="">Escolher peça...</option>

                <?php foreach ($fardas as $f): ?>
                    <option value="<?= $f['id'] ?>">
                        <?= htmlspecialchars($f['nome']) ?>
                        (<?= $f['cor'] ?>/<?= $f['tamanho'] ?>)
                        — Stock: <?= $f['quantidade'] ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </div>

        <div>
            <label class="block font-medium text-gray-700">Quantidade</label>
            <input type="number" name="quantidade" min="1"
                   class="w-full border rounded-md p-2" required>
        </div>

        <div>
            <label class="block font-medium text-gray-700">Motivo</label>
            <select name="motivo" required class="w-full border rounded-md p-2">
                <option value="">Selecionar…</option>
                <option value="Danificado">Danificado</option>
                <option value="Manchado">Manchado</option>
                <option value="Perdido">Perdido</option>
                <option value="Roubo">Roubo</option>
                <option value="Desgaste normal">Desgaste normal</option>
                <option value="Outro">Outro (especificar)</option>
            </select>
        </div>

        <div>
            <label class="block font-medium text-gray-700">Descrição adicional (opcional)</label>
            <input type="text" name="motivo_extra" class="w-full border rounded-md p-2">
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="gerir_stock_farda.php"
               class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">
                Cancelar
            </a>

            <button type="submit"
                    class="bg-red-600 text-white px-6 py-2 rounded-md hover:bg-red-700">
                Confirmar Baixa
            </button>
        </div>

    </form>
</main>

</body>
</html>
