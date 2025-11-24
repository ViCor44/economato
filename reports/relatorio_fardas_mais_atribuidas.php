<?php
require '../config/db.php';
require '../src/auth_guard.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// URL do Ollama
$OLLAMA_URL = "http://127.0.0.1:11434/api/chat";
$OLLAMA_MODEL = "mistral:instruct";

// Datas opcionais
$data_inicio = $_GET['inicio'] ?? '2000-01-01';
$data_fim    = $_GET['fim'] ?? date('Y-m-d');

// 1️⃣ Buscar dados da BD
$stmt = $pdo->prepare("
    SELECT 
        f.nome AS farda,
        c.nome AS cor,
        t.nome AS tamanho,
        SUM(fa.quantidade) AS total_atribuido,
        COUNT(DISTINCT fa.colaborador_id) AS colaboradores,
        MIN(fa.data_atribuicao) AS primeiro_registo,
        MAX(fa.data_atribuicao) AS ultimo_registo
    FROM farda_atribuicoes fa
    JOIN fardas f ON f.id = fa.farda_id
    JOIN cores c ON c.id = f.cor_id
    JOIN tamanhos t ON t.id = f.tamanho_id
    WHERE fa.data_atribuicao BETWEEN ? AND ?
    GROUP BY f.id
    ORDER BY total_atribuido DESC
");

$stmt->execute([$data_inicio, $data_fim]);
$fardas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$fardas) {
    die("Não há dados suficientes para gerar o relatório.");
}

// 2️⃣ Preparar JSON para IA
$payload = [
    "periodo" => "$data_inicio até $data_fim",
    "total_registos" => count($fardas),
    "ranking" => $fardas
];

$prompt = "
Gera um relatório profissional em HTML baseado nos seguintes dados:

" . json_encode($payload, JSON_PRETTY_PRINT) . "

O relatório deve conter:
- Título e período analisado
- Resumo executivo
- Top 10 de fardas mais atribuídas
- Tabela comparativa
- Observações sobre padrões
- Tendências
- Alertas de consumo incomum
- Recomendações
";

// 3️⃣ Chamada ao Ollama (modo seguro, sem streaming)
$ch = curl_init($OLLAMA_URL);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        "model" => $OLLAMA_MODEL,
        "messages" => [
            ["role" => "system", "content" => "És um especialista em análise estatística e geração de relatórios."],
            ["role" => "user", "content" => $prompt]
        ],
        "stream" => false
    ]),
    CURLOPT_TIMEOUT => 300
]);

$response = curl_exec($ch);

if (!$response) {
    echo "<h2>⚠ Erro ao comunicar com o Ollama</h2>";
    echo "<p>" . curl_error($ch) . "</p>";
    echo "<p>Tenta reiniciar o modelo:</p><pre>ollama run mistral</pre>";
    exit;
}

curl_close($ch);

// 4️⃣ Decodificar resposta
$json = json_decode($response, true);

$html_ia = $json['message']['content'] 
        ?? ($json['response'] ?? null);

if (!$html_ia) {
    echo "<h3>⚠ Resposta vazia ou inválida</h3>";
    echo "<pre>$response</pre>";
    exit;
}

// 5️⃣ Gerar PDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html_ia);
$dompdf->setPaper('A4');
$dompdf->render();

$dompdf->stream("Relatorio_Fardas_Mais_Atribuidas.pdf", ["Attachment" => true]);
