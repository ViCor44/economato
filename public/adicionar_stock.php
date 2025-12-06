<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

$erro = '';
$sucesso = '';

// Carregar lista de fardas existentes
$stmt = $pdo->query("
    SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.ean, f.preco_unitario
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    ORDER BY f.nome ASC
");
$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Receber valores
    $farda_id_post = $_POST['farda_id'] ?? '';
    $ean_post = trim($_POST['ean'] ?? '');
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $preco_compra = $_POST['preco_compra'] !== '' ? (float)$_POST['preco_compra'] : null;

    // Se foi fornecido EAN e não foi selecionada farda, tentar resolver pelo EAN
    $farda_id = null;
    if (!empty($ean_post) && empty($farda_id_post)) {
        $stmt = $pdo->prepare("SELECT id FROM fardas WHERE ean = ? LIMIT 1");
        $stmt->execute([$ean_post]);
        $found = $stmt->fetchColumn();
        if ($found) {
            $farda_id = (int)$found;
        } else {
            $erro = "EAN não encontrado no stock.";
        }
    } else {
        $farda_id = (int)$farda_id_post;
    }

    if (!$erro) {
        if (!$farda_id || $quantidade <= 0) {
            $erro = "Por favor, selecione a farda (ou insira um EAN válido) e indique uma quantidade positiva.";
        } else {
            try {
                $pdo->beginTransaction();

                // Atualiza o stock
                $stmt = $pdo->prepare("UPDATE fardas SET quantidade = quantidade + ? WHERE id = ?");
                $stmt->execute([$quantidade, $farda_id]);

                // Regista o movimento de compra
                $stmt = $pdo->prepare("
                    INSERT INTO farda_compras (farda_id, quantidade_adicionada, preco_compra, criado_por, data_compra)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$farda_id, $quantidade, $preco_compra, $utilizador_logado['id'] ?? null]);

                $pdo->commit();

                adicionarLog(
                    $pdo,
                    "Adicionou stock",
                    "Farda ID $farda_id | Quantidade: $quantidade | Preço compra: " . ($preco_compra !== null ? number_format($preco_compra,2,',','.') : '—') .
                    ($ean_post ? " | EAN: $ean_post" : '')
                );

                $sucesso = "Stock atualizado com sucesso.";
                header('Location: gerir_stock_farda.php?sucesso=1');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $erro = "Erro ao adicionar stock: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Adicionar Stock - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
    <style>
        .ean-hint { font-size: 0.9rem; color: #555; margin-top:6px; }
        .ean-found { color: #0b8a16; font-weight:600; }
        .ean-notfound { color: #c02626; font-weight:600; }
    </style>
</head>
<body class="p-8">
<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-lg mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">📦 Adicionar Stock de Farda</h1>

    <?php if (!empty($erro)): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-md mb-4"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if (!empty($sucesso)): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded-md mb-4"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4" id="formAdicionarStock">
        <div>
            <label for="ean" class="block font-medium text-gray-700">EAN (opcional — escolha automática)</label>
            <div class="flex gap-2">
                <input type="text" id="ean" name="ean" placeholder="EAN (13 dígitos) — opcional"
                       class="w-full border rounded-md p-2" value="<?= isset($_POST['ean']) ? htmlspecialchars($_POST['ean']) : '' ?>">
                <button type="button" id="btnProcurarEAN" class="bg-blue-600 text-white px-3 rounded-md">Procurar</button>
            </div>
            <div id="eanStatus" class="ean-hint"></div>
        </div>

        <div>
            <label for="farda_id" class="block font-medium text-gray-700">Farda</label>
            <select name="farda_id" id="farda_id" required class="w-full border rounded-md p-2">
                <option value="">-- Selecione a farda --</option>
                <?php foreach ($fardas as $f): ?>
                    <option value="<?= $f['id'] ?>" data-ean="<?= htmlspecialchars($f['ean']) ?>" data-preco="<?= htmlspecialchars($f['preco_unitario']) ?>">
                        <?= htmlspecialchars($f['nome'] . " (" . $f['cor'] . " - " . $f['tamanho'] . ")") ?>
                        <?= $f['ean'] ? " — EAN: " . htmlspecialchars($f['ean']) : "" ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="quantidade" class="block font-medium text-gray-700">Quantidade a adicionar</label>
            <input type="number" name="quantidade" id="quantidade" required min="1"
                   class="w-full border rounded-md p-2" value="<?= isset($_POST['quantidade']) ? (int)$_POST['quantidade'] : '' ?>">
        </div>

        <div>
            <label for="preco_compra" class="block font-medium text-gray-700">Preço de compra (opcional, €)</label>
            <input type="number" step="0.01" name="preco_compra" id="preco_compra"
                   class="w-full border rounded-md p-2" value="<?= isset($_POST['preco_compra']) ? htmlspecialchars($_POST['preco_compra']) : '' ?>">
            <div class="text-sm text-gray-500 mt-1">Se o EAN corresponder a uma farda com preço unitário definido, esse preço será preenchido automaticamente.</div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="gerir_stock_farda.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-300">Cancelar</a>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">Guardar</button>
        </div>
    </form>
</main>

<script>
document.getElementById('btnProcurarEAN').addEventListener('click', function(){
    const ean = document.getElementById('ean').value.trim();
    if (!ean) {
        document.getElementById('eanStatus').innerHTML = '<span class="ean-notfound">Introduza um EAN.</span>';
        return;
    }
    searchEan(ean);
});

document.getElementById('ean').addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
        e.preventDefault();
        const ean = this.value.trim();
        if (ean) searchEan(ean);
    }
});

document.getElementById('farda_id').addEventListener('change', function(){
    const opt = this.selectedOptions[0];
    if (opt && opt.dataset.preco) {
        document.getElementById('preco_compra').value = opt.dataset.preco;
    }
});

// função para procurar EAN via endpoint
function searchEan(ean) {
    const status = document.getElementById('eanStatus');
    status.textContent = 'A procurar...';

    fetch('get_farda_by_ean.php?ean=' + encodeURIComponent(ean))
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                status.innerHTML = '<span class="ean-notfound">' + data.error + '</span>';
            } else if (data.id) {
                // selecionar no dropdown
                const sel = document.getElementById('farda_id');
                let option = sel.querySelector('option[value="' + data.id + '"]');
                if (option) {
                    option.selected = true;
                } else {
                    // se não existir opção (cache), adiciona-a
                    option = document.createElement('option');
                    option.value = data.id;
                    option.textContent = data.nome + (data.ean ? ' — EAN: ' + data.ean : '');
                    option.dataset.preco = data.preco_unitario ?? '';
                    sel.appendChild(option);
                    option.selected = true;
                }

                // preencher preço de compra com o preço unitário encontrado (se houver)
                if (data.preco_unitario !== null && data.preco_unitario !== undefined && data.preco_unitario !== '') {
                    document.getElementById('preco_compra').value = data.preco_unitario;
                }

                status.innerHTML = '<span class="ean-found">Encontrado: ' + data.nome + '</span>';
            } else {
                status.innerHTML = '<span class="ean-notfound">EAN não encontrado.</span>';
            }
        })
        .catch(err => {
            status.innerHTML = '<span class="ean-notfound">Erro ao procurar EAN.</span>';
            console.error(err);
        });
}
</script>
<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
