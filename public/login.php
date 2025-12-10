<?php
require_once '../src/session_bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/db.php';
require_once '../vendor/autoload.php';
require_once '../src/logger_file.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "Email e password são obrigatórios.";
    } else {
        try {
            // 🔍 Buscar utilizador pelo email
            $stmt = $pdo->prepare("SELECT id, email, password_hash, role_id, nome, google_authenticator_secret, is_active 
                                   FROM utilizadores 
                                   WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // ⚠️ Verificar se a conta foi aprovada
                if (!$user['is_active']) {
                    $errors[] = "A sua conta ainda não foi aprovada por um administrador.";
                    log_event_file('WARNING', 'LOGIN_BLOCKED', "Tentativa de login em conta inativa: {$email}");
                }
                // ✅ Verificar password
                elseif (password_verify($password, $user['password_hash'])) {

                    log_event_file('INFO', 'LOGIN_SUCCESS', "Utilizador '{$email}' fez login com sucesso.", $user['id']);

                    // 🔐 Se tiver 2FA ativo
                    if (!empty($user['google_authenticator_secret'])) {
                        $_SESSION['2fa_user_id'] = $user['id'];
                        header('Location: verificar_2fa.php');
                        exit;
                    }

                    // ✅ Login normal
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role_id'] = $user['role_id'];
                    $_SESSION['user_name'] = $user['nome'];

                    header('Location: index.php');
                    exit;

                } else {
                    log_event_file('WARNING', 'LOGIN_FAILURE', "Tentativa de login falhada (senha incorreta) para '{$email}'.");
                    $errors[] = "Credenciais inválidas.";
                }
            } else {
                $errors[] = "Credenciais inválidas.";
            }

        } catch (PDOException $e) {
            $errors[] = "Erro ao aceder à base de dados. " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CrewGest</title>
    <link href="/economato/public/css/style.css" rel="stylesheet">
    <style>
        body {
            background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            /*color: #fff;*/
            font-family: 'Inter', sans-serif;
            overflow: hidden; /* Para evitar scroll desnecessário */
        }
        header {
            width: 100%;
            opacity: 0;
            transform: translateY(-20px);
            animation: fadeInDown 1s ease-out forwards;
        }
        .form-container {
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 2.5rem;
    box-shadow:
        0 10px 30px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    max-width: 420px;
    width: 100%;
    margin-top: 4rem;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 1s ease-out 0.5s forwards;
}
        @keyframes fadeInDown {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .form-container h1 {
            color: #1f2937;
            font-size: 1.875rem;
            font-weight: 700;
        }
        .form-container p {
            color: #6b7280;
        }
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-group label {
            color: #374151;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .input-group input {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            width: 100%;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .input-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        button {
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            padding: 0.75rem;
            border-radius: 0.5rem;
            width: 100%;
            transition: background 0.3s ease, transform 0.1s ease;
        }
        button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        .link {
            color: #3b82f6;
            font-weight: 500;
            text-decoration: none;
        }
        .link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <?php include_once '../src/templates/header_public.php'; ?>

    <div class="form-container">
        <h1 class="text-center mb-2">Bem-vindo ao CrewGest</h1>
        <p class="text-center mb-8">Faça login para aceder ao sistema.</p>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-6" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="input-group">
                <label for="email" class="block mb-1">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                    required>
            </div>
            <div class="input-group">
                <label for="password" class="block mb-1">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required>
            </div>
            <button type="submit">Entrar</button>
            <p class="text-center text-sm mt-6">
                Não tem conta? <a href="registar.php" class="link">Registe-se aqui.</a>
            </p>
            <p class="text-center text-sm mt-4">
                Esqueceu a password? <a href="forgot_password.php" class="link">Reset aqui.</a>
            </p>
        </form>
    </div>
</body>
</html>