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
            background: linear-gradient(to bottom, #6B46C1, #553C9A);
            height: 100vh;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            margin: 0;
        }

        .container {
            display: flex;
            flex-direction: row;
            height: 100vh;
        }

        .info-section {
            width: 40%;
            background: linear-gradient(to bottom, #6B46C1, #553C9A);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            position: relative;
        }

        .info-section::after {
            content: '';
            position: absolute;
            top: 0;
            right: -50px;
            width: 100px;
            height: 100%;
            background: linear-gradient(to right, #553C9A, transparent);
            z-index: 1;
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: #B794F4;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            color: #553C9A;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .logo-subtext {
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .info-section h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .info-section p {
            font-size: 1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .sobre-link {
            color: #4299E1;
            text-decoration: none;
            font-weight: 500;
        }

        .form-section {
            width: 60%;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            border-top-left-radius: 50px;
            border-bottom-left-radius: 50px;
            position: relative;
            z-index: 2;
        }

        .form-container {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
        }

        .form-container h2 {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            color: #4A5568;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #E2E8F0;
            border-radius: 0.375rem;
            background: #F7FAFC;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: #4299E1;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-weight: 600;
            cursor: pointer;
        }

        .links {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        .links a {
            color: #4299E1;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .info-section {
                width: 100%;
                height: 40%;
                border-bottom-right-radius: 50px;
                border-bottom-left-radius: 50px;
                padding: 2rem;
                text-align: center;
            }

            .info-section::after {
                display: none;
            }

            .form-section {
                width: 100%;
                height: 60%;
                border-top-left-radius: 50px;
                border-top-right-radius: 50px;
            }

            .logo {
                justify-content: center;
            }
        }
        .cg-logo {
            width:56px;height:56px;border-radius:12px;
            background:linear-gradient(135deg,#2563eb,#1d4ed8);
            display:flex;align-items:center;justify-content:center;
            color:#fff;font-weight:700;font-size:18px;
            box-shadow:0 6px 18px rgba(37,99,235,0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Lado Esquerdo: Informações -->
        <div class="info-section">
            <div class="logo">
                <div class="cg-logo mr-4">Cr
                <!-- Placeholder para ícone; substitua por SVG ou imagem real se disponível -->
                </div>
                <div>
                    <div class="logo-text">CrewGest</div>
                    <div class="logo-subtext">Gestão de fardas, stock e inventários</div>
                </div>
            </div>
            <h1>Bem-vindo ao CrewGest</h1>
            <p>Gestão de fardas, stock e inventários de forma simples e eficiente.</p>
            <a href="about.php" class="sobre-link">Sobre</a>
        </div>

        <!-- Lado Direito: Formulário de Login -->
        <div class="form-section">
            <div class="form-container">
                <h2>Login</h2>

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
                        <label for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                            required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required>
                    </div>
                    <button type="submit">Entrar</button>
                    <div class="links">
                        <span>Não tem conta? <a href="registar.php">Registe-se</a></span>
                        <a href="forgot_password.php">Esqueci-me da password</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>