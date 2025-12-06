<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Mensagem de estado
$mensagem = '';

// Se houver devolução
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emprestimo_id = $_POST['emprestimo_id'];
    $condicao = $_POST['condicao'];
    $observacoes = trim($_POST['observacoes'] ?? '');
    $user_id = $utilizador_logado['id'];

    try {
        $pdo->beginTransaction();

        // Buscar empréstimo
        $stmt = $pdo->prepare("SELECT farda_id, quantidade FROM farda_emprestimos WHERE id = :id AND devolvido = 0");
        $stmt->execute(['id' => $emprestimo_id]);
        $emprestimo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emprestimo) {
            throw new Exception("Empréstimo inválido ou já devolvido.");
        }

        // Atualizar estado do empréstimo
        $update = $pdo->prepare("
            UPDATE farda_emprestimos
            SET devolvido = 1, data_devolucao = NOW(), condicao_devolucao = :cond, observacoes = :obs
            WHERE id = :id
        ");
        $update->execute([
            'cond' => $condicao,
            'obs' => $observacoes,
            'id' => $emprestimo_id
        ]);

        // Se devolvido em bom estado, repor stock
        if ($condicao === 'bom_estado') {
            $repor = $pdo->prepare("UPDATE fardas SET quantidade = quantidade + :qtd WHERE id = :id");
            $repor->execute([
                'qtd' => $emprestimo['quantidade'],
                'id' => $emprestimo['farda_id']
            ]);
        }

        $pdo->commit();
        $mensagem = "✅ Devolução registada com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "❌ Erro: " . $e->getMessage();
    }
}

// Buscar todos os empréstimos não devolvidos
$stmt = $pdo->query("
    SELECT e.id, c.nome AS colaborador, f.nome AS farda, co.nome AS cor, t.nome AS tamanho,
           e.quantidade, e.data_emprestimo
    FROM farda_emprestimos e
    JOIN colaboradores c ON e.colaborador_id = c.id
    JOIN fardas f ON e.farda_id = f.id
    JOIN cores co ON f.cor_id = co.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE e.devolvido = 0
    ORDER BY e.data_emprestimo ASC
");
$emprestimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Devolução de Empréstimos - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <?php include '../src/templates/header.php'; ?>

    <main class="max-w-5xl mx-auto bg-white p-8 rounded-2xl shadow-md mt-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">↩️ Devolução de Fardas Emprestadas</h1>
            <a href="gerir_stock_farda.php" class="text-blue-600 hover:underline">← Voltar</a>
        </div>

        <?php if ($mensagem): ?>
            <div class="mb-6 p-4 bg-blue-100 border-l-4 border-blue-600 text-blue-800 rounded-md"><?= $mensagem ?></div>
        <?php endif; ?>

        <?php if (empty($emprestimos)): ?>
            <p class="text-gray-600 italic">Nenhum empréstimo pendente de devolução.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left">Colaborador</th>
                            <th class="px-4 py-2 text-left">Peça</th>
                            <th class="px-4 py-2 text-left">Cor</th>
                            <th class="px-4 py-2 text-left">Tamanho</th>
                            <th class="px-4 py-2 text-center">Qtd</th>
                            <th class="px-4 py-2 text-center">Data Empréstimo</th>
                            <th class="px-4 py-2 text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprestimos as $e): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($e['colaborador']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['farda']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['cor']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($e['tamanho']) ?></td>
                            <td class="px-4 py-2 text-center"><?= (int)$e['quantidade'] ?></td>
                            <td class="px-4 py-2 text-center"><?= date('d/m/Y H:i', strtotime($e['data_emprestimo'])) ?></td>
                            <td class="px-4 py-2 text-center">
                                <form method="POST" class="inline-block space-x-2">
                                    <input type="hidden" name="emprestimo_id" value="<?= $e['id'] ?>">
                                    <select name="condicao" required class="border rounded-md px-2 py-1 text-sm">
                                        <option value="">Condição...</option>
                                        <option value="bom_estado">Bom estado</option>
                                        <option value="danificado">Danificado</option>
                                        <option value="perdido">Perdido</option>
                                    </select>
                                    <input type="text" name="observacoes" placeholder="Observações" class="border px-2 py-1 rounded-md text-sm w-40">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md text-sm">Devolver</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    <?php include '../src/templates/footer.php'; ?>
</body>
</html>
