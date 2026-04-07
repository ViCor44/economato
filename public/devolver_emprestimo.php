<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

function garantirConversaoEmprestimos(PDO $pdo): void
{
    $colunasNecessarias = [
        'convertido_em_atribuicao' => "ALTER TABLE farda_emprestimos ADD COLUMN convertido_em_atribuicao TINYINT(1) NOT NULL DEFAULT 0 AFTER devolvido",
        'atribuicao_id' => "ALTER TABLE farda_emprestimos ADD COLUMN atribuicao_id INT NULL AFTER convertido_em_atribuicao",
    ];

    foreach ($colunasNecessarias as $nomeColuna => $ddl) {
        $stmt = $pdo->query("SHOW COLUMNS FROM farda_emprestimos LIKE '" . $nomeColuna . "'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec($ddl);
        }
    }

    $stmt = $pdo->query("SHOW INDEX FROM farda_emprestimos WHERE Key_name = 'idx_farda_emprestimos_atribuicao_id'");
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("CREATE INDEX idx_farda_emprestimos_atribuicao_id ON farda_emprestimos (atribuicao_id)");
    }
}

garantirConversaoEmprestimos($pdo);

$colaboradorId = isset($_GET['colaborador_id']) ? (int) $_GET['colaborador_id'] : 0;
$colaborador = null;

if ($colaboradorId > 0) {
    $stmtColaborador = $pdo->prepare("SELECT id, nome FROM colaboradores WHERE id = ?");
    $stmtColaborador->execute([$colaboradorId]);
    $colaborador = $stmtColaborador->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$colaborador) {
        die('Colaborador inválido.');
    }
}

// Mensagem de estado
$mensagem = '';

// Se houver devolução
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emprestimo_id = (int) ($_POST['emprestimo_id'] ?? 0);
    $acao = $_POST['acao'] ?? 'devolver';
    $condicao = $_POST['condicao'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $user_id = $utilizador_logado['id'];

    try {
        $pdo->beginTransaction();

        // Buscar empréstimo
        $sqlEmprestimo = "SELECT id, farda_id, quantidade, colaborador_id FROM farda_emprestimos WHERE id = :id AND devolvido = 0";
        if ($colaboradorId > 0) {
            $sqlEmprestimo .= " AND colaborador_id = :colaborador_id";
        }

        $stmt = $pdo->prepare($sqlEmprestimo);
        $paramsEmprestimo = ['id' => $emprestimo_id];
        if ($colaboradorId > 0) {
            $paramsEmprestimo['colaborador_id'] = $colaboradorId;
        }

        $stmt->execute($paramsEmprestimo);
        $emprestimo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$emprestimo) {
            throw new Exception("Empréstimo inválido ou já devolvido.");
        }

        if ($acao === 'atribuir') {
            $criarAtribuicao = $pdo->prepare("
                INSERT INTO farda_atribuicoes
                (colaborador_id, farda_id, quantidade, estado, data_atribuicao)
                VALUES (:colaborador_id, :farda_id, :quantidade, 'atribuida', NOW())
            ");
            $criarAtribuicao->execute([
                'colaborador_id' => $emprestimo['colaborador_id'],
                'farda_id' => $emprestimo['farda_id'],
                'quantidade' => $emprestimo['quantidade'],
            ]);

            $atribuicaoId = (int) $pdo->lastInsertId();
            $observacoesConversao = $observacoes;
            if ($observacoesConversao !== '') {
                $observacoesConversao .= "\n";
            }
            $observacoesConversao .= 'Empréstimo convertido em atribuição definitiva.';

            $update = $pdo->prepare("
                UPDATE farda_emprestimos
                SET devolvido = 1,
                    data_devolucao = NOW(),
                    convertido_em_atribuicao = 1,
                    atribuicao_id = :atribuicao_id,
                    observacoes = :obs
                WHERE id = :id
            ");
            $update->execute([
                'atribuicao_id' => $atribuicaoId,
                'obs' => $observacoesConversao,
                'id' => $emprestimo_id,
            ]);

            adicionarLog(
                $pdo,
                'Conversão de empréstimo em atribuição',
                "Empréstimo ID {$emprestimo_id} convertido em atribuição ID {$atribuicaoId} para colaborador ID {$emprestimo['colaborador_id']}"
            );

            $pdo->commit();
            $mensagem = '✅ Empréstimo convertido em atribuição com sucesso. Já pode gerar novo termo.';
        } else {
            if ($condicao === '') {
                throw new Exception('Selecione a condição da devolução.');
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

            adicionarLog(
                $pdo,
                'Devolução de empréstimo',
                "Empréstimo ID {$emprestimo_id} devolvido pelo colaborador ID {$emprestimo['colaborador_id']} com condição {$condicao}"
            );

            $pdo->commit();
            $mensagem = '✅ Devolução registada com sucesso!';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "❌ Erro: " . $e->getMessage();
    }
}

// Buscar empréstimos não devolvidos
$sqlEmprestimos = "
    SELECT e.id, c.nome AS colaborador, f.nome AS farda, co.nome AS cor, t.nome AS tamanho,
           e.quantidade, e.data_emprestimo
    FROM farda_emprestimos e
    JOIN colaboradores c ON e.colaborador_id = c.id
    JOIN fardas f ON e.farda_id = f.id
    JOIN cores co ON f.cor_id = co.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE e.devolvido = 0
";

if ($colaboradorId > 0) {
    $sqlEmprestimos .= " AND e.colaborador_id = :colaborador_id";
}

$sqlEmprestimos .= " ORDER BY e.data_emprestimo ASC";

$stmt = $pdo->prepare($sqlEmprestimos);
$paramsEmprestimos = [];
if ($colaboradorId > 0) {
    $paramsEmprestimos['colaborador_id'] = $colaboradorId;
}
$stmt->execute($paramsEmprestimos);
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
            <a href="<?= $colaboradorId > 0 ? 'detalhes_colaborador.php?id=' . $colaboradorId : 'gerir_stock_farda.php' ?>" class="text-blue-600 hover:underline">← Voltar</a>
        </div>

        <?php if ($colaborador): ?>
            <p class="mb-4 text-sm text-gray-600">
                A mostrar apenas os empréstimos de <strong><?= htmlspecialchars($colaborador['nome']) ?></strong>.
            </p>
        <?php endif; ?>

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
                                <form method="POST" class="inline-flex items-center gap-2 flex-wrap justify-center">
                                    <input type="hidden" name="emprestimo_id" value="<?= $e['id'] ?>">
                                    <input type="hidden" name="acao" value="devolver">
                                    <select name="condicao" required class="border rounded-md px-2 py-1 text-sm">
                                        <option value="">Condição...</option>
                                        <option value="bom_estado">Bom estado</option>
                                        <option value="danificado">Danificado</option>
                                        <option value="perdido">Perdido</option>
                                    </select>
                                    <input type="text" name="observacoes" placeholder="Observações" class="border px-2 py-1 rounded-md text-sm w-40">
                                    <button type="submit"
                                        style="background-color:#16a34a;color:#fff;font-weight:600;padding:6px 12px;border-radius:6px;font-size:13px;"
                                        onmouseover="this.style.backgroundColor='#15803d';"
                                        onmouseout="this.style.backgroundColor='#16a34a';">Devolver</button>
                                </form>
                                <form method="POST" class="inline-flex items-center gap-2 ml-2 mt-2 sm:mt-0">
                                    <input type="hidden" name="emprestimo_id" value="<?= $e['id'] ?>">
                                    <input type="hidden" name="acao" value="atribuir">
                                    <input type="hidden" name="observacoes" value="Convertido em atribuição definitiva a partir da gestão de empréstimos.">
                                    <button
                                        type="submit"
                                        style="background-color:#2563eb;color:#fff;font-weight:600;padding:6px 12px;border-radius:6px;font-size:13px;"
                                        onmouseover="this.style.backgroundColor='#1d4ed8';"
                                        onmouseout="this.style.backgroundColor='#2563eb';"
                                        onclick="return confirm('Este empréstimo será convertido em atribuição definitiva ao colaborador. Continuar?');"
                                    >
                                        Atribuir ao colaborador
                                    </button>
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
