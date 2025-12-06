<?php
// public/reset_password.php
declare(strict_types=1);

require_once '../config/db.php';

function show_message($msg_html) {
    echo '<div style="max-width:700px;margin:30px auto;font-family:Arial,sans-serif">';
    echo '<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.06)">';
    echo $msg_html;
    echo '</div></div>';
}

// helper: tentar encontrar utilizador por id em duas tabelas e devolver [table, row]
function find_user_by_id(PDO $pdo, $id) {
    if (!$id) return null;
    // tenta 'colaboradores' primeiro
    $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) return ['table' => 'colaboradores', 'row' => $r];

    // tenta 'utilizadores'
    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) return ['table' => 'utilizadores', 'row' => $r];

    return null;
}

// helper: detectar a coluna de password na row (preferência)
function detect_password_column(array $row) {
    $candidates = ['password_hash','password','senha','pwd'];
    foreach ($candidates as $c) {
        if (array_key_exists($c, $row)) return $c;
    }
    // fallback: primeira coluna que contenha "pass" no nome
    foreach ($row as $col => $v) {
        if (stripos($col, 'pass') !== false) return $col;
    }
    return null;
}

// obter token do GET
$token = trim((string)($_GET['token'] ?? ''));

// Se POST: tratar update da password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_post = trim((string)($_POST['token'] ?? ''));
    $new_password = trim((string)($_POST['password'] ?? ''));
    $confirm = trim((string)($_POST['password_confirm'] ?? ''));

    if ($token_post === '' || $new_password === '' || $confirm === '') {
        show_message('<p style="color:#b91c1c">Preencha todos os campos.</p>');
        exit;
    }
    if ($new_password !== $confirm) {
        show_message('<p style="color:#b91c1c">As passwords não coincidem.</p>');
        exit;
    }
    if (strlen($new_password) < 6) {
        show_message('<p style="color:#b91c1c">A password deve ter pelo menos 6 caracteres.</p>');
        exit;
    }

    $token = $token_post;

    // Buscar tokens válidos (não expirados). Limitamos a alguns para performance.
    $stmt = $pdo->prepare("SELECT id, user_id, email, token_hash, expires_at FROM password_resets WHERE expires_at >= NOW() ORDER BY created_at DESC LIMIT 500");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $match = null;
    foreach ($rows as $r) {
        if (!empty($r['token_hash']) && password_verify($token, $r['token_hash'])) {
            $match = $r;
            break;
        }
    }

    if (!$match) {
        show_message('<p style="color:#b91c1c">Token inválido ou expirado.</p>');
        exit;
    }

    $user_id = $match['user_id'];
    $user_email = $match['email'];

    // Se não existe user_id (token gerado para um email não existente), aceitaremos
    // o pedido mas não atualizaremos nenhuma conta — só apagamos tokens.
    try {
        $pdo->beginTransaction();

        if ($user_id) {
            $found = find_user_by_id($pdo, $user_id);
            if ($found) {
                $table = $found['table'];
                $row = $found['row'];
                $passcol = detect_password_column($row);
                if (!$passcol) {
                    // não sabemos qual coluna atualizar -> log e falha
                    error_log("reset_password: não foi possível detectar coluna de password para user_id={$user_id} (table={$table})");
                    throw new Exception("Erro interno ao actualizar a password.");
                }

                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE {$table} SET {$passcol} = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$hash, $user_id]);
                // regista para debug (sem expor password)
                error_log("reset_password: password actualizada para user_id={$user_id} table={$table} col={$passcol}");
            } else {
                // user_id presente no token mas não encontramos user -- logar
                error_log("reset_password: user_id {$user_id} não encontrado em colaboradores nem utilizadores.");
            }
        } else {
            // token associado a um email mas sem user_id -> apenas logamos
            error_log("reset_password: token válido para email {$user_email} mas user_id é NULL. Nenhuma conta será actualizada.");
        }

        // apagar tokens associados ao email OU ao user_id (garantir que não reutilizam)
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ? OR user_id = ?");
        $stmt->execute([$user_email, $user_id]);

        $pdo->commit();

        show_message('<p style="color:#15803d">Password alterada com sucesso. Já pode entrar com a nova password.</p>');
        echo '<p style="text-align:center"><a href="/economato/public/login.php">Ir para Login</a></p>';
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("reset_password: erro ao actualizar password: " . $e->getMessage());
        show_message('<p style="color:#b91c1c">Erro ao atualizar a password. Tente novamente mais tarde.</p>');
        exit;
    }
}

// === GET: mostrar formulário ===
if ($token === '') {
    show_message('<p>Pedido inválido. Sem token.</p>');
    exit;
}

// procurar token válido (não expirado)
$stmt = $pdo->prepare("SELECT id, user_id, email, token_hash, expires_at FROM password_resets WHERE expires_at >= NOW() ORDER BY created_at DESC LIMIT 500");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$match = null;
foreach ($rows as $r) {
    if (!empty($r['token_hash']) && password_verify($token, $r['token_hash'])) {
        $match = $r;
        break;
    }
}

if (!$match) {
    show_message('<p style="color:#b91c1c">Token inválido ou expirado.</p>');
    exit;
}

// token válido -> renderizar formulário
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8" />
  <title>Redefinir password</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="/economato/public/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include_once '../src/templates/header_public.php'; ?>
  <main style="max-width:640px;margin:40px auto;font-family:Arial,Helvetica,sans-serif">
    <div style="background:#fff;padding:22px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.06)">
      <h1 class="text-2xl font-bold mb-4">Redefinir a password</h1>
      <p class="mb-2">Insira uma nova password para a conta associada ao pedido.</p>

      <form method="post" action="">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div style="margin-bottom:12px">
          <label>Nova password</label><br>
          <input name="password" type="password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px">
        </div>
        <div style="margin-bottom:12px">
          <label>Confirmar password</label><br>
          <input name="password_confirm" type="password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px">
        </div>
        <div style="text-align:right">
          <button type="submit" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;border:none;font-weight:600">Atualizar password</button>
        </div>
      </form>
    </div>
  </main>
    <?php include_once '../src/templates/footer.php'; ?>
</body>
</html>
