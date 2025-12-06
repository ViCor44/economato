<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$appName = defined('APP_NAME') ? APP_NAME : 'CrewGest';
?>
<footer class="mt-12 border-t border-gray-200 bg-white">
    <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-sm text-gray-500">
        
        <div>
            © <?= date('Y') ?> <?= htmlspecialchars($appName) ?>
        </div>

        <div class="flex items-center gap-4">
            <a href="<?= $baseUrl ?>/public/about.php"
               class="text-gray-500 hover:text-blue-600 hover:underline">
                Sobre o sistema
            </a>
        </div>
    </div>
</footer>
