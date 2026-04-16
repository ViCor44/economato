<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// 🔐 Apenas Admins podem acessar
if ($_SESSION['role_id'] !== 1) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte e Manutenção - CrewGest</title>
    <link href="css/style.css" rel="stylesheet">
    <style>
        .container { max-width: 1000px; margin: 30px auto; padding: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 25px; margin-bottom: 20px; }
        .section-title { color: #0f172a; font-size: 24px; margin-bottom: 10px; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; }
        .subsection { margin-top: 30px; padding: 20px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px; }
        .subsection h3 { color: #1e40af; margin-top: 0; }
        .step { margin: 15px 0; padding: 12px; background: white; border-left: 3px solid #10b981; }
        .step strong { color: #065f46; }
        .warning { background: #fef2f2; border-left: 3px solid #dc2626; padding: 12px; margin: 15px 0; }
        .warning strong { color: #991b1b; }
        .success { background: #f0fdf4; border-left: 3px solid #16a34a; padding: 12px; margin: 15px 0; }
        .success strong { color: #15803d; }
        .card.highlight { border: 2px solid #f59e0b; }
        .btn-group { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .btn { padding: 10px 16px; border-radius: 4px; text-decoration: none; border: none; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        ul { line-height: 1.8; }
        .feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .feature-box { background: #f9fafb; padding: 15px; border-radius: 4px; border: 1px solid #e5e7eb; }
        .feature-box h4 { margin: 0 0 10px 0; color: #0f172a; }
    </style>
</head>
<body>
<?php include '../src/templates/header.php'; ?>

<main class="container">
    <h1 style="color: #0f172a; text-align: center; margin-bottom: 30px;">🛠️ Suporte e Manutenção</h1>

    <!-- ========== REVERTER TERMO DE DEVOLUÇÃO ========== -->
    <div class="card highlight">
        <h2 class="section-title">🔄 Reverter Termo de Devolução</h2>
        
        <p style="font-size: 16px; color: #475569; line-height: 1.8;">
            Se um <strong>termo de devolução foi gerado por engano</strong>, pode revertê-lo facilmente. 
            Isto devolve o colaborador ao estado anterior e restaura todas as fardas como atribuídas.
        </p>

        <div class="subsection">
            <h3>📋 Como Funciona</h3>
            <ol style="line-height: 2;">
                <li><strong>Identifica o termo</strong> que foi criado incorretamente</li>
                <li><strong>Acede</strong> à página de reversão (botão abaixo)</li>
                <li><strong>Seleciona</strong> o colaborador e o termo a reverter</li>
                <li><strong>Confirma</strong> a operação (irreversível, requer confirmação)</li>
                <li><strong>Valida</strong> os resultados na base de dados</li>
            </ol>
        </div>

        <div class="subsection">
            <h3>✅ O que acontece durante a reversão</h3>
            <div class="step">
                <strong>✓ Reativa o colaborador</strong><br>
                O colaborador volta ao estado "ativo" no sistema
            </div>
            <div class="step">
                <strong>✓ Restaura as fardas</strong><br>
                Todas as fardas marcadas como "devolvidas" voltam a estar "atribuídas"
            </div>
            <div class="step">
                <strong>✓ Corrige o stock</strong><br>
                Reduz o stock das fardas que tinham sido re-adicionadas
            </div>
            <div class="step">
                <strong>✓ Remove a dívida</strong><br>
                Limpa todo o registo de dívida associado ao termo
            </div>
            <div class="step">
                <strong>✓ Registra a ação</strong><br>
                A reversão fica registada nos logs do sistema para auditoria
            </div>
        </div>

        <div class="warning">
            <strong>⚠️ Importante:</strong> Esta ação é irreversível. Certifique-se que quer reverter antes de confirmar.
            O número de funcionário do colaborador, se tiver sido apagado durante a inativação, terá de ser restaurado manualmente.
        </div>

        <div class="btn-group">
            <a href="reverter_termo_devolucao.php" class="btn btn-warning">🔄 Ir para Reversão de Termos</a>
        </div>
    </div>

    <!-- ========== PROBLEMAS COMUNS ========== -->
    <div class="card">
        <h2 class="section-title">❓ Perguntas Frequentes</h2>

        <div class="subsection">
            <h3>P: Posso reverter um termo depois de vários meses?</h3>
            <p>
                <strong>R:</strong> Sim, desde que o termo esteja ainda registado na base de dados (coluna <code>termo_id</code> 
                em <code>farda_atribuicoes</code>). A reversão restaurará o estado anterior.
            </p>
        </div>

        <div class="subsection">
            <h3>P: E se o colaborador tem um novo número de funcionário atribuído?</h3>
            <p>
                <strong>R:</strong> A reversão reativa o colaborador mas não toca no número de funcionário. 
                Se este foi apagado, terá de ser manualmente actualizado no perfil do colaborador.
            </p>
        </div>

        <div class="subsection">
            <h3>P: O que acontece ao stock se a farda foi enviada para reciclagem?</h3>
            <p>
                <strong>R:</strong> Se a farda foi devolvida com destino "stock", o stock é reduzido (porque tinha sido 
                adicionado durante o termo). Se o destino era "reciclagem", o stock não é afetado.
            </p>
        </div>

        <div class="subsection">
            <h3>P: Como faço para reverter múltiplos termos em simultâneo?</h3>
            <p>
                <strong>R:</strong> Terá de reverter um de cada vez através da interface. Cada reversão é registada no log.
            </p>
        </div>
    </div>

    <!-- ========== GUIA PASSO A PASSO ========== -->
    <div class="card">
        <h2 class="section-title">📖 Guia Passo a Passo</h2>

        <div style="background: #f9fafb; padding: 20px; border-radius: 4px; border: 1px solid #e5e7eb;">
            <h3 style="margin-top: 0; color: #0f172a;">Passo 1: Aceder à página de reversão</h3>
            <p>Clique no botão "Reverter Termos" acima ou vá a: <code>public/reverter_termo_devolucao.php</code></p>

            <h3 style="color: #0f172a;">Passo 2: Procurar o termo</h3>
            <p>A página mostra todos os termos disponíveis para reverter (colaboradores inativos com termos gerados). 
            Identifique o colaborador e a data do termo.</p>

            <h3 style="color: #0f172a;">Passo 3: Clicar em "Reverter"</h3>
            <p>Clique no botão 🔄 Reverter correspondente à linha do termo que quer reverter.</p>

            <h3 style="color: #0f172a;">Passo 4: Confirmar</h3>
            <p>Um pop-up pedirá confirmação. Confirme se tem a certeza que quer reverter.</p>

            <h3 style="color: #0f172a;">Passo 5: Validar resultado</h3>
            <p>Após a reversão sucedida, verifique no perfil do colaborador que:</p>
            <ul style="margin: 10px 0;">
                <li>O estado é agora "ativo" ✓</li>
                <li>As fardas estão novamente atribuídas ✓</li>
                <li>Não existem dívidas registadas ✓</li>
            </ul>
        </div>
    </div>

    <!-- ========== CONTACTO ========== -->
    <div class="card">
        <h2 class="section-title">📞 Contacto</h2>
        <p>Se tiver dúvidas ou problemas, contacte o administrador do sistema ou verifique os logs para mais detalhes sobre operações.</p>
        <p style="margin-top: 20px;">
            <a href="index.php" class="btn btn-primary">← Voltar ao Dashboard</a>
        </p>
    </div>
</main>

<?php include '../src/templates/footer.php'; ?>
</body>
</html>
