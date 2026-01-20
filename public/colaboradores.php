<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

$pesquisa = trim($_GET['pesquisa'] ?? '');
$mostrar_inativos = isset($_GET['mostrar_inativos']) && $_GET['mostrar_inativos'] == '1';

$colaboradores = [];

try {

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

    /* 🔴 FILTRO ATIVOS / INATIVOS (FALTAVA ISTO) */
    if (!$mostrar_inativos) {
        $sql .= " AND c.ativo = 1 ";
    }


    // 🔍 Pesquisa
    if ($pesquisa !== '') {

        if (ctype_digit($pesquisa)) {
            // 🔢 Número de funcionário → EXATO
            $sql .= " AND c.numero_funcionario = ? ";
            $params[] = (int)$pesquisa;
        } else {
            // 🔎 Texto
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

    // ✅ Por defeito: apenas ativos
    if (!$mostrar_inativos) {
        $sql .= " AND c.ativo = 1 ";
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

<main class="p-8 max-w-7xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">👥 Colaboradores</h1>
        <a href="adicionar_colaborador.php" 
           class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-200 active:scale-95">
            ➕ Adicionar Colaborador
        </a>
    </div>

    <!-- 🔍 Pesquisa + filtro -->
    <form method="GET" class="mb-6 space-y-3">

        <input type="hidden" name="mostrar_inativos" value="0">

        <div class="flex items-center gap-2">
            <input type="text" name="pesquisa"
                value="<?= htmlspecialchars($pesquisa) ?>"
                placeholder="🔍 Nome, cartão ou nº funcionário"
                class="flex-1 px-4 py-2 border rounded-md">

            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md">
                Pesquisar
            </button>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="mostrar_inativos" value="1"
                <?= $mostrar_inativos ? 'checked' : '' ?>>
            Mostrar colaboradores inativos
        </label>

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
                ?>
                <tr class="<?= $temDivida ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50' ?>">

                    <!-- 👤 Colaborador -->
                    <td class="px-6 py-3 border-b">
                        <div class="flex items-center gap-4">
                            <?php if (!empty($c['foto'])): ?>
                                <img src="<?= BASE_URL ?>/public/uploads/colaboradores/<?= htmlspecialchars($c['foto']) ?>"
                                     style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid #e5e7eb;">
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
                                    Nº <?= htmlspecialchars($c['numero_funcionario']) ?>
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
</body>
</html>
