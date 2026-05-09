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
  @page { margin: 0; }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 10pt;
    color: #1a202c;
    line-height: 1.6;
    padding: 22mm 20mm;
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
    overflow: hidden;
  }
  .page-header .ph-right { float: right; }
  .page-header .ph-left  { float: left; }

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
    overflow: hidden;
    padding: 1.5mm 0;
    border-bottom: 1px dotted #d1d5db;
    font-size: 10pt;
  }
  .toc-entry-num { float: right; color: #9ca3af; }
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

<!-- CAPA -->
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


<!-- ÍNDICE -->
<div class="toc">
  <div class="toc-title">Índice</div>

  <div class="toc-entry toc-entry-chapter"><span>1. Introdução e Visão Geral</span></div>
  <div class="toc-entry toc-entry-section"><span>1.1 O que é o CrewGest</span></div>
  <div class="toc-entry toc-entry-section"><span>1.2 Módulos principais</span></div>
  <div class="toc-entry toc-entry-section"><span>1.3 Perfis de utilizador</span></div>

  <div class="toc-entry toc-entry-chapter"><span>2. Autenticação e Segurança</span></div>
  <div class="toc-entry toc-entry-section"><span>2.1 Login / Logout</span></div>
  <div class="toc-entry toc-entry-section"><span>2.2 Autenticação de dois fatores (2FA)</span></div>
  <div class="toc-entry toc-entry-section"><span>2.3 Recuperação de password</span></div>
  <div class="toc-entry toc-entry-section"><span>2.4 Registo e aprovação de utilizadores</span></div>

  <div class="toc-entry toc-entry-chapter"><span>3. Gestão de Colaboradores</span></div>
  <div class="toc-entry toc-entry-section"><span>3.1 Lista de colaboradores</span></div>
  <div class="toc-entry toc-entry-section"><span>3.2 Adicionar colaborador</span></div>
  <div class="toc-entry toc-entry-section"><span>3.3 Detalhes e edição</span></div>
  <div class="toc-entry toc-entry-section"><span>3.4 Inativar / eliminar colaborador</span></div>
  <div class="toc-entry toc-entry-section"><span>3.5 Departamentos</span></div>

  <div class="toc-entry toc-entry-chapter"><span>4. Catálogo de Fardas e Stock</span></div>
  <div class="toc-entry toc-entry-section"><span>4.1 Lista de fardas</span></div>
  <div class="toc-entry toc-entry-section"><span>4.2 Adicionar e editar farda</span></div>
  <div class="toc-entry toc-entry-section"><span>4.3 Gestão de stock</span></div>
  <div class="toc-entry toc-entry-section"><span>4.4 Códigos EAN</span></div>
  <div class="toc-entry toc-entry-section"><span>4.5 Dar baixa de farda</span></div>

  <div class="toc-entry toc-entry-chapter"><span>5. Atribuição de Fardas</span></div>
  <div class="toc-entry toc-entry-section"><span>5.1 Atribuir farda a colaborador</span></div>
  <div class="toc-entry toc-entry-section"><span>5.2 Editar e anular atribuição</span></div>
  <div class="toc-entry toc-entry-section"><span>5.3 Termos de entrega</span></div>

  <div class="toc-entry toc-entry-chapter"><span>6. Devoluções e Termos de Devolução</span></div>
  <div class="toc-entry toc-entry-section"><span>6.1 Fluxo de devolução</span></div>
  <div class="toc-entry toc-entry-section"><span>6.2 Devolução parcial (1 peça)</span></div>
  <div class="toc-entry toc-entry-section"><span>6.3 Marcar como dívida</span></div>
  <div class="toc-entry toc-entry-section"><span>6.4 Gerar e reverter termo de devolução</span></div>

  <div class="toc-entry toc-entry-chapter"><span>7. Empréstimos de Farda</span></div>
  <div class="toc-entry toc-entry-section"><span>7.1 Registar empréstimo</span></div>
  <div class="toc-entry toc-entry-section"><span>7.2 Devolver empréstimo</span></div>
  <div class="toc-entry toc-entry-section"><span>7.3 Alertas de atraso</span></div>

  <div class="toc-entry toc-entry-chapter"><span>8. Dívidas de Fardamento</span></div>
  <div class="toc-entry toc-entry-section"><span>8.1 Como surgem as dívidas</span></div>
  <div class="toc-entry toc-entry-section"><span>8.2 Regularização de dívida</span></div>

  <div class="toc-entry toc-entry-chapter"><span>9. Gestão de Cacifos</span></div>
  <div class="toc-entry toc-entry-section"><span>9.1 Registar e editar cacifo</span></div>
  <div class="toc-entry toc-entry-section"><span>9.2 Atribuir e devolver cacifo</span></div>

  <div class="toc-entry toc-entry-chapter"><span>10. Centro de Relatórios</span></div>
  <div class="toc-entry toc-entry-section"><span>10.1 Catálogo de relatórios disponíveis</span></div>
  <div class="toc-entry toc-entry-section"><span>10.2 Filtros e opções</span></div>
  <div class="toc-entry toc-entry-section"><span>10.3 Confirmação RGPD e seleção de colunas</span></div>
  <div class="toc-entry toc-entry-section"><span>10.4 Formatos de exportação</span></div>

  <div class="toc-entry toc-entry-chapter"><span>11. Gestão de Utilizadores do Sistema</span></div>
  <div class="toc-entry toc-entry-section"><span>11.1 Aprovação de contas</span></div>
  <div class="toc-entry toc-entry-section"><span>11.2 Editar e eliminar utilizadores</span></div>
  <div class="toc-entry toc-entry-section"><span>11.3 Perfil pessoal</span></div>

  <div class="toc-entry toc-entry-chapter"><span>12. Validação de Documentos</span></div>
  <div class="toc-entry toc-entry-chapter"><span>13. Suporte</span></div>
  <div class="toc-entry toc-entry-chapter"><span>14. Referência Rápida — Estados das Atribuições</span></div>
</div>


<!-- 1. INTRODUÇÃO -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
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


<!-- 2. AUTENTICAÇÃO -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
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
  <p>Após introduzir email e password corretos, será pedido o código TOTP de 6 dígitos. O código é válido por 30 segundos.</p>
  <div class="warning">&#9888; Se perder acesso à aplicação autenticadora, contacte o administrador do sistema para desativar o 2FA.</div>

  <h2>2.3 Recuperação de Password</h2>
  <ol>
    <li>Na página de login clique em <strong>Esqueci-me da password</strong></li>
    <li>Introduza o email associado à conta</li>
    <li>Receberá um email com link de redefinição (válido por 1 hora)</li>
    <li>Clique no link e defina a nova password</li>
  </ol>

  <h2>2.4 Registo e Aprovação de Utilizadores</h2>
  <p>Qualquer pessoa com acesso à aplicação pode registar uma conta em <code>/public/registar.php</code>. A conta fica pendente até um administrador a aprovar em <strong>Gerir Utilizadores</strong>.</p>
</div>


<!-- 3. COLABORADORES -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">3. Gestão de Colaboradores</div>
  <div class="chapter-intro">
    Os colaboradores são o núcleo do sistema. Cada colaborador pode ter fardas atribuídas, empréstimos ativos, cacifos e um histórico completo de movimentos.
  </div>

  <h2>3.1 Lista de Colaboradores</h2>
  <p>Aceda em <strong>Colaboradores</strong> no menu principal. A lista mostra todos os colaboradores com filtros por estado, departamento e pesquisa livre. São apresentados 50 por página. Colaboradores com dívidas aparecem assinalados com <span class="badge badge-red">&#9888; Dívida</span>.</p>

  <h2>3.2 Adicionar Colaborador</h2>
  <table>
    <tr><th>Campo</th><th>Obrigatório</th><th>Notas</th></tr>
    <tr><td>Nome completo</td><td>Sim</td><td></td></tr>
    <tr><td>Nº Funcionário</td><td>Não</td><td>Identificador interno</td></tr>
    <tr><td>Cartão (ID físico)</td><td>Sim</td><td>Deve ser único</td></tr>
    <tr><td>Email</td><td>Não</td><td>Para notificações</td></tr>
    <tr><td>Telefone</td><td>Não</td><td></td></tr>
    <tr><td>Departamento</td><td>Sim</td><td></td></tr>
    <tr><td>Foto</td><td>Não</td><td>JPG/PNG até 5 MB</td></tr>
  </table>

  <h2>3.3 Detalhes e Edição</h2>
  <p>Na página de detalhes encontra: informações pessoais, fardas atribuídas (agrupadas por artigo), cacifos, empréstimos pendentes, fardas em dívida e todos os botões de ação.</p>
  <div class="tip">&#10003; O botão de Termo mostra: <em>Gerar Termo</em>, <em>Gerar Novo Termo</em> (houve alterações) ou <em>Termo em vigor</em>.</div>

  <h2>3.4 Inativar / Eliminar Colaborador</h2>
  <p>Colaboradores inativos mantêm o histórico mas não podem receber novas atribuições ou empréstimos.</p>
  <div class="warning">&#9888; A eliminação definitiva é irreversível e remove todos os dados associados.</div>

  <h2>3.5 Departamentos</h2>
  <p>Aceda em <strong>Departamentos</strong> no menu para criar, editar e eliminar departamentos. Usados para agrupar colaboradores e filtrar relatórios.</p>
</div>


<!-- 4. FARDAS E STOCK -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">4. Catálogo de Fardas e Stock</div>
  <div class="chapter-intro">
    As fardas representam os artigos disponíveis para atribuição. Cada artigo é identificado por nome, cor, tamanho, preço unitário e quantidade em stock.
  </div>

  <h2>4.1 Lista de Fardas</h2>
  <p>Aceda em <strong>Fardas</strong> no menu. A listagem mostra stock atual, stock mínimo, preço unitário e código EAN. Artigos com stock abaixo do mínimo aparecem destacados a vermelho.</p>

  <h2>4.2 Adicionar e Editar Farda</h2>
  <table>
    <tr><th>Campo</th><th>Obrigatório</th><th>Notas</th></tr>
    <tr><td>Nome</td><td>Sim</td><td>Ex: "Polo Manga Curta"</td></tr>
    <tr><td>Cor</td><td>Sim</td><td>Pode criar nova cor inline</td></tr>
    <tr><td>Tamanho</td><td>Sim</td><td>Pode criar novo tamanho inline</td></tr>
    <tr><td>Preço unitário (€)</td><td>Sim</td><td>Usado em relatórios financeiros</td></tr>
    <tr><td>Quantidade em stock</td><td>Sim</td><td>Stock inicial</td></tr>
    <tr><td>Stock mínimo</td><td>Não</td><td>Alerta quando stock desce abaixo deste valor</td></tr>
    <tr><td>Código EAN</td><td>Não</td><td>Pode ser gerado automaticamente</td></tr>
  </table>

  <h2>4.3 Gestão de Stock</h2>
  <p>Para adicionar stock a uma farda existente, clique em <strong>Gerir Stock</strong> → <strong>+ Adicionar Stock</strong>. O stock é decrementado automaticamente na atribuição e reposto na devolução a stock.</p>

  <h2>4.4 Códigos EAN</h2>
  <ol>
    <li>Aceda à farda e clique em <strong>Gerar EAN</strong></li>
    <li>O código EAN-13 é gerado e associado ao artigo</li>
    <li>Para imprimir etiquetas: <strong>Relatórios</strong> → <em>27. Imprimir EAN</em></li>
    <li>Leitores de código de barras preenchem automaticamente o artigo nos formulários</li>
  </ol>

  <h2>4.5 Dar Baixa de Farda</h2>
  <p>Para retirar artigos inutilizáveis do stock (desgaste, perda), use <strong>Dar Baixa</strong> na página da farda. A operação é registada nos logs.</p>
  <div class="note">&#9432; A baixa é irreversível. Confirme a quantidade antes de submeter.</div>
</div>


<!-- 5. ATRIBUIÇÕES -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">5. Atribuição de Fardas</div>
  <div class="chapter-intro">
    A atribuição regista formalmente a entrega de fardas a um colaborador. Cada atribuição é rastreada individualmente com data, quantidade e estado.
  </div>

  <h2>5.1 Atribuir Farda a Colaborador</h2>
  <ol>
    <li>Aceda à página de detalhes do colaborador e clique em <strong>&#10133; Atribuir</strong></li>
    <li>Selecione a farda — pode usar leitor EAN para preenchimento rápido</li>
    <li>Defina a quantidade e confirme</li>
  </ol>
  <p>O stock decrementar automaticamente e o estado da atribuição passa a <span class="badge badge-blue">atribuída</span>.</p>
  <div class="warning">&#9888; Não é possível atribuir fardas a colaboradores inativos.</div>

  <h2>5.2 Editar e Anular Atribuição</h2>
  <ul>
    <li><strong>Editar</strong>: corrige quantidade ou data de atribuição</li>
    <li><strong>Anular</strong>: cancela a atribuição e devolve o stock — apenas para correção de erros de registo</li>
  </ul>

  <h2>5.3 Termos de Entrega</h2>
  <table>
    <tr><th>Estado do botão</th><th>Significado</th></tr>
    <tr><td><span class="badge badge-blue">Gerar Termo</span></td><td>Ainda não existe termo — gerar o primeiro</td></tr>
    <tr><td><span class="badge badge-amber">Gerar Novo Termo</span></td><td>Houve alterações desde o último termo — deve ser re-emitido</td></tr>
    <tr><td><span class="badge badge-gray">Termo em vigor</span></td><td>Termo atual reflete todas as atribuições</td></tr>
  </table>
  <div class="tip">&#10003; O termo anterior é automaticamente invalidado quando se gera um novo. O histórico de documentos é mantido.</div>
</div>


<!-- 6. DEVOLUÇÕES -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">6. Devoluções e Termos de Devolução</div>
  <div class="chapter-intro">
    O processo de devolução permite recolher fardas de um colaborador, total ou parcialmente, com destino a stock ou reciclagem, e gerir situações de não-devolução.
  </div>

  <h2>6.1 Fluxo de Devolução</h2>
  <p>Aceda em <strong>&#128260; Devolver</strong> na página de detalhes do colaborador. Para cada artigo:</p>
  <table>
    <tr><th>Ação</th><th>Efeito</th></tr>
    <tr><td><strong>1 Peça</strong></td><td>Marca 1 unidade para devolução. Cliques repetidos agrupam na mesma linha.</td></tr>
    <tr><td><strong>Todas</strong></td><td>Marca todas as peças do artigo de uma vez</td></tr>
    <tr><td><strong>&#128179; Marcar como Dívida</strong></td><td>Assinala o artigo (ou parte dele) como não devolvido</td></tr>
  </table>

  <h2>6.2 Devolução Parcial</h2>
  <p>Exemplo — colaborador tem 4 polos, devolve 2:</p>
  <ol>
    <li>Clique <strong>1 Peça</strong> duas vezes (com o mesmo destino)</li>
    <li>O sistema agrupa numa única linha com quantidade 2</li>
    <li>A atribuição original fica com quantidade 2 (as restantes)</li>
  </ol>

  <h2>6.3 Marcar como Dívida</h2>
  <ol>
    <li>Clique <strong>&#128179; Marcar como Dívida</strong></li>
    <li>Se a atribuição tiver mais de 1 unidade, escolha a quantidade no modal</li>
    <li>O artigo fica marcado com <span class="badge badge-amber">&#128179; Marcada como dívida</span></li>
    <li>Para reverter: <strong>&#8617;&#65039; Desmarcar de dívida</strong> — o sistema reagrupa automaticamente</li>
  </ol>

  <h2>6.4 Gerar e Reverter Termo de Devolução</h2>
  <p>Quando todas as fardas estiverem tratadas, o botão <strong>Gerar Termo de Devolução</strong> fica disponível. O termo PDF inclui artigos devolvidos, artigos em dívida com valor, datas e campo de assinaturas.</p>
  <div class="warning">&#9888; Após gerar o termo de devolução, o colaborador é marcado como inativo automaticamente.</div>
</div>


<!-- 7. EMPRÉSTIMOS -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">7. Empréstimos de Farda</div>
  <div class="chapter-intro">
    Os empréstimos permitem ceder fardas temporariamente sem as registar como atribuição permanente. São monitorizados por prazo e geram alertas em caso de atraso.
  </div>

  <h2>7.1 Registar Empréstimo</h2>
  <p>Na página de detalhes do colaborador, clique em <strong>&#129399; Emprestar</strong>. Selecione a farda e a quantidade. O stock é decrementado.</p>
  <div class="note">&#9432; Empréstimos são cedências temporárias — não geram termo de entrega. Use Atribuições para cedências definitivas.</div>

  <h2>7.2 Devolver Empréstimo</h2>
  <p>Clique em <strong>&#8617;&#65039; Devolver Empréstimo</strong> na página do colaborador. Selecione os itens devolvidos e confirme. O stock é reposto.</p>

  <h2>7.3 Alertas de Atraso</h2>
  <table>
    <tr><th>Dias em aberto</th><th>Estado</th></tr>
    <tr><td>Menos de 15 dias</td><td>Normal</td></tr>
    <tr><td>15 dias ou mais</td><td><span class="badge badge-red">Em atraso</span></td></tr>
  </table>
  <p>O sistema pode enviar notificações automáticas por email via cron job configurado.</p>
</div>


<!-- 8. DÍVIDAS -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">8. Dívidas de Fardamento</div>
  <div class="chapter-intro">
    Uma dívida regista artigos não devolvidos pelo colaborador, com responsabilidade financeira associada.
  </div>

  <h2>8.1 Como Surgem as Dívidas</h2>
  <ol>
    <li><strong>Durante a devolução</strong>: marcando artigos como dívida (ver capítulo 6)</li>
    <li><strong>Pelo termo de devolução</strong>: artigos ainda em estado <em>atribuída</em> quando o termo é gerado são classificados automaticamente como dívida</li>
  </ol>

  <h2>8.2 Regularização de Dívida</h2>
  <p>Na página de detalhes do colaborador, na secção <strong>&#9888; Fardas em Dívida</strong>, clique em <strong>&#128182; Regularizar</strong> para cada item. Pode representar pagamento, devolução tardia ou abate administrativo.</p>
  <div class="tip">&#10003; O relatório 7. <em>Colaboradores com dívidas</em> lista todos os colaboradores com dívidas ativas e respetivos valores.</div>
</div>


<!-- 9. CACIFOS -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">9. Gestão de Cacifos</div>
  <div class="chapter-intro">
    O módulo de cacifos controla a atribuição de cacifos a colaboradores, incluindo estado de avaria.
  </div>

  <h2>9.1 Registar e Editar Cacifo</h2>
  <p>Aceda a <strong>Cacifos</strong> no menu e clique em <strong>+ Registar Cacifo</strong>. Cada cacifo tem número identificador (único), estado (OK/Avariado), colaborador atribuído e observações.</p>

  <h2>9.2 Atribuir e Devolver Cacifo</h2>
  <p>Na página de detalhes do colaborador, secção <strong>&#128274; Cacifos Atribuídos</strong>:</p>
  <ul>
    <li><strong>&#10133; Atribuir</strong>: selecionar um cacifo livre</li>
    <li><strong>&#128260; Devolver</strong>: libertar o cacifo para outro colaborador</li>
  </ul>
  <div class="note">&#9432; Colaboradores inativos não podem ter cacifos — as ações ficam desativadas.</div>
</div>


<!-- 10. RELATÓRIOS -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">10. Centro de Relatórios</div>
  <div class="chapter-intro">
    O Centro de Relatórios disponibiliza 29 relatórios organizados por categoria, com filtros avançados e exportação múltipla. Relatórios com dados pessoais requerem confirmação RGPD.
  </div>

  <h2>10.1 Catálogo de Relatórios</h2>

  <h3>Colaboradores (1–8)</h3>
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

  <h3>Fardas (9–15)</h3>
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

  <h3>Cacifos (16–20)</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>16</td><td>Lista completa de cacifos</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>17</td><td>Cacifos ocupados</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>18</td><td>Cacifos livres</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>19</td><td>Cacifos avariados</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>20</td><td>Cacifos de colaboradores inativos</td><td><span class="badge badge-red">Sim</span></td></tr>
  </table>

  <h3>Financeiros (21–24)</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>21</td><td>Valor total em stock</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>22</td><td>Custo de fardamento entregue por colaborador</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>23</td><td>Custo total por departamento</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>24</td><td>Custo total de fardas (atribuídas + stock)</td><td><span class="badge badge-green">Não</span></td></tr>
  </table>

  <h3>Diversos (25–29)</h3>
  <table>
    <tr><th>#</th><th>Relatório</th><th>Dados pessoais</th></tr>
    <tr><td>25</td><td>Logs de sistema filtráveis</td><td><span class="badge badge-red">Sim</span></td></tr>
    <tr><td>26</td><td>Export EAN / códigos de barras (CSV)</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>27</td><td>Imprimir EAN (etiquetas a partir dos PNGs)</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>28</td><td>Itens de farda sem EAN</td><td><span class="badge badge-green">Não</span></td></tr>
    <tr><td>29</td><td>Histórico de atribuições</td><td><span class="badge badge-red">Sim</span></td></tr>
  </table>

  <h2>10.2 Filtros e Opções</h2>
  <ul>
    <li><strong>Período</strong> (data início / fim): para relatórios temporais</li>
    <li><strong>Top N</strong>: limitar o número de resultados</li>
    <li><strong>Departamento</strong>: filtrar por departamento específico</li>
    <li><strong>Threshold</strong>: limite de quantidade (ex: stock mínimo)</li>
    <li><strong>Filtro livre</strong>: pesquisa por texto nos logs e histórico</li>
  </ul>

  <h2>10.3 Confirmação RGPD e Seleção de Colunas</h2>
  <p>Ao gerar um relatório com dados pessoais aparece um modal com:</p>
  <ol>
    <li>Lista dos campos pessoais presentes no relatório</li>
    <li><strong>Seletor de colunas</strong>: desmarque campos que não pretende incluir (ex: excluir Email e Telefone)</li>
    <li>Aviso de uso responsável</li>
    <li>Confirmação obrigatória de base legal (RGPD, Art.º 6.º)</li>
  </ol>
  <div class="tip">&#10003; O relatório inclui apenas as colunas selecionadas — funciona em todos os formatos de exportação.</div>

  <h2>10.4 Formatos de Exportação</h2>
  <table>
    <tr><th>Formato</th><th>Uso recomendado</th></tr>
    <tr><td><strong>HTML</strong> (padrão)</td><td>Visualizar no browser, imprimir</td></tr>
    <tr><td><strong>PDF</strong></td><td>Arquivo digital, envio por email</td></tr>
    <tr><td><strong>XLSX (Excel)</strong></td><td>Análise, tabelas dinâmicas</td></tr>
    <tr><td><strong>CSV</strong></td><td>Importação noutros sistemas, etiquetas EAN</td></tr>
  </table>
</div>


<!-- 11. UTILIZADORES -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">11. Gestão de Utilizadores do Sistema</div>
  <div class="chapter-intro">
    Os utilizadores são as contas de acesso ao CrewGest. Apenas administradores podem gerir contas, aprovar registos e atribuir permissões.
  </div>

  <h2>11.1 Aprovação de Contas</h2>
  <p>Aceda a <strong>Gerir Utilizadores</strong>. Contas pendentes aparecem no topo com botão <strong>Aprovar</strong>. Após aprovação, o utilizador pode fazer login imediatamente.</p>

  <h2>11.2 Editar e Eliminar Utilizadores</h2>
  <ul>
    <li>Editar nome, email e perfil (admin/operador)</li>
    <li>Redefinir password manualmente</li>
    <li>Eliminar conta (irreversível)</li>
    <li>Desativar temporariamente o acesso</li>
  </ul>
  <div class="warning">&#9888; Não é possível eliminar a própria conta com sessão ativa.</div>

  <h2>11.3 Perfil Pessoal</h2>
  <p>Aceda ao <strong>Perfil</strong> (ícone no menu) para: alterar nome e email, mudar password, ativar/desativar 2FA e ver logs de acesso da própria conta.</p>
</div>


<!-- 12. VALIDAÇÃO -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">12. Validação de Documentos</div>
  <div class="chapter-intro">
    Os termos gerados pelo CrewGest contêm um QR Code que permite verificar a autenticidade do documento sem necessidade de login.
  </div>

  <ol>
    <li>Aceda a <code>/public/validar_documento.php</code> (acessível sem login)</li>
    <li>Digitalize o QR Code ou introduza o código manualmente</li>
    <li>O sistema confirma: data de emissão, colaborador, tipo de documento e estado (válido/inválido)</li>
  </ol>
  <div class="tip">&#10003; Útil para auditoria e verificação de documentos entregues por colaboradores.</div>
</div>


<!-- 13. SUPORTE -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">13. Suporte</div>

  <p>Para reportar problemas ou solicitar assistência, aceda a <strong>Suporte</strong> no menu principal.</p>

  <h2>Contactos</h2>
  <table>
    <tr><th>Responsável</th><th>Função</th><th>Email</th></tr>
    <tr><td>Equipa CrewGest</td><td>Desenvolvimento &amp; Suporte</td><td>victor.a.correia@gmail.com</td></tr>
    <tr><td>Administrador</td><td>Admin Sistema</td><td>victor.a.correia@gmail.com</td></tr>
  </table>

  <h2>Informação de Versão</h2>
  <p>Aceda a <strong>Sobre</strong> no rodapé da aplicação para ver a versão instalada e informação de build.</p>
</div>


<!-- 14. REFERÊNCIA RÁPIDA -->
<div class="chapter">
  <div class="page-header">
    <span class="ph-right">{$hoje}</span>
    <span class="ph-left">CrewGest — Manual do Utilizador</span>
  </div>
  <div class="chapter-title">14. Referência Rápida — Estados das Atribuições</div>

  <table>
    <tr><th>Estado</th><th>Descrição</th></tr>
    <tr><td><span class="badge badge-blue">atribuída</span></td><td>Farda entregue ao colaborador, em uso</td></tr>
    <tr><td><span class="badge badge-amber">marcada_devolução</span></td><td>Devolução iniciada — aguarda geração do termo</td></tr>
    <tr><td><span class="badge badge-amber">&#128179; marcada_dívida</span></td><td>Assinalada como não devolvida — será dívida no termo</td></tr>
    <tr><td><span class="badge badge-red">em_dívida</span></td><td>Não devolvida após geração do termo — responsabilidade financeira</td></tr>
    <tr><td><span class="badge badge-green">devolvida</span></td><td>Devolvida ao stock ou para reciclagem</td></tr>
    <tr><td><span class="badge badge-gray">anulada</span></td><td>Atribuição cancelada por erro de registo</td></tr>
  </table>

  <h2>Fluxo Resumido</h2>
  <table>
    <tr><th>Passo</th><th>Ação</th><th>Estado resultante</th></tr>
    <tr><td>1</td><td>Stock disponível → Atribuir ao colaborador</td><td><span class="badge badge-blue">atribuída</span></td></tr>
    <tr><td>2a</td><td>Colaborador devolve → Devolver (1 peça / todas)</td><td><span class="badge badge-amber">marcada_devolução</span></td></tr>
    <tr><td>2b</td><td>Colaborador não devolve → Marcar como dívida</td><td><span class="badge badge-amber">&#128179; marcada_dívida</span></td></tr>
    <tr><td>3</td><td>Gerar Termo de Devolução</td><td><span class="badge badge-green">devolvida</span> / <span class="badge badge-red">em_dívida</span></td></tr>
    <tr><td>4</td><td>Regularizar dívida</td><td><span class="badge badge-green">regularizada</span></td></tr>
  </table>

  <div style="margin-top:14mm; border-top:1px solid #e5e7eb; padding-top:4mm; text-align:center; font-size:8pt; color:#9ca3af;">
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
