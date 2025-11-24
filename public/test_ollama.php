<?php
$ch = curl_init("http://127.0.0.1:11434/api/generate");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        "model" => "mistral:instruct",
        "prompt" => "Teste básico"
    ]),
    CURLOPT_TIMEOUT => 300
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

echo "<pre>";
echo "Resposta:\n$response\n\n";
echo "Erro:\n$error\n";
echo "</pre>";
