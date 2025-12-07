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

// Buscar nome do utilizador logado (responsável)
$user_id = $_SESSION['user_id'] ?? 0; // Assumindo que auth_guard define $_SESSION['user_id']
if ($user_id <= 0) {
    die("Utilizador não autenticado.");
}
$stmt = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilizador não encontrado.");
}

// Buscar fardas atribuídas (AGRUPADAS) - assumindo que estas são as que foram devolvidas
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
$fardas_devolvidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar cacifos atribuídos/devolvidos
$stmt = $pdo->prepare("
    SELECT numero, avariado
    FROM cacifos
    WHERE colaborador_id = ?
    ORDER BY numero ASC
");
$stmt->execute([$colaborador_id]);
$cacifos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se não houver fardas, prosseguir anyway, pois há cacifo e cartão

$data_devolucao = date('d/m/Y'); // Data atual para devolução

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

<h1>DEVOLUÇÃO DE FARDAMENTO, CACIFO E CARTÃO — '.htmlspecialchars($colaborador['departamento']).'</h1>

<p><strong>Nome:</strong> '.htmlspecialchars($colaborador['nome']).'</p>

<h2>Termo de Devolução</h2>

<p>Eu, trabalhador acima identificado, declaro que devolvi à Firma Correia & Santinha, Lda., contribuinte nº 501 585 583, na data indicada, todas as peças de fardamento de trabalho que me foram atribuídas, bem como o cacifo e o cartão de colaborador, em bom estado de conservação, exceto desgaste normal. Declaro ainda estar ciente que:</p>

<ul>
    <li>a) Todas as peças devolvidas são propriedade da Correia & Santinha, Lda e foram entregues nas condições adequadas.</li><br>
    <li>b) Em caso de danos ou extravio não justificado, poderei ser responsabilizado financeiramente.</li><br>
    <li>c) A devolução completa isenta-me de qualquer obrigação futura relacionada a estes itens.</li><br>
    <li>d) Este termo serve como prova de entrega e quitação dos itens mencionados.</li>
</ul>

<h2>Peças Devolvidas</h2>';

if (!empty($fardas_devolvidas)) {
    $html .= '
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

    foreach ($fardas_devolvidas as $f) {
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

    <br><p><strong>Valor total do fardamento devolvido: € '.number_format($valor_total_geral, 2, ',', '.').'</strong></p>';
} else {
    $html .= '<p>Nenhuma peça de fardamento atribuída/devolvida.</p>';
}

$html .= '

<h2>Outros Itens Devolvidos</h2>
<ul>
    <li>Cartão de Colaborador: Devolvido.</li>
';

if (!empty($cacifos)) {
    foreach ($cacifos as $cacifo) {
        $estado = $cacifo['avariado'] ? 'com avaria' : 'em bom estado';
        $html .= '<li>Cacifo Nº ' . htmlspecialchars($cacifo['numero']) . ': Devolvido ' . $estado . '.</li>';
    }
} else {
    $html .= '<li>Nenhum cacifo atribuído/devolvido.</li>';
}

$html .= '
</ul>

<p>Eu, '.htmlspecialchars($user['nome']).', confirmo que todos os itens foram devolvidos e inspecionados.</p>

<br>

<p>Lagoa, '.$data_devolucao.'</p>

<br>
<p>__________________________________________<br>
Assinatura do Trabalhador</p>

<br>
<p>__________________________________________<br>
Assinatura do Responsável pela Empresa</p>

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

$dompdf->stream("termo_devolucao_{$colaborador['id']}.pdf", ["Attachment" => true]);