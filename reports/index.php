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
                                <option value="colaboradores_com_farda">5. Colaboradores com farda atribuída</option>
                                <option value="colaboradores_com_emprestimos">6. Colaboradores com empréstimos activos</option>
                                <option value="colaboradores_com_dividas">7. Colaboradores com dívidas de fardamento</option>
                                <option value="colaboradores_por_departamento">8. Colaboradores por departamento</option>
                            </optgroup>

                            <!-- Fardas -->
                            <optgroup label="Fardas">
                                <option value="fardas_mais_atribuidas">9. Fardas mais atribuídas</option>
                                <option value="fardas_menos_atribuidas">10. Fardas menos atribuídas</option>
                                <option value="stock_atual">11. Stock atual completo</option>
                                <option value="stock_baixo">12. Stock baixo (abaixo do mínimo)</option>
                                <option value="compras_periodo">13. Compras de fardas por período</option>
                                <option value="devolucoes_motivo">14. Devoluções por motivo / estado</option>
                            </optgroup>

                            <!-- Cacifos -->
                            <optgroup label="Cacifos">
                                <option value="cacifos_lista">15. Lista completa de cacifos</option>
                                <option value="cacifos_ocupados">16. Cacifos ocupados</option>
                                <option value="cacifos_livres">17. Cacifos livres</option>
                                <option value="cacifos_avariados">18. Cacifos avariados</option>
                                <option value="cacifos_colabs_inativos">19. Cacifos de colaboradores inativos</option>
                            </optgroup>

                            <!-- Financeiros -->
                            <optgroup label="Financeiros">
                                <option value="valor_total_stock">20. Valor total em stock</option>
                                <option value="custo_por_colaborador">21. Custo de fardamento entregue por colaborador</option>
                                <option value="custo_por_departamento">22. Custo total por departamento</option>
                                <option value="custo_total_fardas">23. Custo total de fardas (atribuídas + stock)</option>
                            </optgroup>

                            <!-- Diversos -->
                            <optgroup label="Diversos">
                                <option value="logs_filtrados">24. Logs de sistema filtráveis</option>
                                <option value="export_ean">25. Export EAN / códigos de barras (CSV)</option>
                                <option value="print_ean">26. Imprimir EAN (etiquetas a partir dos PNGs)</option>
                                <option value="itens_sem_ean">27. Itens de farda sem EAN</option>
                                <option value="historico_atribuicoes">28. Histórico de atribuições</option>
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
                        <button type="button" class="btn btn-primary" onclick="verificarRGPD()">Gerar Relatório</button>
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

<!-- ===== MODAL RGPD ===== -->
<div id="modalRGPD" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:520px;width:95%;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <span style="font-size:1.5rem;">🔒</span>
            <h2 style="margin:0;font-size:1.2rem;font-weight:700;color:#1e3a5f;">Confirmação RGPD</h2>
        </div>

        <p style="color:#374151;margin-bottom:12px;font-size:0.95rem;">
            Este relatório inclui <strong>dados pessoais</strong>. Certifique-se de que o acesso tem base legal ao abrigo do <strong>Regulamento Geral de Proteção de Dados (RGPD, Art.º 6.º)</strong>.
        </p>

        <div id="rgpdCampos" style="background:#f0f4ff;border:1px solid #c7d7f5;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
            <p style="margin:0 0 8px 0;font-weight:600;font-size:0.9rem;color:#1e3a5f;">Campos com dados pessoais neste relatório:</p>
            <ul id="rgpdListaCampos" style="margin:0;padding-left:20px;font-size:0.88rem;color:#374151;"></ul>
        </div>

        <div style="background:#fff8e1;border:1px solid #f59e0b;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.85rem;color:#78350f;">
            ⚠ Os dados gerados neste relatório devem ser utilizados apenas para fins legítimos e armazenados de forma segura. Não partilhe com terceiros sem autorização.
        </div>

        <!-- Seletor de colunas -->
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
            <p style="margin:0 0 10px 0;font-weight:600;font-size:0.9rem;color:#1e3a5f;">Colunas a incluir no relatório:</p>
            <div id="rgpdColunasGrid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;font-size:0.88rem;color:#374151;"></div>
            <div style="margin-top:8px;display:flex;gap:12px;">
                <button type="button" onclick="selecionarTodasColunas(true)" style="font-size:0.8rem;color:#2563eb;background:none;border:none;cursor:pointer;padding:0;">Selecionar todas</button>
                <button type="button" onclick="selecionarTodasColunas(false)" style="font-size:0.8rem;color:#6b7280;background:none;border:none;cursor:pointer;padding:0;">Limpar</button>
            </div>
        </div>

        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:20px;">
            <input type="checkbox" id="rgpdConfirmCheck" style="margin-top:3px;width:16px;height:16px;flex-shrink:0;">
            <span style="font-size:0.9rem;color:#111827;">Confirmo que tenho <strong>base legal</strong> para aceder e tratar estes dados pessoais, nos termos do RGPD.</span>
        </label>

        <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" onclick="fecharModalRGPD()"
                style="padding:9px 20px;background:#e5e7eb;color:#374151;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
                Cancelar
            </button>
            <button type="button" id="rgpdBtnConfirmar" onclick="confirmarRGPD()"
                style="padding:9px 20px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;opacity:0.4;pointer-events:none;">
                ✔ Confirmar e Gerar
            </button>
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

    // Mapa de TODAS as colunas por relatório (para seleção)
    const todasColunasMap = {
        lista_colaboradores:           ['Nº Colaborador','Nome','Cartão','Telefone','Email','Ativo','Criado Em'],
        colaboradores_ativos:          ['Nº Colaborador','Nome','Cartão','Telefone','Email','Criado Em'],
        colaboradores_inativos:        ['Nº Colaborador','Nome','Cartão','Telefone','Email','Criado Em'],
        colaboradores_sem_farda:       ['Nº Colaborador','Nome','Cartão','Email'],
        colaboradores_com_farda:       ['Nº Colaborador','Nome','Cartão','Email'],
        colaboradores_com_emprestimos: ['Nº Colaborador','Nome','Cartão','Email'],
        colaboradores_com_dividas:     ['Colaborador','Nº Funcionário','Departamento','Itens em Dívida','Total em Dívida (€)'],
        colaboradores_por_departamento:['Departamento','Nome','Cartão','Telefone','Email','Ativo'],
        devolucoes_motivo:             ['ID','Farda','Cor','Tamanho','Qtd','Motivo','Data','Colaborador'],
        cacifos_lista:                 ['Número','Colaborador','Avariado'],
        cacifos_ocupados:              ['Número','Colaborador'],
        cacifos_avariados:             ['Número','Colaborador'],
        cacifos_colabs_inativos:       ['Número','Colaborador','Observações'],
        custo_por_colaborador:         ['Colaborador','Total (€)'],
        logs_filtrados:                ['ID','Data','Ação','Detalhes','Utilizador','IP'],
        historico_atribuicoes:         ['ID','Data','Colaborador','Peça','Qtd'],
    };

    // Mapa de campos com dados pessoais por relatório
    const dadosPessoaisMap = {
        lista_colaboradores:           ['Nome', 'Nº Colaborador', 'Cartão', 'Telefone', 'Email'],
        colaboradores_ativos:          ['Nome', 'Nº Colaborador', 'Cartão', 'Telefone', 'Email'],
        colaboradores_inativos:        ['Nome', 'Nº Colaborador', 'Cartão', 'Telefone', 'Email'],
        colaboradores_sem_farda:       ['Nome', 'Nº Colaborador', 'Cartão', 'Email'],
        colaboradores_com_farda:       ['Nome', 'Nº Colaborador', 'Cartão', 'Email'],
        colaboradores_com_emprestimos: ['Nome', 'Nº Colaborador', 'Cartão', 'Email'],
        colaboradores_com_dividas:     ['Nome', 'Nº Colaborador', 'Cartão', 'Email'],
        colaboradores_por_departamento:['Nome', 'Nº Colaborador', 'Cartão', 'Telefone', 'Email'],
        devolucoes_motivo:             ['Nome do Colaborador'],
        cacifos_lista:                 ['Nome do Colaborador'],
        cacifos_ocupados:              ['Nome do Colaborador'],
        cacifos_avariados:             ['Nome do Colaborador'],
        cacifos_colabs_inativos:       ['Nome do Colaborador'],
        custo_por_colaborador:         ['Nome do Colaborador'],
        logs_filtrados:                ['Nome do Utilizador', 'Endereço IP', 'Registo de ações do sistema'],
        historico_atribuicoes:         ['Nome do Colaborador'],
    };

    function verificarRGPD() {
        const val = report.value;
        if (!val) {
            alert('Por favor seleciona um relatório.');
            return;
        }

        // Relatório print_ean é tratado separadamente
        if (val === 'print_ean') {
            submeterFormulario();
            return;
        }

        const campos = dadosPessoaisMap[val];
        if (campos && campos.length > 0) {
            // Preencher lista de campos pessoais
            const lista = document.getElementById('rgpdListaCampos');
            lista.innerHTML = '';
            campos.forEach(c => {
                const li = document.createElement('li');
                li.textContent = c;
                lista.appendChild(li);
            });
            // Preencher seletor de colunas
            const grid = document.getElementById('rgpdColunasGrid');
            grid.innerHTML = '';
            const todasColunas = todasColunasMap[val] || [];
            todasColunas.forEach(col => {
                const lbl = document.createElement('label');
                lbl.style.cssText = 'display:flex;align-items:center;gap:6px;cursor:pointer;';
                const chk = document.createElement('input');
                chk.type = 'checkbox';
                chk.name = 'rgpd_col';
                chk.value = col;
                chk.checked = true;
                chk.style.cssText = 'width:14px;height:14px;flex-shrink:0;';
                lbl.appendChild(chk);
                lbl.appendChild(document.createTextNode(col));
                grid.appendChild(lbl);
            });
            // Reset checkbox RGPD
            const check = document.getElementById('rgpdConfirmCheck');
            check.checked = false;
            atualizarBotaoConfirmar();
            // Mostrar modal
            document.getElementById('modalRGPD').style.display = 'flex';
        } else {
            // Sem dados pessoais — submeter diretamente
            submeterFormulario();
        }
    }

    function atualizarBotaoConfirmar() {
        const check = document.getElementById('rgpdConfirmCheck');
        const btn = document.getElementById('rgpdBtnConfirmar');
        if (check.checked) {
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        } else {
            btn.style.opacity = '0.4';
            btn.style.pointerEvents = 'none';
        }
    }
    document.getElementById('rgpdConfirmCheck').addEventListener('change', atualizarBotaoConfirmar);

    function fecharModalRGPD() {
        document.getElementById('modalRGPD').style.display = 'none';
    }

    function confirmarRGPD() {
        fecharModalRGPD();
        submeterFormulario();
    }

    function selecionarTodasColunas(sel) {
        document.querySelectorAll('#rgpdColunasGrid input[type=checkbox]').forEach(c => c.checked = sel);
    }

    function submeterFormulario() {
        const val = report.value;
        if (val === 'print_ean') {
            const params = new URLSearchParams();
            const deptEl = document.getElementById('departamento');
            if (deptEl && deptEl.value) params.set('departamento', deptEl.value);
            const qEl = form.querySelector('input[name="q"]');
            if (qEl && qEl.value.trim() !== '') params.set('q', qEl.value.trim());
            const url = 'etiquetas_ean_from_pngs.php' + (params.toString() ? ('?' + params.toString()) : '');
            window.open(url, '_blank');
            return;
        }
        // Remover inputs de colunas anteriores
        form.querySelectorAll('input[name="cols[]"]').forEach(el => el.remove());
        // Adicionar colunas selecionadas (se houver seletor ativo)
        const colChecks = document.querySelectorAll('#rgpdColunasGrid input[type=checkbox]');
        if (colChecks.length > 0) {
            const selecionadas = [...colChecks].filter(c => c.checked).map(c => c.value);
            if (selecionadas.length === 0) {
                alert('Seleciona pelo menos uma coluna para incluir no relatório.');
                return;
            }
            selecionadas.forEach(col => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'cols[]';
                inp.value = col;
                form.appendChild(inp);
            });
        }
        form.submit();
    }

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
        if ([
            'fardas_mais_atribuidas',
            'colaboradores_sem_farda',
            'custo_por_departamento',
            'colaboradores_por_departamento'
        ].includes(val)) {
            boxDept.classList.remove('hidden');
        }
        if (val === 'stock_baixo') boxThreshold.classList.remove('hidden');
        if (['logs_filtrados','export_ean','itens_sem_ean','historico_atribuicoes'].includes(val)) boxFreeText.classList.remove('hidden');
    }

    report.addEventListener('change', updateBoxes);
    updateBoxes();
</script>
<?php include_once '../src/templates/footer.php'; ?>

</body>
</html>
