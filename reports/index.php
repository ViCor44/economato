<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Carregar opções dinâmicas
$departamentos = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$cores = $pdo->query("SELECT id, nome FROM cores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Path para o ficheiro que enviaste (será transformado em URL pelo teu ambiente)
$template_docx_path = '/mnt/data/JARDINEIROS.docx';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Relatórios - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
    <style>
        body { background:#f3f4f6; }
        .container { max-width:1100px; margin:36px auto; padding:20px; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 6px 18px rgba(16,24,40,0.06); }
        .grid { display:grid; grid-template-columns: 1fr 320px; gap:20px; }
        .hidden { display:none; }
        .small { font-size:0.9rem; color:#6b7280; }
        label { display:block; margin-bottom:6px; font-weight:600; color:#374151; }
        .muted { color:#6b7280; }
        .actions { display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
        .btn { padding:10px 14px; border-radius:8px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:8px; cursor:pointer; }
        .btn-primary { background:#2563eb; color:#fff; border:none; }
        .btn-ghost { background:#fff; color:#374151; border:1px solid #e5e7eb; }
        .col { background:#fff; padding:14px; border-radius:10px; }
        .field { margin-bottom:12px; }
        input[type="date"], input[type="number"], select, textarea, input[type="text"] { width:100%; padding:8px 10px; border-radius:8px; border:1px solid #e5e7eb; }
        .checkbox-grid { display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; }
        .small-note { font-size:0.85rem; color:#9ca3af; }
    </style>
</head>
<body>

<?php include '../src/templates/header.php'; ?>

<div class="container">
    <div class="card">
        <h1 class="text-2xl" style="margin:0 0 8px 0">📊 Centro de Relatórios</h1>
        <p class="small" style="margin-bottom:14px">Escolhe o relatório pretendido, define filtros e clica em "Gerar Relatório". O resultado será aberto numa nova aba com opções de export (HTML / PDF / Excel / CSV).</p>

        <div class="grid">
            <div class="col">
                <!-- Form abre em nova aba -->
                <form id="reportForm" action="generate.php" method="GET" target="_blank">

                    <div class="field">
                        <label for="report">Selecionar relatório</label>
                        <select name="report" id="report" required>
                            <option value="">-- Escolha um relatório --</option>

                            <!-- Colaboradores -->
                            <optgroup label="Colaboradores">
                                <option value="lista_colaboradores">1. Lista de todos os colaboradores</option>
                                <option value="colaboradores_ativos">2. Colaboradores ativos</option>
                                <option value="colaboradores_inativos">3. Colaboradores inativos</option>
                                <option value="colaboradores_sem_farda">4. Colaboradores sem farda atribuída</option>
                                <option value="colaboradores_com_emprestimos">5. Colaboradores com empréstimos activos</option>
                            </optgroup>

                            <!-- Fardas -->
                            <optgroup label="Fardas">
                                <option value="fardas_mais_atribuidas">6. Fardas mais atribuídas</option>
                                <option value="fardas_menos_atribuidas">7. Fardas menos atribuídas</option>
                                <option value="stock_atual">8. Stock atual completo</option>
                                <option value="stock_baixo">9. Stock baixo (abaixo do mínimo)</option>
                                <option value="compras_periodo">10. Compras de fardas por período</option>
                                <option value="devolucoes_motivo">11. Devoluções por motivo / estado</option>
                                <option value="inativos_com_farda">12. Colaboradores inativos com farda</option>
                            </optgroup>

                            <!-- Cacifos -->
                            <optgroup label="Cacifos">
                                <option value="cacifos_lista">13. Lista completa de cacifos</option>
                                <option value="cacifos_ocupados">14. Cacifos ocupados</option>
                                <option value="cacifos_livres">15. Cacifos livres</option>
                                <option value="cacifos_avariados">16. Cacifos avariados</option>
                                <option value="cacifos_colabs_inativos">17. Cacifos de colaboradores inativos</option>
                            </optgroup>

                            <!-- Financeiros -->
                            <optgroup label="Financeiros">
                                <option value="valor_total_stock">18. Valor total em stock</option>
                                <option value="custo_por_colaborador">19. Custo de fardamento entregue por colaborador</option>
                                <option value="custo_por_departamento">20. Custo total por departamento</option>
                            </optgroup>

                            <!-- Diversos -->
                            <optgroup label="Diversos">
                                <option value="logs_filtrados">21. Logs de sistema filtráveis</option>
                                <option value="export_ean">22. Export EAN / códigos de barras (CSV)</option>
                                <option value="print_ean">23. Imprimir EAN (etiquetas a partir dos PNGs)</option>
                                <option value="itens_sem_ean">24. Itens de farda sem EAN</option>
                                <option value="historico_atribuicoes">25. Histórico de atribuições</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Datas -->
                    <div id="boxDates" class="field hidden">
                        <label>Período</label>
                        <div style="display:flex; gap:8px;">
                            <input type="date" name="inicio" value="<?= date('Y-m-01') ?>">
                            <input type="date" name="fim" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <!-- Top N -->
                    <div id="boxTop" class="field hidden">
                        <label>Top N</label>
                        <input type="number" name="top" min="1" value="10">
                        <p class="small-note">Quantos resultados queres listar (Top N).</p>
                    </div>

                    <!-- Departamento -->
                    <div id="boxDept" class="field hidden">
                        <label>Departamento (opcional)</label>
                        <select name="departamento" id="departamento">
                            <option value="">-- Todos --</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Threshold -->
                    <div id="boxThreshold" class="field hidden">
                        <label>Threshold (quantidade)</label>
                        <input type="number" name="threshold" min="1" value="5">
                        <p class="small-note">Usado por relatórios de stock baixo.</p>
                    </div>

                    <!-- Texto / filtro livre -->
                    <div id="boxFreeText" class="field hidden">
                        <label>Filtro livre / termo</label>
                        <input type="text" name="q" placeholder="Nome, cartão, EAN, ...">
                    </div>

                    <!-- Export format (quando aplicável) -->
                    <div id="boxFormat" class="field">
                        <label>Formato de saída preferido</label>
                        <select name="format">
                            <option value="html">Visualizar HTML (padrão)</option>
                            <option value="pdf">Forçar PDF</option>
                            <option value="xlsx">Exportar Excel (XLSX)</option>
                            <option value="csv">Exportar CSV</option>
                        </select>
                    </div>

                    <div class="actions" style="margin-top:18px;">
                        <button type="submit" class="btn btn-primary">Gerar Relatório</button>
                    </div>

                </form>
            </div>

            <div>
                <div class="card">
                    <h3 style="margin:0 0 8px 0">Sugestões rápidas</h3>
                    <p class="small">- Quando escolheres relatórios com período, assegura que a data de início é anterior à data fim.<br>
                    - Para export EAN usa formato CSV e importa num software de etiquetas.<br>
                    - Logs têm filtros adicionais no generate.php (user, ação, intervalo).</p>
                </div>

                <div class="card" style="margin-top:14px;">
                    <h3 style="margin:0 0 8px 0">Exportações</h3>
                    <p class="small">Os relatórios suportam export para <strong>HTML, PDF, XLSX, CSV</strong>. O generate.php decide o formato com base no parâmetro <code>format</code>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const report = document.getElementById('report');
    const boxDates = document.getElementById('boxDates');
    const boxTop = document.getElementById('boxTop');
    const boxDept = document.getElementById('boxDept');
    const boxThreshold = document.getElementById('boxThreshold');
    const boxFreeText = document.getElementById('boxFreeText');
    const form = document.getElementById('reportForm');

    function updateBoxes() {
        const val = report.value;
        boxDates.classList.add('hidden');
        boxTop.classList.add('hidden');
        boxDept.classList.add('hidden');
        boxThreshold.classList.add('hidden');
        boxFreeText.classList.add('hidden');

        const needsDate = [
            'fardas_mais_atribuidas', 'fardas_menos_atribuidas', 'compras_periodo',
            'devolucoes_motivo', 'valor_total_stock', 'custo_por_colaborador',
            'custo_por_departamento', 'logs_filtrados', 'historico_atribuicoes'
        ];

        if (needsDate.includes(val)) boxDates.classList.remove('hidden');
        if (['fardas_mais_atribuidas','fardas_menos_atribuidas','custo_por_colaborador','custo_por_departamento'].includes(val)) boxTop.classList.remove('hidden');
        if (['fardas_mais_atribuidas','colaboradores_sem_farda','custo_por_departamento'].includes(val)) boxDept.classList.remove('hidden');
        if (val === 'stock_baixo') boxThreshold.classList.remove('hidden');
        if (['logs_filtrados','export_ean','itens_sem_ean','historico_atribuicoes'].includes(val)) boxFreeText.classList.remove('hidden');
    }

    report.addEventListener('change', updateBoxes);
    // inicializar (se desejar preseleção)
    updateBoxes();

    // Interceptar submit: se relatório for print_ean, abrir etiquetas_ean_from_pngs.php em nova aba
    form.addEventListener('submit', function(e){
        const val = report.value;
        if (val === 'print_ean') {
            e.preventDefault();

            // construir query string com os filtros que fazem sentido para as etiquetas
            const params = new URLSearchParams();

            // departamento (opcional)
            const deptEl = document.getElementById('departamento');
            if (deptEl && deptEl.value) params.set('departamento', deptEl.value);

            // filtro livre / termo (q)
            const qEl = form.querySelector('input[name="q"]');
            if (qEl && qEl.value.trim() !== '') params.set('q', qEl.value.trim());

            // poderás querer passar também format / outras flags
            // abrir numa nova aba a página que processa os PNGs localmente
            const url = 'etiquetas_ean_from_pngs.php' + (params.toString() ? ('?' + params.toString()) : '');
            window.open(url, '_blank');

            // opcional: mostrar um pequeno feedback ao utilizador na mesma aba
            // alert('A abrir etiquetas em nova aba...');
            return false;
        }
        // caso contrário, deixa o form submeter normalmente para generate.php (já tem target="_blank")
    });
</script>

</body>
</html>
