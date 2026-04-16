<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$pesquisa = trim($_GET['pesquisa'] ?? '');
$mostrar_inativos = isset($_GET['mostrar_inativos']) && $_GET['mostrar_inativos'] == '1';
$estado = $_GET['estado'] ?? '';

if (!in_array($estado, ['ativos', 'inativos', 'todos'], true)) {
    $estado = $mostrar_inativos ? 'todos' : 'ativos';
}

$mostrar_inativos = ($estado === 'todos');

$colaboradores = [];
$total_ativos = 0;
$total_inativos = 0;

// Carregar departamentos para o filtro
$departamento_id = isset($_GET['departamento_id']) ? (int)$_GET['departamento_id'] : 0;
$departamentos = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM departamentos ORDER BY nome ASC");
    $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departamentos = [];
}

try {

    $stmtTotais = $pdo->query("SELECT
        SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS total_ativos,
        SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) AS total_inativos
        FROM colaboradores");
    $totais = $stmtTotais->fetch(PDO::FETCH_ASSOC);
    $total_ativos = (int)($totais['total_ativos'] ?? 0);
    $total_inativos = (int)($totais['total_inativos'] ?? 0);

    $sql = "
        SELECT
            c.*,
            d.nome AS departamento_nome,
            COALESCE(SUM(
                CASE 
                    WHEN fa.estado = 'em_divida'
                    THEN fa.quantidade * f.preco_unitario
                    ELSE 0
                END
            ), 0) AS total_divida
        FROM colaboradores c
        LEFT JOIN departamentos d ON c.departamento_id = d.id
        LEFT JOIN farda_atribuicoes fa ON fa.colaborador_id = c.id
        LEFT JOIN fardas f ON f.id = fa.farda_id
        WHERE 1 = 1
    ";

    $params = [];

    if ($estado === 'ativos') {
        $sql .= " AND c.ativo = 1 ";
    } elseif ($estado === 'inativos') {
        $sql .= " AND c.ativo = 0 ";
    }


    // 🔍 Pesquisa
    if ($pesquisa !== '') {
        if (ctype_digit($pesquisa) && strlen($pesquisa) <= 4) {
            // 🔢 Nº funcionário (curto → exato)
            $sql .= " AND c.numero_funcionario = ? ";
            $params[] = (int)$pesquisa;

        } else {
            // 🔎 Nome, email OU cartão (numérico ou texto)
            $sql .= " AND (
                c.nome LIKE ?
                OR c.cartao LIKE ?
                OR c.email LIKE ?
            ) ";
            $like = "%{$pesquisa}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }

        // Filtro por departamento
        if ($departamento_id > 0) {
            $sql .= " AND c.departamento_id = ? ";
            $params[] = $departamento_id;
        }

    $sql .= "
        GROUP BY c.id, d.nome
        ORDER BY c.nome ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao carregar colaboradores: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colaboradores - CrewGest</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include_once '../src/templates/header.php'; ?>

<?php
$queryBase = [
    'pesquisa' => $pesquisa,
    'departamento_id' => $departamento_id,
];
?>

<main class="p-8 max-w-7xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">👥 Colaboradores</h1>
        <div class="flex items-center gap-3">
            <?php if ($_SESSION['role_id'] === 1): // Apenas para admins ?>
            <a href="reverter_termo_devolucao.php" 
               class="flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-200 active:scale-95">
                🔄 Reverter Termos
            </a>
            <?php endif; ?>
            <a href="adicionar_colaborador.php" 
               class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-200 active:scale-95">
                ➕ Adicionar Colaborador
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
        <a href="?<?= htmlspecialchars(http_build_query($queryBase + ['estado' => 'ativos'])) ?>"
           class="block rounded-xl border px-4 py-3 transition <?= $estado === 'ativos' ? 'bg-green-100 border-green-400 ring-2 ring-green-200' : 'bg-green-50 border-green-200 hover:bg-green-100' ?>">
            <p class="text-xs font-semibold text-green-700 uppercase tracking-wide">Ativos</p>
            <p class="text-2xl font-bold text-green-800"><?= $total_ativos ?></p>
        </a>
        <a href="?<?= htmlspecialchars(http_build_query($queryBase + ['estado' => 'inativos'])) ?>"
           class="block rounded-xl border px-4 py-3 transition <?= $estado === 'inativos' ? 'bg-gray-200 border-gray-500 ring-2 ring-gray-300' : 'bg-gray-100 border-gray-300 hover:bg-gray-200' ?>">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Inativos</p>
            <p class="text-2xl font-bold text-gray-800"><?= $total_inativos ?></p>
        </a>
    </div>

    <!-- 🔍 Pesquisa + filtro -->
    <form method="GET" class="mb-6 space-y-3">

        <input type="hidden" name="mostrar_inativos" value="0">
        <input type="hidden" name="estado" id="estado_filtro" value="<?= htmlspecialchars($estado) ?>">

        <div class="flex items-center gap-2">
            <input type="text" name="pesquisa"
                value="<?= htmlspecialchars($pesquisa) ?>"
                placeholder="🔍 Nome, cartão ou nº funcionário"
                class="flex-1 px-4 py-2 border rounded-md">

                <select name="departamento_id" class="px-4 py-2 border rounded-md">
                    <option value="0">Todos os departamentos</option>
                    <?php foreach ($departamentos as $dep): ?>
                        <option value="<?= $dep['id'] ?>" <?= $departamento_id == $dep['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dep['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md">
                Pesquisar
            </button>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="mostrar_inativos" value="1"
                <?= $mostrar_inativos ? 'checked' : '' ?>>
            Mostrar colaboradores inativos juntamente com os ativos
        </label>

        <?php if ($estado === 'inativos'): ?>
            <p class="text-sm text-gray-500">Filtro ativo: a lista está a mostrar apenas colaboradores inativos.</p>
        <?php elseif ($estado === 'ativos'): ?>
            <p class="text-sm text-gray-500">Filtro ativo: a lista está a mostrar apenas colaboradores ativos.</p>
        <?php else: ?>
            <p class="text-sm text-gray-500">Filtro ativo: a lista está a mostrar colaboradores ativos e inativos.</p>
        <?php endif; ?>

    </form>

    <!-- 📋 Tabela -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <table class="min-w-full text-sm text-gray-700">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-3 text-left">Colaborador</th>
                    <th class="px-6 py-3 text-left">Cartão</th>
                    <th class="px-6 py-3 text-left">Telefone</th>
                    <th class="px-6 py-3 text-left">Email</th>
                    <th class="px-6 py-3 text-left">Departamento</th>
                    <th class="px-6 py-3 text-center">Estado</th>
                    <th class="px-6 py-3 text-right">Ações</th>
                </tr>
            </thead>
            <tbody>

            <?php if (empty($colaboradores)): ?>
                <tr>
                    <td colspan="7" class="text-center py-6 text-gray-500">
                        Nenhum colaborador encontrado.
                    </td>
                </tr>
            <?php else: ?>

                <?php foreach ($colaboradores as $c): 
                    $temDivida = $c['total_divida'] > 0;
                    $numeroColaborador = trim((string)($c['numero_funcionario'] ?? ''));
                ?>
                <tr class="<?= $temDivida ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50' ?>">

                    <!-- 👤 Colaborador -->
                    <td class="px-6 py-3 border-b">
                        <div class="flex items-center gap-4">
                            <?php if (!empty($c['foto'])): ?>
                            <div style="width:64px;height:64px;border-radius:50%;overflow:hidden;border:1px solid #e5e7eb;flex-shrink:0;">
                                <img src="<?= BASE_URL ?>/public/uploads/colaboradores/<?= htmlspecialchars($c['foto']) ?>"
                                    style="width:100%;height:100%;object-fit:cover;">
                            </div>                            
                            <?php else: ?>
                                <div style="width:64px;height:64px;border-radius:50%;background:#e5e7eb;
                                            display:flex;align-items:center;justify-content:center;
                                            color:#6b7280;flex-shrink:0;">
                                    👤
                                </div>
                            <?php endif; ?>

                            <div>
                                <a href="detalhes_colaborador.php?id=<?= $c['id'] ?>"
                                   class="text-blue-600 hover:underline font-medium">
                                    <?= htmlspecialchars($c['nome']) ?>
                                </a>

                                <?php if ($temDivida): ?>
                                    <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5
                                                 rounded-full bg-red-100 text-red-700 text-xs font-semibold">
                                        ⚠ Dívida
                                    </span>
                                <?php endif; ?>

                                <div class="text-xs text-gray-500">
                                    <?= $numeroColaborador !== ''
                                        ? 'Nº ' . htmlspecialchars($numeroColaborador)
                                        : 'Sem número atribuído' ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['cartao'] ?: '—') ?></td>
                    <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['telefone'] ?: '—') ?></td>
                    <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['email'] ?: '—') ?></td>
                    <td class="px-6 py-3 border-b"><?= htmlspecialchars($c['departamento_nome'] ?: '—') ?></td>

                    <td class="px-6 py-3 border-b text-center">
                        <?= $c['ativo']
                            ? '<span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Ativo</span>'
                            : '<span class="px-3 py-1 rounded-full bg-gray-200 text-gray-600 text-xs font-semibold">Inativo</span>' ?>
                    </td>

                    <td class="px-6 py-3 border-b text-right">
                        <a href="editar_colaborador.php?id=<?= $c['id'] ?>"
                           class="text-indigo-600 hover:text-indigo-800 font-medium mr-3">
                            Editar
                        </a>
                        <form action="eliminar_colaborador.php" method="POST" class="inline"
                              onsubmit="return confirm('Tem certeza que deseja eliminar este colaborador?');">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit"
                                    class="text-red-600 hover:text-red-800 font-medium">
                                Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>
        </table>
    </div>

</main>

<?php include_once '../src/templates/footer.php'; ?>
<script>
    const mostrarInativosCheckbox = document.querySelector('input[name="mostrar_inativos"][value="1"]');
    const estadoFiltroInput = document.getElementById('estado_filtro');

    if (mostrarInativosCheckbox && estadoFiltroInput) {
        mostrarInativosCheckbox.addEventListener('change', () => {
            estadoFiltroInput.value = mostrarInativosCheckbox.checked ? 'todos' : 'ativos';
        });
    }
</script>
</body>
</html>
