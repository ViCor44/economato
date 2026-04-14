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

// Buscar colaborador
$stmt = $pdo->prepare("SELECT id, nome FROM colaboradores WHERE id = ?");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

$success = '';
$errors = [];

// 🔄 PROCESSAR DEVOLUÇÃO (pré-registo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $atribuicao_id   = (int)($_POST['atribuicao_id'] ?? 0);
    $estado_devolucao = $_POST['estado_devolucao'] ?? '';

    if ($atribuicao_id <= 0) {
        $errors[] = "Atribuição inválida.";
    }

    if (!in_array($estado_devolucao, ['stock', 'reciclagem'], true)) {
        $errors[] = "Selecione o estado da farda.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 🔍 Bloquear e obter a atribuição pendente para devolver 1 peça
            $stmt = $pdo->prepare("
                SELECT id, farda_id, quantidade
                FROM farda_atribuicoes
                WHERE id = ?
                  AND colaborador_id = ?
                  AND estado = 'atribuida'
                FOR UPDATE
            ");
            $stmt->execute([$atribuicao_id, $colaborador_id]);
            $atribuicao = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$atribuicao) {
                throw new Exception("A atribuição já foi tratada ou não existe.");
            }

            $quantidadeAtual = (int)$atribuicao['quantidade'];
            if ($quantidadeAtual <= 0) {
                throw new Exception("Quantidade inválida para devolução.");
            }

            if ($quantidadeAtual === 1) {
                // Última peça desta atribuição: marca diretamente a linha atual.
                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET
                        estado = 'marcada_devolucao',
                        estado_devolucao = ?,
                        data_devolucao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$estado_devolucao, $atribuicao_id]);
            } else {
                // Devolução unitária: reduz 1 na atribuição e cria um registo de devolução com qtd 1.
                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET quantidade = quantidade - 1
                    WHERE id = ?
                ");
                $stmt->execute([$atribuicao_id]);

                $stmt = $pdo->prepare("
                    INSERT INTO farda_atribuicoes
                    (colaborador_id, farda_id, quantidade, estado, estado_devolucao, data_atribuicao, data_devolucao)
                    VALUES (?, ?, 1, 'marcada_devolucao', ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $colaborador_id,
                    (int)$atribuicao['farda_id'],
                    $estado_devolucao
                ]);
            }

            $pdo->commit();
            $success = "Devolução unitária registada. Gere o termo para concluir.";

            adicionarLog(
                $pdo,
                "Pré-devolução de farda",
                "Colaborador ID {$colaborador_id} marcou devolução unitária (estado: {$estado_devolucao}, atribuição ID: {$atribuicao_id})"
            );

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao registar devolução: " . $e->getMessage();
        }
    }
}

// 🔍 Buscar fardas atribuídas (ainda abertas)
$stmt = $pdo->prepare("
    SELECT
        fa.id AS atribuicao_id,
        f.nome,
        c.nome AS cor,
        t.nome AS tamanho,
        fa.quantidade,
        fa.estado,
        fa.estado_devolucao
    FROM farda_atribuicoes fa
    JOIN fardas f ON fa.farda_id = f.id
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE fa.colaborador_id = ?
            AND fa.estado IN ('atribuida', 'marcada_devolucao')
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

    <h1 class="text-3xl font-bold mb-2 text-gray-800">♻️ Devolução de Farda</h1>
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
        <p class="text-gray-600">
            Este colaborador não tem fardas atribuídas pendentes.
        </p>
    <?php else: ?>

        <div class="space-y-4">
            <?php foreach ($fardas_atribuidas as $f): ?>
                <div class="p-4 bg-gray-50 border rounded-lg flex justify-between items-center">

                    <div>
                        <p class="font-semibold text-gray-800">
                            <?= htmlspecialchars($f['nome']) ?>
                            (<?= htmlspecialchars($f['cor']) ?>, <?= htmlspecialchars($f['tamanho']) ?>)
                        </p>
                        <p class="text-sm text-gray-600">
                            Quantidade: <?= $f['quantidade'] ?>
                        </p>

                        <?php if ($f['estado'] === 'marcada_devolucao'): ?>
                            <p class="text-sm mt-1 text-green-600 font-medium">
                                ✔ Marcada para devolução
                                (<?= $f['estado_devolucao'] === 'stock' ? 'volta ao stock' : 'reciclagem' ?>)
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($f['estado'] === 'atribuida'): ?>
                        <button
                            onclick="abrirModal(
                                <?= $f['atribuicao_id'] ?>,
                                '<?= htmlspecialchars($f['nome']) ?>',
                                '<?= htmlspecialchars($f['cor']) ?>',
                                '<?= htmlspecialchars($f['tamanho']) ?>'
                            )"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            ♻️ Devolver 1 peça
                        </button>
                    <?php else: ?>
                        <span class="bg-green-100 text-green-700 px-4 py-2 rounded-lg font-medium text-sm">
                            Marcada para termo
                        </span>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <div class="mt-8 border-t pt-6 text-right">

        <?php if (!empty($fardas_atribuidas)): ?>

            <a href="gerar_termo_devolucao.php?colaborador_id=<?= $colaborador_id ?>"           
                style="background-color:#16a34a; color:#fff; font-weight:600;
                    display:inline-flex; align-items:center; gap:8px; padding:8px 16px;
                    border-radius:8px; text-decoration:none;
                    box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                onmouseover="this.style.backgroundColor='#15803d';"
                onmouseout="this.style.backgroundColor='#16a34a';"
                target="_blank">
                📄 <span>Gerar Termo de Devolução</span>
            </a>

        <?php else: ?>

            <span
                style="background-color:#e5e7eb; color:#6b7280; font-weight:600;
                    display:inline-flex; align-items:center; gap:8px; padding:8px 16px;
                    border-radius:8px;
                    box-shadow:0 2px 4px rgba(0,0,0,0.1);
                    cursor:not-allowed;"
                title="Não existem fardas atribuídas para gerar termo">
                📄 <span>Gerar Termo de Devolução</span>
            </span>

            <p class="text-sm text-gray-500 mt-2">
                Não existem fardas atribuídas a este colaborador.
            </p>

        <?php endif; ?>
    </div>
</main>

<!-- ==================== MODAL ==================== -->
<div id="modalDevolucao" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">

        <h2 id="tituloModal" class="text-xl font-bold mb-4"></h2>
        <p class="text-sm text-gray-600 mb-4">Vai ser registada a devolução de 1 peça.</p>

        <form method="POST">
            <input type="hidden" name="atribuicao_id" id="atribuicao_id">

            <label class="block mb-2 font-medium">Estado da farda devolvida</label>
            <select name="estado_devolucao" class="w-full border rounded-md px-3 py-2 mb-4" required>
                <option value="">Selecione...</option>
                <option value="stock">Boas condições (volta ao stock)</option>
                <option value="reciclagem">Reciclagem (não volta ao stock)</option>
            </select>

            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="fecharModal()" class="px-4 py-2 bg-gray-200 rounded-md mr-4">
                    Cancelar
                </button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md">
                    Confirmar devolução
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function abrirModal(atribuicaoId, nome, cor, tamanho) {
    document.getElementById('modalDevolucao').classList.remove('hidden');
    document.getElementById('atribuicao_id').value = atribuicaoId;
    document.getElementById('tituloModal').innerText =
        `Devolver: ${nome} (${cor}, ${tamanho})`;
}

function fecharModal() {
    document.getElementById('modalDevolucao').classList.add('hidden');
}
</script>

<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>
