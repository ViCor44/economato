<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Inicializar variáveis para evitar erros

$error_message = null;
$alertas_colaboradores = [];
$total_alertas_colaboradores = 0;
$emprestimos_em_alerta = [];
$emprestimos_dashboard = [];
$total_emprestimos_alerta = 0;
$total_emprestimos_pendentes = 0;

// --- BUSCAR DADOS (DENTRO DE UM TRY...CATCH) ---
try {

    $stmt_alertas = $pdo->query(" 
        SELECT id, nome, cartao, telefone, email, foto, departamento_id, ativo
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
        $foto = trim((string)($colaborador['foto'] ?? ''));
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

        if ($foto === '') {
            $motivos[] = 'Fotografia por carregar';
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

    $stmt_emprestimos = $pdo->query(" 
        SELECT e.id, e.colaborador_id, c.nome AS colaborador_nome, f.nome AS farda_nome,
               co.nome AS cor_nome, t.nome AS tamanho_nome, e.quantidade, e.data_emprestimo,
               DATEDIFF(CURDATE(), DATE(e.data_emprestimo)) AS dias_em_aberto
        FROM farda_emprestimos e
        JOIN colaboradores c ON e.colaborador_id = c.id
        JOIN fardas f ON e.farda_id = f.id
        JOIN cores co ON f.cor_id = co.id
        JOIN tamanhos t ON f.tamanho_id = t.id
        WHERE e.devolvido = 0
        ORDER BY e.data_emprestimo ASC
    ");

    $emprestimos_dashboard = $stmt_emprestimos->fetchAll(PDO::FETCH_ASSOC);
    $total_emprestimos_pendentes = count($emprestimos_dashboard);

    foreach ($emprestimos_dashboard as $emprestimo) {
        $diasEmAberto = (int)($emprestimo['dias_em_aberto'] ?? 0);
        if ($diasEmAberto >= 15) {
            $emprestimos_em_alerta[] = [
                'id' => (int)$emprestimo['id'],
                'colaborador_id' => (int)$emprestimo['colaborador_id'],
                'colaborador_nome' => (string)$emprestimo['colaborador_nome'],
                'farda_nome' => (string)$emprestimo['farda_nome'],
                'cor_nome' => (string)$emprestimo['cor_nome'],
                'tamanho_nome' => (string)$emprestimo['tamanho_nome'],
                'quantidade' => (int)$emprestimo['quantidade'],
                'data_emprestimo' => (string)$emprestimo['data_emprestimo'],
                'dias_em_aberto' => $diasEmAberto,
            ];
        }
    }

    usort($emprestimos_em_alerta, static function ($a, $b) {
        return $b['dias_em_aberto'] <=> $a['dias_em_aberto'];
    });

    $total_emprestimos_alerta = count($emprestimos_em_alerta);

    $total_utilizadores_pendentes = 0;
    $stmt_pendentes_u = $pdo->query("SELECT COUNT(*) FROM utilizadores WHERE is_active = 0");
    $total_utilizadores_pendentes = (int)$stmt_pendentes_u->fetchColumn();

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
        #modalAlertasColaboradores *,
        #modalAlertasEmprestimos,
        #modalAlertasEmprestimos * {
            box-sizing: border-box;
        }

        #modalAlertasColaboradores,
        #modalAlertasEmprestimos {
            position: fixed;
            inset: 0;
            z-index: 1000;
        }

        #modalAlertasColaboradores .modal-wrap,
        #modalAlertasEmprestimos .modal-wrap {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        #modalAlertasColaboradores .modal-panel,
        #modalAlertasEmprestimos .modal-panel {
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
        #modalAlertasColaboradores .modal-footer,
        #modalAlertasEmprestimos .modal-header,
        #modalAlertasEmprestimos .modal-footer {
            padding: 14px 20px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }

        #modalAlertasColaboradores .modal-footer,
        #modalAlertasEmprestimos .modal-footer {
            border-bottom: 0;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        #modalAlertasColaboradores .modal-body,
        #modalAlertasEmprestimos .modal-body {
            min-height: 0;
            overflow-y: auto;
            padding: 16px 20px;
            background: #f8fafc;
            scrollbar-gutter: stable;
        }

        #listaAlertasColaboradores,
        #listaAlertasEmprestimos {
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #e2e8f0;
        }

        #listaAlertasColaboradores::-webkit-scrollbar,
        #listaAlertasEmprestimos::-webkit-scrollbar {
            width: 10px;
        }

        #listaAlertasColaboradores::-webkit-scrollbar-track,
        #listaAlertasEmprestimos::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 9999px;
        }

        #listaAlertasColaboradores::-webkit-scrollbar-thumb,
        #listaAlertasEmprestimos::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 9999px;
            border: 2px solid #e2e8f0;
        }

        #listaAlertasColaboradores::-webkit-scrollbar-thumb:hover,
        #listaAlertasEmprestimos::-webkit-scrollbar-thumb:hover {
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
                    <div class="flex items-center gap-4 relative">
                        <?php if ($total_utilizadores_pendentes > 0): ?>
                            <span style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:0.7rem;font-weight:700;line-height:1;padding:3px 7px;border-radius:9999px;min-width:20px;text-align:center;">
                                <?= $total_utilizadores_pendentes ?>
                            </span>
                        <?php endif; ?>
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

                <?php if ($role_id === ROLE_ADMIN || $role_id === ROLE_GESTOR): ?>
                    <button
                        type="button"
                        id="cardAlertasEmprestimos"
                        class="block w-full text-left bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                        aria-expanded="false"
                        aria-controls="modalAlertasEmprestimos"
                    >
                        <div class="flex items-center gap-4 relative">
                            <?php if ($total_emprestimos_alerta > 0): ?>
                                <span style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:0.7rem;font-weight:700;line-height:1;padding:3px 7px;border-radius:9999px;min-width:20px;text-align:center;">
                                    <?= $total_emprestimos_alerta ?>
                                </span>
                            <?php endif; ?>
                            <div style="background-color:#fee2e2;color:#dc2626;padding:0.75rem;border-radius:9999px;flex-shrink:0;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="font-semibold text-lg text-gray-800">Gerir Empréstimos</h2>
                                <?php if ($total_emprestimos_alerta > 0): ?>
                                    <p class="text-sm text-red-600"><?= $total_emprestimos_alerta ?> empréstimo(s) com 15+ dias por devolver.</p>
                                <?php elseif ($total_emprestimos_pendentes > 0): ?>
                                    <p class="text-sm text-gray-600"><?= $total_emprestimos_pendentes ?> empréstimo(s) pendente(s), sem atrasos acima de 15 dias.</p>
                                <?php else: ?>
                                    <p class="text-sm text-emerald-700">Sem empréstimos pendentes no momento.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </button>

                    <button
                        type="button"
                        id="cardAlertasColaboradores"
                        class="block w-full text-left bg-white p-6 rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                        aria-expanded="false"
                        aria-controls="modalAlertasColaboradores"
                    >
                        <div class="flex items-center gap-4 relative">
                            <?php if ($total_alertas_colaboradores > 0): ?>
                                <span style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:0.7rem;font-weight:700;line-height:1;padding:3px 7px;border-radius:9999px;min-width:20px;text-align:center;">
                                    <?= $total_alertas_colaboradores ?>
                                </span>
                            <?php endif; ?>
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
                <?php endif; ?>
             
            </div>
        </div>
    </main>
    <?php
        // Podemos definir o contexto para personalizar a mensagem inicial
        $aiContext = 'dashboard'; // ou 'etiquetas', 'dashboard', etc.
        include __DIR__ . '/../src/templates/assistant_widget.php';
    ?>

    <?php if ($role_id === ROLE_ADMIN || $role_id === ROLE_GESTOR): ?>
        <div id="modalAlertasEmprestimos" class="hidden fixed inset-0 z-[1000]" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tituloModalAlertasEmprestimos">
            <div id="overlayModalAlertasEmprestimos" class="absolute inset-0 bg-black/50 opacity-0 transition-opacity duration-200"></div>

            <div class="modal-wrap">
                <div id="conteudoModalAlertasEmprestimos" class="modal-panel opacity-0 scale-95 transition-all duration-200">
                    <div class="modal-header flex items-center justify-between">
                        <h3 id="tituloModalAlertasEmprestimos" class="text-lg font-semibold text-gray-800">Alertas de Empréstimos</h3>
                        <button type="button" id="fecharModalAlertasEmprestimos" class="text-gray-500 hover:text-gray-800 text-2xl leading-none" aria-label="Fechar modal de alertas de empréstimos">&times;</button>
                    </div>

                    <div id="listaAlertasEmprestimos" class="modal-body">
                        <?php if ($total_emprestimos_pendentes === 0): ?>
                            <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
                                Não existem empréstimos pendentes neste momento.
                            </p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <p class="text-sm <?= $total_emprestimos_alerta > 0 ? 'text-red-700 bg-red-50 border border-red-200' : 'text-emerald-700 bg-emerald-50 border border-emerald-200' ?> rounded-md px-3 py-2">
                                    <?= $total_emprestimos_pendentes ?> empréstimo(s) pendente(s). <?= $total_emprestimos_alerta > 0 ? $total_emprestimos_alerta . ' em atraso (15+ dias).' : 'Sem atrasos acima de 15 dias.' ?>
                                </p>

                                <?php foreach ($emprestimos_dashboard as $emprestimo): ?>
                                    <?php $emAtraso = ((int)$emprestimo['dias_em_aberto'] >= 15); ?>
                                    <div class="flex items-start justify-between gap-3 p-3 border rounded-md <?= $emAtraso ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50' ?>">
                                        <div class="min-w-0">
                                            <a href="detalhes_colaborador.php?id=<?= (int)$emprestimo['colaborador_id'] ?>" class="font-medium text-gray-800 hover:text-blue-700">
                                                <?= htmlspecialchars($emprestimo['colaborador_nome']) ?>
                                            </a>
                                            <p class="text-sm text-gray-700">
                                                <?= htmlspecialchars($emprestimo['farda_nome']) ?> - <?= htmlspecialchars($emprestimo['cor_nome']) ?> (<?= htmlspecialchars($emprestimo['tamanho_nome']) ?>)
                                            </p>
                                            <p class="text-sm font-medium <?= $emAtraso ? 'text-red-700' : 'text-gray-700' ?>">
                                                <?= (int)$emprestimo['quantidade'] ?> unidade(s) emprestada(s) há <?= (int)$emprestimo['dias_em_aberto'] ?> dia(s), desde <?= date('d/m/Y', strtotime($emprestimo['data_emprestimo'])) ?>.
                                                <?php if ($emAtraso): ?>
                                                    <span class="ml-1 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Atrasado</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <a href="devolver_emprestimo.php?colaborador_id=<?= (int)$emprestimo['colaborador_id'] ?>" class="text-xs font-semibold <?= $emAtraso ? 'text-red-700 hover:text-red-900' : 'text-blue-700 hover:text-blue-900' ?> whitespace-nowrap shrink-0">
                                            Resolver
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer flex items-center justify-between">
                        <p class="text-xs text-gray-500">Alertas automáticos após 15 dias sem devolução.</p>
                        <div class="flex items-center gap-2">
                            <a href="devolver_emprestimo.php" class="rounded-md bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-orange-700">
                                Gerir empréstimos
                            </a>
                            <button type="button" id="fecharModalAlertasEmprestimosRodape" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                                Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
        function configurarModal(config) {
            const trigger = document.getElementById(config.triggerId);
            const modal = document.getElementById(config.modalId);
            const overlay = document.getElementById(config.overlayId);
            const content = document.getElementById(config.contentId);
            const closeButtons = config.closeIds.map(function (id) {
                return document.getElementById(id);
            }).filter(Boolean);

            if (!trigger || !modal || !overlay || !content) {
                return null;
            }

            function abrir() {
                modal.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');

                requestAnimationFrame(function () {
                    overlay.classList.remove('opacity-0');
                    overlay.classList.add('opacity-100');
                    content.classList.remove('opacity-0', 'scale-95');
                    content.classList.add('opacity-100', 'scale-100');
                });
            }

            function fechar() {
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0');
                content.classList.remove('opacity-100', 'scale-100');
                content.classList.add('opacity-0', 'scale-95');

                setTimeout(function () {
                    modal.classList.add('hidden');
                }, 200);

                trigger.setAttribute('aria-expanded', 'false');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            }

            trigger.addEventListener('click', abrir);
            overlay.addEventListener('click', fechar);

            closeButtons.forEach(function (button) {
                button.addEventListener('click', fechar);
            });

            if (modal.classList.contains('hidden')) {
                modal.setAttribute('aria-hidden', 'true');
            }

            return {
                modal: modal,
                fechar: fechar,
            };
        }

        const modalAlertasEmprestimos = configurarModal({
            triggerId: 'cardAlertasEmprestimos',
            modalId: 'modalAlertasEmprestimos',
            overlayId: 'overlayModalAlertasEmprestimos',
            contentId: 'conteudoModalAlertasEmprestimos',
            closeIds: ['fecharModalAlertasEmprestimos', 'fecharModalAlertasEmprestimosRodape']
        });

        const modalAlertasColaboradores = configurarModal({
            triggerId: 'cardAlertasColaboradores',
            modalId: 'modalAlertasColaboradores',
            overlayId: 'overlayModalAlertas',
            contentId: 'conteudoModalAlertas',
            closeIds: ['fecharModalAlertas', 'fecharModalAlertasRodape']
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (modalAlertasEmprestimos && modalAlertasEmprestimos.modal && !modalAlertasEmprestimos.modal.classList.contains('hidden')) {
                modalAlertasEmprestimos.fechar();
                return;
            }

            if (modalAlertasColaboradores && modalAlertasColaboradores.modal && !modalAlertasColaboradores.modal.classList.contains('hidden')) {
                modalAlertasColaboradores.fechar();
            }
        });

        window.addEventListener('beforeunload', function () {
            document.body.classList.remove('overflow-hidden');
        });
    </script>
</body>
</html>