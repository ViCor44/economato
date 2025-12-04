<?php
// public/editar_cacifo.php
declare(strict_types=1);

require_once '../src/auth_guard.php';   // garante sessão e $utilizador_logado
require_once '../config/db.php';       // $pdo

// Só utilizadores autenticados
if (!isset($utilizador_logado) || empty($utilizador_logado['id'])) {
    header('Location: login.php');
    exit;
}

// obter número do cacifo
$numero = isset($_GET['numero']) ? (int)$_GET['numero'] : 0;
if ($numero <= 0) {
    echo "Número do cacifo inválido.";
    exit;
}

// carregar informação do cacifo
$stmt = $pdo->prepare("SELECT colaborador_id, avariado FROM cacifos WHERE numero = ?");
$stmt->execute([$numero]);
$cacifo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cacifo) {
    echo "Cacifo número {$numero} não encontrado.";
    exit;
}

// carregar lista de colaboradores para select
$colStmt = $pdo->query("SELECT id, nome FROM colaboradores WHERE ativo = 1 ORDER BY nome ASC");
$colaboradores = $colStmt->fetchAll(PDO::FETCH_ASSOC);

// valores iniciais
$colaborador_id = $cacifo['colaborador_id'] !== null ? (int)$cacifo['colaborador_id'] : null;
$avariado = $cacifo['avariado'] ? 1 : 0;


$erro = null;
$sucesso = null;

// tratar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // se checkbox "avariado" estiver marcada, colocamos 1, senão 0
    $avariado_post = isset($_POST['avariado']) && ($_POST['avariado'] === '1' || $_POST['avariado'] === 'on' || $_POST['avariado'] === 'true') ? 1 : 0;

    // colaborador_id só deve ser usado se não estiver avariado
    $colaborador_post = null;
    if (!$avariado_post && isset($_POST['colaborador_id']) && $_POST['colaborador_id'] !== '') {
        $colaborador_post = (int)$_POST['colaborador_id'];
        if ($colaborador_post <= 0) $colaborador_post = null;
    }

    // actualizar na BD
    try {
        $uStmt = $pdo->prepare("UPDATE cacifos SET colaborador_id = ?, avariado = ?, estado = ? WHERE numero = ?");
        // passar NULL correcto para colaborador_id se necessário; PDO cuidará do binding
        $uStmt->execute([$colaborador_post, $avariado_post, $numero]);

        $sucesso = "Cacifo atualizado com sucesso.";
        // actualizar valores locais para mostrar no form
        $colaborador_id = $colaborador_post;
        $avariado = $avariado_post;
        
        // redirecionar para a lista (ajusta se o teu ficheiro de listagem tiver outro nome)
        header("Location: list_lockers.php");
        exit;

    } catch (PDOException $e) {
        $erro = "Erro ao atualizar cacifo: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <title>Editar Cacifo Nº <?= htmlspecialchars((string)$numero) ?></title>
  <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
  <style>
    /* estilos mínimos, adapta ao teu tema */
    .container { max-width:720px; margin:28px auto; padding:18px; background:#fff; border-radius:10px; box-shadow:0 8px 20px rgba(0,0,0,0.06); }
    header.page { padding:18px 0; text-align:center; }
    .row { margin-bottom:12px; }
    label { display:block; font-weight:600; margin-bottom:6px; }
    select,input[type="text"] { width:100%; padding:8px 10px; border-radius:8px; border:1px solid #e5e7eb; }
    .actions { display:flex; gap:8px; justify-content:flex-end; margin-top:14px; }
    .btn { padding:8px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
    .btn-primary { background:#2563eb; color:#fff; }
    .btn-ghost { background:#fff; border:1px solid #e5e7eb; color:#374151; }
    .notice { padding:10px; border-radius:6px; margin-bottom:12px; }
    .err { background:#fee2e2; color:#991b1b; }
    .ok { background:#ecfdf5; color:#065f46; }
    .inline { display:flex; align-items:center; gap:8px; }
  </style>

  <script>
    function toggleColaboradorField() {
        const chk = document.getElementById('avariado');
        const colsel = document.getElementById('colaborador_id');
        if (!colsel) return;
        if (chk.checked) {
            colsel.disabled = true;
            colsel.value = ""; // limpar seleção se tiver
        } else {
            colsel.disabled = false;
        }
    }
    window.addEventListener('DOMContentLoaded', function(){
        const chk = document.getElementById('avariado');
        if (chk) {
            chk.addEventListener('change', toggleColaboradorField);
            // inicializa estado
            toggleColaboradorField();
        }
    });
  </script>
</head>
<body class="bg-gray-100 p-8">
<?php include_once '../src/templates/header.php'; ?>

<main class="container">
  <header class="page">
    <h1>Editar Cacifo Nº <?= htmlspecialchars((string)$numero) ?></h1>
  </header>

  <?php if ($erro): ?>
    <div class="notice err"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <?php if ($sucesso): ?>
    <div class="notice ok"><?= htmlspecialchars($sucesso) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="row">
      <label for="colaborador_id">Colaborador associado (se aplicável)</label>
      <select id="colaborador_id" name="colaborador_id">
        <option value="">-- Nenhum --</option>
        <?php foreach ($colaboradores as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $colaborador_id === (int)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nome']) ?>
            </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row inline">
      <label style="margin:0;">
        <input type="checkbox" id="avariado" name="avariado" value="1" <?= $avariado ? 'checked' : '' ?>>
        &nbsp; Cacifo avariado
      </label>
    </div>

    <div class="actions">
      <a class="btn btn-ghost" href="list_lockers.php">Cancelar</a>
      <button type="submit" class="btn btn-primary">Atualizar</button>
    </div>
  </form>
</main>

</body>
</html>
