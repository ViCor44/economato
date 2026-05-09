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

// � PROCESSAR MARCAR COMO DÍVIDA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'marcar_divida') {
    $atribuicao_id = (int)($_POST['atribuicao_id'] ?? 0);

    $quantidade_divida = (int)($_POST['quantidade_divida'] ?? 0);

    if ($atribuicao_id <= 0) {
        $errors[] = "Atribuição inválida.";
    } elseif ($quantidade_divida <= 0) {
        $errors[] = "Quantidade inválida.";
    } else {
        try {
            $pdo->beginTransaction();

            // Verificar que a atribuição existe e está em estado 'atribuida'
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
                throw new Exception("A atribuição não existe ou já foi tratada.");
            }

            $quantidadeAtual = (int)$atribuicao['quantidade'];
            if ($quantidade_divida > $quantidadeAtual) {
                throw new Exception("Não pode marcar mais unidades do que as atribuídas.");
            }

            if ($quantidade_divida === $quantidadeAtual) {
                // Marcar TUDO como dívida
                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET
                        marcado_como_divida = 1,
                        data_marcacao_divida = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$atribuicao_id]);
                $msg_acao = "todas as " . $quantidade_divida . " unidade(s)";
            } else {
                // Marcar APENAS algumas como dívida
                // 1. Reduzir quantidade da atribuição original
                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET quantidade = quantidade - ?
                    WHERE id = ?
                ");
                $stmt->execute([$quantidade_divida, $atribuicao_id]);

                // 2. Criar nova atribuição com as unidades em dívida
                $stmt = $pdo->prepare("
                    INSERT INTO farda_atribuicoes
                    (colaborador_id, farda_id, quantidade, estado, marcado_como_divida, data_atribuicao, data_marcacao_divida)
                    VALUES (?, ?, ?, 'atribuida', 1, NOW(), NOW())
                ");
                $stmt->execute([
                    $colaborador_id,
                    (int)$atribuicao['farda_id'],
                    $quantidade_divida
                ]);
                $msg_acao = $quantidade_divida . " de " . $quantidadeAtual . " unidade(s)";
            }

            $pdo->commit();

            $logMsg  = "Colaborador ID {$colaborador_id} marcou {$msg_acao} da atribuição ID {$atribuicao_id} como dívida";
            $success = "Farda marcada como dívida com sucesso.";
            adicionarLog($pdo, "Marcar farda como dívida", $logMsg);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao marcar como dívida: " . $e->getMessage();
        }
    }
}

// 🔄 PROCESSAR DESMARCAR COMO DÍVIDA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'desmarcar_divida') {
    $atribuicao_id = (int)($_POST['atribuicao_id'] ?? 0);

    if ($atribuicao_id <= 0) {
        $errors[] = "Atribuição inválida.";
    } else {
        try {
            $pdo->beginTransaction();

            // Verificar que a atribuição existe e está marcada como dívida
            $stmt = $pdo->prepare("
                SELECT id, farda_id, quantidade
                FROM farda_atribuicoes
                WHERE id = ?
                  AND colaborador_id = ?
                  AND marcado_como_divida = 1
                FOR UPDATE
            ");
            $stmt->execute([$atribuicao_id, $colaborador_id]);
            $atribuicao = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$atribuicao) {
                throw new Exception("A atribuição não existe ou não está marcada como dívida.");
            }

            // Verificar se existe outra atribuição ativa do mesmo farda para reagrupar
            $stmt = $pdo->prepare("
                SELECT id
                FROM farda_atribuicoes
                WHERE farda_id = ?
                  AND colaborador_id = ?
                  AND estado = 'atribuida'
                  AND marcado_como_divida = 0
                  AND id != ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$atribuicao['farda_id'], $colaborador_id, $atribuicao_id]);
            $atribuicao_existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($atribuicao_existente) {
                // Reagrupar: somar a quantidade na atribuição existente e eliminar esta
                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET quantidade = quantidade + ?
                    WHERE id = ?
                ");
                $stmt->execute([$atribuicao['quantidade'], $atribuicao_existente['id']]);

                $stmt = $pdo->prepare("DELETE FROM farda_atribuicoes WHERE id = ?");
                $stmt->execute([$atribuicao_id]);
            } else {
                // Apenas desmarcar como dívida
                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET
                        marcado_como_divida = 0,
                        data_marcacao_divida = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$atribuicao_id]);
            }

            $pdo->commit();

            $logMsg  = "Colaborador ID {$colaborador_id} desmarcou atribuição ID {$atribuicao_id} de dívida" . ($atribuicao_existente ? " (reagrupado)" : "");
            $success = "Marcação de dívida removida com sucesso.";
            adicionarLog($pdo, "Desmarcar farda como dívida", $logMsg);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao desmarcar como dívida: " . $e->getMessage();
        }
    }
}

// 🔄 PROCESSAR DEVOLUÇÃO (pré-registo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['acao']) || ($_POST['acao'] !== 'marcar_divida' && $_POST['acao'] !== 'desmarcar_divida'))) {

    $atribuicao_id    = (int)($_POST['atribuicao_id'] ?? 0);
    $estado_devolucao = $_POST['estado_devolucao'] ?? '';
    $tipo_devolucao   = $_POST['tipo_devolucao'] ?? 'unitario';
    $farda_id_param   = (int)($_POST['farda_id'] ?? 0);

    if (!in_array($tipo_devolucao, ['unitario', 'total_artigo', 'tudo'], true)) {
        $errors[] = "Tipo de devolução inválido.";
    }

    if ($tipo_devolucao === 'unitario' && $atribuicao_id <= 0) {
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
                    // Devolução unitária: reduz 1 na atribuição original
                    $stmt = $pdo->prepare("
                        UPDATE farda_atribuicoes
                        SET quantidade = quantidade - 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$atribuicao_id]);

                    // Verificar se já existe um registo marcada_devolucao para o mesmo artigo e mesmo estado
                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM farda_atribuicoes
                        WHERE farda_id = ?
                          AND colaborador_id = ?
                          AND estado = 'marcada_devolucao'
                          AND estado_devolucao = ?
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $stmt->execute([(int)$atribuicao['farda_id'], $colaborador_id, $estado_devolucao]);
                    $registo_existente = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($registo_existente) {
                        // Reagrupar: incrementar quantidade no registo existente
                        $stmt = $pdo->prepare("
                            UPDATE farda_atribuicoes
                            SET quantidade = quantidade + 1
                            WHERE id = ?
                        ");
                        $stmt->execute([$registo_existente['id']]);
                    } else {
                        // Criar novo registo de devolução
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
                }

                $logMsg  = "Colaborador ID {$colaborador_id} marcou devolução unitária (estado: {$estado_devolucao}, atribuição ID: {$atribuicao_id})";
                $success = "Devolução unitária registada. Gere o termo para concluir.";

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

            } elseif ($tipo_devolucao === 'tudo') {
                // Devolver todas as atribuições ativas do colaborador
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM farda_atribuicoes
                    WHERE colaborador_id = ?
                      AND estado = 'atribuida'
                    FOR UPDATE
                ");
                $stmt->execute([$colaborador_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($rows)) {
                    throw new Exception("Não existem atribuições ativas para devolver.");
                }

                $stmt = $pdo->prepare("
                    UPDATE farda_atribuicoes
                    SET
                        estado = 'marcada_devolucao',
                        estado_devolucao = ?,
                        data_devolucao = NOW()
                    WHERE colaborador_id = ?
                      AND estado = 'atribuida'
                ");
                $stmt->execute([$estado_devolucao, $colaborador_id]);

                $logMsg  = "Colaborador ID {$colaborador_id} marcou devolução de todas as atribuições (estado: {$estado_devolucao})";
                $success = "Todas as fardas foram marcadas para devolução. Gere o termo para concluir.";
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
        fa.estado_devolucao,
        fa.marcado_como_divida,
        fa.data_marcacao_divida
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

// Verifica se ainda há fardas não tratadas (não devolvidas e não marcadas como dívida)
$tem_fardas_nao_tratadas = array_filter($fardas_atribuidas, fn($f) => 
    $f['estado'] === 'atribuida' && !$f['marcado_como_divida']
);
$pode_gerar_termo = !empty($fardas_atribuidas) && empty($tem_fardas_nao_tratadas);
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

    <div class="flex items-center justify-between mb-2">
        <h1 class="text-3xl font-bold text-gray-800">♻️ Devolução de Farda</h1>
        <a href="<?= BASE_URL ?>/public/detalhes_colaborador.php?id=<?= $colaborador_id ?>"
           class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">
            ← Voltar ao colaborador
        </a>
    </div>
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

        <div class="flex justify-end mb-4">
            <button
                onclick="abrirModal('tudo', 0, 0, '', '', '')"
                class="px-5 py-2 rounded-lg font-medium text-sm"
                style="background-color:#ea580c; color:#ffffff; border:1px solid #c2410c;"
                onmouseover="this.style.backgroundColor='#c2410c';"
                onmouseout="this.style.backgroundColor='#ea580c';">
                ♻️ Devolver toda a atribuição
            </button>
        </div>

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

                    <?php if ($f['marcado_como_divida']): ?>
                        <div class="flex items-center gap-2">
                            <span class="bg-yellow-100 text-yellow-700 px-4 py-2 rounded-lg font-medium text-sm">
                                💳 Marcada como dívida
                            </span>
                            <form method="POST" class="inline">
                                <input type="hidden" name="acao" value="desmarcar_divida">
                                <input type="hidden" name="atribuicao_id" value="<?= $f['atribuicao_id'] ?>">
                                <button
                                    type="submit"
                                    class="px-3 py-2 rounded-lg text-sm whitespace-nowrap"
                                    style="background-color:#6b7280; color:#ffffff; border:1px solid #4b5563;"
                                    onmouseover="this.style.backgroundColor='#4b5563';"
                                    onmouseout="this.style.backgroundColor='#6b7280';"
                                    onclick="return confirm('Tem a certeza que quer remover a marcação de dívida?');">
                                    ↩️ Desmarcar de dívida
                                </button>
                            </form>
                        </div>
                    <?php elseif ($f['estado'] === 'atribuida'): ?>
                        <div class="flex flex-row gap-2 items-center flex-shrink-0 ml-4">
                            <button
                                onclick="abrirModal('unitario', <?= $f['atribuicao_id'] ?>, <?= $f['farda_id'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['cor'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['tamanho'], ENT_QUOTES) ?>')"
                                class="px-3 py-2 rounded-lg text-sm whitespace-nowrap"
                                style="background-color:#2563eb; color:#ffffff; border:1px solid #1d4ed8;"
                                onmouseover="this.style.backgroundColor='#1d4ed8';"
                                onmouseout="this.style.backgroundColor='#2563eb';">
                                ♻️ 1 peça
                            </button>
                            <button
                                onclick="abrirModal('total_artigo', <?= $f['atribuicao_id'] ?>, <?= $f['farda_id'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['cor'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['tamanho'], ENT_QUOTES) ?>')"
                                class="px-3 py-2 rounded-lg text-sm whitespace-nowrap"
                                style="background-color:#4f46e5; color:#ffffff; border:1px solid #4338ca;"
                                onmouseover="this.style.backgroundColor='#4338ca';"
                                onmouseout="this.style.backgroundColor='#4f46e5';">
                                ♻️ Todas
                            </button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="acao" value="marcar_divida">
                                <input type="hidden" name="atribuicao_id" value="<?= $f['atribuicao_id'] ?>">
                                <button
                                    type="button"
                                    class="px-3 py-2 rounded-lg text-sm whitespace-nowrap"
                                    style="background-color:#dc2626; color:#ffffff; border:1px solid #b91c1c;"
                                    onmouseover="this.style.backgroundColor='#b91c1c';"
                                    onmouseout="this.style.backgroundColor='#dc2626';"
                                    onclick="abrirModalDivida(<?= $f['atribuicao_id'] ?>, <?= $f['quantidade'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['cor'], ENT_QUOTES) ?>', '<?= htmlspecialchars($f['tamanho'], ENT_QUOTES) ?>')">
                                    💳 Marcar como dívida
                                </button>
                            </form>
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

        <?php if ($pode_gerar_termo): ?>

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
                title="<?= !empty($tem_fardas_nao_tratadas) ? 'Marque todas as fardas para devolução ou como dívida antes de gerar o termo' : 'Não existem fardas atribuídas para gerar termo' ?>">
                📄 <span>Gerar Termo de Devolução</span>
            </span>

            <?php if (!empty($tem_fardas_nao_tratadas)): ?>
                <p class="text-sm text-orange-500 mt-2">
                    ⚠ Existem fardas ainda não marcadas para devolução ou como dívida.
                </p>
            <?php else: ?>
                <p class="text-sm text-gray-500 mt-2">
                    Não existem fardas atribuídas a este colaborador.
                </p>
            <?php endif; ?>

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

<!-- ==================== MODAL DE DÍVIDA ==================== -->
<div id="modalDivida" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">

        <h2 class="text-xl font-bold mb-4">💳 Marcar como Dívida</h2>
        <p id="descricaoDivida" class="text-sm text-gray-600 mb-4"></p>

        <form method="POST">
            <input type="hidden" name="acao" value="marcar_divida">
            <input type="hidden" name="atribuicao_id" id="atribuicao_id_divida">

            <label class="block mb-2 font-medium">Quantidade a marcar como dívida</label>
            <input type="number" name="quantidade_divida" id="quantidade_divida" min="1" max="1" class="w-full border rounded-md px-3 py-2 mb-4" required>

            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="fecharModalDivida()"
                    style="padding:8px 16px;background:#e5e7eb;border-radius:6px;font-weight:600;border:none;cursor:pointer;">
                    Cancelar
                </button>
                <button type="submit"
                    style="padding:8px 24px;background:#dc2626;color:#fff;border-radius:6px;font-weight:600;border:none;cursor:pointer;">
                    Confirmar Dívida
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function abrirModal(tipo, atribuicaoId, fardaId, nome, cor, tamanho) {
    const labels = {
        unitario: 'Devolver 1 peça',
        total_artigo: 'Devolver todas as peças do artigo',
        tudo: 'Devolver toda a atribuição'
    };
    const descricoes = {
        unitario: 'Vai ser registada a devolução de 1 peça.',
        total_artigo: 'Vão ser devolvidas todas as peças deste artigo (todas as atribuições ativas).',
        tudo: 'Vão ser devolvidas TODAS as fardas atribuídas a este colaborador.'
    };
    const titulo = tipo === 'tudo'
        ? labels[tipo]
        : `${labels[tipo]}: ${nome} (${cor}, ${tamanho})`;
    document.getElementById('modalDevolucao').classList.remove('hidden');
    document.getElementById('atribuicao_id').value = atribuicaoId;
    document.getElementById('farda_id').value = fardaId;
    document.getElementById('tipo_devolucao').value = tipo;
    document.getElementById('tituloModal').innerText = titulo;
    document.getElementById('descricaoModal').innerText = descricoes[tipo];
}

function fecharModal() {
    document.getElementById('modalDevolucao').classList.add('hidden');
}

function abrirModalDivida(atribuicaoId, quantidade, nome, cor, tamanho) {
    document.getElementById('modalDivida').classList.remove('hidden');
    document.getElementById('atribuicao_id_divida').value = atribuicaoId;
    document.getElementById('quantidade_divida').max = quantidade;
    document.getElementById('quantidade_divida').value = quantidade;
    document.getElementById('descricaoDivida').innerText = `${nome} (${cor}, ${tamanho}) - Quantidade disponível: ${quantidade}`;
}

function fecharModalDivida() {
    document.getElementById('modalDivida').classList.add('hidden');
}
</script>

<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>
