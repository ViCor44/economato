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

    $atribuicao_id    = (int)($_POST['atribuicao_id'] ?? 0);
    $estado_devolucao = $_POST['estado_devolucao'] ?? '';
    $tipo_devolucao   = $_POST['tipo_devolucao'] ?? 'unitario';
    $farda_id_param   = (int)($_POST['farda_id'] ?? 0);

    if (!in_array($tipo_devolucao, ['unitario', 'total_atribuicao', 'total_artigo'], true)) {
        $errors[] = "Tipo de devolução inválido.";
    }

    if ($tipo_devolucao !== 'total_artigo' && $atribuicao_id <= 0) {
        $errors[] = "Atribuição inválida.";
    }

    if ($tipo_devolucao === 'total_artigo' && $farda_id_param <= 0) {
        $errors[] = "Artigo inválido.";
    }

    if (!in_array($estado_devolucao, ['stock', 'reciclagem'], true)) {
        $errors[] = "Selecione o estado da farda.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($tipo_devolucao === 'unitario') {
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

                $logMsg  = "Colaborador ID {$colaborador_id} marcou devolução unitária (estado: {$estado_devolucao}, atribuição ID: {$atribuicao_id})";
                $success = "Devolução unitária registada. Gere o termo para concluir.";

            } elseif ($tipo_devolucao === 'total_atribuicao') {
                // Devolver toda a quantidade desta atribuição de uma vez
                $stmt = $pdo->prepare("
                    SELECT id, quantidade
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

                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET
                        estado = 'marcada_devolucao',
                        estado_devolucao = ?,
                        data_devolucao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$estado_devolucao, $atribuicao_id]);

                $logMsg  = "Colaborador ID {$colaborador_id} marcou devolução total da atribuição ID {$atribuicao_id} (estado: {$estado_devolucao})";
                $success = "Toda a atribuição foi marcada para devolução. Gere o termo para concluir.";

            } elseif ($tipo_devolucao === 'total_artigo') {
                // Devolver todas as atribuições ativas deste artigo
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM farda_atribuicoes
                    WHERE farda_id = ?
                      AND colaborador_id = ?
                      AND estado = 'atribuida'
                    FOR UPDATE
                ");
                $stmt->execute([$farda_id_param, $colaborador_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($rows)) {
                    throw new Exception("Não existem atribuições ativas para este artigo.");
                }

                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET
                        estado = 'marcada_devolucao',
                        estado_devolucao = ?,
                        data_devolucao = NOW()
                    WHERE farda_id = ?
                      AND colaborador_id = ?
                      AND estado = 'atribuida'
                ");
                $stmt->execute([$estado_devolucao, $farda_id_param, $colaborador_id]);

                $logMsg  = "Colaborador ID {$colaborador_id} marcou devolução de todo o artigo (farda ID: {$farda_id_param}, estado: {$estado_devolucao})";
                $success = "Todas as peças do artigo foram marcadas para devolução. Gere o termo para concluir.";
            }

            $pdo->commit();
            adicionarLog($pdo, "Pré-devolução de farda", $logMsg);

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
        fa.farda_id,
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
                        <div class="flex flex-col gap-2 items-end">
                            <button
                                onclick="abrirModal('unitario', <?= $f['atribuicao_id'] ?>, <?= $f['farda_id'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['cor'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['tamanho'], ENT_QUOTES) ?>')"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm whitespace-nowrap">
                                ♻️ 1 peça
                            </button>
                            <button
                                onclick="abrirModal('total_atribuicao', <?= $f['atribuicao_id'] ?>, <?= $f['farda_id'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['cor'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['tamanho'], ENT_QUOTES) ?>')"
                                class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 text-sm whitespace-nowrap">
                                ♻️ Toda a atribuição
                            </button>
                            <button
                                onclick="abrirModal('total_artigo', <?= $f['atribuicao_id'] ?>, <?= $f['farda_id'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['cor'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['tamanho'], ENT_QUOTES) ?>')"
                                class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 text-sm whitespace-nowrap">
                                ♻️ Todo o artigo
                            </button>
                        </div>
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
        <p id="descricaoModal" class="text-sm text-gray-600 mb-4"></p>

        <form method="POST">
            <input type="hidden" name="atribuicao_id" id="atribuicao_id">
            <input type="hidden" name="farda_id" id="farda_id">
            <input type="hidden" name="tipo_devolucao" id="tipo_devolucao" value="unitario">

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
function abrirModal(tipo, atribuicaoId, fardaId, nome, cor, tamanho) {
    const labels = {
        unitario: 'Devolver 1 peça',
        total_atribuicao: 'Devolver toda a atribuição',
        total_artigo: 'Devolver todas as peças do artigo'
    };
    const descricoes = {
        unitario: 'Vai ser registada a devolução de 1 peça.',
        total_atribuicao: 'Vai ser devolvida toda a quantidade desta atribuição.',
        total_artigo: 'Vão ser devolvidas todas as peças deste artigo (todas as atribuições ativas).'
    };
    document.getElementById('modalDevolucao').classList.remove('hidden');
    document.getElementById('atribuicao_id').value = atribuicaoId;
    document.getElementById('farda_id').value = fardaId;
    document.getElementById('tipo_devolucao').value = tipo;
    document.getElementById('tituloModal').innerText = `${labels[tipo]}: ${nome} (${cor}, ${tamanho})`;
    document.getElementById('descricaoModal').innerText = descricoes[tipo];
}

function fecharModal() {
    document.getElementById('modalDevolucao').classList.add('hidden');
}
</script>

<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>
