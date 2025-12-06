<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

// 🔍 Validar colaborador
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

// 🔄 Processar devolução
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $atribuicao_id = (int)$_POST['atribuicao_id'];
    $farda_id = (int)$_POST['farda_id'];
    $quantidade_devolvida = (int)$_POST['quantidade_devolvida'];
    $estado = $_POST['estado'] ?? '';

    if ($quantidade_devolvida <= 0) {
        $errors[] = "A quantidade devolvida deve ser maior que 0.";
    }

    if (!in_array($estado, ['boas_condicoes', 'reciclagem'])) {
        $errors[] = "Selecione o estado da peça devolvida.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Buscar quantidade atribuída
            $stmt = $pdo->prepare("SELECT quantidade FROM farda_atribuicoes WHERE id = ?");
            $stmt->execute([$atribuicao_id]);
            $atr = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$atr) {
                throw new Exception("Atribuição não encontrada.");
            }

            if ($quantidade_devolvida > $atr['quantidade']) {
                throw new Exception("A quantidade devolvida não pode ser superior à atribuída.");
            }

            // ➕ Registar devolução
            $stmt = $pdo->prepare("
                INSERT INTO farda_devolucoes (atribuicao_id, colaborador_id, farda_id, quantidade, estado)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$atribuicao_id, $colaborador_id, $farda_id, $quantidade_devolvida, $estado]);

            // 🔄 Atualizar stock se vier em boas condições
            if ($estado === 'boas_condicoes') {
                $pdo->prepare("UPDATE fardas SET quantidade = quantidade + ? WHERE id = ?")
                    ->execute([$quantidade_devolvida, $farda_id]);
            }

            // 🔄 Atualizar a atribuição
            if ($quantidade_devolvida < $atr['quantidade']) {
                $pdo->prepare("
                    UPDATE farda_atribuicoes 
                    SET quantidade = quantidade - ? 
                    WHERE id = ?
                ")->execute([$quantidade_devolvida, $atribuicao_id]);
            } else {
                $pdo->prepare("DELETE FROM farda_atribuicoes WHERE id = ?")
                    ->execute([$atribuicao_id]);
            }

            $pdo->commit();
            $success = "Devolução registada com sucesso!";

            adicionarLog(
                $pdo,
                "Devolução de farda",
                "Colaborador ID $colaborador_id devolveu farda ID $farda_id | Quantidade: $quantidade_devolvida | Estado: $estado"
            );

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao processar devolução: " . $e->getMessage();
        }
    }
}

// 🔍 Buscar peças atribuídas
$stmt = $pdo->prepare("
    SELECT fa.id AS atribuicao_id, f.id AS farda_id, f.nome, 
           c.nome AS cor, t.nome AS tamanho, fa.quantidade
    FROM farda_atribuicoes fa
    JOIN fardas f ON fa.farda_id = f.id
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE fa.colaborador_id = ?
    ORDER BY f.nome ASC
");
$stmt->execute([$colaborador_id]);
$fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Devolução de Farda</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include '../src/templates/header.php'; ?>

<main class="max-w-4xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">

    <h1 class="text-3xl font-bold mb-6 text-gray-800">♻️ Devolução de Farda</h1>
    <p class="text-gray-600 mb-6">
        Colaborador: <strong><?= htmlspecialchars($colaborador['nome']) ?></strong>
    </p>

    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($fardas_atribuidas)): ?>
        <p class="text-gray-600">Este colaborador não tem fardas atribuídas.</p>

    <?php else: ?>

        <div class="space-y-4">
            <?php foreach ($fardas_atribuidas as $f): ?>
                <div class="p-4 bg-gray-50 border rounded-lg flex justify-between items-center">

                    <div>
                        <p class="font-semibold text-gray-800">
                            <?= htmlspecialchars($f['nome']) ?>
                            (<?= htmlspecialchars($f['cor']) ?>, <?= htmlspecialchars($f['tamanho']) ?>)
                        </p>
                        <p class="text-gray-600 text-sm">
                            Quantidade atribuída: <?= $f['quantidade'] ?>
                        </p>
                    </div>

                    <button
                        onclick="abrirDevolucao(
                            <?= $f['atribuicao_id'] ?>,
                            <?= $f['farda_id'] ?>,
                            '<?= htmlspecialchars($f['nome']) ?>',
                            '<?= htmlspecialchars($f['cor']) ?>',
                            '<?= htmlspecialchars($f['tamanho']) ?>',
                            <?= $f['quantidade'] ?>
                        )"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        ♻️ Devolver
                    </button>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</main>


<!-- ==================== MODAL ==================== -->
<div id="modalDevolucao" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">

        <h2 id="tituloDevolucao" class="text-xl font-bold mb-4"></h2>

        <form method="POST">

            <input type="hidden" name="atribuicao_id" id="atribuicao_id">
            <input type="hidden" name="farda_id" id="farda_id">

            <label class="block mb-2 font-medium">Quantidade devolvida</label>
            <input type="number" name="quantidade_devolvida" id="quantidade_devolvida"
                   class="w-full border rounded-md px-3 py-2 mb-4" required min="1">

            <label class="block mb-2 font-medium">Estado da peça</label>
            <select name="estado" id="estado"
                    class="w-full border rounded-md px-3 py-2 mb-4" required>
                <option value="">Selecione...</option>
                <option value="boas_condicoes">Boas condições (volta ao stock)</option>
                <option value="reciclagem">Reciclagem (não volta ao stock)</option>
            </select>

            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="fecharDevolucao()" class="px-4 py-2 bg-gray-200 rounded-md">
                    Cancelar
                </button>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md">
                    Confirmar
                </button>
            </div>

        </form>
    </div>
</div>


<script>
function abrirDevolucao(atribuicaoId, fardaId, nome, cor, tamanho, quantidade) {
    document.getElementById("modalDevolucao").classList.remove("hidden");

    document.getElementById("atribuicao_id").value = atribuicaoId;
    document.getElementById("farda_id").value = fardaId;

    const q = document.getElementById("quantidade_devolvida");
    q.max = quantidade;
    q.value = 1;

    document.getElementById("tituloDevolucao").innerText =
        `Devolver: ${nome} (${cor}, ${tamanho})`;
}

function fecharDevolucao() {
    document.getElementById("modalDevolucao").classList.add("hidden");
}
</script>
<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>
