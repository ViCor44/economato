<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Verifica se o ID foi passado
$colaborador_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($colaborador_id <= 0) {
    header("Location: colaboradores.php");
    exit;
}

$success = '';

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
    // ✅ Atualizar estado de entrega do cartão (botão no topo)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cartao_entregue_status'])) {
        $entregue = ($_POST['cartao_entregue_status'] === '1') ? 1 : 0;
        $stmtUpdate = $pdo->prepare("UPDATE colaboradores SET cartao_entregue = ? WHERE id = ?");
        $stmtUpdate->execute([$entregue, $colaborador_id]);
        $colaborador['cartao_entregue'] = $entregue;
        $success = $entregue ? 'Cartão marcado como entregue.' : 'Cartão marcado como não entregue.';
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
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_itens,
            SUM(fa.quantidade * f.preco_unitario) AS total_divida
        FROM farda_atribuicoes fa
        JOIN fardas f ON f.id = fa.farda_id
        WHERE fa.colaborador_id = ?
        AND fa.estado = 'em_divida'
    ");
    $stmt->execute([$colaborador_id]);
    $divida = $stmt->fetch(PDO::FETCH_ASSOC);

    $temDivida = ((int)$divida['total_itens'] > 0);
    $valorDivida = (float)($divida['total_divida'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT
            fa.id,
            f.nome,
            c.nome AS cor,
            t.nome AS tamanho,
            fa.quantidade,
            f.preco_unitario,
            (fa.quantidade * f.preco_unitario) AS total
        FROM farda_atribuicoes fa
        JOIN fardas f ON f.id = fa.farda_id
        JOIN cores c ON c.id = f.cor_id
        JOIN tamanhos t ON t.id = f.tamanho_id
        WHERE fa.colaborador_id = ?
        AND fa.estado = 'em_divida'
    ");
    $stmt->execute([$colaborador_id]);
    $fardas_em_divida = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $colaboradorInativo = !$colaborador['ativo'];

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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="p-4 md:p-8">

<?php include_once '../src/templates/header.php'; ?>

<main class="max-w-5xl mx-auto bg-white rounded-2xl shadow-md p-4 md:p-8 mt-8 mb-8">

    <?php if (!empty($success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

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

        <div class="flex items-center gap-3">
            <a href="colaboradores.php" class="text-blue-600 hover:underline">← Voltar</a>

            <form method="POST" style="margin:0;">
                <input type="hidden" name="cartao_entregue_status" value="<?= $colaborador['cartao_entregue'] ? '0' : '1' ?>">
                <button type="submit"
                    class="ml-4 px-4 py-2 text-white rounded-md font-semibold shadow-md transition-colors duration-150"
                    style="background-color: <?= $colaborador['cartao_entregue'] ? '#10b981' : '#ef4444' ?>;">
                    <?= $colaborador['cartao_entregue'] ? '✔ Cartão Entregue' : '✘ Marcar Cartão Entregue' ?>
                </button>
            </form>

            <a href="editar_colaborador.php?id=<?= $colaborador_id ?>"
               class="ml-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold shadow-md transition-colors duration-150">
               ✏️ Editar Colaborador
            </a>
        </div>
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
            <?php if (
                !empty($colaborador['sector']) &&
                (stripos($colaborador['departamento_nome'], 'vigilantes') !== false || 
                 stripos($colaborador['departamento_nome'], 'supervisores') !== false)
            ): ?>
                <p>
                    <strong>Sector:</strong>
                    <?= htmlspecialchars($colaborador['sector']) ?>
                </p>
            <?php endif; ?>
            <p><strong>Status:</strong>
                <?= $colaborador['ativo']
                    ? '<span class="text-green-600 font-medium">Ativo</span>'
                    : '<span class="text-red-600 font-medium">Inativo</span>' ?>
            </p>
            <p><strong>Cartão Entregue:</strong>
                <?= $colaborador['cartao_entregue']
                    ? '<span class="text-green-600 font-medium">Sim</span>'
                    : '<span class="text-red-600 font-medium">Não</span>' ?>
            </p>
            <p><strong>Data de criação:</strong>
                <?= date('d/m/Y H:i', strtotime($colaborador['criado_em'])) ?>
            </p>
        </div>
    </section>

    <!-- 🔒 CACIFOS -->
    <section class="mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">🔒 Cacifos Atribuídos</h2>
        
        <div class="flex flex-wrap gap-3 mb-4">

        <?php if ($colaboradorInativo): ?>

            <span class="px-4 py-2 rounded-lg text-sm font-semibold
                        bg-gray-100 text-gray-400 cursor-not-allowed 
                        ">
                ➕ Atribuir
            </span>

            <span class="px-4 py-2 rounded-lg text-sm font-semibold
                        bg-gray-100 text-gray-400 cursor-not-allowed">
                🔁 Devolver
            </span>

            <span class="text-sm text-red-600 ml-2">
                ⚠ Colaborador inativo
            </span>

        <?php else: ?>

            <a href="list_lockers.php?colaborador_id=<?= (int)$colaborador['id'] ?>"
            class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold
                    bg-green-100 text-green-700 hover:bg-green-200 transition mr-4">
                ➕ <span>Atribuir</span>
            </a>

            <a href="list_lockers.php?pesquisa=<?= htmlspecialchars($colaborador['nome']) ?>"
            class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold
                    bg-red-100 text-red-700 hover:bg-red-200 transition">
                🔁 <span>Devolver</span>
            </a>

        <?php endif; ?>

        </div>

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

    <?php if (!empty($fardas_em_divida)): ?>
    <section class="mb-8">
        <h2 class="text-xl font-semibold text-red-700 mb-4 flex items-center gap-2">
            ⚠ Fardas em Dívida
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full border border-red-200 text-sm">
                <thead class="bg-red-50">
                    <tr>
                        <th class="px-4 py-2 border-b">Peça</th>
                        <th class="px-4 py-2 border-b">Cor</th>
                        <th class="px-4 py-2 border-b">Tamanho</th>
                        <th class="px-4 py-2 border-b text-center">Qtd</th>
                        <th class="px-4 py-2 border-b text-right">Valor (€)</th>
                        <th class="px-4 py-2 border-b text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fardas_em_divida as $f): ?>
                        <tr class="bg-red-50 hover:bg-red-100">
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['nome']) ?></td>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['cor']) ?></td>
                            <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['tamanho']) ?></td>
                            <td class="px-4 py-2 border-b text-center"><?= $f['quantidade'] ?></td>
                            <td class="px-4 py-2 border-b text-right font-semibold">
                                <?= number_format($f['total'], 2, ',', '.') ?>
                            </td>
                            <td class="px-4 py-2 border-b text-right">
                                <form method="POST" action="regularizar_divida.php">
                                    <input type="hidden" name="atribuicao_id" value="<?= (int)$f['id'] ?>">
                                    <button type="submit"
                                        class="px-3 py-1 bg-blue-600 text-white rounded-md text-xs font-semibold hover:bg-green-700">
                                        💶 Regularizar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>


    <!-- 🧥 FARDAS -->

    <?php
        $stmt = $pdo->prepare("
            SELECT
                fa.id,
                f.nome,
                c.nome AS cor,
                t.nome AS tamanho,
                fa.quantidade,
                f.preco_unitario,
                fa.data_atribuicao
            FROM farda_atribuicoes fa
            JOIN fardas f ON fa.farda_id = f.id
            JOIN cores c ON f.cor_id = c.id
            JOIN tamanhos t ON f.tamanho_id = t.id
            WHERE fa.colaborador_id = ?
            AND fa.estado IN ('atribuida','marcada_devolucao')
            ORDER BY fa.data_atribuicao DESC
        ");
        $stmt->execute([$colaborador_id]);
        $fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_geral = 0;
        $temFardas = !empty($fardas_atribuidas);

        // --- Estado do botão Gerar Termo ---
        // Garantir coluna updated_at em farda_atribuicoes
        $_mig = $pdo->query("SHOW COLUMNS FROM farda_atribuicoes LIKE 'updated_at'");
        if (!$_mig->fetch()) {
            $pdo->exec("ALTER TABLE farda_atribuicoes ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER data_atribuicao");
        }

        // Último termo válido para este colaborador
        $_stmtTermo = $pdo->prepare("
            SELECT id, codigo, criado_em
            FROM documentos
            WHERE colaborador_id = ?
              AND tipo = 'termo_farda'
              AND (estado = 'valido' OR estado IS NULL)
            ORDER BY criado_em DESC, id DESC
            LIMIT 1
        ");
        $_stmtTermo->execute([$colaborador_id]);
        $ultimoTermo = $_stmtTermo->fetch(PDO::FETCH_ASSOC) ?: null;

        // Última atividade: atribuição nova ou edição
        $_stmtAtiv = $pdo->prepare("
            SELECT MAX(GREATEST(data_atribuicao, COALESCE(updated_at, data_atribuicao))) AS ultima_atividade
            FROM farda_atribuicoes
            WHERE colaborador_id = ?
              AND estado IN ('atribuida','marcada_devolucao')
        ");
        $_stmtAtiv->execute([$colaborador_id]);
        $_rowAtiv = $_stmtAtiv->fetch(PDO::FETCH_ASSOC);
        $ultimaAtividadeFarda = $_rowAtiv['ultima_atividade'] ?? null;

        // gerar | novo_termo | em_vigor | sem_fardas
        if (!$temFardas) {
            $termoEstado = 'sem_fardas';
        } elseif ($ultimoTermo === null) {
            $termoEstado = 'gerar';
        } elseif ($ultimaAtividadeFarda && strtotime($ultimaAtividadeFarda) > strtotime($ultimoTermo['criado_em'])) {
            $termoEstado = 'novo_termo';
        } else {
            $termoEstado = 'em_vigor';
        }

        $_stmtEmprestimos = $pdo->prepare(" 
            SELECT e.id, e.quantidade, e.data_emprestimo,
                   f.nome AS farda_nome, c.nome AS cor_nome, t.nome AS tamanho_nome,
                   DATEDIFF(CURDATE(), DATE(e.data_emprestimo)) AS dias_em_aberto
            FROM farda_emprestimos e
            JOIN fardas f ON e.farda_id = f.id
            JOIN cores c ON f.cor_id = c.id
            JOIN tamanhos t ON f.tamanho_id = t.id
            WHERE e.colaborador_id = ?
              AND e.devolvido = 0
            ORDER BY e.data_emprestimo ASC
        ");
        $_stmtEmprestimos->execute([$colaborador_id]);
        $emprestimosPendentes = $_stmtEmprestimos->fetchAll(PDO::FETCH_ASSOC);
    ?>


    <section class="mt-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">🧥 Fardas Atribuídas</h2>
        
        <div class="flex flex-wrap gap-3 mb-4">

        <?php if ($colaboradorInativo): ?>

            <?php
            $btnDisabled = 'px-4 py-2 rounded-lg text-sm font-semibold
                            bg-gray-100 text-gray-400 cursor-not-allowed';
            ?>

            <span class="<?= $btnDisabled ?>">➕ Atribuir</span>
            <span class="<?= $btnDisabled ?>">🔁 Devolver</span>
            <span class="<?= $btnDisabled ?>">📄 Gerar Termo</span>

            <span class="text-sm text-red-600 ml-2">
                ⚠ Colaborador inativo
            </span>

        <?php else: ?>

            <!-- ➕ Atribuir farda -->
            <a href="atribuir_farda.php?colaborador_id=<?= $colaborador['id'] ?>"
            style="background-color:#16a34a;color:#fff;font-weight:600;
                    display:flex;align-items:center;gap:8px;
                    padding:8px 16px;border-radius:8px;text-decoration:none;
                    box-shadow:0 2px 4px rgba(0,0,0,0.1);"  class="mr-4"
            onmouseover="this.style.backgroundColor='#15803d';"
            onmouseout="this.style.backgroundColor='#16a34a';">
                ➕ <span>Atribuir</span>
            </a>

            <!-- 🔁 Devolver farda -->
            <a href="devolucao_farda.php?colaborador_id=<?= $colaborador['id'] ?>"
            style="background-color:#dc2626;color:#fff;font-weight:600;
                    display:flex;align-items:center;gap:8px;
                    padding:8px 16px;border-radius:8px;text-decoration:none;
                    box-shadow:0 2px 4px rgba(0,0,0,0.1);" class="mr-4"
            onmouseover="this.style.backgroundColor='#b91c1c';"
            onmouseout="this.style.backgroundColor='#dc2626';">
                🔁 <span>Devolver</span>
            </a>

            <!-- 📄 Termo de entrega -->
            <?php if ($termoEstado === 'gerar'): ?>

            <a href="gerar_termo_farda.php?colaborador_id=<?= $colaborador['id'] ?>"
            id="btnGerarTermo"
            style="background-color:#2563eb;color:#fff;font-weight:600;
            display:flex;align-items:center;gap:8px;
            padding:8px 16px;border-radius:8px;text-decoration:none;
            box-shadow:0 2px 4px rgba(0,0,0,0.1);" class="mr-4"
            onmouseover="this.style.backgroundColor='#1d4ed8';"
            onmouseout="this.style.backgroundColor='#2563eb';">
            📄 <span>Gerar Termo</span>
            </a>

            <?php elseif ($termoEstado === 'novo_termo'): ?>

            <a href="gerar_termo_farda.php?colaborador_id=<?= $colaborador['id'] ?>"
            id="btnGerarTermo"
            title="Existem alterações de fardamento desde o último termo de <?= date('d/m/Y H:i', strtotime($ultimoTermo['criado_em'])) ?>"
            style="background-color:#d97706;color:#fff;font-weight:600;
            display:flex;align-items:center;gap:8px;
            padding:8px 16px;border-radius:8px;text-decoration:none;
            box-shadow:0 2px 4px rgba(0,0,0,0.1);" class="mr-4"
            onmouseover="this.style.backgroundColor='#b45309';"
            onmouseout="this.style.backgroundColor='#d97706';">
            🔄 <span>Gerar Novo Termo</span>
            </a>

            <?php elseif ($termoEstado === 'em_vigor'): ?>

            <span
            title="Termo em vigor desde <?= date('d/m/Y H:i', strtotime($ultimoTermo['criado_em'])) ?>. Edite ou adicione fardas para poder gerar um novo."
            style="background:#e5e7eb;color:#9ca3af;font-weight:600;
            display:flex;align-items:center;gap:8px;
            padding:8px 16px;border-radius:8px;
            cursor:not-allowed;" class="mr-4">
            📄 <span>Termo em vigor</span>
            </span>

            <?php else: ?>

            <span style="background:#e5e7eb;color:#9ca3af;font-weight:600;
            display:flex;align-items:center;gap:8px;
            padding:8px 16px;border-radius:8px;
            cursor:not-allowed;" class="mr-4">
            📄 <span>Gerar Termo</span>
            </span>

            <?php endif; ?>
        <?php endif; ?>
        </div>


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
                        <th class="px-4 py-2 border-b text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fardas_atribuidas as $f):
                    $total_item = $f['quantidade'] * $f['preco_unitario'];
                    $total_geral += $total_item;
                ?>
                    <tr>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['nome']) ?></td>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['cor']) ?></td>
                        <td class="px-4 py-2 border-b"><?= htmlspecialchars($f['tamanho']) ?></td>
                        <td class="px-4 py-2 border-b text-center"><?= $f['quantidade'] ?></td>
                        <td class="px-4 py-2 border-b text-right"><?= number_format($f['preco_unitario'], 2, ',', '.') ?></td>
                        <td class="px-4 py-2 border-b text-right font-semibold">
                            <?= number_format($total_item, 2, ',', '.') ?>
                        </td>
                        <td class="px-4 py-2 border-b text-center">
                            <?= date('d/m/Y H:i', strtotime($f['data_atribuicao'])) ?>
                        </td>
                        <td class="px-4 py-2 border-b text-center">
                            <a href="editar_atribuicao.php?id=<?= $f['id'] ?>"
                            class="text-blue-600 hover:text-blue-800 font-semibold mr-2">
                            ✏️
                            </a>
                            <a href="anular_atribuicao.php?id=<?= $f['id'] ?>"
                            class="text-red-600 hover:text-red-800 font-semibold">
                            ❌ Anular
                            </a>
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

    <section class="mt-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-800">↩️ Empréstimos Pendentes</h2>
            <a href="devolver_emprestimo.php?colaborador_id=<?= (int)$colaborador_id ?>" class="text-sm text-blue-600 hover:underline">
                Abrir gestão completa
            </a>
        </div>

        <div class="flex flex-wrap gap-3 mb-4">
            <?php if ($colaboradorInativo): ?>
                <span class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 text-gray-400 cursor-not-allowed" style="margin-right:12px;">
                    🧥 Emprestar
                </span>
                <span class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 text-gray-400 cursor-not-allowed">
                    ↩️ Devolver Empréstimo
                </span>
            <?php else: ?>
                <a href="emprestar_farda.php?colaborador_id=<?= (int)$colaborador['id'] ?>"
                style="background-color:#7c3aed;color:#fff;font-weight:600;
                        display:flex;align-items:center;gap:8px;
                        padding:8px 16px;border-radius:8px;text-decoration:none;
                        box-shadow:0 2px 4px rgba(0,0,0,0.1);
                        margin-right:12px;"
                onmouseover="this.style.backgroundColor='#6d28d9';"
                onmouseout="this.style.backgroundColor='#7c3aed';">
                    🧥 <span>Emprestar</span>
                </a>

                <a href="devolver_emprestimo.php?colaborador_id=<?= (int)$colaborador['id'] ?>"
                style="background-color:#ea580c;color:#fff;font-weight:600;
                        display:flex;align-items:center;gap:8px;
                        padding:8px 16px;border-radius:8px;text-decoration:none;
                        box-shadow:0 2px 4px rgba(0,0,0,0.1);"
                onmouseover="this.style.backgroundColor='#c2410c';"
                onmouseout="this.style.backgroundColor='#ea580c';">
                    ↩️ <span>Devolver Empréstimo</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($emprestimosPendentes)): ?>
            <p class="text-gray-500 italic">Sem empréstimos pendentes para este colaborador.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 border-b text-left">Peça</th>
                            <th class="px-4 py-2 border-b text-left">Cor</th>
                            <th class="px-4 py-2 border-b text-left">Tamanho</th>
                            <th class="px-4 py-2 border-b text-center">Qtd</th>
                            <th class="px-4 py-2 border-b text-center">Data Empréstimo</th>
                            <th class="px-4 py-2 border-b text-center">Dias</th>
                            <th class="px-4 py-2 border-b text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprestimosPendentes as $emp): ?>
                            <?php $emAtraso = ((int)$emp['dias_em_aberto'] >= 15); ?>
                            <tr class="<?= $emAtraso ? 'bg-red-50' : '' ?>">
                                <td class="px-4 py-2 border-b"><?= htmlspecialchars($emp['farda_nome']) ?></td>
                                <td class="px-4 py-2 border-b"><?= htmlspecialchars($emp['cor_nome']) ?></td>
                                <td class="px-4 py-2 border-b"><?= htmlspecialchars($emp['tamanho_nome']) ?></td>
                                <td class="px-4 py-2 border-b text-center"><?= (int)$emp['quantidade'] ?></td>
                                <td class="px-4 py-2 border-b text-center"><?= date('d/m/Y H:i', strtotime($emp['data_emprestimo'])) ?></td>
                                <td class="px-4 py-2 border-b text-center font-semibold <?= $emAtraso ? 'text-red-700' : 'text-gray-700' ?>">
                                    <?= (int)$emp['dias_em_aberto'] ?>
                                </td>
                                <td class="px-4 py-2 border-b">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <form method="POST" action="devolver_emprestimo.php?colaborador_id=<?= (int)$colaborador_id ?>" class="flex items-center gap-2">
                                            <input type="hidden" name="emprestimo_id" value="<?= (int)$emp['id'] ?>">
                                            <input type="hidden" name="acao" value="devolver">
                                            <select name="condicao" required class="border rounded-md px-2 py-1 text-xs">
                                                <option value="">Condição...</option>
                                                <option value="bom_estado">Bom estado</option>
                                                <option value="danificado">Danificado</option>
                                                <option value="perdido">Perdido</option>
                                            </select>
                                            <button type="submit"
                                                style="background-color:#16a34a;color:#fff;font-weight:600;padding:4px 10px;border-radius:6px;font-size:12px;"
                                                onmouseover="this.style.backgroundColor='#15803d';"
                                                onmouseout="this.style.backgroundColor='#16a34a';">
                                                Devolver
                                            </button>
                                        </form>

                                        <form method="POST" action="devolver_emprestimo.php?colaborador_id=<?= (int)$colaborador_id ?>">
                                            <input type="hidden" name="emprestimo_id" value="<?= (int)$emp['id'] ?>">
                                            <input type="hidden" name="acao" value="atribuir">
                                            <input type="hidden" name="observacoes" value="Convertido em atribuição definitiva a partir dos detalhes do colaborador.">
                                            <button
                                                type="submit"
                                                style="background-color:#2563eb;color:#fff;font-weight:600;padding:4px 10px;border-radius:6px;font-size:12px;"
                                                onmouseover="this.style.backgroundColor='#1d4ed8';"
                                                onmouseout="this.style.backgroundColor='#2563eb';"
                                                onclick="return confirm('Este empréstimo será convertido em atribuição definitiva. Continuar?');"
                                            >
                                                Atribuir definitivo
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<script>
    const colaboradorNome = "<?= addslashes($colaborador['nome']) ?>";
    const fardas = <?= json_encode($fardas_atribuidas) ?>;
</script>
</main>
<script>

const btnGerarTermo = document.getElementById('btnGerarTermo');
if (btnGerarTermo) {

    btnGerarTermo.addEventListener('click', function(e){

        e.preventDefault();

        const url = this.href;
        const isNovoTermo = this.innerText.trim().includes('Novo');

        let lista = '';
        let total = 0;

        fardas.forEach(f => {

            const subtotal = f.quantidade * f.preco_unitario;
            total += subtotal;

            lista += `
            <tr>
                <td style="text-align:left">${f.nome}</td>
                <td>${f.cor}</td>
                <td>${f.tamanho}</td>
                <td>${f.quantidade}</td>
            </tr>`;
        });

        const tabela = `
        <table style="width:100%;font-size:14px;border-collapse:collapse">
            <thead>
                <tr>
                    <th style="text-align:left">Peça</th>
                    <th>Cor</th>
                    <th>Tam</th>
                    <th>Qtd</th>
                </tr>
            </thead>
            <tbody>
                ${lista}
            </tbody>
        </table>
        `;

        Swal.fire({

            icon: 'warning',
            title: isNovoTermo ? 'Confirmar Novo Termo de Fardamento' : 'Confirmar Termo de Fardamento',

            html: `
            <p><strong>Colaborador:</strong> ${colaboradorNome}</p>

            ${tabela}

            <br>

            <p style="font-weight:bold">
            Total estimado: € ${total.toFixed(2)}
            </p>

            ${isNovoTermo ? '<p style="color:#d97706;font-size:13px">Este termo substituirá e invalidará o anterior.</p>' : ''}

            <p style="color:#dc2626;font-size:13px">
            Após gerar o termo não será possível editar ou anular estas atribuições.
            </p>
            `,

            width: 600,
            showCancelButton: true,
            confirmButtonText: isNovoTermo ? 'Gerar Novo Termo' : 'Gerar Termo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: isNovoTermo ? '#d97706' : '#2563eb',
            cancelButtonColor: '#6b7280'

        }).then((result) => {

            if (result.isConfirmed) {
                window.open(url, '_blank');
            }

        });

    });

}

</script>
<?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
