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
    // 🔹 Buscar dados do colaborador
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

    // 🔔 Verificar dívida de fardamento
    $stmtDivida = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_itens,
            SUM(fa.quantidade * f.preco_unitario) AS total_divida
        FROM farda_atribuicoes fa
        JOIN fardas f ON fa.farda_id = f.id
        WHERE fa.colaborador_id = ?
          AND fa.estado = 'em_divida'
    ");
    $stmtDivida->execute([$colaborador_id]);
    $divida = $stmtDivida->fetch(PDO::FETCH_ASSOC);

    $temDivida   = ($divida['total_itens'] ?? 0) > 0;
    $valorDivida = $divida['total_divida'] ?? 0;

} catch (PDOException $e) {
    die("Erro ao carregar detalhes do colaborador: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Detalhes do Colaborador - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="p-4 md:p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-5xl mx-auto bg-white rounded-2xl shadow-md p-4 md:p-8 mt-8 mb-8">

    <!-- 👤 CABEÇALHO -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <?php if (!empty($colaborador['foto'])): ?>
                <img src="<?= BASE_URL ?>/public/uploads/colaboradores/<?= htmlspecialchars($colaborador['foto']) ?>"
                     alt="Foto do colaborador"
                     style="width:160px;height:160px;object-fit:cover;border-radius:50%;border:1px solid #e5e7eb;">
            <?php else: ?>
                <div class="h-20 w-20 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-2xl">
                    👤
                </div>
            <?php endif; ?>

            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mr-8">
                        <?= htmlspecialchars($colaborador['nome']) ?>
                    </h1>

                    <?php if ($temDivida): ?>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full 
                                     bg-red-100 text-red-700 text-sm font-semibold"
                              title="Colaborador com fardas em dívida">
                            ⚠ Dívida
                        </span>
                    <?php endif; ?>
                </div>

                <p class="text-sm text-gray-600">
                    Nº Funcionário: <strong><?= htmlspecialchars($colaborador['numero_funcionario']) ?></strong>
                </p>
            </div>
        </div>

        <a href="colaboradores.php" class="text-blue-600 hover:underline">← Voltar</a>
    </div>

    <!-- ⚠ ALERTA DE DÍVIDA -->
    <?php if ($temDivida): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6">
            <strong>⚠ Atenção:</strong> Este colaborador tem fardas não devolvidas.<br>
            <strong>Total em dívida:</strong>
            € <?= number_format($valorDivida, 2, ',', '.') ?>
        </div>
    <?php endif; ?>

    <!-- 🧾 INFORMAÇÕES -->
    <section class="mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Informações Pessoais</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">
            <p><strong>Cartão:</strong> <?= htmlspecialchars($colaborador['cartao']) ?></p>
            <p><strong>Telefone:</strong> <?= htmlspecialchars($colaborador['telefone'] ?: '—') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($colaborador['email'] ?: '—') ?></p>
            <p><strong>Departamento:</strong> <?= htmlspecialchars($colaborador['departamento_nome'] ?? '—') ?></p>
            <p><strong>Status:</strong>
                <?= $colaborador['ativo']
                    ? '<span class="text-green-600 font-medium">Ativo</span>'
                    : '<span class="text-red-600 font-medium">Inativo</span>' ?>
            </p>
            <p><strong>Data de criação:</strong>
                <?= date('d/m/Y H:i', strtotime($colaborador['criado_em'])) ?>
            </p>
        </div>
    </section>

    <!-- 🔒 CACIFOS -->
    <section class="mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">🔒 Cacifos Atribuídos</h2>

        <?php if ($cacifos): ?>
            <table class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 border-b text-left">Número</th>
                        <th class="px-4 py-2 border-b text-left">Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cacifos as $c): ?>
                    <tr>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($c['numero']) ?></td>
                        <td class="px-4 py-2 border-b">
                            <?= $c['avariado']
                                ? '<span class="text-red-600">Avariado</span>'
                                : '<span class="text-green-600">OK</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-600">Nenhum cacifo atribuído.</p>
        <?php endif; ?>
    </section>

    <!-- 🧥 FARDAS -->
    <section class="mt-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">🧥 Fardas Atribuídas</h2>

        <?php
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
            WHERE fa.colaborador_id = ?
              AND fa.estado IN ('atribuida', 'marcada_devolucao')
            GROUP BY f.nome, c.nome, t.nome, f.preco_unitario
            ORDER BY ultima_data DESC
        ");
        $stmt->execute([$colaborador_id]);
        $fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_geral = 0;
        ?>

        <?php if ($fardas_atribuidas): ?>
            <table class="min-w-full border border-gray-200 text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 border-b">Peça</th>
                        <th class="px-4 py-2 border-b">Cor</th>
                        <th class="px-4 py-2 border-b">Tamanho</th>
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
                    <tr>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['nome']) ?></td>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['cor']) ?></td>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['tamanho']) ?></td>
                        <td class="px-4 py-2 border-b text-center"><?= $f['quantidade_total'] ?></td>
                        <td class="px-4 py-2 border-b text-right"><?= number_format($f['preco_unitario'], 2, ',', '.') ?></td>
                        <td class="px-4 py-2 border-b text-right font-semibold">
                            <?= number_format($total_item, 2, ',', '.') ?>
                        </td>
                        <td class="px-4 py-2 border-b text-center">
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
        <?php else: ?>
            <p class="text-gray-500 italic">Nenhuma farda atribuída.</p>
        <?php endif; ?>
    </section>

</main>

<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
