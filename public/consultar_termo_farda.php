<?php
require_once '../config/db.php';
require_once '../src/auth_guard.php';

$colaboradorId = (int)($_GET['colaborador_id'] ?? 0);
if ($colaboradorId <= 0) {
    http_response_code(400);
    die('Colaborador inválido.');
}

$stmt = $pdo->prepare("
    SELECT ficheiro
    FROM documentos
    WHERE colaborador_id = ?
      AND tipo = 'termo_farda'
      AND (estado = 'valido' OR estado IS NULL)
    ORDER BY criado_em DESC, id DESC
    LIMIT 1
");
$stmt->execute([$colaboradorId]);
$ficheiro = $stmt->fetchColumn();

if (!$ficheiro) {
    http_response_code(404);
    die('Não existe um termo de fardamento em vigor para este colaborador.');
}

$nomeFicheiro = basename((string)$ficheiro);
$caminho = __DIR__ . '/../storage/pdfs/' . $nomeFicheiro;

if (!is_file($caminho) || !is_readable($caminho)) {
    http_response_code(404);
    die('O ficheiro do termo não foi encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: inline; filename="' . addcslashes($nomeFicheiro, '"\\') . '"');
header('X-Content-Type-Options: nosniff');
readfile($caminho);
exit;
