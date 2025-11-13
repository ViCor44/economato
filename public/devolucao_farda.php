<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
if ($colaborador_id <= 0) {
    header('Location: colaboradores.php');
    exit;
}

// Buscar nome do colaborador
$stmt = $pdo->prepare("SELECT nome FROM colaboradores WHERE id = ?");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

$success = '';
$errors = [];

// 🔹 Processar devolução
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farda_id = (int)$_POST['farda_id'];
    $atribuicao_id = (int)$_POST['atribuicao_id'];
    $quantidade_devolvida = (int)$_POST['quantidade_devolvida'];
    $estado = $_POST['estado'] ?? '';

    if ($quantidade_devolvida <= 0) {
        $errors[] = "A quantidade devolvida deve ser maior que 0.";
    }

    if ($estado !== 'boas_condicoes' && $estado !== 'reciclagem') {
        $errors[] = "Selecione o estado da farda devolvida.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Buscar atribuição
            $stmt = $pdo->prepare("SELECT quantidade FROM farda_atribuicoes WHERE id = ?");
            $stmt->execute([$atribuicao_id]);
            $atribuicao = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$atribuicao) {
                throw new Exception("Atribuição não encontrada.");
            }

            if ($quantidade_devolvida > $atribuicao['quantidade']) {
                throw new Exception("A quantidade devolvida não pode ser superior à atribuída.");
            }

            // 1️⃣ Inserir registo de devolução
            $ins = $pdo->prepare("
                INSERT INTO farda_devolucoes (atribuicao_id, colaborador_id, farda_id, quantidade, estado)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$atribuicao_id, $colaborador_id, $farda_id, $quantidade_devolvida, $estado]);

            // 2️⃣ Atualizar stock (se em boas condições)
            if ($estado === 'boas_condicoes') {
                $pdo->prepare("UPDATE fardas SET quantidade = quantidade + ? WHERE id = ?")
                    ->execute([$quantidade_devolvida, $farda_id]);
            }

            // 3️⃣ Atualizar atribuição (diminuir quantidade ou apagar)
            if ($quantidade_devolvida < $atribuicao['quantidade']) {
                $pdo->prepare("UPDATE farda_atribuicoes SET quantidade = quantidade - ? WHERE id = ?")
                    ->execute([$quantidade_devolvida, $atribuicao_id]);
            } else {
                $pdo->prepare("DELETE FROM farda_atribuicoes WHERE id = ?")->execute([$atribuicao_id]);
            }

            $pdo->commit();
            $success = "Devolução registada com sucesso.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao processar devolução: " . $e->getMessage();
        }
    }
}

// 🔹 Buscar fardas atribuídas ao colaborador
$stmt = $pdo->prepare("
    SELECT fa.id AS atribuicao_id, f.id AS farda_id, f.nome, c.nome AS cor, t.nome AS tamanho, fa.quantidade
    FROM farda_atribuicoes fa
    JOIN fardas f ON fa.farda_id = f.id
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE fa.colaborador_id = ?
");
$stmt->execute([$colaborador_id]);
$fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devolução de Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-4xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">♻️ Devolução de Farda</h1>
    <p class="text-gray-600 mb-6">Colaborador: <strong><?= htmlspecialchars($colaborador['nome']) ?></strong></p>

    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($fardas_atribuidas)): ?>
        <p class="text-gray-600">Este colaborador não tem fardas atribuídas.</p>
    <?php else: ?>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Selecionar peça para devolução</label>
                <select name="atribuicao_id" required class="w-full border rounded-md px-4 py-2">
                    <option value="">-- Escolha uma peça --</option>
                    <?php foreach ($fardas_atribuidas as $f): ?>
                        <option value="<?= $f['atribuicao_id'] ?>" data-farda-id="<?= $f['farda_id'] ?>">
                            <?= htmlspecialchars($f['nome']) ?> (<?= htmlspecialchars($f['cor']) ?>, <?= htmlspecialchars($f['tamanho']) ?>) — <?= $f['quantidade'] ?> unid.
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="hidden" name="farda_id" id="farda_id">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade devolvida</label>
                <input type="number" name="quantidade_devolvida" min="1" required class="w-full border rounded-md px-4 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado da peça</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="estado" value="boas_condicoes" required>
                        <span>✅ Boas condições (volta ao stock)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="estado" value="reciclagem" required>
                        <span>♻️ Reciclagem (não volta ao stock)</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <a href="detalhes_colaborador.php?id=<?= $colaborador_id ?>" class="bg-gray-200 px-4 py-2 rounded-md">Cancelar</a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md">Registar Devolução</button>
            </div>
        </form>

        <script>
            // Atualiza campo farda_id automaticamente quando se seleciona atribuição
            document.querySelector('select[name="atribuicao_id"]').addEventListener('change', e => {
                const opt = e.target.selectedOptions[0];
                document.getElementById('farda_id').value = opt ? opt.dataset.fardaId : '';
            });
        </script>
    <?php endif; ?>
</main>

</body>
</html>
