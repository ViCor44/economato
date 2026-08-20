<?php
require_once '../config/db.php';
require_once '../src/auth_guard.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function garantirVersionamentoDocumentos(PDO $pdo): void
{
    $colunasNecessarias = [
        'estado' => "ALTER TABLE documentos ADD COLUMN estado ENUM('valido','invalidado') NOT NULL DEFAULT 'valido' AFTER criado_em",
        'invalidado_em' => "ALTER TABLE documentos ADD COLUMN invalidado_em DATETIME NULL AFTER estado",
        'motivo_invalidacao' => "ALTER TABLE documentos ADD COLUMN motivo_invalidacao VARCHAR(255) NULL AFTER invalidado_em",
        'invalida_documento_id' => "ALTER TABLE documentos ADD COLUMN invalida_documento_id INT NULL AFTER motivo_invalidacao",
        'invalidado_por_documento_id' => "ALTER TABLE documentos ADD COLUMN invalidado_por_documento_id INT NULL AFTER invalida_documento_id",
    ];

    foreach ($colunasNecessarias as $nomeColuna => $ddl) {
        $stmt = $pdo->query("SHOW COLUMNS FROM documentos LIKE '" . $nomeColuna . "'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec($ddl);
        }
    }

    $indicesNecessarios = [
        'idx_documentos_colaborador_tipo_estado' => "CREATE INDEX idx_documentos_colaborador_tipo_estado ON documentos (colaborador_id, tipo, estado)",
        'idx_documentos_invalidado_por' => "CREATE INDEX idx_documentos_invalidado_por ON documentos (invalidado_por_documento_id)",
        'idx_documentos_invalida' => "CREATE INDEX idx_documentos_invalida ON documentos (invalida_documento_id)",
    ];

    foreach ($indicesNecessarios as $nomeIndice => $ddl) {
        $stmt = $pdo->query("SHOW INDEX FROM documentos WHERE Key_name = '" . $nomeIndice . "'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec($ddl);
        }
    }
}

/* ======================================================
   CONFIG QR / BASE URL
====================================================== */

$baseUrlValidacao = 'http://191.188.126.13/economato/public/validar_documento.php';

/* ======================================================
   GERAR CÓDIGO ÚNICO
====================================================== */

$codigoDocumento = bin2hex(random_bytes(16));
$urlValidacao = $baseUrlValidacao . '?codigo=' . $codigoDocumento;

/* ======================================================
   FUNÇÃO GERAR QR (API externa)
====================================================== */

function gerarQrCode($texto, $path) {

    $api = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($texto);

    $img = file_get_contents($api);

    file_put_contents($path, $img);
}

/* ======================================================
   COLABORADOR
====================================================== */

$colaborador_id = $_GET['colaborador_id'] ?? 0;
if ($colaborador_id <= 0) {
    die("Colaborador inválido.");
}

$stmt = $pdo->prepare("
    SELECT c.id, c.nome, c.email, d.nome AS departamento
    FROM colaboradores c
    LEFT JOIN departamentos d ON c.departamento_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

garantirVersionamentoDocumentos($pdo);

$stmt = $pdo->prepare("
    SELECT id, codigo, criado_em
    FROM documentos
    WHERE colaborador_id = ?
      AND tipo = 'termo_farda'
      AND estado = 'valido'
    ORDER BY criado_em DESC, id DESC
    LIMIT 1
");
$stmt->execute([$colaborador_id]);
$termoAnterior = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

/* ======================================================
   FARDAS
====================================================== */

$stmt = $pdo->prepare("
    SELECT 
        f.nome,
        c.nome AS cor,
        t.nome AS tamanho,
        SUM(fa.quantidade) AS quantidade_total,
        DATE(fa.data_atribuicao) AS data_atribuicao,
        f.preco_unitario
    FROM farda_atribuicoes fa
    JOIN fardas f ON fa.farda_id = f.id
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE fa.colaborador_id = ?
      AND fa.estado = 'atribuida'
    GROUP BY f.id, f.nome, c.nome, t.nome, f.preco_unitario, DATE(fa.data_atribuicao)
    ORDER BY data_atribuicao ASC, f.nome ASC
");
$stmt->execute([$colaborador_id]);
$fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$fardas_atribuidas) {
    die("O colaborador não tem fardas atribuídas.");
}

$data_atribuicao = $fardas_atribuidas[0]['data_atribuicao'];

/* ======================================================
   GERAR QR
====================================================== */

$qrPath = __DIR__ . '/../storage/qrcodes/' . $codigoDocumento . '.png';

if (!is_dir(dirname($qrPath))) {
    mkdir(dirname($qrPath), 0777, true);
}

gerarQrCode($urlValidacao, $qrPath);

$qrBase64 = base64_encode(file_get_contents($qrPath));

/* ======================================================
   GERAR PDF
====================================================== */

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

/* ---------------- HTML ---------------- */

$html = '
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<style>
    body { 
        font-family: Arial, sans-serif; 
        font-size: 14px; 
        margin: 20px; 
        text-align: justify;
    }
    h1 { text-align: center; margin-bottom: 20px; font-size: 18px; }
    h2 { margin-top: 30px; font-size: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table, th, td { border: 1px solid black; }
    th, td { padding: 6px; font-size: 10px; }
    ul { margin-left: 20px; font-size: 11px;}
    p { text-align: justify; font-size: 11px; }
</style>
</head>
<body>

<h1>ENTREGA DE FARDAMENTO — '.htmlspecialchars($colaborador['departamento']).'</h1>

<p><strong>Nome:</strong> '.htmlspecialchars($colaborador['nome']).'</p>

<h2>Termo de Responsabilidade</h2>

<p>Eu, colaborador acima identificado, recebo a farda entregue pela empresa Correia & Santinha, Lda., contribuinte nº 501 585 583 na data indicada as peças de fardamento de trabalho que abaixo estão assinaladas, comprometendo-me a usá-las exclusivamente na minha atividade profissional, com zelo de forma a garantir o seu bom estado de conservação e asseio, ao serviço da Correia & Santinha, Lda. </p>
<p>Declaro ainda estar ciente que:</p>

<ul>
    <li>a) As peças de fardamento que recebi são propriedade da Correia & Santinha, Lda e que, quando cessar o meu vínculo contratual com a empresa as devolverei nas melhores condições.</li><br>
    <li>b) quando se revelar necessária a substituição de alguma peça do uniforme, decorrente da normal utilização no serviço, a mesma ocorrerá mediante a entrega da peça em mau estado.</li><br>
    <li>c) poderei ser responsabilizado caso se verifique que o uso dado ao uniforme não respeita as regras estabelecidas pela empresa;</li><br>
    <li>d) em caso de extravio ou falta de apresentação do uniforme aquando a sua substituição, poderei ser obrigado a repor o fardamento pelo valor correspondente ao pago pela empresa.</li><br>
    <li>e) o pagamento do fardamento extraviado ou danificado sem justificação, poderá ser liquidado pelo colaborador ou descontado no respetivo vencimento mensal.</li>
</ul>

<h2>Peças Entregues</h2>

<table>
<thead>
<tr>
    <th>Peça</th>
    <th>Cor</th>
    <th>Tamanho</th>
    <th>Qtd</th>
    <th>Data de atribuição</th>
    <th>Preço Unit. (€)</th>
    <th>Total (€)</th>
</tr>
</thead>
<tbody>';

$valor_total_geral = 0;

foreach ($fardas_atribuidas as $f) {

    $total_item = $f['preco_unitario'] * $f['quantidade_total'];
    $valor_total_geral += $total_item;

    $html .= "
    <tr>
        <td>".htmlspecialchars($f['nome'])."</td>
        <td>".htmlspecialchars($f['cor'])."</td>
        <td>".htmlspecialchars($f['tamanho'])."</td>
        <td>".$f['quantidade_total']."</td>
        <td>".date('d/m/Y', strtotime($f['data_atribuicao']))."</td>
        <td>".number_format($f['preco_unitario'], 2, ',', '.')."</td>
        <td>".number_format($total_item, 2, ',', '.')."</td>
    </tr>";
}

$html .= '
</tbody>
</table>

<br><p><strong>Valor total do fardamento: € '.number_format($valor_total_geral, 2, ',', '.').'</strong></p>
';

if ($termoAnterior) {
    $html .= '
<p style="margin-top:10px; border:1px solid #b91c1c; padding:10px; color:#991b1b; background:#fef2f2;">
<strong>Nota de substituição:</strong> Este documento substitui e invalida o termo anterior
código <strong>'.htmlspecialchars($termoAnterior['codigo']).'</strong>, emitido em
'.date('d/m/Y H:i', strtotime($termoAnterior['criado_em'])).'.
</p>
';
}

$html .= '

<p>Após o recebimento da farda, verifique todas as peças de vestuário, afim de confirmar se os respetivos tamanhos são adequados, e caso seja necessário a troca de tamanho de alguma peça de vestuário, a mesma tem de ser efetuada antes de ser usada.</p>

<p><strong>Farda entregue e conferida por:</strong> 
'.htmlspecialchars($_SESSION['user_name']).'</p>

<br>

<p>Lagoa, '.date('d/m/Y', strtotime($data_atribuicao)).'</p>

<p>__________________________________________<br>
Assinatura do Colaborador</p>

<p><em>Foi enviada uma cópia deste termo para o email 
'.htmlspecialchars($colaborador['email']).'.</em></p>

<div style="margin-top:25px;">
    <img src="data:image/png;base64,'.$qrBase64.'" style="width:80px;">
<br>
    <span style="font-size:10px;">
        Validar documento:<br>
        '.$urlValidacao.'
    </span>
</div>

</body>
</html>
';
file_put_contents(__DIR__.'/../storage/debug_html.html', $html);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

$pdfContent = $dompdf->output();

/* ======================================================
   GUARDAR PDF
====================================================== */

$nomePdf = "termo_fardamento_{$colaborador['id']}_" . date('Ymd_His') . ".pdf";
$caminho = __DIR__ . '/../storage/pdfs/' . $nomePdf;

file_put_contents($caminho, $pdfContent);

/* ======================================================
   REGISTAR DOCUMENTO NA BD
====================================================== */

$pdo->beginTransaction();

try {
    $termoAnteriorAtualizado = null;

    if ($termoAnterior) {
        $stmt = $pdo->prepare("
            SELECT id, codigo, criado_em
            FROM documentos
            WHERE id = ?
            FOR UPDATE
        ");
        $stmt->execute([(int)$termoAnterior['id']]);
        $termoAnteriorAtualizado = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("
        INSERT INTO documentos
        (codigo, tipo, colaborador_id, ficheiro, criado_por, estado, invalida_documento_id)
        VALUES (?, 'termo_farda', ?, ?, ?, 'valido', ?)
    ");

    $stmt->execute([
        $codigoDocumento,
        $colaborador['id'],
        $nomePdf,
        $_SESSION['user_id'],
        $termoAnteriorAtualizado ? (int)$termoAnteriorAtualizado['id'] : null,
    ]);

    $novoDocumentoId = (int)$pdo->lastInsertId();

    if ($termoAnteriorAtualizado) {
        $stmt = $pdo->prepare("
            UPDATE documentos
            SET estado = 'invalidado',
                invalidado_em = NOW(),
                motivo_invalidacao = ?,
                invalidado_por_documento_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            'Substituido por novo termo apos alteracoes de atribuicao.',
            $novoDocumentoId,
            (int)$termoAnteriorAtualizado['id'],
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    @unlink($caminho);
    die('Erro ao registar documento: ' . $e->getMessage());
}

/* ======================================================
   ENVIAR EMAIL
====================================================== */

$mail = new PHPMailer(true);

try {

    $smtp = require __DIR__ . '/../config/mail.php';

    $mail->isSMTP();
    $mail->Host       = $smtp['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp['username'];
    $mail->Password   = $smtp['password'];
    $mail->Port       = $smtp['port'];
    $mail->SMTPSecure = $smtp['encryption'];

    $mail->CharSet = 'UTF-8';

    $mail->setFrom($smtp['from_email'], $smtp['from_name']);
    $mail->addAddress($colaborador['email'], $colaborador['nome']);

    $mail->Subject = 'Termo de Entrega de Fardamento';

    $mail->Body = "
Olá {$colaborador['nome']},

Segue em anexo o termo de entrega de fardamento.

Cumprimentos,
CrewGest
";

    $mail->addStringAttachment(
        $pdfContent,
        $nomePdf,
        'base64',
        'application/pdf'
    );

    $mail->send();

} catch (Exception $e) {
    error_log('Erro envio termo farda: ' . $mail->ErrorInfo);
}

/* ======================================================
   DOWNLOAD
====================================================== */

header('Content-Type: application/pdf');
header("Content-Disposition: attachment; filename=\"$nomePdf\"");
echo $pdfContent;
exit;
