<?php
// reports/generate.php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// --- Helpers ----------------------------------------------------------------

function safeDate($v, $fallback = null) {
    if (!$v) return $fallback;
    $d = date_create($v);
    return $d ? $d->format('Y-m-d') : $fallback;
}

function respondCSV($filename, $columns, $rows) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";
    fputcsv($out, $columns);
    foreach ($rows as $r) {
        $line = [];
        foreach ($columns as $c) $line[] = $r[$c] ?? '';
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

function respondXLSX($filename, $columns, $rows) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $colIndex = 1;
    foreach ($columns as $col) {
        $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . '1';
        $sheet->setCellValue($cell, $col);
    }
    $rowIndex = 2;
    foreach ($rows as $r) {
        $colIndex = 1;
        foreach ($columns as $col) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex;
            $sheet->setCellValue($cell, $r[$col] ?? '');
        }
        $rowIndex++;
    }
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $writer->save('php://output');
    exit;
}

// função melhorada para gerar PDF com header e footer
function respondPDF($htmlBody, $filename, $title = '') {
    // obter nome do utilizador que gerou (se disponível)
    $geradoPor = '';
    if (isset($GLOBALS['utilizador_logado']['nome'])) {
        $geradoPor = $GLOBALS['utilizador_logado']['nome'];
    } elseif (!empty($_SESSION['user_name'])) {
        $geradoPor = $_SESSION['user_name'];
    } elseif (!empty($_SESSION['user_id'])) {
        $geradoPor = 'user_' . $_SESSION['user_id'];
    } else {
        $geradoPor = 'Sistema';
    }

    $timestamp = date('d/m/Y H:i');

    // estilos para header/footer e margem da página
    $css = "
    <style>
        @page {
            margin: 90px 40px 70px 40px; /* top right bottom left */
        }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color:#111; }
        /* header (aparece fixo no topo) */
        .pdf-header {
            position: fixed;
            top: -70px;
            left: 0;
            right: 0;
            height: 70px;
            padding: 10px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pdf-header .app {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
        }
        .pdf-header .title {
            font-size: 14px;
            color: #374151;
        }
        /* footer (aparece fixo no rodapé) */
        .pdf-footer {
            position: fixed;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 50px;
            padding: 8px 20px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #374151;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pdf-footer .left { }
        .pdf-footer .right { }
        .pagenum:before { content: counter(page) ' / ' counter(pages); }

        /* pequenas melhorias para tabelas */
        table { border-collapse: collapse; width:100%; font-size:12px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight:700; }
    </style>
    ";

    // Construir HTML completo com header/footer
    $appName = defined('APP_NAME') ? APP_NAME : 'CrewGest';
    $headerHtml = "
    <div class='pdf-header'>
        <div class='app'>{$appName}</div>
        <div class='title'>".htmlspecialchars($title)."</div>
    </div>";

    $footerHtml = "
    <div class='pdf-footer'>
        <div class='left'>Gerado por: ".htmlspecialchars($geradoPor)." — {$timestamp}</div>
        <div class='right'>Página <span class='pagenum'></span></div>
    </div>";

    $fullHtml = '<!doctype html><html><head><meta charset=\"utf-8\">' . $css . '</head><body>'
              . $headerHtml
              . $footerHtml
              // conteúdo principal precisa de um wrapper para respeitar os offsets
              . "<main style='margin-top:0;'>" . $htmlBody . "</main>"
              . '</body></html>';

    // DOMPDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($fullHtml);
    $dompdf->setPaper('A4');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}


function renderHTMLPage($title, $columns, $rows, $currentQuery) {
    // simples página HTML com botões de export
    $qs = http_build_query($currentQuery);
    $base = strtok($_SERVER["REQUEST_URI"], '?');
    $urlHtml = $base . '?' . $qs . '&format=html';
    $urlPdf  = $base . '?' . $qs . '&format=pdf';
    $urlXlsx = $base . '?' . $qs . '&format=xlsx';
    $urlCsv  = $base . '?' . $qs . '&format=csv';
    ob_start();
    ?>
    <!doctype html>
    <html lang="pt-PT">
    <head>
        <meta charset="utf-8">
        <title><?= htmlspecialchars($title) ?></title>
        <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
        <style>
            body { font-family: Arial, sans-serif; margin:20px; background:#f3f4f6 }
            .card { background:#fff; padding:14px; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,0.06) }
            table { border-collapse:collapse; width:100%; margin-top:12px }
            th, td { border:1px solid #e5e7eb; padding:8px; text-align:left; }
            .toolbar { display:flex; gap:8px; justify-content:flex-end; margin-bottom:8px }
            .btn { padding:8px 12px; border-radius:8px; text-decoration:none; font-weight:600 }
            .btn-primary { background:#2563eb; color:white }
            .btn-ghost { background:#fff; border:1px solid #e5e7eb; color:#374151 }
            .notice { background:#fff8e6; border-left:4px solid #f59e0b; padding:10px; margin-bottom:12px; border-radius:6px }
            .debug { background:#0b1220; color:#cbd5e1; padding:12px; border-radius:6px; font-family:monospace; white-space:pre-wrap; margin-bottom:12px }
        </style>
    </head>
    <body>
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="margin:0"><?= htmlspecialchars($title) ?></h2>
            <div class="toolbar">
                <a class="btn btn-ghost" href="<?= $urlHtml ?>" target="_blank">Ver HTML</a>
                <a class="btn btn-ghost" href="<?= $urlCsv ?>" target="_blank">Exportar CSV</a>
                <a class="btn btn-ghost" href="<?= $urlXlsx ?>" target="_blank">Exportar Excel</a>
                <a class="btn btn-primary" href="<?= $urlPdf ?>" target="_blank">Exportar PDF</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <?php foreach ($columns as $c): ?><th><?= htmlspecialchars($c) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($columns) ?>">Nenhum registo encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php foreach ($columns as $c): ?>
                                <td><?= nl2br(htmlspecialchars($r[$c] ?? '')) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </body>
    </html>
    <?php
    echo ob_get_clean();
    exit;
}

// --- Receber parâmetros ----------------------------------------------------

$report = $_GET['report'] ?? '';
$format = $_GET['format'] ?? 'html'; // html | pdf | xlsx | csv
$inicio = safeDate($_GET['inicio'], date('2000-01-01'));
$fim = safeDate($_GET['fim'], date('Y-m-d'));
$top = max(1, intval($_GET['top'] ?? 10));
$departamento = intval($_GET['departamento'] ?? 0);
$threshold = max(1, intval($_GET['threshold'] ?? 5));
$q = trim($_GET['q'] ?? '');

// normalize report
$report = trim(str_replace(['..','/'], '', $report));

// normalize full datetime range
$inicioFull = $inicio . " 00:00:00";
$fimFull    = $fim . " 23:59:59";

// --- Switch reports --------------------------------------------------------

try {
    switch ($report) {

        // ------------------- Colaboradores -------------------
        case 'lista_colaboradores':
            $title = "Lista de Todos os Colaboradores";
            $stmt = $pdo->query("SELECT id, nome, cartao, telefone, email, ativo, criado_em FROM colaboradores ORDER BY nome ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Nome','Cartão','Telefone','Email','Ativo','Criado Em'];
            $rows = array_map(function($r){
                return [
                    'ID'=>$r['id'],
                    'Nome'=>$r['nome'],
                    'Cartão'=>$r['cartao'],
                    'Telefone'=>$r['telefone'] ?? '',
                    'Email'=>$r['email'] ?? '',
                    'Ativo'=>($r['ativo'] ? 'Sim' : 'Não'),
                    'Criado Em'=>$r['criado_em']
                ];
            }, $data);
            break;

        case 'colaboradores_ativos':
            $title = "Colaboradores Ativos";
            $stmt = $pdo->prepare("SELECT id, nome, cartao, telefone, email, criado_em FROM colaboradores WHERE ativo = 1 ORDER BY nome ASC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Nome','Cartão','Telefone','Email','Criado Em'];
            $rows = array_map(function($r){
                return [
                    'ID'=>$r['id'],
                    'Nome'=>$r['nome'],
                    'Cartão'=>$r['cartao'],
                    'Telefone'=>$r['telefone'] ?? '',
                    'Email'=>$r['email'] ?? '',
                    'Criado Em'=>$r['criado_em']
                ];
            }, $data);
            break;

        case 'colaboradores_inativos':
            $title = "Colaboradores Inativos";
            $stmt = $pdo->prepare("SELECT id, nome, cartao, telefone, email, criado_em FROM colaboradores WHERE ativo = 0 ORDER BY nome ASC");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Nome','Cartão','Telefone','Email','Criado Em'];
            $rows = array_map(function($r){
                return [
                    'ID'=>$r['id'],
                    'Nome'=>$r['nome'],
                    'Cartão'=>$r['cartao'],
                    'Telefone'=>$r['telefone'] ?? '',
                    'Email'=>$r['email'] ?? '',
                    'Criado Em'=>$r['criado_em']
                ];
            }, $data);
            break;

        case 'colaboradores_sem_farda':
            $title = "Colaboradores sem Farda Atribuída";
            $stmt = $pdo->prepare("
                SELECT c.id, c.nome, c.cartao, c.email
                FROM colaboradores c
                LEFT JOIN farda_atribuicoes fa ON fa.colaborador_id = c.id
                WHERE fa.id IS NULL
                ORDER BY c.nome ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Nome','Cartão','Email'];
            $rows = array_map(function($r){ return ['ID'=>$r['id'],'Nome'=>$r['nome'],'Cartão'=>$r['cartao'],'Email'=>$r['email']]; }, $data);
            break;

        case 'colaboradores_com_emprestimos':
            $title = "Colaboradores com Empréstimos Activos";
            // assumindo tabela farda_emprestimos com campo devolvido (0/1)
            $stmt = $pdo->prepare("
                SELECT DISTINCT c.id, c.nome, c.cartao, c.email
                FROM colaboradores c
                JOIN farda_emprestimos fe ON fe.colaborador_id = c.id
                WHERE fe.devolvido = 0
                ORDER BY c.nome ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Nome','Cartão','Email'];
            $rows = array_map(function($r){ return ['ID'=>$r['id'],'Nome'=>$r['nome'],'Cartão'=>$r['cartao'],'Email'=>$r['email']]; }, $data);
            break;

        // ------------------- Fardas -------------------
        case 'fardas_mais_atribuidas':
            $title = "Fardas Mais Atribuídas";
            $sql = "
                SELECT f.id, f.nome AS farda, c.nome AS cor, t.nome AS tamanho,
                       SUM(fa.quantidade) AS total_atribuido,
                       COUNT(DISTINCT fa.colaborador_id) AS colaboradores,
                       MIN(fa.data_atribuicao) AS primeiro_registo,
                       MAX(fa.data_atribuicao) AS ultimo_registo
                FROM farda_atribuicoes fa
                JOIN fardas f ON f.id = fa.farda_id
                JOIN cores c ON c.id = f.cor_id
                JOIN tamanhos t ON t.id = f.tamanho_id
                WHERE fa.data_atribuicao BETWEEN ? AND ?
            ";
            if ($departamento) {
                $sql .= " AND EXISTS(SELECT 1 FROM farda_departamentos fd WHERE fd.farda_id = f.id AND fd.departamento_id = ?) ";
            }
            $sql .= " GROUP BY f.id ORDER BY total_atribuido DESC LIMIT ?";
            $stmt = $pdo->prepare($sql);
            if ($departamento) {
                $stmt->execute([$inicioFull, $fimFull, $departamento, $top]);
            } else {
                $stmt->execute([$inicioFull, $fimFull, $top]);
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Farda','Cor','Tamanho','Total Atribuído','Colaboradores','Primeiro','Último'];
            $rows = array_map(function($r){
                return [
                    'Farda'=>$r['farda'],
                    'Cor'=>$r['cor'],
                    'Tamanho'=>$r['tamanho'],
                    'Total Atribuído'=>$r['total_atribuido'],
                    'Colaboradores'=>$r['colaboradores'],
                    'Primeiro'=>$r['primeiro_registo'],
                    'Último'=>$r['ultimo_registo']
                ];
            }, $data);
            break;

        case 'fardas_menos_atribuidas':
            $title = "Fardas Menos Atribuídas ({$inicio} → {$fim})";
            $sql = "
                SELECT f.id, f.nome AS farda, c.nome AS cor, t.nome AS tamanho,
                       SUM(fa.quantidade) AS total_atribuido
                FROM farda_atribuicoes fa
                JOIN fardas f ON f.id = fa.farda_id
                JOIN cores c ON c.id = f.cor_id
                JOIN tamanhos t ON t.id = f.tamanho_id
                WHERE fa.data_atribuicao BETWEEN ? AND ?
                GROUP BY f.id
                ORDER BY total_atribuido ASC
                LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicioFull, $fimFull, $top]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Farda','Cor','Tamanho','Total Atribuído'];
            $rows = array_map(function($r){
                return ['Farda'=>$r['farda'],'Cor'=>$r['cor'],'Tamanho'=>$r['tamanho'],'Total Atribuído'=>$r['total_atribuido']];
            }, $data);
            break;

        case 'stock_atual':
            $title = "Stock Atual de Fardas";
            $stmt = $pdo->query("
                SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.preco_unitario, f.quantidade
                FROM fardas f
                JOIN cores c ON c.id = f.cor_id
                JOIN tamanhos t ON t.id = f.tamanho_id
                ORDER BY f.nome ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Farda','Cor','Tamanho','Preço Unit. (€)','Quantidade','Valor Total (€)'];
            $rows = array_map(function($r){
                $valor = $r['preco_unitario'] * $r['quantidade'];
                return [
                    'ID'=>$r['id'],
                    'Farda'=>$r['nome'],
                    'Cor'=>$r['cor'],
                    'Tamanho'=>$r['tamanho'],
                    'Preço Unit. (€)'=>number_format($r['preco_unitario'],2,',','.'),
                    'Quantidade'=>$r['quantidade'],
                    'Valor Total (€)'=>number_format($valor,2,',','.')
                ];
            }, $data);
            break;

        case 'stock_baixo':
            $title = "Stock Baixo (≤ {$threshold})";
            $stmt = $pdo->prepare("
                SELECT f.id, f.nome, c.nome AS cor, t.nome AS tamanho, f.quantidade
                FROM fardas f
                JOIN cores c ON c.id = f.cor_id
                JOIN tamanhos t ON t.id = f.tamanho_id
                WHERE f.quantidade <= ?
                ORDER BY f.quantidade ASC
            ");
            $stmt->execute([$threshold]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Farda','Cor','Tamanho','Quantidade'];
            $rows = array_map(function($r){ return ['ID'=>$r['id'],'Farda'=>$r['nome'],'Cor'=>$r['cor'],'Tamanho'=>$r['tamanho'],'Quantidade'=>$r['quantidade']]; }, $data);
            break;

        case 'compras_periodo':
            $title = "Compras de Fardas ({$inicio} → {$fim})";
            $stmt = $pdo->prepare("
                SELECT fc.id, f.nome, c.nome AS cor, t.nome AS tamanho, fc.quantidade_adicionada, fc.preco_compra, fc.data_compra, u.nome AS criado_por
                FROM farda_compras fc
                JOIN fardas f ON f.id = fc.farda_id
                JOIN cores c ON c.id = f.cor_id
                JOIN tamanhos t ON t.id = f.tamanho_id
                LEFT JOIN utilizadores u ON u.id = fc.criado_por
                WHERE fc.data_compra BETWEEN ? AND ?
                ORDER BY fc.data_compra DESC
            ");
            $stmt->execute([$inicioFull, $fimFull]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Farda','Cor','Tamanho','Qtd Adicionada','Preço Compra (€)','Data','Criado Por'];
            $rows = array_map(function($r){ return [
                'ID'=>$r['id'],'Farda'=>$r['nome'],'Cor'=>$r['cor'],'Tamanho'=>$r['tamanho'],
                'Qtd Adicionada'=>$r['quantidade_adicionada'],
                'Preço Compra (€)'=>number_format($r['preco_compra'],2,',','.'),
                'Data'=>$r['data_compra'],'Criado Por'=>$r['criado_por'] ?? ''
            ]; }, $data);
            break;

        case 'devolucoes_motivo':
            $title = "Devoluções ({$inicio} → {$fim})";

            // garantir que temos timestamps completos (se ainda não existirem no teu script)
            $inicioFull = $inicio . ' 00:00:00';
            $fimFull    = $fim . ' 23:59:59';

            $stmt = $pdo->prepare("
                SELECT fd.id,
                    f.nome,
                    c.nome AS cor,
                    t.nome AS tamanho,
                    fd.quantidade,
                    fd.motivo,
                    fd.data_baixa,            -- ou fd.criado_em conforme o teu esquema
                    fa.colaborador_id,
                    col.nome AS colaborador
                FROM farda_baixas fd
                JOIN fardas f ON f.id = fd.farda_id
                JOIN cores c ON c.id = f.cor_id
                JOIN tamanhos t ON t.id = f.tamanho_id
                LEFT JOIN farda_atribuicoes fa ON fa.id = fd.farda_id
                LEFT JOIN colaboradores col ON col.id = fa.colaborador_id
                WHERE fd.data_baixa BETWEEN ? AND ?
                ORDER BY fd.data_baixa DESC
            ");
            $stmt->execute([$inicioFull, $fimFull]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $columns = ['ID','Farda','Cor','Tamanho','Qtd','Motivo','Data','Colaborador'];
            $rows = array_map(function($r){
                return [
                    'ID' => $r['id'],
                    'Farda' => $r['nome'],
                    'Cor' => $r['cor'],
                    'Tamanho' => $r['tamanho'],
                    'Qtd' => $r['quantidade'],
                    'Motivo' => $r['motivo'],
                    'Data' => $r['data_baixa'],
                    'Colaborador' => $r['colaborador'] ?? ('ID ' . ($r['colaborador_id'] ?? 'N/A'))
                ];
            }, $data);
            break;

        // ------------------- Cacifos -------------------
        case 'cacifos_lista':
            $title = "Lista Completa de Cacifos";
            $stmt = $pdo->query("SELECT numero, colaborador_id, avariado FROM cacifos ORDER BY numero ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Número','Colaborador','Avariado'];
            $rows = [];
            foreach ($data as $r) {
                $nome = '';
                if ($r['colaborador_id']) {
                    $s = $pdo->prepare("SELECT nome FROM colaboradores WHERE id = ?");
                    $s->execute([$r['colaborador_id']]);
                    $nome = $s->fetchColumn() ?: ("ID " . $r['colaborador_id']);
                }
                $rows[] = ['Número'=>$r['numero'],'Colaborador'=>$nome,'Avariado'=>($r['avariado'] ? 'Sim' : 'Não')];
            }
            break;

        case 'cacifos_ocupados':
            $title = "Cacifos Ocupados";
            $stmt = $pdo->query("SELECT numero, colaborador_id FROM cacifos WHERE colaborador_id IS NOT NULL ORDER BY numero ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Número','Colaborador'];
            $rows = [];
            foreach ($data as $r) {
                $s = $pdo->prepare("SELECT nome FROM colaboradores WHERE id = ?");
                $s->execute([$r['colaborador_id']]);
                $nome = $s->fetchColumn() ?: ("ID " . $r['colaborador_id']);
                $rows[] = ['Número'=>$r['numero'],'Colaborador'=>$nome];
            }
            break;

        case 'cacifos_livres':
            $title = "Cacifos Livres";
            $stmt = $pdo->query("SELECT numero FROM cacifos WHERE colaborador_id IS NULL AND avariado = 0 ORDER BY numero ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Número'];
            $rows = array_map(function($r){ return ['Número'=>$r['numero']]; }, $data);
            break;

        case 'cacifos_avariados':
            $title = "Cacifos Avariados";
            $stmt = $pdo->query("SELECT numero, colaborador_id FROM cacifos WHERE avariado = 1 ORDER BY numero ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Número','Colaborador'];
            $rows = [];
            foreach ($data as $r) {
                $nome = '';
                if ($r['colaborador_id']) {
                    $s = $pdo->prepare("SELECT nome FROM colaboradores WHERE id = ?");
                    $s->execute([$r['colaborador_id']]);
                    $nome = $s->fetchColumn() ?: ("ID " . $r['colaborador_id']);
                }
                $rows[] = ['Número'=>$r['numero'],'Colaborador'=>$nome];
            }
            break;

        case 'cacifos_colabs_inativos':
            $title = "Cacifos de Colaboradores Inativos";
            $stmt = $pdo->query("
                SELECT c.numero, col.nome, col.id
                FROM cacifos c
                JOIN colaboradores col ON col.id = c.colaborador_id
                WHERE col.ativo = 0
                ORDER BY c.numero ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Número','Colaborador','Observações'];
            $rows = array_map(function($r){ return ['Número'=>$r['numero'],'Colaborador'=>$r['nome'],'Colaborador ID'=>'']; }, $data);
            break;

        // ------------------- Financeiros -------------------
        case 'valor_total_stock':
            $title = "Valor Total em Stock";
            $stmt = $pdo->query("SELECT SUM(preco_unitario * quantidade) FROM fardas");
            $valor = $stmt->fetchColumn();
            $columns = ['Descrição','Valor (€)'];
            $rows = [['Descrição'=>'Valor total atual em stock','Valor (€)'=>number_format($valor ?? 0,2,',','.')]];
            break;

        case 'custo_por_colaborador':
            $title = "Custo de Fardamento por Colaborador ({$inicio} → {$fim})";
            $sql = "
                SELECT col.id AS colaborador_id, col.nome AS colaborador,
                       SUM(f.preco_unitario * fa.quantidade) AS total
                FROM farda_atribuicoes fa
                JOIN fardas f ON f.id = fa.farda_id
                JOIN colaboradores col ON col.id = fa.colaborador_id
                WHERE fa.data_atribuicao BETWEEN ? AND ?
                GROUP BY col.id
                ORDER BY total DESC
                LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicioFull, $fimFull, $top]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Colaborador','Total (€)'];
            $rows = array_map(function($r){ return ['Colaborador'=>$r['colaborador'],'Total (€)'=>number_format($r['total'],2,',','.')]; }, $data);
            break;

        case 'custo_por_departamento':
            $title = "Custo Total por Departamento ({$inicio} → {$fim})";
            $sql = "
                SELECT d.id AS departamento_id, d.nome AS departamento,
                       SUM(f.preco_unitario * fa.quantidade) AS total
                FROM farda_atribuicoes fa
                JOIN fardas f ON f.id = fa.farda_id
                JOIN colaboradores col ON col.id = fa.colaborador_id
                JOIN departamentos d ON d.id = col.departamento_id
                WHERE fa.data_atribuicao BETWEEN ? AND ?
                GROUP BY d.id
                ORDER BY total DESC
                LIMIT ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicioFull, $fimFull, $top]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['Departamento','Total (€)'];
            $rows = array_map(function($r){ return ['Departamento'=>$r['departamento'],'Total (€)'=>number_format($r['total'],2,',','.')]; }, $data);
            break;

        // ------------------- Diversos -------------------
        case 'logs_filtrados':
            $title = "Logs do Sistema";
            $sql = "SELECT l.id, l.criado_em, l.acao, l.detalhes, u.nome AS usuario, l.ip FROM logs l LEFT JOIN utilizadores u ON u.id = l.user_id WHERE 1=1 ";
            $params = [];
            if ($inicio && $fim) {
                $sql .= " AND l.criado_em BETWEEN ? AND ? ";
                $params[] = $inicioFull;
                $params[] = $fimFull;
            }
            if ($q) {
                $sql .= " AND (l.acao LIKE ? OR l.detalhes LIKE ? OR u.nome LIKE ?) ";
                $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
            }
            $sql .= " ORDER BY l.id DESC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // substituir IDs por nomes quando possível (ex. na string detalhes)
            foreach ($data as &$row) {
                // tenta extrair "Utilizador ID X" e trocar pelo nome se existir
                if (preg_match_all('/(?:Utilizador|user) ID (\d+)/i', $row['detalhes'], $m)) {
                    foreach ($m[1] as $id) {
                        $s = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
                        $s->execute([$id]);
                        $nome = $s->fetchColumn();
                        if ($nome) $row['detalhes'] = str_ireplace("Utilizador ID $id", $nome, $row['detalhes']);
                    }
                }
            }
            $columns = ['ID','Data','Ação','Detalhes','Utilizador','IP'];
            $rows = array_map(function($r){ return ['ID'=>$r['id'],'Data'=>$r['criado_em'],'Ação'=>$r['acao'],'Detalhes'=>$r['detalhes'],'Utilizador'=>$r['usuario'] ?? '','IP'=>$r['ip']]; }, $data);
            break;

        case 'export_ean':
            $title = "Export EAN / Códigos de Barra";
            $stmt = $pdo->query("SELECT id, nome, ean FROM fardas ORDER BY nome ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Nome','EAN'];
            $rows = array_map(function($r){ return ['ID'=>$r['id'],'Nome'=>$r['nome'],'EAN'=>$r['ean']]; }, $data);
            break;

        case 'itens_sem_ean':
            $title = "Itens de Farda sem EAN";
            $stmt = $pdo->query("SELECT id, nome, cor_id, tamanho_id FROM fardas WHERE IFNULL(ean,'') = '' ORDER BY nome ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Nome','Cor ID','Tamanho ID'];
            $rows = array_map(function($r){ return ['ID'=>$r['id'],'Nome'=>$r['nome'],'Cor ID'=>$r['cor_id'],'Tamanho ID'=>$r['tamanho_id']]; }, $data);
            break;

        case 'historico_atribuicoes':
            $title = "Histórico de Atribuições";
            $sql = "
                SELECT fa.id, fa.data_atribuicao, col.nome AS colaborador, f.nome AS farda, fa.quantidade
                FROM farda_atribuicoes fa
                JOIN colaboradores col ON col.id = fa.colaborador_id
                JOIN fardas f ON f.id = fa.farda_id
                WHERE fa.data_atribuicao BETWEEN ? AND ?
                ORDER BY fa.data_atribuicao DESC
                LIMIT 1000
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$inicioFull, $fimFull]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $columns = ['ID','Data','Colaborador','Peça','Qtd'];
            $rows = array_map(function($r){ return ['ID'=>$r['id'],'Data'=>$r['data_atribuicao'],'Colaborador'=>$r['colaborador'],'Peça'=>$r['farda'],'Qtd'=>$r['quantidade']]; }, $data);
            break;

        // ------------------- Defaults / not found -------------------
        default:
            http_response_code(400);
            echo "Relatório inválido ou não especificado.";
            exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo "Erro a gerar relatório: " . htmlspecialchars($e->getMessage());
    exit;
}

// --- Entregar output conforme format --------------------------------------

$currentQuery = $_GET;
$currentQuery['report'] = $report;
$currentQuery['inicio'] = $inicio;
$currentQuery['fim'] = $fim;

if ($format === 'csv') {
    $filename = "report_{$report}_" . date('Ymd_His') . ".csv";
    respondCSV($filename, $columns, $rows);
} elseif ($format === 'xlsx') {
    $filename = "report_{$report}_" . date('Ymd_His') . ".xlsx";
    respondXLSX($filename, $columns, $rows);
} elseif ($format === 'pdf') {
    // gerar um HTML simples (o corpo) e passar para a função respondPDF
    ob_start();
    // aqui podes manter exatamente o HTML de tabela que já tens
    
    echo "<p style='margin: 0 0 12px 0; font-size:12px; color:#555;'>
            <strong>Período:</strong> "
            . htmlspecialchars($inicio) . " - " . htmlspecialchars($fim) .
        "</p>";    
    echo "<table style='border-collapse:collapse; width:100%;'>";
    echo "<thead><tr>";
    foreach ($columns as $c) echo "<th style='padding:6px;'>" . htmlspecialchars($c) . "</th>";
    echo "</tr></thead><tbody>";
    if (empty($rows)) {
        echo "<tr><td colspan='".count($columns)."' style='padding:8px;'>Nenhum registo encontrado.</td></tr>";
    } else {
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($columns as $c) echo "<td>" . nl2br(htmlspecialchars($r[$c] ?? '')) . "</td>";
            echo "</tr>";
        }
    }
    echo "</tbody></table>";

    // adicionar rodapé de geração (visível dentro do corpo também)

    $html = ob_get_clean();
    $filename = "report_{$report}_" . date('Ymd_His') . ".pdf";
    respondPDF($html, $filename, $title);
} else {
    // HTML view com botões de exportação
    renderHTMLPage($title, $columns, $rows, $currentQuery);
}
