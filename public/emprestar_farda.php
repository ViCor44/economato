<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;

if ($colaborador_id <= 0) {
    header("Location: colaboradores.php");
    exit;
}

// Buscar dados do colaborador
$stmt = $pdo->prepare("
    SELECT c.id, c.nome, c.departamento_id, d.nome AS departamento_nome
    FROM colaboradores c
    LEFT JOIN departamentos d ON c.departamento_id = d.id
    WHERE c.id = :id
");
$stmt->execute(['id' => $colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// Processar o empréstimo
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farda_id = (int)$_POST['farda_id'];
    $quantidade = (int)$_POST['quantidade'];
    $user_id = $utilizador_logado['id'];

    try {
        // Verificar stock
        $stmtStock = $pdo->prepare("SELECT quantidade FROM fardas WHERE id = ?");
        $stmtStock->execute([$farda_id]);
        $stockAtual = $stmtStock->fetchColumn();

        if ($stockAtual === false) {
            throw new Exception("Item de farda não encontrado.");
        }
        if ($stockAtual < $quantidade) {
            throw new Exception("Quantidade solicitada excede o stock disponível ($stockAtual).");
        }

        // Inserir empréstimo
        $stmt = $pdo->prepare("
            INSERT INTO farda_emprestimos (colaborador_id, farda_id, quantidade, criado_por)
            VALUES (:colab, :farda, :qtd, :user)
        ");
        $stmt->execute([
            'colab' => $colaborador_id,
            'farda' => $farda_id,
            'qtd' => $quantidade,
            'user' => $user_id
        ]);

        // Atualizar stock
        $updateStock = $pdo->prepare("UPDATE fardas SET quantidade = quantidade - :qtd WHERE id = :id");
        $updateStock->execute(['qtd' => $quantidade, 'id' => $farda_id]);

        $mensagem = "✅ Empréstimo registado com sucesso!";
    } catch (Exception $e) {
        $mensagem = "❌ Erro: " . $e->getMessage();
    }
}

// Buscar fardas do mesmo departamento
$stmtFardas = $pdo->prepare("
    SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.quantidade
    FROM fardas f
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    JOIN farda_departamentos fd ON f.id = fd.farda_id
    WHERE fd.departamento_id = :dep_id
    AND f.quantidade > 0
    ORDER BY f.nome ASC
");
$stmtFardas->execute(['dep_id' => $colaborador['departamento_id']]);
$fardas = $stmtFardas->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Emprestar Farda - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <?php include '../src/templates/header.php'; ?>

    <main class="max-w-4xl mx-auto bg-white p-8 rounded-2xl shadow-md mt-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">🧥 Emprestar Farda</h1>
            <a href="detalhes_colaborador.php?id=<?= $colaborador['id'] ?>" 
                style="color:#2563eb; text-decoration:none; font-weight:600;">← Voltar</a>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 border-l-4 <?= strpos($mensagem, '✅') !== false ? 'border-green-600 bg-green-100 text-green-800' : 'border-red-600 bg-red-100 text-red-800' ?> rounded-md">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <p class="mb-6 text-gray-700">
            Emprestar farda para <strong><?= htmlspecialchars($colaborador['nome']) ?></strong> 
            (<em><?= htmlspecialchars($colaborador['departamento_nome']) ?></em>)
        </p>

        <?php if (empty($fardas)): ?>
            <p class="text-gray-600 italic">Nenhuma farda disponível para este departamento.</p>
        <?php else: ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block font-medium text-gray-700 mb-1">Peça de Farda</label>
                    <select name="farda_id" required
                        style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($fardas as $f): ?>
                            <option value="<?= $f['id'] ?>">
                                <?= htmlspecialchars($f['nome']) ?> - <?= htmlspecialchars($f['cor']) ?> (<?= htmlspecialchars($f['tamanho']) ?>)
                                — Stock: <?= (int)$f['quantidade'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block font-medium text-gray-700 mb-1">Quantidade</label>
                    <input type="number" name="quantidade" min="1" value="1" required
                        style="width:100px; padding:6px; border-radius:6px; border:1px solid #ccc;">
                </div>

                <button type="submit"
                    style="background-color:#7c3aed; color:#fff; font-weight:600; display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:8px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                    onmouseover="this.style.backgroundColor='#6d28d9';"
                    onmouseout="this.style.backgroundColor='#7c3aed';">
                    🧾 <span>Confirmar Empréstimo</span>
                </button>
            </form>
        <?php endif; ?>
    </main>
    <?php include '../src/templates/footer.php'; ?>
</body>
</html>
