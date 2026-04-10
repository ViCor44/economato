<?php
require_once '../config/db.php';
require_once '../src/auth_guard.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 🔍 Validar colaborador
$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
if ($colaborador_id <= 0) {
    die("Colaborador inválido.");
}

// 🔍 Buscar colaborador
$stmt = $pdo->prepare("
    SELECT c.id, c.nome, c.numero_funcionario, d.nome AS departamento
    FROM colaboradores c
    LEFT JOIN departamentos d ON c.departamento_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$colaborador_id]);
$colaborador = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// 🔍 Utilizador logado
$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Utilizador não encontrado.");
}

// 🔍 Buscar fardas em aberto
$stmt = $pdo->prepare("
    SELECT
        fa.id,
        f.nome,
        c.nome AS cor,
        t.nome AS tamanho,
        fa.quantidade,
        fa.estado,
        fa.estado_devolucao,
        f.preco_unitario,
        f.id AS farda_id
    FROM farda_atribuicoes fa
    JOIN fardas f ON fa.farda_id = f.id
    JOIN cores c ON f.cor_id = c.id
    JOIN tamanhos t ON f.tamanho_id = t.id
    WHERE fa.colaborador_id = ?
      AND fa.estado IN ('atribuida', 'marcada_devolucao')
    ORDER BY f.nome ASC
");
$stmt->execute([$colaborador_id]);
$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔍 Separar devolvidas / não devolvidas
$fardas_devolvidas = [];
$fardas_em_divida = [];
$total_divida = 0;

foreach ($fardas as $f) {
    if ($f['estado'] === 'marcada_devolucao') {
        $fardas_devolvidas[] = $f;
    } else {
        $fardas_em_divida[] = $f;
        $total_divida += $f['quantidade'] * $f['preco_unitario'];
    }
}

// 🔒 INICIAR FECHO DEFINITIVO
$pdo->beginTransaction();

try {
    // 🧾 Criar termo
    $stmt = $pdo->prepare("
        INSERT INTO termos_devolucao (colaborador_id, total_divida)
        VALUES (?, ?)
    ");
    $stmt->execute([$colaborador_id, $total_divida]);
    $termo_id = $pdo->lastInsertId();

    // ✅ Processar devolvidas
    foreach ($fardas_devolvidas as $f) {

        if ($f['estado_devolucao'] === 'stock') {
            // 🔄 Volta ao stock
            $pdo->prepare("
                UPDATE fardas
                SET quantidade = quantidade + ?
                WHERE id = ?
            ")->execute([$f['quantidade'], $f['farda_id']]);
        }

        // 🔒 Fechar atribuição
        $pdo->prepare("
            UPDATE farda_atribuicoes
            SET estado = 'devolvida_confirmada',
                termo_id = ?
            WHERE id = ?
        ")->execute([$termo_id, $f['id']]);
    }

    // ❌ Processar não devolvidas (dívida)
    if (!empty($fardas_em_divida)) {
        $ids = array_column($fardas_em_divida, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $pdo->prepare("
            UPDATE farda_atribuicoes
            SET estado = 'em_divida',
                termo_id = ?
            WHERE id IN ($placeholders)
        ")->execute(array_merge([$termo_id], $ids));
    }

    // 🔒 Inativar colaborador
    $pdo->prepare("
        UPDATE colaboradores
        SET ativo = 0,
            numero_funcionario = ''
        WHERE id = ?
    ")->execute([$colaborador_id]);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erro ao gerar termo: " . $e->getMessage());
}

// 📄 GERAR PDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

$data = date('d/m/Y');

$html = "
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
<h1 style='text-align:center'>TERMO DE DEVOLUÇÃO DE FARDAMENTO</h1>

<p><strong>Colaborador:</strong> {$colaborador['nome']}<br>
<strong>Nº Funcionário:</strong> {$colaborador['numero_funcionario']}<br>
<strong>Departamento:</strong> {$colaborador['departamento']}<br>
<strong>Data:</strong> {$data}</p>

<p>
Declaro que procedi à devolução das peças abaixo identificadas como <strong>devolvidas</strong>.
Fui igualmente informado de que as peças indicadas como <strong>não devolvidas</strong>
constituem uma dívida a meu cargo.
</p>
";

// ✅ DEVOLVIDAS
$html .= "<h2>Peças Devolvidas</h2>";

if ($fardas_devolvidas) {
    $html .= "<table border='1' width='100%' cellspacing='0' cellpadding='6'>
    <tr>
        <th>Peça</th><th>Cor</th><th>Tamanho</th><th>Qtd</th><th>Destino</th>
    </tr>";

    foreach ($fardas_devolvidas as $f) {
        $destino = $f['estado_devolucao'] === 'stock' ? 'Volta ao stock' : 'Reciclagem';
        $html .= "
        <tr>
            <td>{$f['nome']}</td>
            <td>{$f['cor']}</td>
            <td>{$f['tamanho']}</td>
            <td>{$f['quantidade']}</td>
            <td>{$destino}</td>
        </tr>";
    }

    $html .= "</table>";
} else {
    $html .= "<p>Nenhuma peça devolvida.</p>";
}

// ❌ EM DÍVIDA
$html .= "<h2>Peças Não Devolvidas (Em Dívida)</h2>";

if ($fardas_em_divida) {
    $html .= "<table border='1' width='100%' cellspacing='0' cellpadding='6'>
    <tr>
        <th>Peça</th><th>Cor</th><th>Tamanho</th><th>Qtd</th><th>Valor (€)</th>
    </tr>";

    foreach ($fardas_em_divida as $f) {
        $valor = number_format($f['quantidade'] * $f['preco_unitario'], 2, ',', '.');
        $html .= "
        <tr>
            <td>{$f['nome']}</td>
            <td>{$f['cor']}</td>
            <td>{$f['tamanho']}</td>
            <td>{$f['quantidade']}</td>
            <td>{$valor}</td>
        </tr>";
    }

    $html .= "</table>
    <p><strong>Total em dívida: € " . number_format($total_divida, 2, ',', '.') . "</strong></p>";
} else {
    $html .= "<p>Não existem peças em dívida.</p>";
}

$html .= "
<br><br>
<p>_________________________________<br>Assinatura do Colaborador</p>

<br>
<p>
Eu, <strong>{$user['nome']}</strong>, confirmo a veracidade deste termo.
</p>

<p>_________________________________<br>Assinatura do Responsável</p>
";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();
$dompdf->stream("termo_devolucao_{$colaborador_id}.pdf", ["Attachment" => true]);
