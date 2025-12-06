<?php
// public/about.php
declare(strict_types=1);

// tenta usar constantes já definidas no projecto
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$appName = defined('APP_NAME') ? APP_NAME : 'CrewGest';
$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';

// tenta ler ficheiro VERSION se existir
$versionFile = __DIR__ . '/../VERSION';
if (file_exists($versionFile)) {
    $v = trim(file_get_contents($versionFile));
    if ($v !== '') $appVersion = $v;
}

// informação da equipa (ajusta conforme necessário)
$maintainers = [
    ['name' => 'Equipa CrewGest', 'role' => 'Desenvolvimento & Suporte', 'email' => 'support@crewgest.example'],
    ['name' => 'Administrador', 'role' => 'Admin Sistema', 'email' => 'admin@example.com'],
];

// links úteis
$links = [
    ['label' => 'Início', 'href' => $baseUrl . '/public/index.php'],
    ['label' => 'Documentação (README)', 'href' => $baseUrl . '/README.md'],
    ['label' => 'Relatórios', 'href' => $baseUrl . '/public/reports/index.php'],
    ['label' => 'Contactar Suporte', 'href' => 'mailto:support@crewgest.example'],
];

?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <title>Sobre — <?= htmlspecialchars($appName) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="<?= htmlspecialchars($baseUrl) ?>/public/images/favicon.png" />
  <style>
    :root{
      --bg:#f6f7fb; --card:#fff; --muted:#6b7280; --accent:#2563eb; --accent-dark:#1d4ed8;
      --radius:14px; --shadow: 0 8px 30px rgba(16,24,40,0.08);
      font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }
    html,body{height:100%; margin:0; background:linear-gradient(180deg,#f8fafc 0%,var(--bg) 100%);}
    .site{max-width:1100px;margin:36px auto;padding:20px;}
    .top { display:flex; align-items:center; gap:18px; margin-bottom:20px;}
    .brand {
      display:flex; align-items:center; gap:14px; text-decoration:none; color:inherit;
    }
    .logo {
      width:64px; height:64px; border-radius:12px; background:linear-gradient(135deg,var(--accent),var(--accent-dark));
      display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:20px;
      box-shadow: 0 6px 18px rgba(37,99,235,0.15);
    }
    h1{margin:0;font-size:22px;color:#0f172a}
    .subtitle{color:var(--muted); margin-top:6px}
    .grid{display:grid; grid-template-columns: 1fr 360px; gap:20px; margin-top:18px; align-items:start}
    .card{background:var(--card); border-radius:var(--radius); padding:18px; box-shadow:var(--shadow)}
    .lead{font-size:15px; color:#111827; margin-bottom:12px}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#eef2ff;color:var(--accent);font-weight:600;font-size:13px}
    ul.features{list-style:none;padding:0;margin:12px 0 0 0; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px}
    ul.features li{background:#f8fafc;border:1px solid #eef2ff;padding:10px;border-radius:10px;color:#111827;font-weight:600;font-size:14px}
    .meta{display:flex; gap:12px; flex-wrap:wrap; margin-top:12px}
    .meta .m{background:#fbfdff;padding:8px;border-radius:8px;border:1px solid #eef2ff;color:#0f172a;font-weight:600}
    .small{font-size:13px;color:var(--muted)}
    .contacts {display:flex; flex-direction:column; gap:10px}
    .contact-item{display:flex; gap:10px; align-items:center}
    .contact-item .avatar{width:44px;height:44;border-radius:8px;background:#eef2ff;color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700}
    .links{display:flex;flex-direction:column;gap:8px}
    a.btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700;color:#fff;background:var(--accent)}
    .muted-block{background:#fffaf0;border-left:4px solid #f59e0b;padding:10px;border-radius:8px;color:#92400e}
    .changelog{font-family:monospace,ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;background:#0b1220;color:#cbd5e1;padding:12px;border-radius:8px}
    footer{margin-top:18px;text-align:center;color:var(--muted);font-size:13px}
    @media (max-width:900px){ .grid{grid-template-columns:1fr} .logo{width:56px;height:56px}}
  </style>
</head>
<body>
  
  <div class="site">
    <div class="top">
      <a class="brand">
        <div class="logo"><?= htmlspecialchars(substr($appName,0,2)) ?></div>
        <div>
          <h1><?= htmlspecialchars($appName) ?> <span class="small" style="font-weight:600;color:var(--muted)">— Sobre</span></h1>
          <div class="subtitle">Gestão simples e eficiente de fardas, stock e relatórios — versão <?= htmlspecialchars($appVersion) ?></div>
        </div>
      </a>
      <div style="margin-left:auto;display:flex;gap:10px;align-items:center">
        <span class="pill">Produção interna</span>
        <a class="btn" href="login.php">Entrar</a>
      </div>
    </div>

    <div class="grid">
      <div>
        <div class="card">
          <p class="lead">O <?= htmlspecialchars($appName) ?> ajuda equipas a gerir fardas, controlar stock, emitir relatórios e manter um histórico auditável de movimentos. Foi desenhado para ser prático, leve e fácil de adaptar ao fluxo das organizações.</p>

          <h3 style="margin-top:12px;margin-bottom:8px">O que faz</h3>
          <ul class="features" aria-label="Funcionalidades">
            <li>Gestão de stock</li>
            <li>Atribuição de fardas</li>
            <li>Relatórios exportáveis (PDF, Excel, CSV)</li>
            <li>Geração e impressão de EAN / códigos de barra</li>
            <li>Registo de devoluções e avarias</li>
            <li>Logs auditáveis de operações</li>
            <li>Recuperação de password por email</li>
            <li>Integração com PHPMailer</li>
          </ul>

          <div style="margin-top:18px">
            <h3 style="margin:0 0 8px 0">Porquê usar</h3>
            <p class="small">Projetado para reduzir tempo administrativo, evitar perdas e facilitar a triagem. Ajustável por departamentos e com filtros para relatórios práticos — ideal para equipas operacionais.</p>
            <div class="meta" role="list">
              <div class="m">Leve e rápido</div>
              <div class="m">Código aberto possível</div>
              <div class="m">Exportações fáceis</div>
            </div>
          </div>

          <div style="margin-top:18px">
            <h3 style="margin-bottom:8px">Stack técnico</h3>
            <p class="small">PHP 7.4+/8.x, MySQL/MariaDB, Composer, PHPMailer, Dompdf, PhpSpreadsheet, HTML/CSS responsivo.</p>
          </div>

          <div style="margin-top:18px">
            <h3 style="margin-bottom:8px">Changelog recente</h3>
            <div class="changelog">
              v<?= htmlspecialchars($appVersion) ?> — Melhorias UI, export PDF com header/footer, geração EAN + preview.<br>
              v1.0.0 — Lançamento inicial com gestão de fardas e relatórios.
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:14px">
          <h3 style="margin:0 0 8px 0">Notas de segurança</h3>
          <p class="small">As operações sensíveis (reset de password, envio de email) são tratadas com tokens e PHPMailer. Recomendamos configurar SMTP seguro e não usar credenciais de conta pessoal em produção.</p>
          <div class="muted-block" style="margin-top:10px">
            <strong>Atenção:</strong> Faz backup da base de dados regularmente e protege o acesso à pasta `public/` e aos ficheiros de configuração.
          </div>
        </div>
      </div>

      <aside>
        <div class="card">
          <h3 style="margin:0 0 8px 0">Contactos & Equipa</h3>
          <div class="contacts">
            <?php foreach ($maintainers as $m): ?>
              <div class="contact-item" role="article">
                <div class="avatar"><?= htmlspecialchars(substr($m['name'],0,2)) ?></div>
                <div>
                  <div style="font-weight:700"><?= htmlspecialchars($m['name']) ?></div>
                  <div class="small"><?= htmlspecialchars($m['role']) ?></div>
                  <div class="small" style="margin-top:6px"><a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <hr style="margin:12px 0;border:none;border-top:1px solid #f1f5f9">

          
        </div>

        <div class="card" style="margin-top:14px">
          <h4 style="margin:0 0 8px 0">Licença & Créditos</h4>
          <p class="small">Projeto interno — ajusta a licença conforme necessário. Bibliotecas utilizadas: Dompdf, PHPMailer, PhpSpreadsheet. Obrigado a todas as equipes que contribuíram.</p>
        </div>
      </aside>
    </div>

    <footer>
      <div>© <?= date('Y') ?> <?= htmlspecialchars($appName) ?> — Versão <?= htmlspecialchars($appVersion) ?></div>
      <div style="margin-top:6px" class="small">Feito com ♥ — precisa de ajustes? Contacta a equipa.</div>
    </footer>
  </div>
</body>
</html>
