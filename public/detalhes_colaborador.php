<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
// Verifica se o ID foi passado
$colaborador_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($colaborador_id <= 0) {
    header("Location: colaboradores.php");
    exit;
}
try {
    // 🔹 Buscar dados do colaborador (agora com telefone e email)
    $stmt = $pdo->prepare("
        SELECT c.*, d.nome AS departamento_nome
        FROM colaboradores c
        LEFT JOIN departamentos d ON c.departamento_id = d.id
        WHERE c.id = :id
    ");
    $stmt->execute(['id' => $colaborador_id]);
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$colaborador) {
        die("Colaborador não encontrado.");
    }
    // 🔹 Buscar cacifos atribuídos
    $stmtCacifos = $pdo->prepare("
        SELECT numero, avariado
        FROM cacifos
        WHERE colaborador_id = :id
        ORDER BY numero ASC
    ");
    $stmtCacifos->execute(['id' => $colaborador_id]);
    $cacifos = $stmtCacifos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar detalhes do colaborador: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Colaborador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-4 md:p-8">
<?php include_once '../src/templates/header.php'; ?>
<main class="max-w-5xl mx-auto bg-white rounded-2xl shadow-md p-4 md:p-8 mt-8 mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <?php if (!empty($colaborador['foto'])): ?>
                <img src="<?= BASE_URL ?>/public/uploads/colaboradores/<?= htmlspecialchars($colaborador['foto']) ?>"
                    alt="Foto do colaborador"
                    style="
                        width:160px;
                        height:160px;
                        object-fit:cover;
                        border-radius:50%;
                        border:1px solid #e5e7eb;
                        box-shadow:0 1px 3px rgba(0,0,0,0.1);
                        flex-shrink:0;
                    ">
            <?php else: ?>
                <div class="h-20 w-20 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-2xl">
                    👤
                </div>
            <?php endif; ?>

            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                    <?= htmlspecialchars($colaborador['nome']) ?>
                </h1>
                <p class="text-sm text-gray-600">
                    Nº Funcionário: <strong><?= htmlspecialchars($colaborador['numero_funcionario']) ?></strong>
                </p>
            </div>
        </div>
        <a href="colaboradores.php" class="text-blue-600 hover:underline">← Voltar</a>
    </div>

    <!-- 🧾 Dados principais -->
    <section class="mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Informações Pessoais</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">            
            <p><strong>Cartão:</strong> <?= htmlspecialchars($colaborador['cartao']) ?></p>
            <p><strong>Telefone:</strong> <?= htmlspecialchars($colaborador['telefone'] ?: '—') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($colaborador['email'] ?: '—') ?></p>
            <p><strong>Departamento:</strong> <?= htmlspecialchars($colaborador['departamento_nome'] ?? '—') ?></p>
            <p><strong>Status:</strong>
                <?php if ($colaborador['ativo']): ?>
                    <span class="text-green-600 font-medium">Ativo</span>
                <?php else: ?>
                    <span class="text-red-600 font-medium">Inativo</span>
                <?php endif; ?>
            </p>
            <p><strong>Data de criação:</strong>
                <?= date('d/m/Y H:i', strtotime($colaborador['criado_em'])) ?>
            </p>
        </div>
    </section>
    <!-- 🔒 CACIFOS -->
    <section class="mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">🔒 Cacifos Atribuídos</h2>
            <div class="flex flex-wrap gap-3 mb-4">
                <!-- ✅ Atribuir cacifo -->
                <a href="list_lockers.php" class="ml-4"
                    style="background-color:#16a34a; color:#fff; font-weight:600; display:flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                    onmouseover="this.style.backgroundColor='#15803d';"
                    onmouseout="this.style.backgroundColor='#16a34a';">
                    ➕ <span>Atribuir</span>
                </a>
                <!-- 🔄 Devolver cacifo -->
                <a href="list_lockers.php?pesquisa=<?= htmlspecialchars($colaborador['nome']) ?>" class="ml-4"
                    style="background-color:#dc2626; color:#fff; font-weight:600; display:flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                    onmouseover="this.style.backgroundColor='#b91c1c';"
                    onmouseout="this.style.backgroundColor='#dc2626';">
                    🔁 <span>Devolver</span>
                </a>
            </div>
        </div>
        <?php if (count($cacifos) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="text-left px-4 py-2 border-b">Número</th>
                            <th class="text-left px-4 py-2 border-b">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cacifos as $c): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 border-b"><?= htmlspecialchars($c['numero']) ?></td>
                                <td class="px-4 py-2 border-b">
                                    <?= $c['avariado'] ? '<span class="text-red-600">Avariado</span>' : '<span class="text-green-600">OK</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-600">Nenhum cacifo atribuído.</p>
        <?php endif; ?>
       
    </section>
    <!-- 🧥 FARDAS -->
    <section class="mt-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                🧥 Fardas Atribuídas
            </h2>
            <div class="flex flex-wrap gap-3 mb-4">
    <!-- ✅ Atribuir farda -->
    <a href="atribuir_farda.php?colaborador_id=<?= $colaborador['id'] ?>" class="ml-4"
        style="background-color:#16a34a; color:#fff; font-weight:600; display:flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
        onmouseover="this.style.backgroundColor='#15803d';"
        onmouseout="this.style.backgroundColor='#16a34a';">
        ➕ <span>Atribuir</span>
    </a>
    <!-- 🔄 Devolver farda -->
    <a href="devolucao_farda.php?colaborador_id=<?= $colaborador['id'] ?>" class="ml-4"
        style="background-color:#dc2626; color:#fff; font-weight:600; display:flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
        onmouseover="this.style.backgroundColor='#b91c1c';"
        onmouseout="this.style.backgroundColor='#dc2626';">
        🔁 <span>Devolver</span>
    </a>
    <!-- 🟣 Emprestar farda -->
    <a href="emprestar_farda.php?colaborador_id=<?= $colaborador['id'] ?>" class="ml-4"
        style="background-color:#7c3aed; color:#fff; font-weight:600; display:flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
        onmouseover="this.style.backgroundColor='#6d28d9';"
        onmouseout="this.style.backgroundColor='#7c3aed';">
        🧥 <span>Emprestar</span>
    </a>
    <!-- 🟧 Devolver empréstimo -->
    <a href="devolver_emprestimo.php?colaborador_id=<?= $colaborador['id'] ?>" class="ml-4"
        style="background-color:#ea580c; color:#fff; font-weight:600; display:flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.1);"
        onmouseover="this.style.backgroundColor='#c2410c';"
        onmouseout="this.style.backgroundColor='#ea580c';">
        ↩️ <span>Devolver Empréstimo</span>
    </a>
    <a href="gerar_termo_farda.php?colaborador_id=<?= $colaborador['id'] ?>" class="ml-4"
    style="background-color:#16a34a; color:#fff; font-weight:600;
           display:flex; align-items:center; gap:8px; padding:8px 16px;
           border-radius:8px; text-decoration:none;
           box-shadow:0 2px 4px rgba(0,0,0,0.1);"
    onmouseover="this.style.backgroundColor='#15803d';"
    onmouseout="this.style.backgroundColor='#16a34a';"
    target="_blank">
        📄 <span>Gerar Termo de Entrega</span>
    </a>
   
    <a href="gerar_termo_entrega.php?colaborador_id=<?= $colaborador['id'] ?>" class="ml-4"
    style="background-color:#16a34a; color:#fff; font-weight:600;
           display:flex; align-items:center; gap:8px; padding:8px 16px;
           border-radius:8px; text-decoration:none;
           box-shadow:0 2px 4px rgba(0,0,0,0.1);"
    onmouseover="this.style.backgroundColor='#15803d';"
    onmouseout="this.style.backgroundColor='#16a34a';"
    target="_blank">
        📄 <span>Gerar Termo de Devolução</span>
    </a>
</div>
        </div>
        <?php
        // Buscar fardas atribuídas ao colaborador
        $stmt = $pdo->prepare("
            SELECT
                f.nome,
                c.nome AS cor,
                t.nome AS tamanho,
                SUM(fa.quantidade) AS quantidade_total,
                f.preco_unitario,
                MAX(fa.data_atribuicao) AS ultima_data
            FROM farda_atribuicoes fa
            JOIN fardas f ON fa.farda_id = f.id
            JOIN cores c ON f.cor_id = c.id
            JOIN tamanhos t ON f.tamanho_id = t.id
            WHERE fa.colaborador_id = :id
            GROUP BY f.nome, c.nome, t.nome, f.preco_unitario
            ORDER BY ultima_data DESC
        ");
        $stmt->execute(['id' => $colaborador['id']]);
        $fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_geral = 0;
        ?>
        <?php if ($fardas_atribuidas): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm mt-2">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 border-b text-left">Peça</th>
                            <th class="px-4 py-2 border-b text-left">Cor</th>
                            <th class="px-4 py-2 border-b text-left">Tamanho</th>
                            <th class="px-4 py-2 border-b text-center">Qtd</th>
                            <th class="px-4 py-2 border-b text-right">Preço (€)</th>
                            <th class="px-4 py-2 border-b text-right">Total (€)</th>
                            <th class="px-4 py-2 border-b text-center">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fardas_atribuidas as $f):
                            $total_item = $f['quantidade_total'] * $f['preco_unitario'];
                            $total_geral += $total_item;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['nome']) ?></td>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['cor']) ?></td>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['tamanho']) ?></td>
                            <td class="px-4 py-2 border-b text-center"><?= $f['quantidade_total'] ?></td>
                            <td class="px-4 py-2 border-b text-right"><?= number_format($f['preco_unitario'], 2, ',', '.') ?></td>
                            <td class="px-4 py-2 border-b text-right font-semibold">
                                <?= number_format($total_item, 2, ',', '.') ?>
                            </td>
                            <td class="px-4 py-2 border-b text-center text-gray-600">
                                <?= date('d/m/Y H:i', strtotime($f['ultima_data'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right font-semibold">💰 Total Geral:</td>
                            <td class="px-4 py-3 text-right font-bold text-green-700">
                                <?= number_format($total_geral, 2, ',', '.') ?> €
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">Nenhuma farda atribuída a este colaborador.</p>
        <?php endif; ?>
    </section>
</main>
<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>