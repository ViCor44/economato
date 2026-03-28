<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Inicializar variáveis para evitar erros

$error_message = null;
$alertas_colaboradores = [];
$total_alertas_colaboradores = 0;

// --- BUSCAR DADOS (DENTRO DE UM TRY...CATCH) ---
try {

    $stmt_alertas = $pdo->query(" 
        SELECT id, nome, cartao, telefone, email, departamento_id, ativo
        FROM colaboradores
        WHERE ativo = 1
        ORDER BY nome ASC
    ");

    $colaboradores_dashboard = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($colaboradores_dashboard as $colaborador) {
        $motivos = [];
        $cartao = trim((string)($colaborador['cartao'] ?? ''));
        $telefone = trim((string)($colaborador['telefone'] ?? ''));
        $email = trim((string)($colaborador['email'] ?? ''));
        $departamento_id = $colaborador['departamento_id'] ?? null;

        if ($cartao === '' || stripos($cartao, 'SEM CARTAO') !== false) {
            $motivos[] = 'Sem cartao atribuido';
        }

        if ($telefone === '') {
            $motivos[] = 'Telefone por preencher';
        }

        if ($email === '') {
            $motivos[] = 'Email por preencher';
        }

        if (empty($departamento_id)) {
            $motivos[] = 'Departamento por preencher';
        }

        if (!empty($motivos)) {
            $alertas_colaboradores[] = [
                'id' => (int)$colaborador['id'],
                'nome' => (string)$colaborador['nome'],
                'motivos' => $motivos,
            ];
        }
    }

    usort($alertas_colaboradores, static function ($a, $b) {
        return count($b['motivos']) <=> count($a['motivos']);
    });

    $total_alertas_colaboradores = count($alertas_colaboradores);


} catch (PDOException $e) {
    error_log("Erro ao buscar dados para o dashboard: " . $e->getMessage());
    $error_message = "Ocorreu um erro ao carregar os dados do dashboard.";
    $total_eventos_hoje = 0; // Definir valor padrão em caso de erro
}

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Dashboard - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body>

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Dashboard</h1>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

                <?php
                // Vamos usar uma variável para facilitar a leitura
                $role_id = (int)($utilizador_logado['role_id'] ?? 0);

                // Se o utilizador tiver uma função de gestão (qualquer uma exceto Funcionário)
                if ($role_id === ROLE_ADMIN || $role_id === ROLE_GESTOR ):
                ?>
                    <div class="block bg-white p-6 rounded-lg shadow-md border-l-4 border-amber-500 lg:col-span-2">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-amber-100 text-amber-600 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-lg text-gray-800">Alertas de Colaboradores</h2>
                                <p class="text-sm text-gray-600">Colaboradores sem cartão atribuído ou com dados em falta.</p>
                            </div>
                        </div>

                        <?php if ($error_message): ?>
                            <p class="text-sm text-red-600"><?= htmlspecialchars($error_message) ?></p>
                        <?php elseif ($total_alertas_colaboradores === 0): ?>
                            <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
                                Não existem alertas de colaboradores neste momento.
                            </p>
                        <?php else: ?>
                            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2 mb-3">
                                <?= $total_alertas_colaboradores ?> colaborador(es) com alertas.
                            </p>

                            <div class="space-y-2">
                                <?php foreach (array_slice($alertas_colaboradores, 0, 5) as $alerta): ?>
                                    <div class="flex items-start justify-between gap-3 p-3 border border-gray-200 rounded-md bg-gray-50">
                                        <div>
                                            <a href="detalhes_colaborador.php?id=<?= (int)$alerta['id'] ?>" class="font-medium text-gray-800 hover:text-blue-700">
                                                <?= htmlspecialchars($alerta['nome']) ?>
                                            </a>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars(implode(' | ', $alerta['motivos'])) ?></p>
                                        </div>
                                        <a href="editar_colaborador.php?id=<?= (int)$alerta['id'] ?>" class="text-xs font-semibold text-blue-700 hover:text-blue-900 whitespace-nowrap">
                                            Corrigir
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($total_alertas_colaboradores > 5): ?>
                                <p class="text-xs text-gray-500 mt-3">
                                    A mostrar os 5 mais prioritários. Restantes: <?= $total_alertas_colaboradores - 5 ?>.
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <a href="colaboradores.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-4">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-full">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /> </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-lg text-gray-800">Gerir Colaboradores</h2>
                                <p class="text-sm text-gray-600">Ver, adicionar ou editar colaboradores.</p>
                            </div>
                        </div>
                    </a>

                <?php
                endif;
                ?>

                <?php if ((int)($utilizador_logado['role_id'] ?? 0) === ROLE_ADMIN): ?>
                <a href="gerir_utilizadores.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-indigo-100 text-indigo-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /> </svg>                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Gerir Utilizadores</h2>
                            <p class="text-sm text-gray-600">Aprovar e gerir contas de acesso.</p>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ((int)($utilizador_logado['role_id'] ?? 0) === ROLE_ADMIN): ?>
                <a href="<?= BASE_URL ?>/reports/logs.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Logs do Sistema</h2>
                            <p class="text-sm text-gray-600">Ver o registo de atividade.</p>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
                
                <a href="perfil.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-green-100 text-green-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">O Meu Perfil</h2>
                            <p class="text-sm text-gray-600">Alterar password e gerir 2FA.</p>
                        </div>
                    </div>
                </a>
                
                <a href="gerir_stock_farda.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-teal-100 text-teal-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Gerir Stock de Farda</h2>
                            <p class="text-sm text-gray-600">Gerir stock de fardas.</p>
                        </div>
                    </div>
                </a>

                <a href="list_lockers.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <div class="bg-purple-100 text-purple-600 p-3 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /> </svg>                       
                        </div>
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Gerir Cacifos</h2>
                            <p class="text-sm text-gray-600">Atribuição de cacifos.</p>
                        </div>
                    </div>
                </a>

                <!-- 🏢 Gerir Departamentos -->
                <a href="departamentos.php" class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center gap-4">
                        <!-- wrapper com o círculo (mesmo padrão dos outros cards) -->
                        <div class="bg-orange-100 text-orange-600 p-3 rounded-full icon-circle">
                            <!-- SVG em estilo outline; mantém fill="none" para deixar o círculo visível -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M9 8h6m-6 4h6m-6 4h6M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16" />
                            </svg>
                        </div>

                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Gerir Departamentos</h2>
                            <p class="text-sm text-gray-600">Criar e editar departamentos da empresa.</p>
                        </div>
                    </div>
                </a>

                <!-- 📊 Relatórios -->
                <a href="<?= BASE_URL ?>/reports/index.php" 
                class="block bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300">

                    <div class="flex items-center gap-4">

                        <!-- Círculo do ícone (cor igual à dos outros cards informativos) -->
                        <div class="bg-blue-100 text-blue-600 p-3 rounded-full icon-circle">
                            <svg xmlns="http://www.w3.org/2000/svg" 
                                class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 10h16M4 14h10M4 18h6" />
                            </svg>
                        </div>

                        <!-- Texto -->
                        <div>
                            <h2 class="font-semibold text-lg text-gray-800">Relatórios</h2>
                            <p class="text-sm text-gray-600">
                                Consultar relatórios de stock, atribuições, devoluções e mais.
                            </p>
                        </div>

                    </div>
                </a>
             
            </div>
        </div>
    </main>
    <?php
        // Podemos definir o contexto para personalizar a mensagem inicial
        $aiContext = 'dashboard'; // ou 'etiquetas', 'dashboard', etc.
        include __DIR__ . '/../src/templates/assistant_widget.php';
    ?>
    
    <?php include_once '../src/templates/footer.php'; ?>
</body>
</html>