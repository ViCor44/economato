<?php
require_once '../src/auth_guard.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$hoje = date('d/m/Y');
$ano  = date('Y');

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-PT">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 22mm 18mm 22mm 18mm; }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 10pt;
    color: #1a202c;
    line-height: 1.6;
  }

  /* ── Capa ─────────────────────────────────────── */
  .cover {
    page-break-after: always;
    text-align: center;
    padding-top: 60mm;
  }
  .cover-logo {
    font-size: 48pt;
    color: #2563eb;
    margin-bottom: 8mm;
  }
  .cover-title {
    font-size: 28pt;
    font-weight: 700;
    color: #1e3a5f;
    margin-bottom: 4mm;
  }
  .cover-subtitle {
    font-size: 14pt;
    color: #4b5563;
    margin-bottom: 14mm;
  }
  .cover-line {
    width: 120mm;
    border: none;
    border-top: 2px solid #2563eb;
    margin: 0 auto 10mm auto;
  }
  .cover-meta {
    font-size: 10pt;
    color: #6b7280;
    line-height: 1.8;
  }
  .cover-version {
    display: inline-block;
    margin-top: 14mm;
    padding: 4mm 10mm;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    font-size: 9pt;
    color: #1d4ed8;
    font-weight: 700;
  }

  /* ── Cabeçalho de página ──────────────────────── */
  .page-header {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 3mm;
    margin-bottom: 6mm;
    font-size: 8pt;
    color: #9ca3af;
    display: flex;
    justify-content: space-between;
  }

  /* ── Capítulos e secções ─────────────────────── */
  .chapter {
    page-break-before: always;
    margin-bottom: 8mm;
  }
  .chapter-title {
    font-size: 18pt;
    font-weight: 700;
    color: #1e3a5f;
    border-bottom: 2px solid #2563eb;
    padding-bottom: 2mm;
    margin-bottom: 6mm;
  }
  .chapter-intro {
    background: #f0f7ff;
    border-left: 4px solid #2563eb;
    padding: 3mm 5mm;
    border-radius: 0 4px 4px 0;
    margin-bottom: 6mm;
    font-size: 10pt;
    color: #374151;
  }
  h2 {
    font-size: 13pt;
    font-weight: 700;
    color: #1e40af;
    margin: 7mm 0 3mm 0;
  }
  h3 {
    font-size: 11pt;
    font-weight: 700;
    color: #374151;
    margin: 5mm 0 2mm 0;
  }
  p { margin-bottom: 3mm; }

  /* ── Listas ───────────────────────────────────── */
  ul, ol { padding-left: 7mm; margin-bottom: 4mm; }
  li { margin-bottom: 1.5mm; }

  /* ── Tabelas ──────────────────────────────────── */
  table {
    width: 100%;
    border-collapse: collapse;
    margin: 4mm 0 6mm 0;
    font-size: 9pt;
  }
  th {
    background: #1e3a5f;
    color: #fff;
    padding: 2.5mm 3mm;
    text-align: left;
    font-weight: 700;
  }
  td { padding: 2mm 3mm; border-bottom: 1px solid #e5e7eb; }
  tr:nth-child(even) td { background: #f9fafb; }

  /* ── Badges / Notas ───────────────────────────── */
  .note {
    background: #fefce8;
    border: 1px solid #fde047;
    border-radius: 4px;
    padding: 2.5mm 4mm;
    margin: 3mm 0;
    font-size: 9pt;
    color: #713f12;
  }
  .tip {
    background: #f0fdf4;
    border: 1px solid #86efac;
    border-radius: 4px;
    padding: 2.5mm 4mm;
    margin: 3mm 0;
    font-size: 9pt;
    color: #14532d;
  }
  .warning {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: 4px;
    padding: 2.5mm 4mm;
    margin: 3mm 0;
    font-size: 9pt;
    color: #9f1239;
  }

  /* ── Índice ───────────────────────────────────── */
  .toc { page-break-after: always; }
  .toc-title {
    font-size: 18pt;
    font-weight: 700;
    color: #1e3a5f;
    border-bottom: 2px solid #2563eb;
    padding-bottom: 2mm;
    margin-bottom: 6mm;
  }
  .toc-entry {
    display: flex;
    justify-content: space-between;
    padding: 1.5mm 0;
    border-bottom: 1px dotted #d1d5db;
    font-size: 10pt;
  }
  .toc-entry-chapter {
    font-weight: 700;
    color: #1e3a5f;
    margin-top: 3mm;
  }
  .toc-entry-section {
    padding-left: 6mm;
    color: #374151;
    font-size: 9.5pt;
  }

  /* ── Flow breaks ──────────────────────────────── */
  .no-break { page-break-inside: avoid; }

  code {
    background: #f3f4f6;
    padding: 0.5mm 2mm;
    border-radius: 3px;
    font-family: DejaVu Sans Mono, monospace;
    font-size: 9pt;
  }

  .badge {
    display: inline-block;
    padding: 0.5mm 3mm;
    border-radius: 20px;
    font-size: 8pt;
    font-weight: 700;
  }
  .badge-blue  { background:#dbeafe; color:#1d4ed8; }
  .badge-green { background:#dcfce7; color:#166534; }
  .badge-red   { background:#fee2e2; color:#991b1b; }
  .badge-amber { background:#fef3c7; color:#92400e; }
  .badge-gray  { background:#f3f4f6; color:#374151; }
</style>
</head>
<body>

<!-- ════════════════════════════════════════════════
     CAPA
     ════════════════════════════════════════════════ -->
<div class="cover">
  <div class="cover-logo">&#9874;</div>
  <div class="cover-title">CrewGest</div>
  <div class="cover-subtitle">Sistema de Gestão de Fardamento e Colaboradores</div>
  <hr class="cover-line">
  <div class="cover-meta">
    <strong>Manual do Utilizador &amp; Documentação Técnica</strong><br>
    Versão 2.0 &nbsp;|&nbsp; {$hoje}<br>
    Confidencial — uso interno
  </div>
  <div class="cover-version">&#128274; Protegido por RGPD — Acesso restrito a utilizadores autorizados</div>
</div>


<!-- ════════════════════════════════════════════════
     ÍNDICE
     ════════════════════════════════════════════════ -->
<div class="toc">
  <div class="toc-title">Índice</div>

  <div class="toc-entry toc-entry-chapter"><span>1. Introdução e Visão Geral</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>1.1 O que é o CrewGest</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>1.2 Módulos principais</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>1.3 Perfis de utilizador</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>2. Autenticação e Segurança</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>2.1 Login / Logout</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>2.2 Autenticação de dois fatores (2FA)</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>2.3 Recuperação de password</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>2.4 Registo e aprovação de utilizadores</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>3. Gestão de Colaboradores</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>3.1 Lista de colaboradores</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>3.2 Adicionar colaborador</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>3.3 Detalhes e edição</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>3.4 Inativar / eliminar colaborador</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>3.5 Departamentos</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>4. Catálogo de Fardas e Stock</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>4.1 Lista de fardas</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>4.2 Adicionar e editar farda</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>4.3 Gestão de stock</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>4.4 Códigos EAN</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>4.5 Dar baixa de farda</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>5. Atribuição de Fardas</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>5.1 Atribuir farda a colaborador</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>5.2 Editar e anular atribuição</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>5.3 Termos de entrega</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>6. Devoluções e Termos de Devolução</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>6.1 Fluxo de devolução</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>6.2 Devolução parcial (1 peça)</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>6.3 Marcar como dívida</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>6.4 Gerar e reverter termo de devolução</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>7. Empréstimos de Farda</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>7.1 Registar empréstimo</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>7.2 Devolver empréstimo</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>7.3 Alertas de atraso</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>8. Dívidas de Fardamento</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>8.1 Como surgem as dívidas</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>8.2 Regularização de dívida</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>9. Gestão de Cacifos</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>9.1 Registar e editar cacifo</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>9.2 Atribuir e devolver cacifo</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>10. Centro de Relatórios</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>10.1 Catálogo de relatórios disponíveis</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>10.2 Filtros e opções</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>10.3 Confirmação RGPD e seleção de colunas</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>10.4 Formatos de exportação</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>11. Gestão de Utilizadores do Sistema</span><span>—</span></div>
  <div class="toc-entry toc-entry-section"><span>11.1 Aprovação de contas</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>11.2 Editar e eliminar utilizadores</span><span></span></div>
  <div class="toc-entry toc-entry-section"><span>11.3 Perfil pessoal</span><span></span></div>

  <div class="toc-entry toc-entry-chapter"><span>12. Validação de Documentos</span><span>—</span></div>
  <div class="toc-entry toc-entry-chapter"><span>13. Suporte</span><span>—</span></div>
  <div class="toc-entry toc-entry-chapter"><span>14. Referência Rápida — Estados das Atribuições</span><span>—</span></div>
</div>


<!-- ════════════════════════════════════════════════
     1. INTRODUÇÃO
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">1. Introdução e Visão Geral</div>
  <div class="chapter-intro">
    O <strong>CrewGest</strong> é uma aplicação web de gestão de fardamento, equipamentos e colaboradores, desenvolvida para uso interno. Permite controlar todo o ciclo de vida do fardamento: desde a entrada em stock até à atribuição, empréstimo, devolução e eventual dívida.
  </div>

  <h2>1.1 O que é o CrewGest</h2>
  <p>O CrewGest centraliza a gestão de:</p>
  <ul>
    <li>Catálogo e stock de fardas (artigos de vestuário e equipamento)</li>
    <li>Atribuições de fardas a colaboradores, com registo de termos assinados</li>
    <li>Devoluções, empréstimos temporários e dívidas</li>
    <li>Cacifos atribuídos a colaboradores</li>
    <li>Relatórios financeiros, operacionais e de conformidade RGPD</li>
  </ul>

  <h2>1.2 Módulos principais</h2>
  <table>
    <tr><th>Módulo</th><th>Descrição</th></tr>
    <tr><td><strong>Colaboradores</strong></td><td>Registo, edição, inativação e pesquisa de colaboradores</td></tr>
    <tr><td><strong>Fardas &amp; Stock</strong></td><td>Catálogo de artigos, gestão de quantidades e preços</td></tr>
    <tr><td><strong>Atribuições</strong></td><td>Entrega de fardas com geração de termos em PDF</td></tr>
    <tr><td><strong>Devoluções</strong></td><td>Recolha de fardas, total ou parcial, com termo de devolução</td></tr>
    <tr><td><strong>Empréstimos</strong></td><td>Cedência temporária de fardas com controlo de prazo</td></tr>
    <tr><td><strong>Dívidas</strong></td><td>Registo de fardas não devolvidas e regularização</td></tr>
    <tr><td><strong>Cacifos</strong></td><td>Atribuição e gestão de cacifos por colaborador</td></tr>
    <tr><td><strong>Códigos EAN</strong></td><td>Geração e impressão de etiquetas de código de barras</td></tr>
    <tr><td><strong>Relatórios</strong></td><td>29 relatórios exportáveis em HTML, PDF, XLSX e CSV</td></tr>
    <tr><td><strong>Utilizadores</strong></td><td>Contas do sistema com aprovação e 2FA opcional</td></tr>
  </table>

  <h2>1.3 Perfis de utilizador</h2>
  <table>
    <tr><th>Perfil</th><th>Acesso</th></tr>
    <tr><td><span class="badge badge-red">Admin</span></td><td>Acesso total: gestão de utilizadores, aprovação de contas, todos os módulos</td></tr>
    <tr><td><span class="badge badge-blue">Operador</span></td><td>Gestão de colaboradores, fardas, atribuições, devoluções, relatórios</td></tr>
  </table>
  <div class="note">&#9888; Para aceder ao sistema é necessário ter conta aprovada por um administrador.</div>
</div>


<!-- ════════════════════════════════════════════════
     2. AUTENTICAÇÃO
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">2. Autenticação e Segurança</div>
  <div class="chapter-intro">
    O CrewGest utiliza autenticação por email/password com suporte opcional de segundo fator (TOTP). Todas as sessões são protegidas e registadas nos logs do sistema.
  </div>

  <h2>2.1 Login / Logout</h2>
  <p>Aceda à página de login em <code>/public/login.php</code>. Introduza o email e password registados. Após autenticação bem-sucedida, é redirecionado para o painel principal.</p>
  <p>Para terminar a sessão clique em <strong>Logout</strong> no menu de navegação.</p>
  <div class="tip">&#10003; A sessão expira automaticamente por inatividade. Guarde sempre o trabalho antes de se ausentar.</div>

  <h2>2.2 Autenticação de Dois Fatores (2FA)</h2>
  <p>O 2FA adiciona uma camada extra de segurança através de um código TOTP gerado por aplicação autenticadora (ex: Google Authenticator, Aegis, Bitwarden).</p>

  <h3>Ativar o 2FA</h3>
  <ol>
    <li>Aceda a <strong>Perfil</strong> → <strong>Configurar 2FA</strong></li>
    <li>Digitalize o QR Code com a aplicação autenticadora</li>
    <li>Introduza o código de 6 dígitos para confirmar a ativação</li>
    <li>Guarde os códigos de recuperação apresentados em local seguro</li>
  </ol>

  <h3>Login com 2FA ativo</h3>
  <p>Após introduzir email e password corretos, será pedido o código TOTP de 6 dígitos gerado pela aplicação autenticadora. O código é válido por 30 segundos.</p>

  <div class="warning">&#9888; Se perder acesso à aplicação autenticadora, contacte o administrador do sistema para desativar o 2FA.</div>

  <h2>2.3 Recuperação de Password</h2>
  <ol>
    <li>Na página de login clique em <strong>Esqueci-me da password</strong></li>
    <li>Introduza o email associado à conta</li>
    <li>Receberá um email com link de redefinição (válido por 1 hora)</li>
    <li>Clique no link e defina a nova password</li>
  </ol>

  <h2>2.4 Registo e Aprovação de Utilizadores</h2>
  <p>Qualquer pessoa com acesso à aplicação pode registar uma conta em <code>/public/registar.php</code>. A conta fica pendente até um administrador a aprovar.</p>
  <p>O administrador aprova contas em <strong>Gerir Utilizadores</strong> → botão <strong>Aprovar</strong>. Apenas contas aprovadas conseguem fazer login.</p>
</div>


<!-- ════════════════════════════════════════════════
     3. COLABORADORES
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">3. Gestão de Colaboradores</div>
  <div class="chapter-intro">
    Os colaboradores são o núcleo do sistema. Cada colaborador pode ter fardas atribuídas, empréstimos ativos, cacifos e um histórico completo de movimentos.
  </div>

  <h2>3.1 Lista de Colaboradores</h2>
  <p>Aceda em <strong>Colaboradores</strong> no menu principal. A lista mostra todos os colaboradores com filtros por:</p>
  <ul>
    <li>Estado: <span class="badge badge-green">Ativo</span> / <span class="badge badge-gray">Inativo</span> / Todos</li>
    <li>Departamento</li>
    <li>Pesquisa livre por nome, cartão ou número de funcionário</li>
  </ul>
  <p>São apresentados 50 colaboradores por página com paginação. Colaboradores com dívidas ativas aparecem assinalados com <span class="badge badge-red">⚠ Dívida</span>.</p>

  <h2>3.2 Adicionar Colaborador</h2>
  <p>Clique em <strong>+ Novo Colaborador</strong>. Preencha os campos:</p>
  <table>
    <tr><th>Campo</th><th>Obrigatório</th><th>Notas</th></tr>
    <tr><td>Nome completo</td><td>Sim</td><td></td></tr>
    <tr><td>Nº Funcionário</td><td>Não</td><td>Identificador interno</td></tr>
    <tr><td>Cartão (ID físico)</td><td>Sim</td><td>Deve ser único</td></tr>
    <tr><td>Email</td><td>Não</td><td>Para notificações</td></tr>
    <tr><td>Telefone</td><td>Não</td><td></td></tr>
    <tr><td>Departamento</td><td>Sim</td><td>Criado em Departamentos</td></tr>
    <tr><td>Sector</td><td>Não</td><td>Visível para Vigilantes/Supervisores</td></tr>
    <tr><td>Foto</td><td>Não</td><td>JPG/PNG até 5 MB</td></tr>
  </table>

  <h2>3.3 Detalhes e Edição</h2>
  <p>Clique no nome de um colaborador para ver a página de detalhes. Aqui encontra:</p>
  <ul>
    <li>Informações pessoais e estado do cartão de identificação</li>
    <li>Fardas atribuídas (agrupadas por artigo, com total)</li>
    <li>Cacifos atribuídos</li>
    <li>Empréstimos pendentes</li>
    <li>Fardas em dívida (se aplicável)</li>
    <li>Botões de ação: Atribuir farda, Devolver farda, Gerar Termo, Emprestar, Editar</li>
  </ul>
  <div class="tip">&#10003; O botão de Termo de entrega mostra o estado atual: <em>Gerar Termo</em>, <em>Gerar Novo Termo</em> (houve alterações desde o último) ou <em>Termo em vigor</em>.</div>

  <h2>3.4 Inativar / Eliminar Colaborador</h2>
  <p>Um colaborador pode ser marcado como <strong>Inativo</strong> sem perder o histórico. Colaboradores inativos não podem receber novas atribuições ou empréstimos.</p>
  <div class="warning">&#9888; A eliminação definitiva de um colaborador é irreversível e remove todos os dados associados. Use com cuidado.</div>

  <h2>3.5 Departamentos</h2>
  <p>Aceda em <strong>Departamentos</strong> no menu. Pode criar, editar e eliminar departamentos. Os departamentos são usados para agrupar colaboradores e filtrar relatórios.</p>
</div>


<!-- ════════════════════════════════════════════════
     4. FARDAS E STOCK
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">4. Catálogo de Fardas e Stock</div>
  <div class="chapter-intro">
    As fardas representam os artigos disponíveis para atribuição. Cada artigo é identificado por nome, cor, tamanho, preço unitário e quantidade em stock.
  </div>

  <h2>4.1 Lista de Fardas</h2>
  <p>Aceda em <strong>Fardas</strong> no menu. A listagem mostra todos os artigos com stock atual, stock mínimo, preço unitário e código EAN. Artigos com stock abaixo do mínimo aparecem destacados a vermelho.</p>

  <h2>4.2 Adicionar e Editar Farda</h2>
  <p>Clique em <strong>+ Nova Farda</strong>. Campos disponíveis:</p>
  <table>
    <tr><th>Campo</th><th>Obrigatório</th><th>Notas</th></tr>
    <tr><td>Nome</td><td>Sim</td><td>Ex: "Polo Manga Curta"</td></tr>
    <tr><td>Cor</td><td>Sim</td><td>Selecionada de lista; pode criar nova</td></tr>
    <tr><td>Tamanho</td><td>Sim</td><td>Selecionado de lista; pode criar novo</td></tr>
    <tr><td>Preço unitário (€)</td><td>Sim</td><td>Usado em relatórios financeiros</td></tr>
    <tr><td>Quantidade em stock</td><td>Sim</td><td>Stock inicial</td></tr>
    <tr><td>Stock mínimo</td><td>Não</td><td>Alerta quando stock desce abaixo deste valor</td></tr>
    <tr><td>Código EAN</td><td>Não</td><td>Pode ser gerado automaticamente</td></tr>
  </table>

  <h2>4.3 Gestão de Stock</h2>
  <p>Para adicionar stock a uma farda existente, clique em <strong>Gerir Stock</strong> → <strong>+ Adicionar Stock</strong>. É registado o fornecedor, quantidade e preço de compra para rastreabilidade.</p>
  <p>O stock é decrementado automaticamente quando uma farda é atribuída e incrementado quando devolvida ao estado <em>stock</em>.</p>

  <h2>4.4 Códigos EAN</h2>
  <p>O sistema suporta códigos de barras EAN-13 para identificação rápida de artigos por leitores. Para cada farda:</p>
  <ol>
    <li>Aceda à farda e clique em <strong>Gerar EAN</strong></li>
    <li>O código é gerado automaticamente e associado ao artigo</li>
    <li>Para imprimir etiquetas, aceda a <strong>Relatórios</strong> → <em>27. Imprimir EAN</em></li>
    <li>A leitura de um EAN num formulário de atribuição preenche automaticamente o artigo</li>
  </ol>

  <h2>4.5 Dar Baixa de Farda</h2>
  <p>Para retirar artigos inutilizáveis do stock (desgaste, perda, etc.), use <strong>Dar Baixa</strong> na página da farda. Registe o motivo e a quantidade. A operação é registada nos logs do sistema.</p>
  <div class="note">&#9432; A baixa é irreversível. Confirme a quantidade antes de submeter.</div>
</div>


<!-- ════════════════════════════════════════════════
     5. ATRIBUIÇÕES
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">5. Atribuição de Fardas</div>
  <div class="chapter-intro">
    A atribuição regista formalmente a entrega de fardas a um colaborador. Cada atribuição é rastreada individualmente com data, quantidade e estado.
  </div>

  <h2>5.1 Atribuir Farda a Colaborador</h2>
  <p>Aceda à página de detalhes do colaborador e clique em <strong>➕ Atribuir</strong>, ou aceda diretamente a <strong>Atribuir Farda</strong> no menu.</p>
  <ol>
    <li>Selecione o colaborador (ou vem pré-preenchido)</li>
    <li>Selecione a farda — pode usar o leitor de código de barras EAN para preenchimento rápido</li>
    <li>Defina a quantidade</li>
    <li>Clique em <strong>Confirmar Atribuição</strong></li>
  </ol>
  <p>O stock da farda é decrementado automaticamente. O estado da atribuição passa a <span class="badge badge-blue">atribuída</span>.</p>
  <div class="warning">&#9888; Não é possível atribuir fardas a colaboradores inativos.</div>

  <h2>5.2 Editar e Anular Atribuição</h2>
  <p>Na página de detalhes do colaborador, na tabela de fardas, cada linha tem ações <strong>✏️ Editar</strong> e <strong>❌ Anular</strong>.</p>
  <ul>
    <li><strong>Editar</strong>: permite corrigir quantidade ou data de atribuição</li>
    <li><strong>Anular</strong>: cancela a atribuição e devolve o stock — use apenas para correção de erros de registo</li>
  </ul>

  <h2>5.3 Termos de Entrega</h2>
  <p>Após atribuir fardas, gere o <strong>Termo de Entrega</strong> para documentar formalmente a entrega ao colaborador. O documento inclui a lista completa de artigos atribuídos, preços e assinaturas.</p>
  <h3>Quando gerar o termo</h3>
  <table>
    <tr><th>Estado do botão</th><th>Significado</th></tr>
    <tr><td><span class="badge badge-blue">Gerar Termo</span></td><td>Ainda não existe termo — gerar o primeiro</td></tr>
    <tr><td><span class="badge badge-amber">Gerar Novo Termo</span></td><td>Houve alterações desde o último termo — deve ser re-emitido</td></tr>
    <tr><td><span class="badge badge-gray">Termo em vigor</span></td><td>Termo atual reflete todas as atribuições — pode re-emitir se necessário</td></tr>
  </table>
  <div class="tip">&#10003; O termo anterior é automaticamente invalidado quando se gera um novo. O histórico de documentos é mantido.</div>
</div>


<!-- ════════════════════════════════════════════════
     6. DEVOLUÇÕES
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">6. Devoluções e Termos de Devolução</div>
  <div class="chapter-intro">
    O processo de devolução permite recolher fardas de um colaborador, seja total ou parcialmente, com destino a stock ou reciclagem, e gerir situações de não-devolução (dívida).
  </div>

  <h2>6.1 Fluxo de Devolução</h2>
  <p>Aceda em <strong>🔁 Devolver</strong> na página de detalhes do colaborador. A página mostra todas as fardas atribuídas. Para cada artigo pode escolher:</p>

  <table>
    <tr><th>Ação</th><th>Efeito</th></tr>
    <tr><td><strong>1 Peça</strong></td><td>Marca 1 unidade para devolução (escolhe destino: stock ou reciclagem). Cliques repetidos agrupam na mesma linha.</td></tr>
    <tr><td><strong>Todas</strong></td><td>Marca todas as peças do artigo para devolução de uma vez</td></tr>
    <tr><td><strong>💳 Marcar como Dívida</strong></td><td>Assinala o artigo (ou parte dele) como não devolvido — será registado como dívida no termo</td></tr>
  </table>

  <h2>6.2 Devolução Parcial</h2>
  <p>Se um colaborador tem, por exemplo, 4 polos e devolve apenas 2:</p>
  <ol>
    <li>Clique <strong>1 Peça</strong> duas vezes (escolhendo destino em cada clique)</li>
    <li>O sistema agrupa as devoluções na mesma linha se o destino for igual</li>
    <li>A atribuição original fica com quantidade 2 (as restantes)</li>
  </ol>

  <h2>6.3 Marcar como Dívida</h2>
  <p>Quando um colaborador não devolve uma peça:</p>
  <ol>
    <li>Clique <strong>💳 Marcar como Dívida</strong></li>
    <li>Se a atribuição tiver mais de 1 unidade, aparece um modal para escolher a quantidade a marcar</li>
    <li>O artigo fica marcado com <span class="badge badge-amber">💳 Marcada como dívida</span> na listagem</li>
    <li>Para reverter, clique <strong>↩️ Desmarcar de dívida</strong> — o sistema reagrupa automaticamente com a atribuição original</li>
  </ol>

  <h2>6.4 Gerar e Reverter Termo de Devolução</h2>
  <p>Quando todas as fardas estiverem tratadas (devolvidas ou marcadas como dívida), o botão <strong>Gerar Termo de Devolução</strong> fica disponível. O termo em PDF inclui:</p>
  <ul>
    <li>Lista de artigos devolvidos, com destino (stock/reciclagem)</li>
    <li>Lista de artigos em dívida com valor monetário</li>
    <li>Data, colaborador e operador</li>
    <li>Campo de assinaturas</li>
  </ul>
  <p>Se necessário, o termo pode ser revertido em <strong>Reverter Termo de Devolução</strong>, repondo o estado anterior das atribuições.</p>
  <div class="warning">&#9888; Após gerar o termo de devolução, o colaborador é marcado como inativo automaticamente. Para reativar, edite o colaborador.</div>
</div>


<!-- ════════════════════════════════════════════════
     7. EMPRÉSTIMOS
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">7. Empréstimos de Farda</div>
  <div class="chapter-intro">
    Os empréstimos permitem ceder fardas temporariamente a colaboradores sem as registar como atribuição permanente. São monitorizados por prazo e geram alertas em caso de atraso.
  </div>

  <h2>7.1 Registar Empréstimo</h2>
  <p>Na página de detalhes do colaborador, clique em <strong>🧥 Emprestar</strong>. Selecione a farda e a quantidade. O stock é decrementado.</p>
  <div class="note">&#9432; Empréstimos são cedências temporárias — não geram termo de entrega. Use Atribuições para cedências definitivas.</div>

  <h2>7.2 Devolver Empréstimo</h2>
  <p>Clique em <strong>↩️ Devolver Empréstimo</strong> na página do colaborador. Selecione os itens devolvidos e confirme. O stock é reposto.</p>

  <h2>7.3 Alertas de Atraso</h2>
  <p>Empréstimos com mais de <strong>15 dias</strong> em aberto são sinalizados com fundo vermelho na tabela de empréstimos. O sistema pode enviar notificações automáticas por email (via cron job configurado).</p>
  <table>
    <tr><th>Dias em aberto</th><th>Estado</th></tr>
    <tr><td>&lt; 15 dias</td><td>Normal</td></tr>
    <tr><td>≥ 15 dias</td><td><span class="badge badge-red">Em atraso</span></td></tr>
  </table>
</div>


<!-- ════════════════════════════════════════════════
     8. DÍVIDAS
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">8. Dívidas de Fardamento</div>
  <div class="chapter-intro">
    Uma dívida de fardamento regista artigos que não foram devolvidos pelo colaborador e pelos quais existe uma responsabilidade financeira.
  </div>

  <h2>8.1 Como Surgem as Dívidas</h2>
  <p>As dívidas podem surgir de duas formas:</p>
  <ol>
    <li><strong>Durante a devolução</strong>: marcando artigos como dívida na página de devolução (ver Capítulo 6)</li>
    <li><strong>Pelo termo de devolução</strong>: artigos ainda em estado <em>atribuída</em> quando o termo é gerado são automaticamente classificados como em dívida</li>
  </ol>
  <p>O valor da dívida é calculado com base no preço unitário da farda multiplicado pela quantidade.</p>

  <h2>8.2 Regularização de Dívida</h2>
  <p>Na página de detalhes do colaborador, a secção <strong>⚠ Fardas em Dívida</strong> lista os artigos em débito. Para cada item, clique em <strong>💶 Regularizar</strong>.</p>
  <p>A regularização pode representar:</p>
  <ul>
    <li>Pagamento do valor em dívida pelo colaborador</li>
    <li>Devolução tardia da peça</li>
    <li>Abate administrativo autorizado</li>
  </ul>
  <div class="tip">&#10003; O relatório <em>7. Colaboradores com dívidas</em> lista todos os colaboradores com dívidas ativas e os respetivos valores.</div>
</div>


<!-- ════════════════════════════════════════════════
     9. CACIFOS
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">9. Gestão de Cacifos</div>
  <div class="chapter-intro">
    O módulo de cacifos permite controlar a atribuição de cacifos (armários) a colaboradores, incluindo estado de avaria.
  </div>

  <h2>9.1 Registar e Editar Cacifo</h2>
  <p>Aceda a <strong>Cacifos</strong> no menu. Clique em <strong>+ Registar Cacifo</strong>. Cada cacifo tem:</p>
  <ul>
    <li>Número identificador (único)</li>
    <li>Estado: OK ou Avariado</li>
    <li>Colaborador atribuído (opcional)</li>
    <li>Observações</li>
  </ul>

  <h2>9.2 Atribuir e Devolver Cacifo</h2>
  <p>Na página de detalhes do colaborador, a secção <strong>🔒 Cacifos Atribuídos</strong> mostra os cacifos atuais e permite:</p>
  <ul>
    <li><strong>➕ Atribuir</strong>: selecionar um cacifo livre da lista</li>
    <li><strong>🔁 Devolver</strong>: libertar o cacifo — fica disponível para outro colaborador</li>
  </ul>
  <div class="note">&#9432; Colaboradores inativos não podem ter cacifos atribuídos. As ações ficam desativadas.</div>
</div>


<!-- ════════════════════════════════════════════════
     10. RELATÓRIOS
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">10. Centro de Relatórios</div>
  <div class="chapter-intro">
    O Centro de Relatórios centraliza 29 relatórios organizados por categoria, com filtros avançados e exportação múltipla. Relatórios com dados pessoais requerem confirmação RGPD.
  </div>

  <h2>10.1 Catálogo de Relatórios</h2>

  <h3>Colaboradores</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>1</td><td>Lista de todos os colaboradores</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>2</td><td>Colaboradores ativos</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>3</td><td>Colaboradores inativos</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>4</td><td>Colaboradores sem farda atribuída</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>5</td><td>Colaboradores com farda atribuída</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>6</td><td>Colaboradores com empréstimos ativos</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>7</td><td>Colaboradores com dívidas de fardamento</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>8</td><td>Colaboradores por departamento</td><td><span class="badge badge-red">Sim</span></td></tr>
  </table>

  <h3>Fardas</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>9</td><td>Fardas mais atribuídas</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>10</td><td>Fardas menos atribuídas</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>11</td><td>Stock atual completo</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>12</td><td>Stock baixo (abaixo do mínimo)</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>13</td><td>Compras de fardas por período</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>14</td><td>Devoluções por motivo / estado</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>15</td><td>Colaboradores inativos com farda</td><td><span class="badge badge-red">Sim</span></td></tr>
  </table>

  <h3>Cacifos</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>16</td><td>Lista completa de cacifos</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>17</td><td>Cacifos ocupados</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>18</td><td>Cacifos livres</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>19</td><td>Cacifos avariados</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>20</td><td>Cacifos de colaboradores inativos</td><td><span class="badge badge-red">Sim</span></td></tr>
  </table>

  <h3>Financeiros</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>21</td><td>Valor total em stock</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>22</td><td>Custo de fardamento entregue por colaborador</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>23</td><td>Custo total por departamento</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>24</td><td>Custo total de fardas (atribuídas + stock)</td><td><span class="badge badge-green">Não</span></td></tr>
  </table>

  <h3>Diversos</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>25</td><td>Logs de sistema filtráveis</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>26</td><td>Export EAN / códigos de barras (CSV)</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>27</td><td>Imprimir EAN (etiquetas a partir dos PNGs)</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>28</td><td>Itens de farda sem EAN</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>29</td><td>Histórico de atribuições</td><td><span class="badge badge-red">Sim</span></td></tr>
  </table>

  <h2>10.2 Filtros e Opções</h2>
  <p>Conforme o relatório escolhido, são apresentados filtros adicionais:</p>
  <ul>
    <li><strong>Período</strong> (data início / fim): para relatórios temporais</li>
    <li><strong>Top N</strong>: limitar o número de resultados</li>
    <li><strong>Departamento</strong>: filtrar por departamento específico</li>
    <li><strong>Threshold</strong>: limite de quantidade (ex: stock mínimo)</li>
    <li><strong>Filtro livre</strong>: pesquisa por texto nos logs e histórico</li>
  </ul>

  <h2>10.3 Confirmação RGPD e Seleção de Colunas</h2>
  <p>Ao gerar um relatório com dados pessoais, aparece um modal de confirmação RGPD com:</p>
  <ol>
    <li>Lista dos campos com dados pessoais presentes no relatório</li>
    <li><strong>Seletor de colunas</strong>: pode desmarcar campos que não quer incluir (ex: excluir Email e Telefone de uma listagem)</li>
    <li>Aviso de uso responsável</li>
    <li>Checkbox obrigatório de confirmação de base legal (RGPD, Art.º 6.º)</li>
  </ol>
  <div class="tip">&#10003; Use "Selecionar todas" / "Limpar" para gerir rapidamente as colunas. O relatório inclui apenas as colunas selecionadas — funciona em todos os formatos de exportação.</div>

  <h2>10.4 Formatos de Exportação</h2>
  <table>
    <tr><th>Formato</th><th>Uso recomendado</th></tr>
    <tr><td><strong>HTML</strong> (padrão)</td><td>Visualizar no browser, imprimir via browser</td></tr>
    <tr><td><strong>PDF</strong></td><td>Arquivo digital, envio por email</td></tr>
    <tr><td><strong>XLSX (Excel)</strong></td><td>Análise, cruzamento de dados, tabelas dinâmicas</td></tr>
    <tr><td><strong>CSV</strong></td><td>Importação noutros sistemas, etiquetas EAN</td></tr>
  </table>
</div>


<!-- ════════════════════════════════════════════════
     11. UTILIZADORES DO SISTEMA
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">11. Gestão de Utilizadores do Sistema</div>
  <div class="chapter-intro">
    Os utilizadores são as contas de acesso ao CrewGest. Apenas administradores podem gerir contas, aprovar registos e atribuir permissões.
  </div>

  <h2>11.1 Aprovação de Contas</h2>
  <p>Aceda a <strong>Gerir Utilizadores</strong>. Contas pendentes aparecem no topo com botão <strong>Aprovar</strong>. Após aprovação, o utilizador pode fazer login.</p>
  <p>Contas podem também ser rejeitadas/eliminadas diretamente da lista.</p>

  <h2>11.2 Editar e Eliminar Utilizadores</h2>
  <p>Para cada utilizador pode:</p>
  <ul>
    <li>Editar nome, email e perfil (admin/operador)</li>
    <li>Redefinir password manualmente</li>
    <li>Eliminar a conta — ação irreversível</li>
    <li>Desativar temporariamente o acesso</li>
  </ul>
  <div class="warning">&#9888; Não é possível eliminar a própria conta com sessão ativa.</div>

  <h2>11.3 Perfil Pessoal</h2>
  <p>Qualquer utilizador pode aceder ao seu <strong>Perfil</strong> (ícone no canto superior direito) para:</p>
  <ul>
    <li>Alterar nome e email</li>
    <li>Mudar password</li>
    <li>Ativar/desativar 2FA</li>
    <li>Ver logs de acesso da própria conta</li>
  </ul>
</div>


<!-- ════════════════════════════════════════════════
     12. VALIDAÇÃO DE DOCUMENTOS
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">12. Validação de Documentos</div>
  <div class="chapter-intro">
    Os termos gerados pelo CrewGest contêm um código de validação QR que permite verificar a autenticidade do documento sem necessidade de login.
  </div>

  <p>Cada termo de entrega ou devolução gerado tem um <strong>código único</strong> impresso e um QR Code. Para validar:</p>
  <ol>
    <li>Aceda a <code>/public/validar_documento.php</code> (acessível sem login)</li>
    <li>Digitalize o QR Code ou introduza o código manualmente</li>
    <li>O sistema confirma: data de emissão, colaborador, tipo de documento e estado (válido/inválido)</li>
  </ol>
  <div class="tip">&#10003; Esta funcionalidade é útil para auditoria e para verificar documentos entregues por colaboradores.</div>
</div>


<!-- ════════════════════════════════════════════════
     13. SUPORTE
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">13. Suporte</div>

  <p>Para reportar problemas ou solicitar assistência, aceda a <strong>Suporte</strong> no menu principal. Pode descrever o problema e submeter o formulário diretamente.</p>

  <h2>Contactos</h2>
  <table>
    <tr><th>Responsável</th><th>Função</th><th>Email</th></tr>
    <tr><td>Equipa CrewGest</td><td>Desenvolvimento &amp; Suporte</td><td>victor.a.correia@gmail.com</td></tr>
    <tr><td>Administrador</td><td>Admin Sistema</td><td>victor.a.correia@gmail.com</td></tr>
  </table>

  <h2>Informação de Versão</h2>
  <p>Aceda a <strong>Sobre</strong> no rodapé da aplicação para ver a versão instalada, dependências e informação de build.</p>
</div>


<!-- ════════════════════════════════════════════════
     14. REFERÊNCIA RÁPIDA — ESTADOS
     ════════════════════════════════════════════════ -->
<div class="chapter">
  <div class="page-header"><span>CrewGest — Manual do Utilizador</span><span>{$hoje}</span></div>
  <div class="chapter-title">14. Referência Rápida — Estados das Atribuições</div>

  <h2>Estados possíveis de uma farda atribuída</h2>
  <table>
    <tr><th>Estado</th><th>Descrição</th><th>Visível em</th></tr>
    <tr><td><span class="badge badge-blue">atribuída</span></td><td>Farda entregue ao colaborador, em uso</td><td>Detalhes colaborador, Devolução</td></tr>
    <tr><td><span class="badge badge-amber">marcada_devolução</span></td><td>Processo de devolução iniciado — aguarda geração do termo</td><td>Página de devolução</td></tr>
    <tr><td><span class="badge badge-amber">💳 marcada_dívida</span></td><td>Assinalada como não devolvida — será registada como dívida no termo</td><td>Página de devolução</td></tr>
    <tr><td><span class="badge badge-red">em_dívida</span></td><td>Não devolvida após geração do termo — gera responsabilidade financeira</td><td>Detalhes colaborador, Relatórios</td></tr>
    <tr><td><span class="badge badge-green">devolvida</span></td><td>Devolvida ao stock ou para reciclagem</td><td>Histórico</td></tr>
    <tr><td><span class="badge badge-gray">anulada</span></td><td>Atribuição cancelada por erro de registo</td><td>Histórico</td></tr>
  </table>

  <h2>Fluxo resumido</h2>
  <p style="text-align:center; font-size:9pt; color:#6b7280; margin-top:4mm;">
    <strong>Stock</strong> → <em>Atribuição</em> → <span class="badge badge-blue">atribuída</span><br>
    ↓ (devolução parcial) → <span class="badge badge-amber">marcada_devolução</span> → (gerar termo) → <span class="badge badge-green">devolvida</span><br>
    ↓ (marcar dívida) → <span class="badge badge-amber">💳 marcada_dívida</span> → (gerar termo) → <span class="badge badge-red">em_dívida</span><br>
    ↓ (regularizar) → <span class="badge badge-green">regularizada</span>
  </p>

  <div style="margin-top: 12mm; border-top: 1px solid #e5e7eb; padding-top: 4mm; text-align:center; font-size:8pt; color:#9ca3af;">
    CrewGest — Sistema de Gestão de Fardamento e Colaboradores &nbsp;|&nbsp; {$hoje} &nbsp;|&nbsp; &copy; {$ano} Todos os direitos reservados
  </div>
</div>

</body>
</html>
HTML;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('CrewGest_Manual_' . date('Ymd') . '.pdf', ['Attachment' => true]);
