<?php
require_once '../config/db.php';
require_once '../src/auth_guard.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$colaborador_id = $_GET['colaborador_id'] ?? 0;
if ($colaborador_id <= 0) {
    die("Colaborador inválido.");
}

// Buscar informações do colaborador
$stmt = $pdo->prepare("
    SELECT c.id, c.nome, d.nome AS departamento
    FROM colaboradores c
    LEFT JOIN departamentos d ON c.departamento_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// Buscar fardas atribuídas (AGRUPADAS)
$stmt = $pdo->prepare("
    SELECT 
        f.nome,
        c.nome AS cor,
        t.nome AS tamanho,
        SUM(fa.quantidade) AS quantidade_total,
        MIN(fa.data_atribuicao) AS data_atribuicao,
        f.preco_unitario
    FROM farda_atribuicoes fa
    JOIN fardas f ON fa.farda_id = f.id
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE fa.colaborador_id = ?
    GROUP BY f.id, f.nome, c.nome, t.nome, f.preco_unitario
    ORDER BY f.nome ASC
");
$stmt->execute([$colaborador_id]);
$fardas_atribuidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$fardas_atribuidas) {
    die("O colaborador não tem fardas atribuídas.");
}

$data_atribuicao = $fardas_atribuidas[0]['data_atribuicao'];

$options = new Options();
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

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
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table, th, td { border: 1px solid black; }
    th, td { padding: 6px; }
    ul { margin-left: 20px; }
    p { text-align: justify; }
</style>
</head>
<body>

<h1>ENTREGA DE FARDAMENTO — '.htmlspecialchars($colaborador['departamento']).'</h1>

<p><strong>Nome:</strong> '.htmlspecialchars($colaborador['nome']).'</p>

<h2>Termo de Responsabilidade</h2>

<p>Eu, trabalhador acima identificado, recebo a farda entregue pela Firma Correia & Santinha, Lda., contribuinte nº 501 585 583 na data indicada as peças de fardamento de trabalho que abaixo estão assinaladas, comprometendo-me a usá-las exclusivamente na minha atividade profissional, com zelo de forma a garantir o seu bom estado de conservação e asseio, ao serviço da Correia & Santinha, Lda. Declaro ainda estar ciente que:</p>

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
        <td>".number_format($f['preco_unitario'], 2, ',', '.')."</td>
        <td>".number_format($total_item, 2, ',', '.')."</td>
    </tr>";
}

$html .= '
</tbody>
</table>

<br><p><strong>Valor total do fardamento: € '.number_format($valor_total_geral, 2, ',', '.').'</strong></p>

<p>Após o recebimento da farda, verifique todas as peças de vestuário, afim de confirmar se os respetivos tamanhos são adequados, e caso seja necessário a troca de tamanho de alguma peça de vestuário, a mesma tem de ser efetuada antes de ser usada.</p>

<br>

<p>Lagoa, '.date('d/m/Y', strtotime($data_atribuicao)).'</p>

<br>
<p>__________________________________________<br>
Assinatura do Trabalhador</p>

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

$dompdf->stream("termo_fardamento_{$colaborador['id']}.pdf", ["Attachment" => true]);
