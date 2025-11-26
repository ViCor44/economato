<?php
// dar_baixa_farda.php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$erro = null;
$sucesso = null;

// Buscar lista de fardas
$stmt = $pdo->query("
    SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.quantidade, IFNULL(f.ean,'') AS ean
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    ORDER BY f.nome ASC
");
$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Opcional: lista de colaboradores (se quiseres permitir escolher um colaborador que devolveu)
$stmt = $pdo->query("SELECT id, nome FROM colaboradores ORDER BY nome ASC");
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $farda_id = (int)($_POST['farda_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    $motivo_extra = trim($_POST['motivo_extra'] ?? '');
    $colaborador_id_post = isset($_POST['colaborador_id']) && $_POST['colaborador_id'] !== '' ? (int)$_POST['colaborador_id'] : null;

    // Se motivo = Outro e foi preenchido texto extra, usa-o
    if (strcasecmp($motivo, 'Outro') === 0 && $motivo_extra !== '') {
        $motivo = $motivo_extra;
    }

    // Validacoes basicas
    if ($farda_id <= 0) {
        $erro = "Selecione um item válido.";
    } elseif ($quantidade <= 0) {
        $erro = "A quantidade deve ser positiva.";
    } elseif (empty($motivo)) {
        $erro = "Indique o motivo da baixa.";
    }

    // buscar item
    if (!$erro) {
        $stmt = $pdo->prepare("SELECT * FROM fardas WHERE id = ?");
        $stmt->execute([$farda_id]);
        $farda = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$farda) {
            $erro = "Item de farda não encontrado.";
        } elseif ($quantidade > (int)$farda['quantidade']) {
            $erro = "Stock insuficiente para dar baixa dessa quantidade.";
        }
    }

    // validar colaborador (se fornecido)
    $colaborador_para_gravar = null;
    if (!$erro && $colaborador_id_post) {
        $s = $pdo->prepare("SELECT id, nome FROM colaboradores WHERE id = ?");
        $s->execute([$colaborador_id_post]);
        $col_info = $s->fetch(PDO::FETCH_ASSOC);
        if ($col_info) {
            $colaborador_para_gravar = $col_info['id'];
        } else {
            // se o id não existir, ignora e grava NULL (evita dados errados)
            $colaborador_para_gravar = null;
        }
    }

    if (!$erro) {
        try {
            $pdo->beginTransaction();

            // Inserir registo de baixa. Garantimos explicitamente o colaborador_id (pode ser NULL)
            $ins = $pdo->prepare("
                INSERT INTO farda_baixas (farda_id, quantidade, motivo, colaborador_id, data_baixa)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                $farda_id,
                $quantidade,
                $motivo,
                $colaborador_para_gravar
            ]);

            // Atualizar stock (diminuir quantidade)
            $upd = $pdo->prepare("UPDATE fardas SET quantidade = quantidade - ? WHERE id = ?");
            $upd->execute([$quantidade, $farda_id]);

            $pdo->commit();

            // Mensagem de sucesso
            $sucesso = "Baixa registada com sucesso!";

            // Registar log (inclui info sobre colaborador se houver)
            $detalhesLog = "Farda ID $farda_id | Quantidade: $quantidade | Motivo: $motivo";
            if ($colaborador_para_gravar) {
                $detalhesLog .= " | Colaborador ID: $colaborador_para_gravar";
            } else {
                $detalhesLog .= " | Colaborador: (nenhum)";
            }

            adicionarLog(
                $pdo,
                "Baixa de stock",
                $detalhesLog
            );

            // Recarregar dados do formulário (opcional) - atualizar array $fardas para refletir novo stock
            $stmt = $pdo->prepare("
                SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.quantidade, IFNULL(f.ean,'') AS ean
                FROM fardas f
                JOIN cores c ON f.cor_id = c.id
                JOIN tamanhos t ON f.tamanho_id = t.id
                WHERE f.id = ?
            ");
            $stmt->execute([$farda_id]);
            $farda_atualizada = $stmt->fetch(PDO::FETCH_ASSOC);
            // actualizar a lista local (fácil approach: recarregar tudo)
            $stmtAll = $pdo->query("
                SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.quantidade, IFNULL(f.ean,'') AS ean
                FROM fardas f
                JOIN cores c ON f.cor_id = c.id
                JOIN tamanhos t ON f.tamanho_id = t.id
                ORDER BY f.nome ASC
            ");
            $fardas = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $pdo->rollBack();
            // detalhe para debug interno — não mostrar o erro cru ao utilizador
            error_log("Erro ao registar baixa: " . $e->getMessage());
            $erro = "Erro ao registar baixa. Tente novamente ou contacte o administrador.";
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
                        <?= $f['ean'] ? " | EAN: {$f['ean']}" : "" ?>
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
            <select name="motivo" required class="w-full border rounded-md p-2" id="motivoSelect">
                <option value="">Selecionar…</option>
                <option value="Danificado">Danificado</option>
                <option value="Manchado">Manchado</option>
                <option value="Perdido">Perdido</option>
                <option value="Roubo">Roubo</option>
                <option value="Desgaste normal">Desgaste normal</option>
                <option value="Outro">Outro (especificar)</option>
            </select>
        </div>

        <div id="motivoExtraWrap" style="display:none;">
            <label class="block font-medium text-gray-700">Descrição adicional (obrigatório se selecionou "Outro")</label>
            <input type="text" name="motivo_extra" class="w-full border rounded-md p-2">
        </div>

        <div>
            <label class="block font-medium text-gray-700">Devolução por colaborador? (opcional)</label>
            <select name="colaborador_id" class="w-full border rounded-md p-2">
                <option value="">— Não —</option>
                <?php foreach ($colaboradores as $col): ?>
                    <option value="<?= $col['id'] ?>"><?= htmlspecialchars($col['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">
                Se a baixa for devida a uma devolução, selecione o colaborador. Caso contrário, deixe em branco.
            </p>
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

<script>
    // Mostrar input adicional se for "Outro"
    document.getElementById('motivoSelect').addEventListener('change', function() {
        var wrap = document.getElementById('motivoExtraWrap');
        if (this.value === 'Outro') wrap.style.display = 'block'; else wrap.style.display = 'none';
    });
</script>

</body>
</html>
