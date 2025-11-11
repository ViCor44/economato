<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';
require_once '../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

$google2fa = new Google2FA();
$error = null;

// --- LÓGICA PARA VERIFICAR O CÓDIGO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
    $secret_key = $_SESSION['temp_2fa_secret'] ?? '';
    $one_time_password = $_POST['one_time_password'] ?? '';

    if (empty($secret_key)) {
        // Se a chave temporária não existe, algo correu mal. Volta ao perfil.
        header('Location: perfil.php');
        exit;
    }

    $valid = $google2fa->verifyKey($secret_key, $one_time_password);

    if ($valid) {
        // O código está correto! Salvar a chave na base de dados
        try {
            $stmt = $pdo->prepare("UPDATE utilizadores SET google_authenticator_secret = ? WHERE id = ?");
            $stmt->execute([$secret_key, $utilizador_logado['id']]);
            
            unset($_SESSION['temp_2fa_secret']); // Limpar a chave temporária
            
            // Redirecionar para o perfil com mensagem de sucesso (será implementado depois)
            header('Location: perfil.php');
            exit;
        } catch (PDOException $e) {
            $error = "Não foi possível salvar a configuração 2FA.";
        }
    } else {
        $error = "Código de verificação inválido. Tente novamente.";
    }
}

// --- LÓGICA PARA GERAR O QR CODE (GET ou POST inicial) ---
// Gerar e guardar uma chave secreta temporária na sessão
$_SESSION['temp_2fa_secret'] = $google2fa->generateSecretKey();
$secret_key = $_SESSION['temp_2fa_secret'];

$appName = 'CrewSync';
$qrCodeUrl = $google2fa->getQRCodeUrl($appName, $utilizador_logado['email'], $secret_key);

// Gerar a imagem do QR Code
$writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
$qrCodeImage = $writer->writeString($qrCodeUrl);
$qrCodeBase64 = base64_encode($qrCodeImage);

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar 2FA - CrewSync</title>
    <link href="/slide_rh_app/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include_once '../src/templates/header.php'; ?>
    <main class="p-8">
        <div class="max-w-xl mx-auto bg-white p-8 rounded-2xl shadow-lg text-center">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Ativar Autenticação de Dois Fatores</h1>
            
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 mb-4 rounded-md"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <p class="text-gray-600 mb-6">Digitalize o QR Code com a sua app de autenticação (ex: Google Authenticator) e insira o código de 6 dígitos para confirmar.</p>

            <div class="flex justify-center p-4 border rounded-lg my-4">
                <img src="data:image/svg+xml;base64,<?= $qrCodeBase64 ?>" alt="QR Code para 2FA">
            </div>

            <p class="text-xs text-gray-500 mb-6">Ou insira esta chave manualmente: <strong class="font-mono"><?= htmlspecialchars($secret_key) ?></strong></p>

            <form action="ativar_2fa.php" method="POST">
                <input type="hidden" name="action" value="verify_2fa">
                <label for="one_time_password" class="block text-sm font-medium text-gray-700 mb-1">Código de Verificação</label>
                <input type="text" id="one_time_password" name="one_time_password" 
                       class="w-full max-w-xs mx-auto text-center text-2xl tracking-[0.5em] px-4 py-2 border rounded-md" 
                       required maxlength="6" inputmode="numeric" pattern="[0-9]{6}">
                <div class="mt-6 flex justify-center gap-4">
                     <a href="perfil.php" class="bg-gray-200 text-gray-800 font-bold py-2 px-6 rounded-lg hover:bg-gray-300">Cancelar</a>
                    <button type="submit" class="bg-green-500 text-white font-bold py-2 px-6 rounded-lg hover:bg-green-600">Verificar e Ativar</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>