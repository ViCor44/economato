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
    ['name' => 'Equipa CrewGest', 'role' => 'Desenvolvimento & Suporte', 'email' => 'victor.a.correia@gmail.com'],
    ['name' => 'Administrador', 'role' => 'Admin Sistema', 'email' => 'victor.a.correia@gmail.com'],
];
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <title>Sobre — <?= htmlspecialchars($appName) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="<?= htmlspecialchars($baseUrl) ?>/public/images/favicon.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    :root {
      --bg: #f8f9fa;
      --card: #ffffff;
      --text-primary: #212529;
      --text-muted: #6c757d;
      --accent: #0d6efd;
      --accent-light: #e7f1ff;
      --success: #198754;
      --warning: #ffc107;
      --info: #0dcaf0;
      --radius: 0.375rem;
      --shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
      --transition: all 0.3s ease;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    html, body {
      height: 100%;
      margin: 0;
      background: var(--bg);
      color: var(--text-primary);
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1rem;
    }
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 2rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid #dee2e6;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .logo {
      width: 40px;
      height: 40px;
      background: var(--accent);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1.25rem;
      border-radius: 0.25rem;
    }
    .app-name {
      font-size: 1.25rem;
      font-weight: 500;
    }
    .subtitle {
      font-size: 0.875rem;
      color: var(--text-muted);
    }
    .user-info {
      font-size: 0.875rem;
      color: var(--text-muted);
    }
    .user-info a {
      color: var(--accent);
      text-decoration: none;
    }
    .dashboard-title {
      font-size: 1.5rem;
      font-weight: 500;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
    }
    .card {
      background: var(--card);
      border: 1px solid #dee2e6;
      border-radius: var(--radius);
      padding: 1.5rem;
      box-shadow: var(--shadow);
      transition: var(--transition);
      text-align: center;
    }
    .card:hover {
      box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1);
    }
    .icon {
      width: 3rem;
      height: 3rem;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 0.5rem auto;
      font-size: 1.5rem;
      color: #fff;
    }
    .icon-blue { background: #0d6efd; }
    .icon-green { background: #198754; }
    .icon-yellow { background: #ffc107; color: #212529; }
    .icon-purple { background: #6f42c1; }
    .icon-orange { background: #fd7e14; }
    .card-title {
      font-size: 1.1rem;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }
    .card-desc {
      font-size: 0.875rem;
      color: var(--text-muted);
    }
    .section {
      margin-top: 2rem;
      padding: 1.5rem;
      background: var(--card);
      border: 1px solid #dee2e6;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }
    .section-header {
      text-align: center;
      margin-bottom: 1rem;
    }
    .section h2 {
      font-size: 1.25rem;
      font-weight: 500;
      margin: 0;
    }
    .section p {
      line-height: 1.6;
      margin-bottom: 1rem;
    }
    ul.features {
      list-style: none;
      padding: 0;
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      justify-content: center;
    }
    ul.features li {
      background: var(--accent-light);
      padding: 0.5rem 1rem;
      border-radius: var(--radius);
      font-size: 0.875rem;
    }
    .changelog {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: var(--radius);
      font-family: monospace;
      color: var(--text-muted);
    }
    .contacts {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
    }
    .contact {
      text-align: center;
    }
    .muted-block {
      background: #fff3cd;
      border: 1px solid #ffeeba;
      padding: 1rem;
      border-radius: var(--radius);
      color: #856404;
    }
    footer {
      margin-top: 2rem;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.875rem;
    }
    footer a {
      color: var(--accent);
      text-decoration: none;
    }
    .back-btn {
      display: inline-block;
      margin-top: 1rem;
      padding: 0.5rem 1rem;
      background: var(--accent);
      color: #fff;
      border-radius: var(--radius);
      text-decoration: none;
      font-weight: 500;
      transition: var(--transition);
    }
    .back-btn:hover {
      background: #0a58ca;
    }
    @media (max-width: 768px) {
      .grid { grid-template-columns: 1fr; }
      ul.features { flex-direction: column; align-items: center; }
    }
  </style>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="brand">
        <div class="logo">Cr</div>
        <div>
          <div class="app-name">CrewGest</div>
          <div class="subtitle">Gestão simples e eficiente de fardas, stock e relatórios.</div>
        </div>
      </div>
      <div>
        <a href="javascript:history.back()" class="back-btn">Voltar</a>
      </div>
    </header>

    <h1 class="dashboard-title">Sobre o Sistema</h1>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-info-circle icon icon-blue"></i>
        <h2>Visão Geral</h2>
      </div>
      <p>O CrewGest é uma aplicação projetada para simplificar a gestão de fardas, stock e relatórios em equipas operacionais. Com uma interface intuitiva, permite controlar inventários, atribuir itens e gerar análises de forma eficiente.</p>
    </div>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-tools icon icon-green"></i>
        <h2>Funcionalidades</h2>
      </div>
      <ul class="features">
        <li>Gestão de stock de fardas</li>
        <li>Atribuição e registo de itens</li>
        <li>Relatórios em PDF, Excel e CSV</li>
        <li>Geração de códigos EAN</li>
        <li>Registo de devoluções e avarias</li>
        <li>Logs auditáveis</li>
        <li>Recuperação de password</li>
        <li>Integração com email</li>
      </ul>
    </div>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-question-circle icon icon-yellow"></i>
        <h2>Porquê Usar</h2>
      </div>
      <p>Reduza tempo administrativo, minimize perdas e melhore a organização. Ideal para departamentos com necessidades específicas de relatórios e controlo.</p>
    </div>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-code icon icon-purple"></i>
        <h2>Stack Técnico</h2>
      </div>
      <p>PHP 7.4+/8.x, MySQL/MariaDB, Composer, PHPMailer, Dompdf, PhpSpreadsheet, HTML/CSS responsivo.</p>
    </div>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-history icon icon-orange"></i>
        <h2>Changelog Recente</h2>
      </div>
      <div class="changelog">
        v<?= htmlspecialchars($appVersion) ?> — Melhorias UI, export PDF com header/footer, geração EAN + preview.<br>
        v1.0.0 — Lançamento inicial com gestão de fardas e relatórios.
      </div>
    </div>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-rocket icon icon-blue"></i>
        <h2>O Que Vem a Seguir</h2>
      </div>
      <p>O CrewGest evolui com base no feedback da equipa. Aqui estão algumas melhorias planeadas:</p>
      <ul class="features">
        <li>Alertas de stock baixo</li>
        <li>Impressão em massa de etiquetas</li>
        <li>Dashboards com KPIs</li>
        <li>Permissões por utilizador</li>
        <li>Auditoria detalhada</li>
        <li>Integrações externas</li>
      </ul>
      <div class="muted-block">
        <strong>Nota:</strong> Mantemos o foco na simplicidade e adaptação às necessidades reais.
      </div>
    </div>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-users icon icon-green"></i>
        <h2>Equipa e Contactos</h2>
      </div>
      <div class="contacts">
        <?php foreach ($maintainers as $m): ?>
          <div class="contact">
            <i class="fas fa-user-circle icon icon-blue"></i>
            <div class="card-title"><?= htmlspecialchars($m['name']) ?></div>
            <div class="card-desc"><?= htmlspecialchars($m['role']) ?></div>
            <a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="section">
      <div class="section-header">
        <i class="fas fa-balance-scale icon icon-yellow"></i>
        <h2>Licença e Créditos</h2>
      </div>
      <p>Projeto interno. Bibliotecas: Dompdf, PHPMailer, PhpSpreadsheet. Obrigado às comunidades open-source.</p>
    </div>

    <a href="javascript:history.back()" class="back-btn">Voltar</a>

    <footer>
      © <?= date('Y') ?> CrewGest | <a href="#">Sobre o sistema</a>
    </footer>
  </div>
</body>
</html>