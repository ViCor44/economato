<?php
session_start();
require_once '../config/db.php';

// 🔒 Garantir login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pesquisa = trim($_GET['pesquisa'] ?? '');
$cacifos = [];
$ocupados = 0;
$avariados = 0;
$total_cacifos = 540;

try {
    // 🔍 Query principal (JOIN com colaboradores)
    if (!empty($pesquisa)) {
        $stmt = $pdo->prepare("
            SELECT c.numero, c.avariado, col.nome AS colaborador, col.cartao
            FROM cacifos c
            LEFT JOIN colaboradores col ON c.colaborador_id = col.id
            WHERE col.nome LIKE :pesq
            OR col.cartao LIKE :pesq
            ORDER BY c.numero ASC
        ");
        $stmt->execute(['pesq' => "%$pesquisa%"]);
    } else {
        $stmt = $pdo->query("
            SELECT c.numero, c.avariado, col.nome AS colaborador, col.cartao
            FROM cacifos c
            LEFT JOIN colaboradores col ON c.colaborador_id = col.id
            ORDER BY c.numero ASC
        ");
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $num = (int)$row['numero'];
        $cacifos[$num] = [
            'colaborador' => $row['colaborador'],
            'avariado' => (bool)$row['avariado']
        ];

        if ($row['avariado']) {
            $avariados++;
        } elseif (!empty($row['colaborador'])) {
            $ocupados++;
        }
    }

    $livres = $total_cacifos - $ocupados - $avariados;

} catch (PDOException $e) {
    die("Erro ao carregar cacifos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cacifos - CrewGest</title>
    <link href="/economato/public/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f7f7f7;
        }
        main {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .container {
            display: grid;
            grid-template-columns: repeat(30, 1fr);
            grid-gap: 5px;
            padding: 20px 0;
        }
        .cacifo {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border-radius: 5px;
            text-decoration: none;
            transition: transform 0.2s ease;
            position: relative;
        }
        .blue { background-color: blue; }
        .yellow { background-color: yellow; }
        .red { background-color: red; }
        .textblack { color: black; }
        .textwhite { color: white; }
        .blue.occupied { background-color: rgba(0, 0, 255, 0.2); }
        .yellow.occupied { background-color: rgba(255, 255, 0, 0.2); }
        a.link-branco,
        a.link-branco:visited,
        a.link-branco:hover,
        a.link-branco:active { color: white !important; }
        .cacifo:hover {
            cursor: pointer;
            opacity: 0.8;
            transform: scale(1.05);
        }
        .delete-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            background-color: red;
            color: white;
            font-size: 10px;
            padding: 2px 4px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .stats {
            padding: 10px;
            text-align: center;
            font-size: 18px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .actions {
            text-align: center;
            margin: 10px 0 25px;
        }
        .actions a {
            margin: 0 10px;
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }
        .actions a:hover {
            background-color: #0056b3;
        }
        form.pesquisa {
            text-align: center;
            margin-bottom: 15px;
        }
        form.pesquisa input[type="text"] {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            width: 200px;
        }
        form.pesquisa button {
            padding: 6px 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        form.pesquisa button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

<?php include_once '../src/templates/header.php'; ?>

<main>
    <h3 style="text-align:left; margin-bottom: 10px; font-size:xx-large">Gestão de Cacifos</h3>

    <form method="GET" class="pesquisa">
        <label for="pesquisa">Pesquisar colaborador: </label>
        <input type="text" id="pesquisa" name="pesquisa" value="<?= htmlspecialchars($pesquisa) ?>">
        <button type="submit">Pesquisar</button>
    </form>

    <div class="actions">
        <a href="table_lockers.php">🔒 Cacifos Ocupados</a>
        <a href="table_broken_lockers.php">⚠️ Cacifos Avariados</a>
    </div>

    <div class="stats">
        <strong>Cacifos Livres:</strong> <?= $livres ?> |
        <strong>Cacifos Ocupados:</strong> <?= $ocupados ?> |
        <strong>Cacifos Avariados:</strong> <?= $avariados ?>
    </div>

    <div class="container">
        <?php
        for ($i = 1; $i <= $total_cacifos; $i++) {
            if (isset($cacifos[$i])) {
                $colab = $cacifos[$i]['colaborador'] ?? '';
                $tooltip = $cacifos[$i]['avariado'] ? "Avariado" : htmlspecialchars($colab ?: "Ocupado");
                $url = "edit_locker.php?numero=$i";

                if ($cacifos[$i]['avariado']) {
                    $class = 'red link-branco';
                } elseif ($i <= 268) {
                    $class = 'blue occupied link-branco';
                } else {
                    $class = 'yellow occupied';
                }

                $deleteUrl = "delete_locker.php?numero=$i";
                echo "<div class='cacifo $class' title='$tooltip'>
                        <a href='$url'>$i</a>
                        <a href='$deleteUrl' class='delete-btn' onclick='return confirm(\"Remover colaborador deste cacifo?\");'>X</a>
                      </div>";
            } else {
                $class = ($i <= 268) ? 'blue textwhite' : 'yellow textblack';
                $url = "register_locker.php?numero=$i";
                echo "<a href='$url' class='cacifo $class' title='Livre'>$i</a>";
            }
        }
        ?>
    </div>
</main>

</body>
</html>
