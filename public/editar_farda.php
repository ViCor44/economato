<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/ean_functions.php'; // validate_ean13, generate_unique_ean, save_ean_png

$errors = [];
$success = '';

// carregar selects
$cores = $pdo->query("SELECT id, nome FROM cores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = $pdo->query("SELECT id, nome FROM tamanhos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: gerir_stock_farda.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM fardas WHERE id = ?");
    $stmt->execute([$id]);
    $farda = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$farda) throw new Exception("Farda não encontrada.");

    // departamentos associados
    $stmtDeps = $pdo->prepare("SELECT departamento_id FROM farda_departamentos WHERE farda_id = ?");
    $stmtDeps->execute([$id]);
    $deps_assoc = $stmtDeps->fetchAll(PDO::FETCH_COLUMN, 0);

} catch (Exception $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cor_id = $_POST['cor_id'] ?? null;
    $tamanho_id = $_POST['tamanho_id'] ?? null;
    $departamentos_sel = $_POST['departamentos'] ?? [];
    $preco_unitario = str_replace(',', '.', $_POST['preco_unitario'] ?? '');
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $ean_input = trim($_POST['ean'] ?? '');

    // validações
    if ($nome === '') $errors[] = "O nome da peça é obrigatório.";
    if (empty($cor_id)) $errors[] = "Selecione uma cor.";
    if (empty($tamanho_id)) $errors[] = "Selecione um tamanho.";
    if (empty($departamentos_sel) || !is_array($departamentos_sel)) $errors[] = "Selecione pelo menos um departamento.";
    if ($preco_unitario === '' || !is_numeric($preco_unitario) || (float)$preco_unitario < 0) $errors[] = "Preço unitário inválido.";
    if ($quantidade < 0) $errors[] = "Quantidade inválida.";

    // EAN: se fornecido valida e unicidade (ignorar o próprio registo)
    if ($ean_input !== '') {
        if (!validate_ean13($ean_input)) {
            $errors[] = "EAN inválido — tem de ser 13 dígitos com checksum correcto.";
        } else {
            $s = $pdo->prepare("SELECT id FROM fardas WHERE ean = ? AND id <> ?");
            $s->execute([$ean_input, $id]);
            if ($s->fetch()) $errors[] = "Outra farda já usa esse EAN.";
        }
    } else {
        // gerar EAN único com prefixo 200 (ou outro que prefiras)
        try {
            $ean_input = generate_unique_ean($pdo, '200');
        } catch (Exception $e) {
            $errors[] = "Erro ao gerar EAN automático: " . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // guardar antigo EAN para possível limpeza de ficheiro
            $ean_antigo = $farda['ean'] ?? '';

            // atualizar farda
            $stmt = $pdo->prepare("
                UPDATE fardas
                SET nome = ?, cor_id = ?, tamanho_id = ?, preco_unitario = ?, quantidade = ?, ean = ?
                WHERE id = ?
            ");
            $stmt->execute([$nome, $cor_id, $tamanho_id, (float)$preco_unitario, $quantidade, $ean_input, $id]);

            // sincronizar departamentos: apagar e inserir
            $del = $pdo->prepare("DELETE FROM farda_departamentos WHERE farda_id = ?");
            $del->execute([$id]);
            $ins = $pdo->prepare("INSERT INTO farda_departamentos (farda_id, departamento_id) VALUES (?, ?)");
            foreach ($departamentos_sel as $dep_id) {
                $dep_id = (int)$dep_id;
                if ($dep_id > 0) $ins->execute([$id, $dep_id]);
            }

            // Commit antes de gerar ficheiro (se preferires gerar antes, adapta)
            $pdo->commit();

            // actualizar variavel local farda
            $farda['nome'] = $nome;
            $farda['cor_id'] = $cor_id;
            $farda['tamanho_id'] = $tamanho_id;
            $farda['preco_unitario'] = (float)$preco_unitario;
            $farda['quantidade'] = $quantidade;
            $farda['ean'] = $ean_input;
            $deps_assoc = array_map('intval', $departamentos_sel);

            // Gerar e salvar PNG EAN
            $barcodeDir = __DIR__ . '/../public/barcodes';
            if (!is_dir($barcodeDir)) {
                @mkdir($barcodeDir, 0755, true);
            }
            try {
                save_ean_png($ean_input, $barcodeDir); // deve criar <$ean>.png
                // se EAN mudou, apagar PNG antigo (se existir e diferente)
                if (!empty($ean_antigo) && $ean_antigo !== $ean_input) {
                    $oldFile = $barcodeDir . '/' . $ean_antigo . '.png';
                    if (file_exists($oldFile)) @unlink($oldFile);
                }
            } catch (Exception $ex) {
                // não rollback — a base de dados já foi atualizada; avisar o utilizador
                $errors[] = "Farda actualizada, mas falha ao gerar/guardar PNG do EAN: " . $ex->getMessage();
            }

            if (empty($errors)) {
                $success = "✅ Farda actualizada com sucesso. EAN: {$ean_input}";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Erro ao actualizar farda: " . $e->getMessage();
        }
    }
}

// helper para preencher checkbox checked
function is_dep_checked($id, $deps_assoc) {
    return in_array((int)$id, array_map('intval', $deps_assoc));
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="utf-8">
    <title>Editar Farda - CrewGest</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">✏️ Editar Farda</h1>
        <a href="gerir_stock_farda.php" class="text-blue-600 hover:underline">← Voltar ao stock</a>
    </div>

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

    <form method="POST" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Peça</label>
            <input type="text" name="nome" required class="w-full px-4 py-2 border rounded-md"
                   value="<?= htmlspecialchars($farda['nome'] ?? '') ?>">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                <select name="cor_id" required class="w-full px-4 py-2 border rounded-md">
                    <option value="">-- Escolha uma cor --</option>
                    <?php foreach ($cores as $cor): ?>
                        <option value="<?= $cor['id'] ?>" <?= ((int)$farda['cor_id'] === (int)$cor['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cor['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho</label>
                <select name="tamanho_id" required class="w-full px-4 py-2 border rounded-md">
                    <option value="">-- Escolha um tamanho --</option>
                    <?php foreach ($tamanhos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= ((int)$farda['tamanho_id'] === (int)$t['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Departamentos (associar esta peça)</label>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                <?php foreach ($departamentos as $d): ?>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" name="departamentos[]" value="<?= $d['id'] ?>"
                               class="h-4 w-4" <?= is_dep_checked($d['id'], $deps_assoc) ? 'checked' : '' ?>>
                        <span class="text-gray-700"><?= htmlspecialchars($d['nome']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Preço Unitário (€)</label>
                <input type="number" name="preco_unitario" step="0.01" min="0" required
                       class="w-full px-4 py-2 border rounded-md"
                       value="<?= htmlspecialchars(number_format((float)$farda['preco_unitario'], 2, '.', '')) ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
                <input type="number" name="quantidade" min="0" required
                       class="w-full px-4 py-2 border rounded-md"
                       value="<?= (int)($farda['quantidade'] ?? 0) ?>">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">EAN (13 dígitos)</label>
            <div class="flex gap-2">
                <input type="text" name="ean" pattern="\d{13}" maxlength="13" placeholder="opcional"
                       class="p-2 border flex-1" value="<?= htmlspecialchars($farda['ean'] ?? '') ?>">
                <button type="button" id="btn-gen" class="bg-blue-600 text-white px-4 py-2 rounded">Gerar</button>
            </div>
            <small class="text-gray-600">Se deixares vazio será gerado automaticamente um EAN único.</small>
            <?php
            // mostrar preview do PNG se existir
            $pngPath = __DIR__ . '/../public/barcodes/' . ($farda['ean'] ?? '') . '.png';
            if (!empty($farda['ean']) && file_exists($pngPath)): ?>
                <div style="margin-top:8px">
                    <strong>Preview:</strong><br>
                    <img src="<?= BASE_URL ?>/public/barcodes/<?= rawurlencode($farda['ean']) ?>.png" alt="EAN" style="height:80px;">
                </div>
            <?php endif; ?>
        </div>

        <div class="flex justify-end gap-3">
            <a href="gerir_stock_farda.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md">Cancelar</a>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md">Guardar Alterações</button>
        </div>
    </form>
</main>

<script>
document.getElementById('btn-gen').addEventListener('click', function(){
    fetch('gerar_ean.php')
        .then(r => r.json())
        .then(j => {
            if (j.ean) document.querySelector('input[name=ean]').value = j.ean;
            else alert('Erro ao gerar EAN: ' + (j.error || 'Resposta inválida'));
        }).catch(e => {
            alert('Erro ao contactar o servidor para gerar EAN.');
            console.error(e);
        });
});
</script>
<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
