<?php
// /src/templates/header_public.php – versão para páginas sem login

$baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$appName = defined('APP_NAME') ? APP_NAME : 'CrewGest';
$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
$initials = htmlspecialchars(substr($appName,0,2));
?>
<style>
.cg-separator {
    background:#000; height:5px; width:100%;
}
.cg-header {
    background:linear-gradient(180deg,#f8fafc 0%,#f3f4f6 100%);
    border-bottom:1px solid rgba(15,23,42,0.05);
}
.cg-inner {
    max-width:1100px;margin:0 auto;padding:16px 20px;
    display:flex;align-items:center;gap:16px;
}
.cg-logo {
    width:56px;height:56px;border-radius:12px;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:18px;font-weight:700;
}
.cg-brand{display:flex;flex-direction:column;text-decoration:none;color:inherit;}
.cg-brand-title{font-size:18px;font-weight:700;color:#0f172a;}
.cg-brand-sub{font-size:13px;color:#6b7280;margin-top:3px;}
.cg-right{margin-left:auto;display:flex;align-items:center;gap:12px;}
.cg-pill{padding:6px 10px;background:#eef2ff;color:#2563eb;
    border-radius:999px;font-size:13px;font-weight:700;}
.cg-btn{background:#2563eb;color:#fff;text-decoration:none;font-weight:700;
    padding:8px 12px;border-radius:10px;}
</style>



<header class="cg-header">
    <div class="cg-inner">

        <a href="<?= $baseUrl ?>/public/login.php" class="cg-logo"><?= $initials ?></a>

        <a href="<?= $baseUrl ?>/public/login.php" class="cg-brand">
            <span class="cg-brand-title"><?= htmlspecialchars($appName) ?></span>
            <span class="cg-brand-sub">Gestão de fardas, stock e relatórios</span>
        </a>

        

    </div>
    <div class="cg-separator"></div>
</header>
