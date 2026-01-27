<?php
require_once '../config/db.php';
require_once '../src/auth_guard.php';

/* ======================================================
   RECEBER CÓDIGO
====================================================== */

$codigo = $_GET['codigo'] ?? null;

if (!$codigo) {
    die('Código de validação não fornecido.');
}

/* ======================================================
   PROCURAR DOCUMENTO
====================================================== */

$stmt = $pdo->prepare("
    SELECT 
        d.codigo,
        d.tipo,
        d.ficheiro,
        d.criado_em,
        c.nome AS colaborador_nome,
        u.nome AS criado_por_nome
    FROM documentos d
    JOIN colaboradores c ON d.colaborador_id = c.id
    JOIN utilizadores u ON d.criado_por = u.id
    WHERE d.codigo = ?
");

$stmt->execute([$codigo]);

$documento = $stmt->fetch(PDO::FETCH_ASSOC);

$valido = $documento ? true : false;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Validação de Documento</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background:#f4f6f8;
        padding:40px;
    }

    .card {
        background:white;
        max-width:600px;
        margin:auto;
        padding:30px;
        border-radius:8px;
        box-shadow:0 4px 10px rgba(0,0,0,.1);
    }

    h1 {
        margin-top:0;
    }

    .ok {
        color:#1e7e34;
        font-weight:bold;
    }

    .erro {
        color:#c82333;
        font-weight:bold;
    }

    .linha {
        margin:8px 0;
    }

    a.btn {
        display:inline-block;
        margin-top:20px;
        padding:10px 18px;
        background:#007bff;
        color:white;
        text-decoration:none;
        border-radius:5px;
    }
</style>
</head>
<body>

<div class="card">

<?php if ($valido): ?>

    <h1>✅ Documento válido</h1>

    <div class="linha"><strong>Tipo:</strong> <?= htmlspecialchars($documento['tipo']) ?></div>
    <div class="linha"><strong>Colaborador:</strong> <?= htmlspecialchars($documento['colaborador_nome']) ?></div>
    <div class="linha"><strong>Gerado por:</strong> <?= htmlspecialchars($documento['criado_por_nome']) ?></div>
    <div class="linha"><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($documento['criado_em'])) ?></div>
    <div class="linha"><strong>Código:</strong> <?= htmlspecialchars($documento['codigo']) ?></div>

    <a class="btn" href="../storage/pdfs/<?= urlencode($documento['ficheiro']) ?>" target="_blank">
        📄 Abrir PDF
    </a>

<?php else: ?>

    <h1>❌ Documento inválido</h1>

    <p class="erro">
        O código fornecido não corresponde a nenhum documento registado.
    </p>

<?php endif; ?>

</div>

</body>
</html>
