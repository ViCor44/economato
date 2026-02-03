<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/ean_functions.php'; // -> validate_ean13, generate_unique_ean, save_ean_png

$errors = [];
$success = '';
// carregar selects
$cores = $pdo->query("SELECT id, nome FROM cores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$tamanhos = $pdo->query("SELECT id, nome FROM tamanhos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

$old = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    if ($nome === '' && !empty($_POST['nome_select'])) {
        $nome = trim($_POST['nome_select']);
    }
    $cor_id = $_POST['cor_id'] ?? null;
    $tamanho_id = $_POST['tamanho_id'] ?? null;
    $departamentos_sel = $_POST['departamentos'] ?? [];
    $preco_unitario = str_replace(',', '.', $_POST['preco_unitario'] ?? '0');
    $quantidade = (int)($_POST['quantidade'] ?? 0);
    $ean_input = trim($_POST['ean'] ?? '');

    // Validações básicas
    if ($nome === '' || empty($cor_id) || empty($tamanho_id) || empty($departamentos_sel)) {
        $errors[] = "Todos os campos obrigatórios devem ser preenchidos (nome, cor, tamanho e pelo menos 1 departamento).";
    }

    if (!is_numeric($preco_unitario) || (float)$preco_unitario < 0) {
        $errors[] = "Preço unitário inválido.";
    }

    if ($quantidade < 0) $quantidade = 0;

    // Se o utilizador forneceu um EAN, valida e verifica unicidade
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
        // Gerar EAN automático único (prefixo 200 por defeito)
        try {
            $ean_input = generate_unique_ean($pdo, '200');
        } catch (Exception $e) {
            $errors[] = "Erro ao gerar EAN automático: " . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Inserir nova farda
            $stmt = $pdo->prepare("
                INSERT INTO fardas (nome, cor_id, tamanho_id, preco_unitario, quantidade, ean)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $cor_id, $tamanho_id, $preco_unitario, $quantidade, $ean_input]);
            $farda_id = $pdo->lastInsertId();

            // Inserir relação com departamentos
            $stmtDep = $pdo->prepare("INSERT INTO farda_departamentos (farda_id, departamento_id) VALUES (?, ?)");
            foreach ($departamentos_sel as $dep_id) {
                $dep_id = (int)$dep_id;
                if ($dep_id > 0) $stmtDep->execute([$farda_id, $dep_id]);
            }

            // Gerar e guardar imagem PNG do barcode
            $outPath = __DIR__ . '/../public/barcodes';
            if (!is_dir($outPath)) @mkdir($outPath, 0755, true);

            try {
                save_ean_png($ean_input, $outPath); // espera-se que crie $outPath/<ean>.png
            } catch (Exception $e) {
                // Não abortar inserção, mas avisar
                $pdo->commit();
                $errors[] = "Farda criada, mas falha ao gerar imagem do barcode: " . $e->getMessage();
                $success = "✅ Farda adicionada com EAN $ean_input, mas houve um problema ao gerar o PNG do barcode.";
                $old = []; // limpar o form parcialmente
                goto render_form;
            }

            $pdo->commit();
            $success = "✅ Farda adicionada com sucesso! EAN: $ean_input";
            // limpar o POST para evitar reenvio
            $old = [];
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erro ao adicionar farda: " . $e->getMessage();
        }
    } else {
        // manter valores para re-popular o formulário
        $old = $_POST;
        $old['ean'] = $ean_input;
    }
}

// 🔔 Última farda adicionada
$stmtUltima = $pdo->query("
    SELECT 
        f.id,
        f.nome,
        c.nome AS cor,
        t.nome AS tamanho,
        f.ean,
        f.criado_em
    FROM fardas f
    JOIN cores c ON c.id = f.cor_id
    JOIN tamanhos t ON t.id = f.tamanho_id
    ORDER BY f.criado_em DESC
    LIMIT 1
");
$ultimaFarda = $stmtUltima->fetch(PDO::FETCH_ASSOC);

$nomesPecas = $pdo->query("
    SELECT DISTINCT nome
    FROM fardas
    ORDER BY nome ASC
")->fetchAll(PDO::FETCH_COLUMN);

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
        <div class="mb-10 relative overflow-hidden rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-white p-8 shadow-xl hover:shadow-2xl transition mb-12">

            <!-- decoração -->
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-indigo-100 rounded-full opacity-40"></div>

            <div class="relative flex items-center justify-between gap-6">

                <!-- esquerda -->
                <div class="flex items-center gap-5">

                    <!-- ícone -->
                    <div class="bg-gradient-to-br from-indigo-600 to-blue-500 text-white rounded-2xl w-14 h-14 flex items-center justify-center text-2xl shadow-md">
                        👕
                    </div>

                    <div>

                        <p class="text-xs uppercase tracking-wide text-indigo-600 font-semibold">
                            Última Farda Criada
                        </p>

                        <p class="text-sm text-gray-500">
                            <?= date('d/m/Y H:i', strtotime($ultimaFarda['criado_em'])) ?>
                        </p>

                        <h2 class="text-2xl font-bold text-gray-900 mt-1">
                            <?= htmlspecialchars($ultimaFarda['nome']) ?>
                        </h2>

                        <p class="text-gray-600">
                            <?= htmlspecialchars($ultimaFarda['cor']) ?> · <?= htmlspecialchars($ultimaFarda['tamanho']) ?>
                        </p>

                    </div>

                </div>

                <!-- direita -->
                <div class="text-right space-y-3">

                    <div class="inline-flex items-center gap-2 bg-white px-5 py-2 rounded-full border shadow-sm font-mono text-sm">
                        EAN <?= htmlspecialchars($ultimaFarda['ean']) ?>
                    </div>

                    <div class="flex gap-3 justify-end">

                        <a href="editar_farda.php?id=<?= $ultimaFarda['id'] ?>"
                        class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 transition shadow">
                            ✏️ Editar
                        </a>
                        
                    </div>

                </div>

            </div>

        </div>
        <?php endif; ?>

        <h1 class="text-2xl font-bold text-gray-800 mb-6">➕ Nova Farda</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nome do Artigo
                </label>

                <select id="nome_select"
                        class="w-full px-4 py-2 border rounded-md"
                        onchange="toggleNomeInput(this.value)">
                    <option value="">— Selecionar existente —</option>

                    <?php foreach ($nomesPecas as $n): ?>
                        <option value="<?= htmlspecialchars($n) ?>">
                            <?= htmlspecialchars($n) ?>
                        </option>
                    <?php endforeach; ?>

                    <option value="__nova__">➕ Novo artigo…</option>
                </select>

                <input type="text"
                    name="nome"
                    id="nome_input"
                    placeholder="Nome do novo artigo…"
                    class="w-full px-4 py-2 border rounded-md mt-2 hidden">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cor</label>
                    <div class="flex gap-2">
                        <select id="cor_id" name="cor_id" class="w-full px-4 py-2 border rounded-md" required>
                            <option value="">-- Escolha uma cor --</option>
                            <?php foreach ($cores as $cor): ?>
                                <option value="<?= $cor['id'] ?>" <?= isset($old['cor_id']) && $old['cor_id']==$cor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cor['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btnAddCor" style="background:#16a34a; color:white; font-weight:bold; padding:0 12px; border-radius:6px;">+</button>
                    </div>
                    <input type="text" id="novaCorInput" placeholder="Nova cor..." class="w-full px-3 py-2 border rounded-md mt-2 hidden">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho</label>
                    <div class="flex gap-2">
                        <select id="tamanho_id" name="tamanho_id" class="w-full px-4 py-2 border rounded-md" required>
                            <option value="">-- Escolha um tamanho --</option>
                            <?php foreach ($tamanhos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= isset($old['tamanho_id']) && $old['tamanho_id']==$t['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="btnAddTamanho" style="background:#16a34a; color:white; font-weight:bold; padding:0 12px; border-radius:6px;">+</button>
                    </div>
                    <input type="text" id="novoTamanhoInput" placeholder="Novo tamanho..." class="w-full px-3 py-2 border rounded-md mt-2 hidden">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Departamentos</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php foreach ($departamentos as $d): 
                        $checked = in_array($d['id'], $old['departamentos'] ?? []) ? 'checked' : '';
                    ?>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="departamentos[]" value="<?= $d['id'] ?>" class="h-4 w-4 text-blue-600 border-gray-300 rounded" <?= $checked ?>>
                            <span class="text-gray-700 ml-4"><?= htmlspecialchars($d['nome']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Preço Unitário (€)</label>
                    <input type="number" step="0.01" min="0" name="preco_unitario" required
                           class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500"
                           value="<?= htmlspecialchars($old['preco_unitario'] ?? '0.00') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade Inicial</label>
                    <input type="number" name="quantidade" min="0" required
                           class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500"
                           value="<?= (int)($old['quantidade'] ?? 0) ?>">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">EAN (13 dígitos) — opcional</label>
                <div class="flex gap-2">
                    <input type="text" name="ean" pattern="\d{13}" maxlength="13" placeholder="opcional"
                           class="p-2 border flex-1" value="<?= htmlspecialchars($old['ean'] ?? '') ?>">
                    <button type="button" id="btn-gen" class="bg-blue-600 text-white px-4 py-2 rounded">Gerar</button>
                </div>
                <small class="text-gray-600">Se deixares vazio será gerado automaticamente um EAN único.</small>

                <?php
                // preview se já tivermos ean no campo old ou se o PNG existe
                $previewEAN = $old['ean'] ?? '';
                if (!$previewEAN && !empty($success)) {
                    // se há sucesso e $ean_input foi gerado armazenado no sucesso, tenta extrair
                    if (preg_match('/EAN: (\d{13})/', $success, $m)) $previewEAN = $m[1];
                }
                if ($previewEAN) {
                    $pngPath = __DIR__ . '/../public/barcodes/' . $previewEAN . '.png';
                    if (file_exists($pngPath)): ?>
                        <div style="margin-top:8px">
                            <strong>Preview:</strong><br>
                            <img src="<?= BASE_URL ?>/public/barcodes/<?= rawurlencode($previewEAN) ?>.png" alt="EAN" style="height:80px;">
                        </div>
                    <?php endif;
                } ?>
            </div>

            <div class="flex justify-end gap-3">

                <a href="gerir_stock_farda.php"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold px-6 py-2 rounded-lg shadow mr-4">
                    Cancelar
                </a>

                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
                    Guardar Farda
                </button>

            </div>

        </form>
    </main>

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
        // Podemos definir o contexto para personalizar a mensagem inicial
        $aiContext = 'fardas'; // ou 'etiquetas', 'dashboard', etc.
        include __DIR__ . '/../src/templates/assistant_widget.php';
    ?>
<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
