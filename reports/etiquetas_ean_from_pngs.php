<?php
// reports/etiquetas_ean_from_pngs.php
// Gera um relatório (HTML ou PDF) com as imagens PNG de códigos de barras existentes,
// associando cada PNG à peça (por EAN ou farda_id) e aos departamentos da peça.

// carregar ambiente
require_once __DIR__ . '/../src/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// ------------------- parâmetros -------------------
$departamentoFilter = isset($_GET['departamento']) ? (int)$_GET['departamento'] : 0;
$format = $_GET['format'] ?? 'pdf'; // html | pdf
// pasta onde estão os PNGs (relativo a este ficheiro). Podes passar imgDir na querystring para testar.
$defaultImgDir = __DIR__ . '/../public/barcodes';
$imgDirParam = $_GET['imgDir'] ?? null;
$imgDir = $imgDirParam ? realpath(__DIR__ . '/../' . ltrim($imgDirParam, '/')) : $defaultImgDir;
$imgDir = $imgDir ?: $defaultImgDir;

// segurança - verificar pasta
if (!is_dir($imgDir)) {
    http_response_code(500);
    echo "Pasta de imagens inválida: " . htmlspecialchars($imgDir);
    exit;
}

// ------------------- funções auxiliares -------------------
function file_to_data_uri($path) {
    if (!is_readable($path)) return null;
    $data = file_get_contents($path);
    $b64 = base64_encode($data);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $path);
    finfo_close($finfo);
    return "data:$mime;base64,$b64";
}

function fetch_farda_by_ean($pdo, $ean) {
    $stmt = $pdo->prepare("
        SELECT f.id, f.nome, f.ean, f.preco_unitario, f.quantidade,
               c.nome AS cor, t.nome AS tamanho,
               GROUP_CONCAT(DISTINCT d.nome ORDER BY d.nome SEPARATOR '||') AS departamentos
        FROM fardas f
        JOIN cores c ON c.id = f.cor_id
        JOIN tamanhos t ON t.id = f.tamanho_id
        LEFT JOIN farda_departamentos fd ON fd.farda_id = f.id
        LEFT JOIN departamentos d ON d.id = fd.departamento_id
        WHERE f.ean = ?
        GROUP BY f.id
        LIMIT 1
    ");
    $stmt->execute([$ean]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetch_farda_by_id($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT f.id, f.nome, f.ean, f.preco_unitario, f.quantidade,
               c.nome AS cor, t.nome AS tamanho,
               GROUP_CONCAT(DISTINCT d.nome ORDER BY d.nome SEPARATOR '||') AS departamentos
        FROM fardas f
        JOIN cores c ON c.id = f.cor_id
        JOIN tamanhos t ON t.id = f.tamanho_id
        LEFT JOIN farda_departamentos fd ON fd.farda_id = f.id
        LEFT JOIN departamentos d ON d.id = fd.departamento_id
        WHERE f.id = ?
        GROUP BY f.id
        LIMIT 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ------------------- varrer ficheiros PNG -------------------
$items = []; // lista de itens: [ 'file' => , 'path' => , 'match' => assoc row|null ]
$dirIt = new DirectoryIterator($imgDir);
foreach ($dirIt as $fileinfo) {
    if ($fileinfo->isDot() || !$fileinfo->isFile()) continue;
    $ext = strtolower(pathinfo($fileinfo->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','gif'])) continue; // aceitável: png/jpg

    $basename = pathinfo($fileinfo->getFilename(), PATHINFO_FILENAME);

    // tentar extrair EAN (13 dígitos) ou id patterns
    $match = null;
    $row = null;

    // padrão: só dígitos -> pode ser ean ou id; priorizar EAN (13 dígitos)
    if (preg_match('/^(\d{13})$/', $basename, $m)) {
        $ean = $m[1];
        $row = fetch_farda_by_ean($pdo, $ean);
        $match = $row ? 'ean' : null;
    }

    // se ainda não found, tentar farda_N ou apenas número (id)
    if (!$row) {
        if (preg_match('/^farda[_\-]?(\d+)$/i', $basename, $m)) {
            $fid = (int)$m[1];
            $row = fetch_farda_by_id($pdo, $fid);
            $match = $row ? 'id' : null;
        } elseif (preg_match('/^(\d{1,6})$/', $basename, $m)) {
            // pode ser id curto
            $fid = (int)$m[1];
            $row = fetch_farda_by_id($pdo, $fid);
            $match = $row ? 'id' : null;
        }
    }

    $items[] = [
        'file' => $fileinfo->getFilename(),
        'path' => $fileinfo->getPathname(),
        'basename' => $basename,
        'match_type' => $match,
        'farda' => $row // pode ser null
    ];
}

// ------------------- agrupar por departamento -------------------
$groups = []; // departamento => array of items
$unmatched = [];

foreach ($items as $it) {
    $f = $it['farda'];
    if ($f) {
        // departamentos podem estar em 'departamentos' separados por '||'
        $deps = $f['departamentos'] ? explode('||', $f['departamentos']) : ['Sem departamento'];
        // se houve filtro por departamento, só incluir se um dos deps corresponder
        $include = true;
        if ($departamentoFilter) {
            // obter nome do departamento filter
            $s = $pdo->prepare("SELECT nome FROM departamentos WHERE id = ?");
            $s->execute([$departamentoFilter]);
            $depNome = $s->fetchColumn();
            $include = false;
            foreach ($deps as $d) {
                if (trim($d) !== '' && $depNome && trim($d) === $depNome) { $include = true; break; }
            }
        }
        if (!$include) continue;

        foreach ($deps as $dep) {
            $dep = $dep ?: 'Sem departamento';
            if (!isset($groups[$dep])) $groups[$dep] = [];
            $groups[$dep][] = $it;
        }
    } else {
        $unmatched[] = $it;
    }
}

// se existe filtro e não encontrei nada -> aviso mas continua (poderá ser normal)
if ($departamentoFilter && empty($groups)) {
    // procurar nome do departamento para colocar no título
    $s = $pdo->prepare("SELECT nome FROM departamentos WHERE id = ?");
    $s->execute([$departamentoFilter]);
    $depFilterNome = $s->fetchColumn() ?: "ID {$departamentoFilter}";
} else {
    $depFilterNome = null;
}

// ------------------- construir HTML -------------------
$appName = "CrewGest";
$title = "Etiquetas por departamento";
if ($depFilterNome) $title .= " — " . htmlspecialchars($depFilterNome);

$html = '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
$html .= '<style>
body{font-family:Arial,sans-serif;color:#111;margin:20px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.logo{font-weight:800;font-size:20px;color:#0f172a}
.title{font-size:16px;color:#111}
.section{margin-top:18px;margin-bottom:26px}
.section h3{background:#f3f4f6;padding:8px 12px;border-radius:6px}
.grid {
    display: block;
    font-size: 0; /* evita espaços em branco entre inline-blocks */
}

.card {
    display: inline-block;
    width: 27%;              /* 3 por linha */
    margin: 0 1.5% 14px 1.5%;
    font-size: 12px;         /* repõe o tamanho de texto dentro da card */
    vertical-align: top;
    border: 1px solid #e6e6e6;
    border-radius: 6px;
    padding: 8px;
    box-sizing: border-box;
    background: #fff;
}
.card img {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 0 auto 6px auto;
}
.meta{font-size:12px;color:#333}
.small{font-size:11px;color:#666;margin-top:6px}
.footer{font-size:11px;color:#666;margin-top:18px}
</style></head><body>';
$html .= '<div class="header"><div><div class="logo">' . htmlspecialchars($appName) . '</div><div class="title">' . htmlspecialchars($title) . '</div></div>';
$html .= '<div class="small">Gerado em ' . date('d/m/Y H:i') . '</div></div>';

// render grupos
if (empty($groups) && empty($unmatched)) {
    $html .= '<p>Nenhuma imagem encontrada na pasta: ' . htmlspecialchars($imgDir) . '</p>';
} else {
    foreach ($groups as $dep => $list) {
        $html .= '<div class="section"><h3>' . htmlspecialchars($dep) . ' (' . count($list) . ')</h3>';
        $html .= '<div class="grid">';
        foreach ($list as $it) {
            $f = $it['farda'];
            $imgData = file_to_data_uri($it['path']);
            $imgHtml = $imgData ? "<img src=\"$imgData\" alt=\"".htmlspecialchars($it['file'])."\">" : "<div style='height:120px;display:flex;align-items:center;justify-content:center;color:#999;border:1px dashed #ddd;'>Imagem indisponível</div>";
            $html .= '<div class="card">';
            $html .= $imgHtml;
            $html .= '<div class="meta"><strong>' . htmlspecialchars($f['nome']) . '</strong></div>';
            $html .= '<div class="meta">' . htmlspecialchars($f['cor'] . ' / ' . $f['tamanho']) . '</div>';
            $html .= '<div class="meta">EAN: ' . htmlspecialchars($f['ean'] ?? '—') . '</div>';
            $html .= '<div class="small">Farda ID: ' . htmlspecialchars($f['id']) . ' — Stock: ' . (int)$f['quantidade'] . '</div>';
            $html .= '</div>';
        }
        $html .= '</div></div>';
    }

    // unmatched
    if (!empty($unmatched)) {
        $html .= '<div class="section"><h3>Sem correspondência (' . count($unmatched) . ')</h3>';
        $html .= '<div class="grid">';
        foreach ($unmatched as $it) {
            $imgData = file_to_data_uri($it['path']);
            $imgHtml = $imgData ? "<img src=\"$imgData\" alt=\"".htmlspecialchars($it['file'])."\">" : "<div style='height:120px;display:flex;align-items:center;justify-content:center;color:#999;border:1px dashed #ddd;'>Imagem indisponível</div>";
            $html .= '<div class="card">';
            $html .= $imgHtml;
            $html .= '<div class="meta"><strong>' . htmlspecialchars($it['basename']) . '</strong></div>';
            $html .= '<div class="meta">Ficheiro: ' . htmlspecialchars($it['file']) . '</div>';
            $html .= '<div class="small">Nenhuma peça encontrada para este ficheiro (verifica se o nome é EAN ou farda_id).</div>';
            $html .= '</div>';
        }
        $html .= '</div></div>';
    }
}

$html .= '<div class="footer">Relatório gerado por ' . htmlspecialchars($appName) . '</div>';
$html .= '</body></html>';

// ------------------- output -------------------
if ($format === 'html') {
    echo $html;
    exit;
}

// gerar PDF com Dompdf
$options = new Options();
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

// Dompdf pode falhar ao carregar imagens via file:// por permissões; usamos data URIs já embutidos
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'etiquetas_png_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
