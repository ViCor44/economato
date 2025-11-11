<?php
session_start();
require_once '../config/db.php';

// 🔒 Verificar login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🧩 Obter número do cacifo
$numero = isset($_GET['numero']) ? (int)$_GET['numero'] : 0;
if ($numero <= 0) {
    die("Número de cacifo inválido.");
}

$mensagem = '';
$erro = '';

// 🔍 Buscar colaboradores ativos
try {
    $stmt = $pdo->query("SELECT id, nome FROM colaboradores WHERE ativo = 1 ORDER BY nome ASC");
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar colaboradores: " . $e->getMessage());
}

// 🔍 Buscar informações do cacifo atual (se existir)
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.colaborador_id, c.avariado, col.nome AS colaborador
        FROM cacifos c
        LEFT JOIN colaboradores col ON c.colaborador_id = col.id
        WHERE c.numero = :numero
    ");
    $stmt->execute(['numero' => $numero]);
    $cacifo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados do cacifo.");
}

// 🧾 Submissão do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = !empty($_POST['colaborador_id']) ? (int)$_POST['colaborador_id'] : null;
    $avariado = isset($_POST['avariado']) ? 1 : 0;

    try {
        // Se o cacifo ainda não existir, cria-o
        $stmt = $pdo->prepare("SELECT id FROM cacifos WHERE numero = :numero");
        $stmt->execute(['numero' => $numero]);
        $existe = $stmt->fetch();

        if ($existe) {
            $stmt = $pdo->prepare("
                UPDATE cacifos 
                SET colaborador_id = :colab, avariado = :avariado
                WHERE numero = :numero
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO cacifos (numero, colaborador_id, avariado)
                VALUES (:numero, :colab, :avariado)
            ");
        }

        $stmt->execute([
            'numero' => $numero,
            'colab' => $avariado ? null : $colaborador_id,
            'avariado' => $avariado
        ]);

        $mensagem = "Cacifo atualizado com sucesso!";
        // Atualiza os dados locais
        $cacifo['colaborador_id'] = $colaborador_id;
        $cacifo['avariado'] = $avariado;
    } catch (PDOException $e) {
        $erro = "Erro ao atualizar o cacifo: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atribuir Cacifo <?= $numero ?> - CrewGest</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; margin: 0; }
        main { max-width: 700px; margin: 40px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; margin-bottom: 25px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        select, input[type="checkbox"] { margin-bottom: 15px; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; }
        .btn { background: #007bff; color: white; border: none; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #0056b3; }
        .msg { color: green; font-weight: bold; text-align: center; margin-bottom: 15px; }
        .erro { color: red; font-weight: bold; text-align: center; margin-bottom: 15px; }
        .back { display: inline-block; margin-top: 15px; text-decoration: none; color: #007bff; }
        .back:hover { text-decoration: underline; }
        .disabled { color: #aaa; }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .checkbox-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
}

    </style>
</head>
<body>

<?php include_once '../src/templates/header.php'; ?>

<main>
    <h1>Cacifo Nº <?= htmlspecialchars($numero) ?></h1>

    <?php if ($mensagem): ?><p class="msg"><?= htmlspecialchars($mensagem) ?></p><?php endif; ?>
    <?php if ($erro): ?><p class="erro"><?= htmlspecialchars($erro) ?></p><?php endif; ?>

    <form method="POST">
        <label for="colaborador_id">Atribuir a colaborador:</label>
        <select name="colaborador_id" id="colaborador_id" <?= $cacifo['avariado'] ? 'disabled class="disabled"' : '' ?>>
            <option value="">-- Nenhum (livre) --</option>
            <?php foreach ($colaboradores as $col): ?>
                <option value="<?= $col['id'] ?>"
                    <?= (!empty($cacifo['colaborador_id']) && $cacifo['colaborador_id'] == $col['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($col['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        
            <div class="checkbox-row">
                <input type="checkbox" name="avariado" id="avariado" value="1"
                    <?= !empty($cacifo['avariado']) ? 'checked' : '' ?>
                    onclick="toggleColaborador(this)">
                <label for="avariado">Marcar como avariado</label>
            </div>
       

        <button type="submit" class="btn">Guardar</button>
        <a href="list_lockers.php" class="back">← Voltar à lista</a>
    </form>
</main>

<script>
function toggleColaborador(checkbox) {
    const select = document.getElementById('colaborador_id');
    if (checkbox.checked) {
        select.disabled = true;
        select.classList.add('disabled');
    } else {
        select.disabled = false;
        select.classList.remove('disabled');
    }
}
</script>

</body>
</html>
