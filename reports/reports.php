<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Relatórios - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="bg-gray-100 p-8">

<?php include '../src/templates/header.php'; ?>

<main class="max-w-5xl mx-auto bg-white p-8 rounded-2xl shadow-lg mt-8">

    <h1 class="text-3xl font-bold text-gray-800 mb-6">📊 Relatórios</h1>
    <p class="text-gray-600 mb-8">Selecione o relatório que pretende consultar:</p>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">

    <!-- Stock -->
    <a href="report_stock.php"
       class="block bg-white p-6 rounded-lg shadow hover:shadow-lg transition-all hover:-translate-y-1">
        <div class="flex items-center gap-4">
            <div class="bg-blue-100 text-blue-600 p-3 rounded-full text-2xl">
                📦
            </div>
            <div>
                <h2 class="font-semibold text-lg text-gray-800">Relatório de Stock</h2>
                <p class="text-sm text-gray-600">Quantidades por tipo, cor e tamanho.</p>
            </div>
        </div>
    </a>

    <!-- Atribuições -->
    <a href="relatorio_atribuicoes.php"
       class="block bg-white p-6 rounded-lg shadow hover:shadow-lg transition-all hover:-translate-y-1">
        <div class="flex items-center gap-4">
            <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full text-2xl">
                👕
            </div>
            <div>
                <h2 class="font-semibold text-lg text-gray-800">Atribuições a Colaboradores</h2>
                <p class="text-sm text-gray-600">Fardas entregues por colaborador.</p>
            </div>
        </div>
    </a>

    <!-- Devoluções -->
    <a href="relatorio_devolucoes.php"
       class="block bg-white p-6 rounded-lg shadow hover:shadow-lg transition-all hover:-translate-y-1">
        <div class="flex items-center gap-4">
            <div class="bg-green-100 text-green-600 p-3 rounded-full text-2xl">
                ♻️
            </div>
            <div>
                <h2 class="font-semibold text-lg text-gray-800">Devoluções</h2>
                <p class="text-sm text-gray-600">Peças devolvidas e estado.</p>
            </div>
        </div>
    </a>

    <!-- Baixas -->
    <a href="relatorio_baixas.php"
       class="block bg-white p-6 rounded-lg shadow hover:shadow-lg transition-all hover:-translate-y-1">
        <div class="flex items-center gap-4">
            <div class="bg-red-100 text-red-600 p-3 rounded-full text-2xl">
                🚫
            </div>
            <div>
                <h2 class="font-semibold text-lg text-gray-800">Baixas / Danificados</h2>
                <p class="text-sm text-gray-600">Itens removidos de stock.</p>
            </div>
        </div>
    </a>

    <!-- Custos -->
    <a href="relatorio_custos.php"
       class="block bg-white p-6 rounded-lg shadow hover:shadow-lg transition-all hover:-translate-y-1">
        <div class="flex items-center gap-4">
            <div class="bg-purple-100 text-purple-600 p-3 rounded-full text-2xl">
                💰
            </div>
            <div>
                <h2 class="font-semibold text-lg text-gray-800">Custos</h2>
                <p class="text-sm text-gray-600">Compras e valores associados.</p>
            </div>
        </div>
    </a>

    <!-- Cacifos -->
    <a href="relatorio_cacifos.php"
       class="block bg-white p-6 rounded-lg shadow hover:shadow-lg transition-all hover:-translate-y-1">
        <div class="flex items-center gap-4">
            <div class="bg-indigo-100 text-indigo-600 p-3 rounded-full text-2xl">
                🔐
            </div>
            <div>
                <h2 class="font-semibold text-lg text-gray-800">Cacifos</h2>
                <p class="text-sm text-gray-600">Livres, ocupados e avariados.</p>
            </div>
        </div>
    </a>

    <a href="relatorio_fardas_mais_atribuidas.php" 
   class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg shadow font-semibold"
   target="_blank">
   📊 Relatório: Fardas Mais Atribuídas (IA)
</a>


</div>

</main>

</body>
</html>
