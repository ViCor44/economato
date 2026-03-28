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
    <style>
        #modalAlertasColaboradores,
        #modalAlertasColaboradores * {
            box-sizing: border-box;
        }

        #modalAlertasColaboradores {
            position: fixed;
            inset: 0;
            z-index: 1000;
        }

        #modalAlertasColaboradores .modal-wrap {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        #modalAlertasColaboradores .modal-panel {
            position: relative;
            width: min(1100px, 96vw);
            height: min(85vh, 760px);
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.25);
            overflow: hidden;
        }

        #modalAlertasColaboradores .modal-header,
        #modalAlertasColaboradores .modal-footer {
            padding: 14px 20px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }

        #modalAlertasColaboradores .modal-footer {
            border-bottom: 0;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        #modalAlertasColaboradores .modal-body {
            min-height: 0;
            overflow-y: auto;
            padding: 16px 20px;
            background: #f8fafc;
            scrollbar-gutter: stable;
        }

        #listaAlertasColaboradores {
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #e2e8f0;
        }

        #listaAlertasColaboradores::-webkit-scrollbar {
            width: 10px;
        }

        #listaAlertasColaboradores::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 9999px;
        }

        #listaAlertasColaboradores::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 9999px;
            border: 2px solid #e2e8f0;
        }

        #listaAlertasColaboradores::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
    </style>
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
                    <button
                        type="button"
                        id="cardAlertasColaboradores"
                        class="block w-full text-left bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                        aria-expanded="false"
                        aria-controls="modalAlertasColaboradores"
                    >
                        <div class="flex items-center gap-4">
                            <div style="background-color:#fef3c7;color:#d97706;padding:0.75rem;border-radius:9999px;flex-shrink:0;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-lg text-gray-800">Alertas de Colaboradores</h2>
                                <?php if ($error_message): ?>
                                    <p class="text-sm text-red-600">Erro ao carregar alertas.</p>
                                <?php elseif ($total_alertas_colaboradores === 0): ?>
                                    <p class="text-sm text-emerald-700">Sem alertas no momento.</p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-600"><?= $total_alertas_colaboradores ?> colaborador(es) com alerta. Clique para ver.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </button>

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

    <?php if ($role_id === ROLE_ADMIN || $role_id === ROLE_GESTOR): ?>
        <div id="modalAlertasColaboradores" class="hidden fixed inset-0 z-[1000]" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tituloModalAlertas">
            <div id="overlayModalAlertas" class="absolute inset-0 bg-black/50 opacity-0 transition-opacity duration-200"></div>

            <div class="modal-wrap">
                <div id="conteudoModalAlertas" class="modal-panel opacity-0 scale-95 transition-all duration-200">
                    <div class="modal-header flex items-center justify-between">
                        <h3 id="tituloModalAlertas" class="text-lg font-semibold text-gray-800">Situações de Alerta</h3>
                        <button type="button" id="fecharModalAlertas" class="text-gray-500 hover:text-gray-800 text-2xl leading-none" aria-label="Fechar modal de alertas">&times;</button>
                    </div>

                    <div id="listaAlertasColaboradores" class="modal-body">
                        <?php if ($error_message): ?>
                            <p class="text-sm text-red-600"><?= htmlspecialchars($error_message) ?></p>
                        <?php elseif ($total_alertas_colaboradores === 0): ?>
                            <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
                                Não existem alertas de colaboradores neste momento.
                            </p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($alertas_colaboradores as $alerta): ?>
                                    <div class="flex items-start justify-between gap-3 p-3 border border-gray-200 rounded-md bg-gray-50">
                                        <div class="min-w-0">
                                            <a href="detalhes_colaborador.php?id=<?= (int)$alerta['id'] ?>" class="font-medium text-gray-800 hover:text-blue-700">
                                                <?= htmlspecialchars($alerta['nome']) ?>
                                            </a>
                                            <p class="text-sm text-gray-600"><?= htmlspecialchars(implode(' | ', $alerta['motivos'])) ?></p>
                                        </div>
                                        <a href="editar_colaborador.php?id=<?= (int)$alerta['id'] ?>" class="text-xs font-semibold text-blue-700 hover:text-blue-900 whitespace-nowrap shrink-0">
                                            Corrigir
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer flex items-center justify-between">
                        <p class="text-xs text-gray-500">Use o scroll para visualizar todas as situações.</p>
                        <button type="button" id="fecharModalAlertasRodape" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php include_once '../src/templates/footer.php'; ?>
    <script>
        const cardAlertas = document.getElementById('cardAlertasColaboradores');
        const modalAlertas = document.getElementById('modalAlertasColaboradores');
        const overlayModalAlertas = document.getElementById('overlayModalAlertas');
        const conteudoModalAlertas = document.getElementById('conteudoModalAlertas');
        const fecharModalAlertas = document.getElementById('fecharModalAlertas');
        const fecharModalAlertasRodape = document.getElementById('fecharModalAlertasRodape');

        function abrirModalAlertas() {
            if (!modalAlertas || !cardAlertas || !overlayModalAlertas || !conteudoModalAlertas) return;
            modalAlertas.classList.remove('hidden');
            cardAlertas.setAttribute('aria-expanded', 'true');
            modalAlertas.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');

            requestAnimationFrame(function () {
                overlayModalAlertas.classList.remove('opacity-0');
                overlayModalAlertas.classList.add('opacity-100');
                conteudoModalAlertas.classList.remove('opacity-0', 'scale-95');
                conteudoModalAlertas.classList.add('opacity-100', 'scale-100');
            });
        }

        function fecharModalAlertasFn() {
            if (!modalAlertas || !cardAlertas || !overlayModalAlertas || !conteudoModalAlertas) return;
            overlayModalAlertas.classList.remove('opacity-100');
            overlayModalAlertas.classList.add('opacity-0');
            conteudoModalAlertas.classList.remove('opacity-100', 'scale-100');
            conteudoModalAlertas.classList.add('opacity-0', 'scale-95');

            setTimeout(function () {
                modalAlertas.classList.add('hidden');
            }, 200);

            cardAlertas.setAttribute('aria-expanded', 'false');
            modalAlertas.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        }

        if (cardAlertas && modalAlertas) {
            cardAlertas.addEventListener('click', abrirModalAlertas);
        }

        if (overlayModalAlertas) {
            overlayModalAlertas.addEventListener('click', fecharModalAlertasFn);
        }

        if (fecharModalAlertas) {
            fecharModalAlertas.addEventListener('click', fecharModalAlertasFn);
        }

        if (fecharModalAlertasRodape) {
            fecharModalAlertasRodape.addEventListener('click', fecharModalAlertasFn);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modalAlertas && !modalAlertas.classList.contains('hidden')) {
                fecharModalAlertasFn();
            }
        });

        window.addEventListener('beforeunload', function () {
            document.body.classList.remove('overflow-hidden');
        });
        
        if (modalAlertas && modalAlertas.classList.contains('hidden')) {
            modalAlertas.setAttribute('aria-hidden', 'true');
        }
    </script>
</body>
</html>