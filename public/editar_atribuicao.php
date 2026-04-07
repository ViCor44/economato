<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        fa.*,
        f.nome AS farda_nome,
        f.quantidade AS stock_atual,
        c.nome AS colaborador_nome,
        c.departamento_id
    FROM farda_atribuicoes fa
    JOIN fardas f ON f.id = fa.farda_id
    JOIN colaboradores c ON c.id = fa.colaborador_id
    WHERE fa.id = ?
");
$stmt->execute([$id]);
$atribuicao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atribuicao) {
    die("Atribuição não encontrada.");
}

$stmtFardas = $pdo->prepare("
    SELECT DISTINCT
        f.id,
        f.ean,
        f.nome,
        f.quantidade,
        c.nome AS cor,
        t.nome AS tamanho
    FROM fardas f
    JOIN cores c ON c.id = f.cor_id
    JOIN tamanhos t ON t.id = f.tamanho_id
    LEFT JOIN farda_departamentos fd ON fd.farda_id = f.id
    WHERE fd.departamento_id = :dep_id
       OR f.id = :farda_atual
    ORDER BY f.nome ASC, c.nome ASC, t.nome ASC
");
$stmtFardas->execute([
    'dep_id' => (int)$atribuicao['departamento_id'],
    'farda_atual' => (int)$atribuicao['farda_id']
]);
$fardas = $stmtFardas->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

$form_farda_id = (int)$atribuicao['farda_id'];
$form_quantidade = (int)$atribuicao['quantidade'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nova_farda_id = (int)($_POST['farda_id'] ?? 0);
    $nova_qtd = (int)($_POST['quantidade'] ?? 0);

    $form_farda_id = $nova_farda_id;
    $form_quantidade = $nova_qtd;

    $fardaPermitida = false;
    foreach ($fardas as $farda) {
        if ((int)$farda['id'] === $nova_farda_id) {
            $fardaPermitida = true;
            break;
        }
    }

    if (!$fardaPermitida) {
        $errors[] = "Selecione uma peça válida.";
    }

    if ($nova_qtd <= 0) {
        $errors[] = "Quantidade inválida.";
    }

    if (!$errors) {

        try {

            $pdo->beginTransaction();

            $farda_antiga_id = (int)$atribuicao['farda_id'];
            $qtd_antiga = (int)$atribuicao['quantidade'];

            if ($nova_farda_id === $farda_antiga_id) {

                $stmt = $pdo->prepare("
                    SELECT quantidade
                    FROM fardas
                    WHERE id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$farda_antiga_id]);
                $stockAntigo = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$stockAntigo) {
                    throw new Exception("Peça original não encontrada.");
                }

                $dif = $nova_qtd - $qtd_antiga;

                if ($dif > 0 && (int)$stockAntigo['quantidade'] < $dif) {
                    throw new Exception("Stock insuficiente para aumentar a quantidade.");
                }

                $stmt = $pdo->prepare("
                    UPDATE fardas
                    SET quantidade = quantidade - ?
                    WHERE id = ?
                ");
                $stmt->execute([$dif, $farda_antiga_id]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT quantidade
                    FROM fardas
                    WHERE id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$farda_antiga_id]);
                $stockFardaAntiga = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt->execute([$nova_farda_id]);
                $stockFardaNova = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$stockFardaAntiga || !$stockFardaNova) {
                    throw new Exception("Uma das peças selecionadas não existe.");
                }

                if ((int)$stockFardaNova['quantidade'] < $nova_qtd) {
                    throw new Exception("Stock insuficiente na nova peça selecionada.");
                }

                $stmt = $pdo->prepare("
                    UPDATE fardas
                    SET quantidade = quantidade + ?
                    WHERE id = ?
                ");
                $stmt->execute([$qtd_antiga, $farda_antiga_id]);

                $stmt = $pdo->prepare("
                    UPDATE fardas
                    SET quantidade = quantidade - ?
                    WHERE id = ?
                ");
                $stmt->execute([$nova_qtd, $nova_farda_id]);
            }

            // atualizar atribuição
            $stmt = $pdo->prepare("
                UPDATE farda_atribuicoes
                SET farda_id = ?, quantidade = ?
                WHERE id = ?
            ");
            $stmt->execute([$nova_farda_id, $nova_qtd, $id]);

            $pdo->commit();

            $atribuicao['farda_id'] = $nova_farda_id;
            $atribuicao['quantidade'] = $nova_qtd;

            foreach ($fardas as $farda) {
                if ((int)$farda['id'] === $nova_farda_id) {
                    $atribuicao['farda_nome'] = $farda['nome'] . ' - ' . $farda['cor'] . ' - ' . $farda['tamanho'];
                    break;
                }
            }

            adicionarLog(
                $pdo,
                "Editar atribuição",
                "Colaborador ID {$atribuicao['colaborador_id']} | Atribuição ID {$id} alterada | Farda: {$farda_antiga_id} -> {$nova_farda_id} | Quantidade: {$qtd_antiga} -> {$nova_qtd}"
            );

            header("Location: detalhes_colaborador.php?id=".$atribuicao['colaborador_id']);
            exit;

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
<title>Editar Atribuição - CrewGest</title>
<link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8 mb-4">

<div class="flex items-center justify-between mb-4">

<h1 class="text-2xl font-bold text-gray-800">
✏️ Editar Atribuição
</h1>

<a href="<?= BASE_URL ?>/public/detalhes_colaborador.php?id=<?= $atribuicao['colaborador_id'] ?>"
class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">
← Voltar ao colaborador
</a>

</div>

<p class="text-gray-700 mb-6">

Editar atribuição de farda ao colaborador<br>

<strong>Colaborador:</strong> <?= htmlspecialchars($atribuicao['colaborador_nome']) ?> (ID <?= $atribuicao['colaborador_id'] ?>)<br>

<strong>Peça:</strong> <?= htmlspecialchars($atribuicao['farda_nome']) ?>

</p>

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

<div class="mb-3">
<input
type="text"
id="eanSearch"
placeholder="Procurar por EAN..."
class="w-full px-4 py-2 border rounded-md">
</div>

<select
name="farda_id"
id="fardaSelect"
class="w-full px-4 py-2 border rounded-md"
required>

<?php foreach ($fardas as $f): ?>
<option value="<?= (int)$f['id'] ?>"
data-ean="<?= htmlspecialchars((string)$f['ean']) ?>"
<?= ((int)$f['id'] === $form_farda_id) ? 'selected' : '' ?>>
<?= htmlspecialchars("{$f['nome']} ({$f['cor']} - {$f['tamanho']}) — Stock: {$f['quantidade']}") ?>
</option>
<?php endforeach; ?>

</select>

</div>

<div>

<label class="block text-sm font-medium text-gray-700 mb-1">
Quantidade
</label>

<input
type="number"
name="quantidade"
value="<?= $form_quantidade ?>"
min="1"
class="w-full px-4 py-2 border rounded-md"
required>

</div>

<div class="flex justify-end">

<button type="submit"
class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
Guardar Alteração
</button>

</div>

</form>

</main>

<script>
const eanInput = document.getElementById('eanSearch');
const select = document.getElementById('fardaSelect');

if (eanInput && select) {
    eanInput.addEventListener('input', () => {
        const term = eanInput.value.trim();

        [...select.options].forEach(opt => {
            if (!term) {
                opt.hidden = false;
                return;
            }

            const ean = (opt.dataset.ean || '').trim();
            opt.hidden = !ean.startsWith(term);
        });
    });
}
</script>

<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>