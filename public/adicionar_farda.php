<?php
declare(strict_types=1);

require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/ean_functions.php';

$errors = [];
$success = '';
$old = [];

// Carregar dados para os selects
$cores = $pdo->query("SELECT id, nome FROM cores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = $pdo->query("SELECT id, nome FROM tamanhos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // === Sanitização rigorosa ===
    $nome = trim($_POST['nome'] ?? '');
    $nome_select = trim($_POST['nome_select'] ?? '');

    if ($nome === '' && $nome_select !== '') {
        $nome = $nome_select;
    }

    $cor_id = isset($_POST['cor_id']) && is_numeric($_POST['cor_id']) ? (int)$_POST['cor_id'] : null;
    $tamanho_id = isset($_POST['tamanho_id']) && is_numeric($_POST['tamanho_id']) ? (int)$_POST['tamanho_id'] : null;

    $departamentos_sel = [];
    if (!empty($_POST['departamentos']) && is_array($_POST['departamentos'])) {
        $departamentos_sel = array_filter(array_map('intval', $_POST['departamentos']), fn($id) => $id > 0);
    }

    $preco_unitario = str_replace(',', '.', $_POST['preco_unitario'] ?? '0');
    $quantidade = isset($_POST['quantidade']) && is_numeric($_POST['quantidade']) ? (int)$_POST['quantidade'] : 0;
    if ($quantidade < 0) $quantidade = 0;

    $ean_input = trim($_POST['ean'] ?? '');

    // === Validações ===
    if ($nome === '' || $cor_id === null || $tamanho_id === null || empty($departamentos_sel)) {
        $errors[] = "Todos os campos obrigatórios devem ser preenchidos (nome, cor, tamanho e pelo menos 1 departamento).";
    }

    if (!is_numeric($preco_unitario) || (float)$preco_unitario < 0) {
        $errors[] = "Preço unitário inválido.";
    }

    if ($ean_input !== '') {
        if (!validate_ean13($ean_input)) {
            $errors[] = "EAN inválido — tem de ser 13 dígitos com checksum correcto.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM fardas WHERE ean = ?");
            $stmt->execute([$ean_input]);
            if ($stmt->fetch()) {
                $errors[] = "Já existe uma farda com esse EAN.";
            }
        }
    } else {
        try {
            $ean_input = generate_unique_ean($pdo, '200');
        } catch (Exception $e) {
            $errors[] = "Erro ao gerar EAN automático: " . $e->getMessage();
            $ean_input = '';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO fardas (nome, cor_id, tamanho_id, preco_unitario, quantidade, ean)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $cor_id, $tamanho_id, $preco_unitario, $quantidade, $ean_input]);
            $farda_id = $pdo->lastInsertId();

            $stmtDep = $pdo->prepare("INSERT INTO farda_departamentos (farda_id, departamento_id) VALUES (?, ?)");
            foreach ($departamentos_sel as $dep_id) {
                $stmtDep->execute([$farda_id, $dep_id]);
            }

            // Gerar barcode
            $outPath = __DIR__ . '/../public/barcodes';
            if (!is_dir($outPath)) {
                mkdir($outPath, 0755, true);
            }

            try {
                save_ean_png($ean_input, $outPath);
            } catch (Exception $e) {
                $pdo->commit();
                $success = "✅ Farda adicionada com EAN $ean_input (barcode falhou)";
                $errors[] = "Barcode não foi gerado: " . $e->getMessage();
                goto render_form;
            }

            $pdo->commit();
            $success = "✅ Farda adicionada com sucesso! EAN: $ean_input";
            $old = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao adicionar farda: " . $e->getMessage();
        }
    } else {
        // Repopular formulário
        $old = $_POST;
        $old['ean'] = $ean_input;
        $old['departamentos'] = $departamentos_sel;
    }
}

// Última farda criada
$ultimaFarda = $pdo->query("
    SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.ean, f.criado_em
    FROM fardas f
    JOIN cores c ON c.id = f.cor_id
    JOIN tamanhos t ON t.id = f.tamanho_id
    ORDER BY f.criado_em DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$nomesPecas = $pdo->query("SELECT DISTINCT nome FROM fardas ORDER BY nome ASC")
    ->fetchAll(PDO::FETCH_COLUMN);

render_form:
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>

    <main class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md p-8 mt-8 mb-8">

        <?php if (!empty($ultimaFarda)): ?>
        <!-- Card da última farda (mantido igual) -->
        <div class="mb-10 relative overflow-hidden rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-white p-8 shadow-xl">
            <!-- ... (código do card da última farda mantido exatamente igual ao original) ... -->
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-indigo-100 rounded-full opacity-40"></div>
            <div class="relative flex items-center justify-between gap-6">
                <div class="flex items-center gap-5">
                    <div class="bg-gradient-to-br from-indigo-600 to-blue-500 text-white rounded-2xl w-14 h-14 flex items-center justify-center text-2xl shadow-md">👕</div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-indigo-600 font-semibold">Última Farda Criada</p>
                        <p class="text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($ultimaFarda['criado_em'])) ?></p>
                        <h2 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars($ultimaFarda['nome']) ?></h2>
                        <p class="text-gray-600"><?= htmlspecialchars($ultimaFarda['cor']) ?> · <?= htmlspecialchars($ultimaFarda['tamanho']) ?></p>
                    </div>
                </div>
                <div class="text-right space-y-3">
                    <div class="inline-flex items-center gap-2 bg-white px-5 py-2 rounded-full border shadow-sm font-mono text-sm">
                        EAN <?= htmlspecialchars($ultimaFarda['ean']) ?>
                    </div>
                    <a href="editar_farda.php?id=<?= $ultimaFarda['id'] ?>" class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 transition shadow">
                        ✏️ Editar
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <h1 class="text-2xl font-bold text-gray-800 mb-6">➕ Nova Farda</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
                <ul class="list-disc pl-5 space-y-1">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">

            <!-- Nome do Artigo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Artigo</label>
                <select id="nome_select" name="nome_select" class="w-full px-4 py-2 border rounded-md" onchange="toggleNomeInput(this.value)">
                    <option value="">— Selecionar existente —</option>
                    <?php foreach ($nomesPecas as $n): ?>
                        <option value="<?= htmlspecialchars($n) ?>" <?= ($old['nome_select'] ?? '') === $n ? 'selected' : '' ?>>
                            <?= htmlspecialchars($n) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__nova__">➕ Novo artigo…</option>
                </select>

                <input type="text" name="nome" id="nome_input" placeholder="Nome do novo artigo…" 
                       class="w-full px-4 py-2 border rounded-md mt-2 <?= ($old['nome'] ?? '') && !in_array($old['nome'] ?? '', $nomesPecas) ? '' : 'hidden' ?>"
                       value="<?= htmlspecialchars($old['nome'] ?? '') ?>">
            </div>

            <!-- Cor + Tamanho -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Cor -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                    <div class="flex gap-2">
                        <select name="cor_id" id="cor_id" class="w-full px-4 py-2 border rounded-md" required>
                            <option value="">-- Escolha uma cor --</option>
                            <?php foreach ($cores as $cor): ?>
                                <option value="<?= $cor['id'] ?>" <?= $cor_id === $cor['id'] || ($old['cor_id'] ?? null) == $cor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cor['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btnAddCor" class="bg-green-600 text-white font-bold px-4 rounded">+</button>
                    </div>
                    <input type="text" id="novaCorInput" placeholder="Nova cor..." class="w-full px-3 py-2 border rounded-md mt-2 hidden">
                </div>

                <!-- Tamanho -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho</label>
                    <div class="flex gap-2">
                        <select name="tamanho_id" id="tamanho_id" class="w-full px-4 py-2 border rounded-md" required>
                            <option value="">-- Escolha um tamanho --</option>
                            <?php foreach ($tamanhos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $tamanho_id === $t['id'] || ($old['tamanho_id'] ?? null) == $t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btnAddTamanho" class="bg-green-600 text-white font-bold px-4 rounded">+</button>
                    </div>
                    <input type="text" id="novoTamanhoInput" placeholder="Novo tamanho..." class="w-full px-3 py-2 border rounded-md mt-2 hidden">
                </div>
            </div>

            <!-- Departamentos -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Departamentos</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <?php 
                    $old_depts = $old['departamentos'] ?? [];
                    foreach ($departamentos as $d): 
                        $checked = in_array($d['id'], $old_depts) ? 'checked' : '';
                    ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="departamentos[]" value="<?= $d['id'] ?>" <?= $checked ?> class="h-4 w-4 text-blue-600">
                        <span><?= htmlspecialchars($d['nome']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Preço + Quantidade -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Preço Unitário (€)</label>
                    <input type="number" step="0.01" min="0" name="preco_unitario" required
                           class="w-full px-4 py-2 border rounded-md"
                           value="<?= htmlspecialchars($old['preco_unitario'] ?? '0.00') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade Inicial</label>
                    <input type="number" name="quantidade" min="0" required
                           class="w-full px-4 py-2 border rounded-md"
                           value="<?= htmlspecialchars($old['quantidade'] ?? '0') ?>">
                </div>
            </div>

            <!-- EAN -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">EAN (13 dígitos) — opcional</label>
                <div class="flex gap-2">
                    <input type="text" name="ean" id="ean" maxlength="13" placeholder="Deixe vazio para gerar automaticamente"
                           class="p-2 border flex-1 rounded-md" value="<?= htmlspecialchars($old['ean'] ?? '') ?>">
                    <button type="button" id="btn-gen" class="bg-blue-600 text-white px-5 py-2 rounded-md font-medium">Gerar</button>
                </div>
                <small class="text-gray-600">Se deixares vazio será gerado automaticamente um EAN único.</small>

                <?php
                $previewEAN = $old['ean'] ?? '';
                if (!$previewEAN && !empty($success) && preg_match('/EAN[: ]*(\d{13})/', $success, $m)) {
                    $previewEAN = $m[1];
                }
                if ($previewEAN && file_exists(__DIR__ . '/../public/barcodes/' . $previewEAN . '.png')): ?>
                    <div class="mt-3">
                        <strong>Preview Barcode:</strong><br>
                        <img src="<?= BASE_URL ?>/public/barcodes/<?= rawurlencode($previewEAN) ?>.png" 
                             alt="Barcode" class="mt-2 border bg-white p-2" style="height: 85px;">
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex justify-end gap-4 pt-4">
                <a href="gerir_stock_farda.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 rounded-lg font-semibold">Cancelar</a>
                <button type="submit" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow">
                    Guardar Farda
                </button>
            </div>
        </form>
    </main>

    <!-- JavaScript (mantido igual, apenas com pequenas melhorias opcionais) -->
    <script>
    // Função que envia um POST para criar nova cor/tamanho e adiciona ao select
    function adicionarOpcao(url, inputId, selectId) {
        const valor = document.getElementById(inputId).value.trim();
        if (valor === "") return;
        const btn = document.querySelector(`#${inputId}`).closest('div').querySelector('button') || null;
        if (btn) btn.disabled = true;
        fetch(url, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "nome=" + encodeURIComponent(valor)
        })
        .then(async r => {
            if (!r.ok) {
                const text = await r.text();
                throw new Error(text || 'Erro no servidor ao criar opção');
            }
            return r.json();
        })
        .then(data => {
            if (data.erro) { alert(data.erro); return; }
            const sel = document.getElementById(selectId);
            const opt = document.createElement("option");
            opt.value = data.id;
            opt.textContent = data.nome;
            sel.appendChild(opt);
            sel.value = data.id;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            document.getElementById(inputId).value = "";
            document.getElementById(inputId).classList.add("hidden");
        })
        .catch(err => {
            console.error(err);
            alert("Erro ao criar opção: " + (err.message || err));
        })
        .finally(() => { if (btn) btn.disabled = false; });
    }

    // handlers para adicionar cor/tamanho
    document.getElementById("btnAddCor").onclick = () => {
        document.getElementById("novaCorInput").classList.toggle("hidden");
        document.getElementById("novaCorInput").focus();
    };
    document.getElementById("novaCorInput").addEventListener("keydown", e => {
        if (e.key === "Enter") { e.preventDefault(); adicionarOpcao("nova_cor.php", "novaCorInput", "cor_id"); }
    });

    document.getElementById("btnAddTamanho").onclick = () => {
        document.getElementById("novoTamanhoInput").classList.toggle("hidden");
        document.getElementById("novoTamanhoInput").focus();
    };
    document.getElementById("novoTamanhoInput").addEventListener("keydown", e => {
        if (e.key === "Enter") { e.preventDefault(); adicionarOpcao("novo_tamanho.php", "novoTamanhoInput", "tamanho_id"); }
    });

    // Gerar EAN via endpoint gerar_ean.php
    document.getElementById('btn-gen').addEventListener('click', function(){
        fetch('gerar_ean.php')
          .then(r => r.json())
          .then(j => {
             if (j.ean) {
                 document.querySelector('input[name=ean]').value = j.ean;
                 // mostrar preview (reload pequeno): tenta carregar a imagem caso já exista
                 const previewUrl = '<?= BASE_URL ?>/public/barcodes/' + j.ean + '.png';
                 // se a tag de preview existir, substitui; se não, cria (simples)
                 let img = document.querySelector('#ean-preview-img');
                 if (!img) {
                     const div = document.createElement('div');
                     div.style.marginTop = '8px';
                     div.innerHTML = '<strong>Preview:</strong><br>';
                     img = document.createElement('img');
                     img.id = 'ean-preview-img';
                     img.style.height = '80px';
                     div.appendChild(img);
                     document.querySelector('input[name=ean]').closest('div').appendChild(div);
                 }
                 img.src = previewUrl + '?_=' + Date.now(); // cache-bust
             } else alert('Erro ao gerar EAN: ' + (j.error||'Resposta inválida'));
          }).catch(e => {
              alert('Erro ao contactar o servidor para gerar EAN.');
              console.error(e);
          });
    });
    function toggleNomeInput(valor) {
        const input = document.getElementById('nome_input');

        if (valor === '__nova__') {
            input.classList.remove('hidden');
            input.focus();
            input.required = true;
            input.value = '';
        } else if (valor) {
            input.classList.add('hidden');
            input.required = false;
            input.value = valor;
        } else {
            input.classList.add('hidden');
            input.required = false;
            input.value = '';
        }
    }
    </script>

    <?php
    $aiContext = 'fardas';
    include __DIR__ . '/../src/templates/assistant_widget.php';
    ?>

    <?php include_once '../src/templates/footer.php'; ?>
</body>
</html>