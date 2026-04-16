<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../src/log.php';

// 🔐 Apenas Admins podem reverter termos
if ($_SESSION['role_id'] !== 1) {
    die("Acesso negado. Apenas administradores podem reverter termos.");
}

$erro = '';
$sucesso = '';

$termo_id = isset($_GET['termo_id']) ? (int)$_GET['termo_id'] : 0;
$acao = $_POST['acao'] ?? '';

// 🔍 Se vier POST para reverter
if ($_POST && $acao === 'reverter_confirmar') {
    $termo_id_post = (int)($_POST['termo_id'] ?? 0);
    
    if ($termo_id_post <= 0) {
        $erro = "Termo inválido.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 🔍 Buscar o termo de devolução
            $stmt = $pdo->prepare("
                SELECT termo_id, colaborador_id
                FROM farda_atribuicoes
                WHERE termo_id = ?
                LIMIT 1
            ");
            $stmt->execute([$termo_id_post]);
            $primeiraAtribuicao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$primeiraAtribuicao) {
                throw new Exception("Termo não encontrado nas atribuições.");
            }
            
            $colaborador_id = (int)$primeiraAtribuicao['colaborador_id'];
            
            // 🔍 Buscar todas as atribuições relacionadas a este termo
            $stmt = $pdo->prepare("
                SELECT id, farda_id, quantidade, estado, estado_devolucao
                FROM farda_atribuicoes
                WHERE termo_id = ?
            ");
            $stmt->execute([$termo_id_post]);
            $atribuicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($atribuicoes)) {
                throw new Exception("Nenhuma atribuição foi encontrada para este termo.");
            }
            
            // ✅ REVERTER CADA ATRIBUIÇÃO
            foreach ($atribuicoes as $attr) {
                $id = (int)$attr['id'];
                $farda_id = (int)$attr['farda_id'];
                $quantidade = (int)$attr['quantidade'];
                $estado = $attr['estado'];
                $estado_devolucao = $attr['estado_devolucao'];
                
                // Se era 'devolvida_confirmada', reverter para 'atribuida'
                if ($estado === 'devolvida_confirmada') {
                    // 🔄 Se a farda foi devolvida ao stock, reduzir o stock
                    if ($estado_devolucao === 'stock') {
                        $pdo->prepare("
                            UPDATE fardas
                            SET quantidade = quantidade - ?
                            WHERE id = ?
                        ")->execute([$quantidade, $farda_id]);
                    }
                    
                    // 📝 Reverter atribuição para 'atribuida'
                    $pdo->prepare("
                        UPDATE farda_atribuicoes
                        SET estado = 'atribuida',
                            estado_devolucao = NULL,
                            data_devolucao = NULL,
                            termo_id = NULL
                        WHERE id = ?
                    ")->execute([$id]);
                }
                // Se era 'em_divida', também reverter para 'atribuida'
                elseif ($estado === 'em_divida') {
                    $pdo->prepare("
                        UPDATE farda_atribuicoes
                        SET estado = 'atribuida',
                            estado_devolucao = NULL,
                            data_devolucao = NULL,
                            termo_id = NULL
                        WHERE id = ?
                    ")->execute([$id]);
                }
            }
            
            // ✅ REATIVAR COLABORADOR
            // Nota: o número de funcionário ficou perdido. O admin terá que atualizar manualmente se necessário.
            $stmt = $pdo->prepare("
                SELECT numero_funcionario FROM colaboradores WHERE id = ? AND ativo = 0
            ");
            $stmt->execute([$colaborador_id]);
            $colaboradorAtual = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($colaboradorAtual) {
                // Se o colaborador está inativo (numero_funcionario vazio), deixar em branco
                // O admin poderá atualizar depois se necessário
                $pdo->prepare("
                    UPDATE colaboradores
                    SET ativo = 1
                    WHERE id = ?
                ")->execute([$colaborador_id]);
            }
            
            $pdo->commit();
            
            // 📝 Registar no log
            $stmt = $pdo->prepare("SELECT nome FROM colaboradores WHERE id = ?");
            $stmt->execute([$colaborador_id]);
            $colabRow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $logMsg = "Termo de devolução revertido para colaborador {$colabRow['nome']} (ID: {$colaborador_id})";
            registarLog($_SESSION['user_id'], "Reverter termo de devolução", $logMsg);
            
            $sucesso = "✅ Termo revertido com sucesso! O colaborador foi reativado e as fardas foram restauradas.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $erro = "❌ Erro ao reverter termo: " . $e->getMessage();
        }
    }
}

// 🔍 Se é GET, buscar termos disponíveis para reverter
$termos = [];
if (!$_POST) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            fa.termo_id,
            c.id AS colaborador_id,
            c.nome AS colaborador_nome,
            c.numero_funcionario,
            COUNT(*) AS total_atribuicoes,
            SUM(fa.quantidade) AS total_quantidade,
            MAX(fa.data_devolucao) AS data_termo
        FROM farda_atribuicoes fa
        JOIN colaboradores c ON fa.colaborador_id = c.id
        WHERE fa.termo_id IS NOT NULL
          AND c.ativo = 0
        GROUP BY fa.termo_id, c.id, c.nome
        ORDER BY fa.data_devolucao DESC
    ");
    $stmt->execute();
    $termos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reverter Termo de Devolução - CrewGest</title>
    <link href="../public/css/style.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; }
        .container { max-width: 900px; margin: 30px auto; padding: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 20px; }
        h1 { color: #d97706; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
        .alert.success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #f3f4f6; padding: 12px; text-align: left; border-bottom: 2px solid #d1d5db; }
        td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        tr:hover { background: #f9fafb; }
        
        .btn { display: inline-block; padding: 10px 16px; border-radius: 4px; text-decoration: none; border: none; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        
        .empty-state { text-align: center; padding: 40px; color: #6b7280; }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.5; }
        
        .confirmation { padding: 20px; background: #fef2f2; border-radius: 4px; margin: 20px 0; }
        .confirmation h3 { color: #991b1b; margin: 0 0 10px 0; }
        
        .atribuicoes-list { max-height: 300px; overflow-y: auto; background: #f9fafb; padding: 12px; border-radius: 4px; }
        .atribuicao-item { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .atribuicao-item:last-child { border-bottom: none; }
        
        .info-group { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .info { padding: 12px; background: #f0f9ff; border-left: 3px solid #3b82f6; }
        .info strong { display: block; color: #1e40af; }
        .info span { color: #64748b; }
    </style>
</head>
<body>
<?php include '../src/templates/header_public.php'; ?>

<main class="container">
    <h1>🔄 Reverter Termo de Devolução</h1>
    
    <?php if ($sucesso): ?>
        <div class="alert success"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="alert error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    
    <?php if ($_POST && $sucesso && !$erro): ?>
        <!-- ✅ RESUMO DA REVERSÃO SUCEDIDA -->
        <div class="card">
            <h2>✅ Processo Completo</h2>
            <p>O termo foi revertido com sucesso. As seguintes ações foram executadas:</p>
            <ul style="line-height: 1.8;">
                <li>✓ Todas as fardas foram restauradas para o estado "atribuído"</li>
                <li>✓ O stock foi reduzido para as fardas que tinham sido devolvidas</li>
                <li>✓ O colaborador foi reativado (se deixou de estar ativo após o termo)</li>
                <li>✓ Todos os registos foram atualizados na base de dados</li>
            </ul>
            <p style="margin-top: 20px; color: #d97706;">⚠️ <strong>Nota:</strong> Se o número de funcionário foi apagado durante a inativação, terá de atualizá-lo manualmente no perfil do colaborador.</p>
            <a href="colaboradores.php" class="btn btn-primary" style="margin-top: 16px;">← Voltar aos colaboradores</a>
        </div>
    <?php elseif (!$_POST): ?>
        <!-- 🔍 LISTA DE TERMOS DISPONÍVEIS PARA REVERTER -->
        <div class="card">
            <h2>📋 Termos para Reverter</h2>
            
            <?php if (empty($termos)): ?>
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 16l-4-4m0 0l-4-4m4 4l4-4m-4 4l-4 4" />
                    </svg>
                    <p>Não existem termos de devolução para reverter.</p>
                    <p style="font-size: 12px;">Apenas colaboradores inativos com termos de devolução aparecem aqui.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Fardas</th>
                            <th>Data do Termo</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($termos as $termo): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($termo['colaborador_nome']) ?></strong><br>
                                    <small style="color: #6b7280;">ID: <?= (int)$termo['colaborador_id'] ?></small>
                                </td>
                                <td><?= (int)$termo['total_quantidade'] ?> peças<br><small>(<?= (int)$termo['total_atribuicoes'] ?> atribuições)</small></td>
                                <td><?= !empty($termo['data_termo']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($termo['data_termo']))) : 'N/D' ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmarRever(<?= (int)$termo['termo_id'] ?>, '<?= htmlspecialchars($termo['colaborador_nome']) ?>');">
                                        <input type="hidden" name="acao" value="reverter_confirmar">
                                        <input type="hidden" name="termo_id" value="<?= (int)$termo['termo_id'] ?>">
                                        <button type="submit" class="btn btn-warning">🔄 Reverter</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="card" style="background: #f0f9ff; border-left: 3px solid #3b82f6;">
        <h3>ℹ️ Como funciona a reversão</h3>
        <ul style="line-height: 1.8; color: #1e40af;">
            <li><strong>Identifica</strong> o termo de devolução criado por engano</li>
            <li><strong>Reativa</strong> o colaborador (volta a estar "ativo" no sistema)</li>
            <li><strong>Restaura</strong> o estado das fardas para "atribuída"</li>
            <li><strong>Corrige</strong> o stock (remove as quantidades que foram re-adicionadas)</li>
            <li><strong>Registra</strong> a ação no histórico de logs</li>
        </ul>
    </div>
</main>

<script>
function confirmarRever(termoId, nomeColaborador) {
    return confirm(`⚠️ Tem a certeza que quer reverter o termo do colaborador "${nomeColaborador}"?\n\nIsso vai:\n✓ Reativar o colaborador\n✓ Restaurar todas as fardas como atribuídas\n✓ Corrigir o stock\n\nEsta ação é irreversível.`);
}
</script>

<?php include '../src/templates/footer.php'; ?>
</body>
</html>
