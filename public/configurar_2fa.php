<?php
require_once '../src/session_bootstrap.php';

// Medida de segurança: se o utilizador não veio da página de registo, não pode estar aqui.
if (!isset($_SESSION['registration_success']) || !$_SESSION['registration_success']) {
    header('Location: login.php');
    exit;
}

require_once '../vendor/autoload.php';

use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

// Recuperar os dados da sessão
$secret_key = $_SESSION['registration_secret_key'];
$email = $_SESSION['registration_email'];
$appName = 'CrewGest';

// Limpar os dados da sessão para que esta página só possa ser vista uma vez
unset($_SESSION['registration_success']);
unset($_SESSION['registration_secret_key']);
unset($_SESSION['registration_email']);


// Gerar o URL para o QR Code
$google2fa = new Google2FA();
$qrCodeUrl = $google2fa->getQRCodeUrl(
    $appName,
    $email,
    $secret_key
);

// Gerar a imagem SVG do QR Code
$renderer = new ImageRenderer(
    new RendererStyle(200), // Tamanho do QR Code
    new SvgImageBackEnd()
);
$writer = new Writer($renderer);
$qrCodeImage = $writer->writeString($qrCodeUrl);

// Converter o SVG para Base64 para embutir diretamente no HTML
$qrCodeBase64 = base64_encode($qrCodeImage);

?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure a sua Segurança - Slide RH</title>
    <link href="/economato/public/css/style.css" rel="stylesheet">
</head>
<body class="p-8">
    <?php include_once '../src/templates/header_public.php'; ?>

    <main class="flex items-center justify-center py-12">
    <div class="w-full max-w-lg bg-white p-8 rounded-2xl shadow-lg text-center">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Ação Requerida: Configure o 2FA</h1>
        <p class="text-gray-600 mb-8">Para sua segurança, a autenticação de dois fatores (2FA) é obrigatória.</p>

        <div class="text-left space-y-6">
            <div>
                <h2 class="font-bold text-lg text-gray-700">Passo 1: Instale uma App de Autenticação</h2>
                <p class="text-sm text-gray-600">Se ainda não tiver, instale o <strong class="font-medium">Google Authenticator</strong>, Authy ou uma app semelhante no seu telemóvel.</p>
            </div>

            <div>
                <h2 class="font-bold text-lg text-gray-700">Passo 2: Digitalize o QR Code</h2>
                <p class="text-sm text-gray-600 mb-4">Abra a sua app de autenticação, clique para adicionar uma nova conta e digitalize a imagem abaixo.</p>
                <div class="flex justify-center p-4 bg-white border rounded-lg">
                    <img src="data:image/svg+xml;base64,<?= $qrCodeBase64 ?>" alt="QR Code para 2FA">
                </div>
            </div>
            
            <div>
                <h3 class="font-semibold text-gray-700">Não consegue digitalizar?</h3>
                <p class="text-sm text-gray-600">Pode inserir esta chave manualmente na sua app:</p>
                <div class="mt-2 p-3 bg-gray-100 rounded-md text-center font-mono text-sm break-all">
                    <?= htmlspecialchars($secret_key) ?>
                </div>
            </div>

            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-md" role="alert">
                <p class="font-bold">Passo 3: Aguarde a Ativação</p>
                <p>A sua conta foi criada e está pendente de aprovação por um administrador. Depois de aprovada, poderá fazer login com a sua password e o código da sua app.</p>
            </div>
        </div>

        <div class="mt-8">
            <a href="login.php" class="w-full inline-block bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition-colors">
                Percebido, ir para o Login
            </a>
        </div>
    </div>~
    </main>
    <?php include_once '../src/templates/footer.php'; ?>
</body>
</html>