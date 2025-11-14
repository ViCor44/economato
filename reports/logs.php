<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Buscar logs + nome do utilizador (quem fez a ação)
$stmt = $pdo->query("
    SELECT l.*, u.nome AS nome_utilizador
    FROM logs l
    LEFT JOIN utilizadores u ON u.id = l.user_id
    ORDER BY l.id DESC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Util: tenta extrair um nome a partir do texto 'detalhes', usando vários padrões.
 * Retorna: array ['id' => int|null, 'name_from_text' => string|null]
 */
function parse_user_from_details(string $detalhes) {
    $out = ['id' => null, 'name_from_text' => null];

    // 1) procurar padrões "Utilizador ID 123" / "User ID 123"
    if (preg_match('/(?:Utilizador|User)\s+ID\s+(\d+)/i', $detalhes, $m)) {
        $out['id'] = (int)$m[1];
    }

    // 2) procurar padrões "admin ID 45" ou "admin: 45"
    if (preg_match('/(?:admin|Administrador)\s*(?:ID)?\s*[:\s]\s*(\d+)/i', $detalhes, $m2)) {
        // só define se não tinha id ainda (priorizar o id do alvo se for esse o caso)
        if (!$out['id']) {
            $out['id'] = (int)$m2[1];
        }
    }

    // 3) procurar padrões "nome: "Victor Correia"" (aspas) ou nome=Victor
    if (preg_match('/(?:nome|Name)\s*[:=]\s*"(.*?)"/i', $detalhes, $m3) ||
        preg_match('/(?:nome|Name)\s*[:=]\s*\'(.*?)\'/i', $detalhes, $m3) ) {
        $out['name_from_text'] = $m3[1];
    } elseif (preg_match('/(?:Utilizador|User)\s*[:\-]\s*([A-Za-zÀ-ÿ0-9\.\-\_\s]{2,})/i', $detalhes, $m4)) {
        // fallback: "Utilizador: Victor Correia" (sem aspas)
        $cand = trim($m4[1]);
        // evitar capturar textos muito longos
        if (strlen($cand) < 120) {
            $out['name_from_text'] = $cand;
        }
    }

    // 4) procurar "deleted user 123 (Victor)" style: id and parenthesis name
    if (!$out['name_from_text'] && preg_match('/\(([^)]+)\)\s*$/', $detalhes, $m5)) {
        $out['name_from_text'] = trim($m5[1]);
    }

    return $out;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Logs do Sistema</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
</head>

<body class="p-8 bg-gray-100">
<?php include '../src/templates/header.php'; ?>

<main class="max-w-5xl mx-auto bg-white p-8 rounded-xl shadow mt-8">

    <h1 class="text-3xl font-bold mb-6">📜 Logs do Sistema</h1>

    <table class="min-w-full border">
        <thead>
            <tr class="bg-gray-200 text-left">
                <th class="p-2">Data</th>
                <th class="p-2">Utilizador (actor)</th>
                <th class="p-2">Ação</th>
                <th class="p-2">Detalhes</th>
                <th class="p-2">IP</th>
            </tr>
        </thead>
        <tbody>

        <?php foreach ($logs as $log): ?>

            <?php
            // --- tratar o campo detalhes para tentar mostrar nomes legíveis ---
            $det = $log['detalhes'] ?? '';

            // Tentar extrair possível alvo/admin info do texto
            $parsed = parse_user_from_details($det);

            $display_target = null; // nome do utilizador alvo (por ex. o eliminado)
            $display_admin  = null; // nome do admin (se aplicável)

            // Se temos um ID extraído, tentar buscar o nome na BD
            if (!empty($parsed['id'])) {
                try {
                    $stmtName = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
                    $stmtName->execute([$parsed['id']]);
                    $nameFromDb = $stmtName->fetchColumn();
                    if ($nameFromDb !== false) {
                        $display_target = $nameFromDb;
                    } else {
                        // utilizador não existe (foi apagado): usar fallback com ID
                        $display_target = "ID {$parsed['id']} (apagado)";
                    }
                } catch (PDOException $e) {
                    $display_target = "ID {$parsed['id']}";
                }
            }

            // Se conseguimos extrair um nome a partir do texto, preferir esse nome
            if (!empty($parsed['name_from_text'])) {
                // Quando o texto contiver um nome explícito, usa-o (por ex. logs antigos)
                $display_target = $parsed['name_from_text'];
            }

            // Se não houver target identificado, tentar detectar "admin" no detalhe
            if (preg_match('/admin(?:istrador)?\s*(?:ID)?\s*[:\s]?\s*(\d+)/i', $det, $admMatch)) {
                $admId = (int)$admMatch[1];
                try {
                    $stmtA = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
                    $stmtA->execute([$admId]);
                    $n = $stmtA->fetchColumn();
                    $display_admin = $n !== false ? $n : "Admin ID {$admId} (apagado)";
                } catch (PDOException $e) {
                    $display_admin = "Admin ID {$admId}";
                }
            } elseif (preg_match('/admin(?:istrador)?[:=\-]\s*([A-Za-zÀ-ÿ0-9\.\-\_\s]{2,})/i', $det, $admNameMatch)) {
                $display_admin = trim($admNameMatch[1]);
            }

            // Se o log já tem user_id (quem executou a ação), usar o nome desse user como actor
            $actor_name = $log['nome_utilizador'] ?? null;
            if (empty($actor_name) && !empty($log['user_id'])) {
                // tentar buscar por segurança (caso left join falhou)
                try {
                    $stmtActor = $pdo->prepare("SELECT nome FROM utilizadores WHERE id = ?");
                    $stmtActor->execute([$log['user_id']]);
                    $actor_name = $stmtActor->fetchColumn() ?: "ID {$log['user_id']}";
                } catch (Exception $e) {
                    $actor_name = "ID {$log['user_id']}";
                }
            }

            // Construir um detalhe legível:
            $readable_details = $det; // por defeito, mostra o texto original
            // se detectámos um alvo/admin, criar frase mais clara
            if ($display_target && $display_admin) {
                $readable_details = "Utilizador {$display_target} eliminado pelo administrador {$display_admin}";
            } elseif ($display_target && $display_admin === null) {
                $readable_details = "Utilizador {$display_target}";
            } elseif (!$display_target && $display_admin) {
                $readable_details = "Ação por administrador {$display_admin}";
            }
            ?>

            <tr class="border-b">
                <td class="p-2"><?= htmlspecialchars($log['criado_em']) ?></td>
                <td class="p-2"><?= htmlspecialchars($actor_name ?? 'N/A') ?></td>
                <td class="p-2"><?= htmlspecialchars($log['acao']) ?></td>
                <td class="p-2 text-sm text-gray-600"><?= htmlspecialchars($readable_details) ?></td>
                <td class="p-2"><?= htmlspecialchars($log['ip']) ?></td>
            </tr>

        <?php endforeach; ?>

        </tbody>
    </table>

</main>
</body>
</html>
